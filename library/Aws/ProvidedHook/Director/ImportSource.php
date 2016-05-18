<?php

namespace Icinga\Module\Aws\ProvidedHook\Director;

use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Aws\AwsClient;
use Icinga\Module\Aws\AwsKey;
use Icinga\Application\Benchmark;

class ImportSource extends ImportSourceHook
{
    protected $db;

    public function fetchData()
    {
        $client = new AwsClient(
            AwsKey::loadByName($this->getSetting('aws_access_key')),
            $this->getSetting('aws_region')
        );

        return $client->getAutoscalingConfig();
    }

    public function listColumns()
    {
        return array(
            'name',
            'launch_config',
            'ctime',
            'zones',
            'desired_size',
            'min_size',
            'max_size',
            'lb_names',
            'health_check_type',
            'tags',
            'tags.Name',
            'tags.aws:cloudformation:logical-id',
            'tags.aws:cloudformation:stack-id',
            'tags.aws:cloudformation:stack-name',
        );
    }

    public static function getDefaultKeyColumnName()
    {
        return 'name';
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('select', 'aws_region', array(
            'label'        => 'AWS region',
            'required'     => true,
            'multiOptions' => $form->optionalEnum(AwsClient::enumRegions()),
        ));

        $form->addElement('select', 'aws_access_key', array(
            'label'        => 'AWS access key',
            'required'     => true,
            'multiOptions' => $form->optionalEnum(AwsKey::enumKeyNames()),
            'class'        => 'autosubmit',
        ));
    }
}
