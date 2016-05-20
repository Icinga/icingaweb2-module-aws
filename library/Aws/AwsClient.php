<?php

namespace Icinga\Module\Aws;

use Aws\AutoScaling\AutoScalingClient;
use Aws\Common\Aws;
use Icinga\Application\Config;

class AwsClient
{
    protected $key;

    protected $client;

    protected $region;

    public function __construct(AwsKey $key, $region)
    {
        $this->region = $region;
        $this->key = $key;
        $this->prepareAwsLibs();
    }

    public function getAutoscalingConfig()
    {
        $groups = array();

        $lbClient = $this->client()->get('AutoScaling');
        $res = $lbClient->describeAutoScalingGroups();
        foreach ($res->get('AutoScalingGroups') as $grp) {

            $group = (object) array(
                'name'              => $grp['AutoScalingGroupName'],
                'launch_config'     => $grp['LaunchConfigurationName'],
                'ctime'             => strtotime($grp['CreatedTime']),
                'zones'             => $grp['AvailabilityZones'],
                // 'current_size'      => count($grp['Instances']),
                'desired_size'      => (int) $grp['DesiredCapacity'],
                'min_size'          => (int) $grp['MinSize'],
                'max_size'          => (int) $grp['MaxSize'],
                // 'instances'         => $grp['Instances'],
                'lb_names'          => $grp['LoadBalancerNames'],
                'health_check_type' => $grp['HealthCheckType'],
                'tags'              => (object) array(),
            );


            foreach ($grp['Tags'] as $t) {
                $group->tags->{$t['Key']} = $t['Value'];
            }
            $groups[] = $group;
        }

        return $groups;
    }

    public static function enumRegions()
    {
        return array(
            'us-east-1'      => 'US East (N. Virginia)',
            'us-west-2'      => 'US West (Oregon)',
            'us-west-1'      => 'US West (N. California)',
            'eu-west-1'      => 'EU (Ireland)',
            'eu-central-1'   => 'EU (Frankfurt)',
            'ap-southeast-1' => 'Asia Pacific (Singapore)',
            'ap-northeast-1' => 'Asia Pacific (Tokyo)',
            'ap-southeast-2' => 'Asia Pacific (Sydney)',
            'ap-northeast-2' => 'Asia Pacific (Seoul)',
            'sa-east-1'      => 'South America (São Paulo)',
        );
    }

    protected function client()
    {
        if ($this->client === null) {
            $this->initializeClient();
        }

        return $this->client;
    }

    protected function initializeClient()
    {
        $params = array(
            'region'  => $this->region,
            'credentials' => $this->key->getCredentials(),
        );

        $config = Config::module('aws');
        if ($proxy = $config->get('network', 'proxy')) {
            $params['request.options'] = array(
                'proxy' => $proxy
            );
        }

        if ($ca = $config->get('network', 'ssl_ca')) {
            $params['ssl.certificate_authority'] = $ca;
        }

        $this->client = Aws::factory($params);
    }

    protected function prepareAwsLibs()
    {
        require_once dirname(__DIR__) . '/vendor/aws/aws-autoloader.php';
    }
}
