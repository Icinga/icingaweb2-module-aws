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
        $keyName = $this->getSetting('aws_access_key');
        $key = null;

        if ($keyName) {
            $key = AwsKey::loadByName($keyName);
        }

        $client = new AwsClient($key, $this->getSetting('aws_region'));

        switch ($this->getObjectType()) {
            case 'asg':
                return $client->getAutoscalingConfig();
            case 'lb':
                return $client->getLoadBalancers();
            case 'ec2instance':
                return $client->getEc2Instances();
            case 'rdsinstance':
                return $client->getRdsInstances();
        }
    }

    protected function getObjectType()
    {
        // Compat for old configs, asg used to be the only available type:
        $type = $this->getSetting('object_type', 'asg');

        $validTypes = array_keys(static::enumObjectTypes());

        if (! in_array($type, $validTypes)) {
            throw new ConfigurationError(
                'Got no invalid AWS object type: "%s"',
                $type
            );
        }

        return $type;
    }

    public function listColumns()
    {
        switch ($this->getObjectType()) {
            case 'asg':
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
            case 'lb':
                return array(
                    'name',
                    'dnsname',
                    'scheme',
                    'zones',
                    'listeners',
                    'health_check',
                );
            case 'rdsinstance':
                return array(
                    'name',
                    'port',
                    'fqdn',
                    'engine',
                    'version',
                    'security_groups',
                );
            case 'ec2instance':
                return array(
                    'name',
                    'image',
                    'architecture',
                    'root_device_type',
                    'root_device_name',
                    'hypervisor',
                    'instance_type',
                    'virt_type',
                    'vpc_id',
                    'public_ip',
                    'public_dns',
                    'private_ip',
                    'private_dns',
                    'disabled',
                    'monitoring_state',
                    'security_groups',
                    'status',
                    'tags',
                    'tags.Name',
                    'tags.aws:autoscaling:groupName',
                    'tags.aws:cloudformation:logical-id',
                    'tags.aws:cloudformation:stack-id',
                    'tags.aws:cloudformation:stack-name',
                );
        }
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
            'label'        => 'AWS access method',
            'required'     => false,
            'description'  => $form->translate(
                'Use IAM role credential or select your AWS key. This shows all keys from your keys.ini.'
                . ' Please check the documentation if you miss the keys in the list.'
            ),
            'multiOptions' => $form->optionalEnum(AwsKey::enumKeyNames(), $form->translate(
                'IAM role credentials'
            )),
            'class'        => 'autosubmit',
        ));

        $form->addElement('select', 'object_type', array(
            'label'        => 'Object type',
            'required'     => true,
            'description'  => $form->translate(
                'AWS object type'
            ),
            'multiOptions' => $form->optionalEnum(
                static::enumObjectTypes($form)
            ),
            'class'        => 'autosubmit',
        ));
    }

    protected static function enumObjectTypes($form)
    {
        static $enumerationTypes = null;

        if ($enumerationTypes === null) {
            $enumerationTypes = array(
                'asg'         => $form->translate('Auto Scaling Groups'),
                'lb'          => $form->translate('Elastic Load Balancers'),
                'ec2instance' => $form->translate('EC2 Instances'),
                'rdsinstance' => $form->translate('RDS Instances')
            );
        }

        return $enumerationTypes;
    }
}
