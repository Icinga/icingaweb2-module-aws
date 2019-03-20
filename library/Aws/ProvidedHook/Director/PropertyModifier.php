<?php

namespace Icinga\Module\Aws\ProvidedHook\Director;

use DateInterval;
use DateTime;
use Icinga\Module\Aws\AwsClient;
use Icinga\Module\Aws\AwsKey;
use LogicException;
use Icinga\Exception\InvalidPropertyException;
use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyModifier extends PropertyModifierHook
{
    const ICINGA_DAYS = [
        'sunday',
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday'
    ];

    protected $client;

    protected $dynamoSettings;

    public function __construct()
    {
        $this->client = new AwsClient(
            AwsKey::loadByName($this->getSetting('aws_access_key')),
            $this->getSetting('aws_region')
        );
    }

    public function getName()
    {
        return 'EC2 Scheduler tags to ranges';
    }

    public function requiresRow()
    {
        return true;
    }

    public function transform($value)
    {
        if (empty($value)) {
            return null;
        }

        if (! is_object($value) && !is_array($value)) {
            throw new InvalidPropertyException('The AWS property modifier only supports the tags property');
        }

        $customTagName = 'scheduler:ec2-startstop';
        $customTagLength = 23;
        if (isset($this->DynamoSettings()['CustomTagName'])) {
            $customTagName = $this->DynamoSettings()['CustomTagName'];
            $customTagLength = strlen($customTagName);
        }

        $schedules = [];
        foreach ($value as $tagKey => $tagValue) {
            if (empty($tagValue) || $tagValue === 'none') {
                continue;
            }

            if (substr($tagKey, 0, $customTagLength) === $customTagName) {
                $schedule = $this->parse($tagValue);
                if (! empty($schedule)) {
                    $schedules = array_merge($schedules, $schedule);
                }
            }
        }

        if (empty($schedules)) {
            return null;
        }

        $schedules = $this->mergeSchedules($schedules);
        usort($schedules, function ($timeA, $timeB) {
            if ($timeA[0]['tm_wday'] > $timeB[0]['tm_wday']) {
                return 1;
            } elseif ($timeA[0]['tm_wday'] < $timeB[0]['tm_wday']) {
                return -1;
            } elseif ($timeA[1]['tm_hour'] > $timeB[0]['tm_hour']) {
                return 1;
            } elseif ($timeA[1]['tm_hour'] < $timeB[0]['tm_hour']) {
                return -1;
            }

            return 0;
        });

        switch ($this->getSetting('range_type')) {
            case 'timeperiod':
                return $this->createTimeperiodRanges($schedules);
            case 'downtime':
                return $this->createDowntimeRanges($schedules);
            default:
                throw new LogicException('Invalid range type');
        }
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement(
            'select',
            'aws_region',
            [
                'label'        => 'AWS region',
                'required'     => true,
                'multiOptions' => $form->optionalEnum(AwsClient::enumRegions())
            ]
        );

        $form->addElement(
            'select',
            'aws_access_key',
            [
                'label'        => 'AWS access key',
                'required'     => true,
                'description'  => $form->translate(
                    'Your AWS key, this shows all keys from your keys.ini. Please'
                    . ' check the documentation in case this list is empty'
                ),
                'multiOptions' => $form->optionalEnum(AwsKey::enumKeyNames())
            ]
        );

        $form->addElement(
            'select',
            'range_type',
            [
                'required'      => true,
                'label'         => mt('aws', 'Range Type'),
                'multiOptions'  => [
                    'timeperiod'    => mt('aws', 'Timeperiod'),
                    'downtime'      => mt('aws', 'Downtime')
                ]
            ]
        );

        return $form;
    }

    protected function DynamoSettings()
    {
        $stackName = $this->getRow()->stack_name;
        if (isset($this->dynamoSettings[$stackName])) {
            return $this->dynamoSettings[$stackName];
        }

        $stack = $this->client->getStacks()[$stackName];

        $ddTableName = null;
        foreach ($stack['Outputs'] as $outputStructure) {
            if ($outputStructure['OutputKey'] === 'DDBTableName') {
                $ddTableName = $outputStructure['OutputValue'];
                break;
            }
        }

        $dynamoDb = $this->client->getDynamoDb();
        $res = $dynamoDb->getItem([
            'TableName' => $ddTableName,
            'Key'       => [
                'SolutionName'  => 'EC2Scheduler'
            ]
        ]);

        $this->dynamoSettings[$stackName] = $res['Item'];
        return $this->dynamoSettings[$stackName];
    }

    /**
     * Parse the given scheduler tag into a schedule
     *
     * @param   string  $value
     *
     * @return  null|array
     */
    protected function parse($value)
    {
        $start = $this->DynamoSettings()['DefaultStartTime'];
        $stop = $this->DynamoSettings()['DefaultStopTime'];
        $weekdays = $this->DynamoSettings()['DefaultDaysActive'];

        if (in_array(strtolower($value), ['true', 'default'], true)) {
            $value = $start;
        }

        $parts = explode(';', $value);
        switch (count($parts)) {
            case 4:
                $weekdays = array_pop($parts);
            case 3:
                // timezone is UTC only
                $_ = array_pop($parts);
            case 2:
                $stop = array_pop($parts);
            case 1:
                $start = array_pop($parts);
                break;
            default:
                exit;
        }

        if ($start === 'none' || $stop === 'none') {
            return null; // These require manual intervention in any case, not our business
        } elseif (! ctype_digit($start) || !ctype_digit($stop)) {
            return null; // Random values are ignored by ec2-scheduler.py
        }

        if ($weekdays === 'all') {
            $weekdays = 'mon,tue,wed,thu,fri,sat,sun';
        } elseif ($weekdays === 'weekdays') {
            $weekdays = 'mon,tue,wed,thu,fri';
        }

        $days = [];
        foreach (explode(',', $weekdays) as $day) {
            $startOn = strptime("$start $day", '%H%M %a');
            $stopOn = strptime("$stop $day", '%H%M %a');
            if ($startOn !== false && $stopOn !== false) {
                $days[] = [$startOn, $stopOn];
            }
        }

        return $days;
    }

    /**
     * Attempt to merge overlapping schedules
     *
     * @param   array   $schedules
     *
     * @return  array
     */
    protected function mergeSchedules(array $schedules)
    {
        $overlaps = [];
        $x = 0;
        foreach ($schedules as &$schedule) {
            // TODO: Minute handling is undefined
            $day = $schedule[0]['tm_wday'];
            $overlaps[$day][$x++] = &$schedule;

            if (count($overlaps[$day]) === 1) {
                continue;
            }

            foreach ($overlaps[$day] as $i => &$iSchedule) {
                foreach ($overlaps[$day] as $j => &$jSchedule) {
                    if ($jSchedule[0]['tm_hour'] > $iSchedule[0]['tm_hour']
                        && $jSchedule[1]['tm_hour'] < $iSchedule[1]['tm_hour']
                    ) { // include
                        $iSchedule[1] = $jSchedule[1];
                        unset($schedules[$j], $overlaps[$day][$j]);
                    } elseif ($jSchedule[0]['tm_hour'] < $iSchedule[0]['tm_hour']
                        && $jSchedule[1]['tm_hour'] > $iSchedule[1]['tm_hour']
                    ) { // left and right
                        $iSchedule[0] = $jSchedule[0];
                        unset($schedules[$j], $overlaps[$day][$j]);
                    } elseif ($jSchedule[0]['tm_hour'] < $iSchedule[0]['tm_hour']
                        && $jSchedule[1]['tm_hour'] > $iSchedule[0]['tm_hour']
                    ) { // left only
                        unset($schedules[$i], $overlaps[$day][$i]);
                    } elseif ($jSchedule[1]['tm_hour'] > $iSchedule[1]['tm_hour']
                        && $jSchedule[0]['tm_hour'] < $iSchedule[1]['tm_hour']
                    ) { // right only
                        unset($schedules[$j], $overlaps[$day][$j]);
                    }
                }
            }
        }

        return $schedules;
    }

    /**
     * Transform schedules to timeperiod ranges
     *
     * @param   array   $schedules
     *
     * @return  array
     */
    protected function createTimeperiodRanges(array $schedules)
    {
        $ranges = [];
        foreach ($schedules as $time) {
            list($startOn, $stopOn) = $time;

            $range = sprintf(
                '%\'02u:%\'02u-%\'02u:%\'02u',
                $startOn['tm_hour'],
                $startOn['tm_min'],
                $stopOn['tm_hour'],
                $stopOn['tm_min']
            );

            $ranges[self::ICINGA_DAYS[$stopOn['tm_wday']]][] = $range;
        }

        return $ranges;
    }

    /**
     * Transform schedules to downtime ranges
     *
     * @param   array   $schedules
     *
     * @return  array
     */
    protected function createDowntimeRanges(array $schedules)
    {
        $ranges = [];
        for ($i = 0; $i < count($schedules); $i++) {
            list($_, $stopOn) = $schedules[$i];
            if (isset($schedules[$i + 1])) {
                $nextStart = $schedules[$i + 1][0];
            } else {
                $nextStart = $schedules[0][0];
            }

            $diff = diff($stopOn, $nextStart);
            if ($diff->d > 0) {
                $hourDelta = 24 - $stopOn['tm_hour'];

                $minDelta = 0;
                if ($stopOn['tm_min'] > 0) {
                    $hourDelta -= 1; // It's not a full hour
                    $minDelta = 60 - $stopOn['tm_min'];
                }

                $diff->h -= $hourDelta;
                $diff->i -= $minDelta;
            } else {
                $hourDelta = $diff->h;
                $minDelta = $diff->i;
            }

            $currentDay = $stopOn['tm_wday'];
            $ranges[self::ICINGA_DAYS[$currentDay]][] = renderRange(
                $stopOn['tm_hour'],
                $stopOn['tm_min'],
                $stopOn['tm_hour'] + $hourDelta,
                $stopOn['tm_min'] + $minDelta
            );

            if ($diff->d === 0) {
                continue;
            }

            while ($diff->d > 1) {
                $currentDay = $currentDay < 6 ? $currentDay + 1: 0;
                $ranges[self::ICINGA_DAYS[$currentDay]][] = renderRange(0, 0, 24, 0);

                --$diff->d;
            }

            $currentDay = $currentDay < 6 ? $currentDay + 1: 0;
            $ranges[self::ICINGA_DAYS[$currentDay]][] = renderRange(0, 0, 24 + $diff->h, $diff->i);
        }

        return $ranges;
    }

    /**
     * Calculate difference between the given start and end
     *
     * @param   array   $start
     * @param   array   $end
     *
     * @return  bool|DateInterval
     */
    protected function diff(array $start, array $end)
    {
        $startTime = mktime($start['tm_hour'], $start['tm_min']);
        $endTime = mktime($end['tm_hour'], $end['tm_min']);

        if ($end['tm_wday'] > $start['tm_wday']) {
            $endTime += 3600*24*($end['tm_wday'] - $start['tm_wday']);
        } elseif ($end['tm_wday'] < $start['tm_wday'] || $end['tm_hour'] < $start['tm_hour']) {
            $endTime += 3600*24*(7 - $start['tm_wday'] + $end['tm_wday']);
        }

        $dtStart = (new DateTime())->setTimestamp($startTime);
        $dtEnd = (new DateTime())->setTimestamp($endTime);

        return $dtStart->diff($dtEnd);
    }

    /**
     * Render a icinga 2 range definition
     *
     * @param   int $startH
     * @param   int $startM
     * @param   int $endH
     * @param   int $endM
     *
     * @return  string
     */
    protected function renderRange($startH, $startM, $endH, $endM)
    {
        return sprintf('%\'02u:%\'02u-%\'02u:%\'02u', $startH, $startM, $endH, $endM);
    }
}
