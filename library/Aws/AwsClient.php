<?php

namespace Icinga\Module\Aws;

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
        $objects = array();
        $client = $this->client()->get('AutoScaling');
        $res = $client->describeAutoScalingGroups();

        foreach ($res->get('AutoScalingGroups') as $entry) {

            $objects[] = $object = $this->extractAttributes($entry, array(
                'name'              => 'AutoScalingGroupName',
                'launch_config'     => 'LaunchConfigurationName',
                'zones'             => 'AvailabilityZones',
                'lb_names'          => 'LoadBalancerNames',
                'health_check_type' => 'HealthCheckType',
            ));

            $object->ctime        = strtotime($entry['CreatedTime']);
            $object->desired_size = (int) $entry['DesiredCapacity'];
            $object->min_size     = (int) $entry['MinSize'];
            $object->max_size     = (int) $entry['MaxSize'];
            $this->extractTags($entry, $object);
        }

        return $this->sortByName($objects);
    }

    public function getLoadBalancers()
    {
        $client = $this->client()->get('ElasticLoadBalancing');
        $res = $client->describeLoadBalancers();
        $objects = array();
        foreach ($res->get('LoadBalancerDescriptions') as $entry) {
            $objects[] = $object = $this->extractAttributes($entry, array(
                'name'    => 'LoadBalancerName',
                'dnsname' => 'DNSName',
                'scheme'  => 'Scheme',
                'zones'   => 'AvailabilityZones',
            ));

            $object->health_check = $entry['HealthCheck']['Target'];

            $object->listeners = (object) array();
            foreach ($entry['ListenerDescriptions'] as $l) {
                $listener = $l['Listener'];
                $object->listeners->{$listener['LoadBalancerPort']} = $this->extractAttributes(
                    $listener,
                    array(
                        'port'              => 'LoadBalancerPort',
                        'protocol'          => 'Protocol',
                        'instance_port'     => 'InstancePort',
                        'instance_protocol' => 'InstanceProtocol',
                    )
                );
            }
        }

        return $this->sortByName($objects);
    }

    public function getEc2Instances()
    {
        $client = $this->client()->get('Ec2');
        $res = $client->describeInstances();
        $objects = array();
        foreach ($res->get('Reservations') as $reservation) {

            foreach ($reservation['Instances'] as $entry) {
                $objects[] = $object = $this->extractAttributes($entry, array(
                    'name'             => 'InstanceId',
                    'image'            => 'ImageId',
                    'architecture'     => 'Architecture',
                    'hypervisor'       => 'Hypervisor',
                    'virt_type'        => 'VirtualizationType',
                ), array(
                    'vpc_id'           => 'VpcId',
                    'root_device_type' => 'RootDeviceType',
                    'root_device_name' => 'RootDeviceName',
                    'public_ip'        => 'PublicIpAddress',
                    'public_dns'       => 'PublicDnsName',
                    'private_ip'       => 'PrivateIpAddress',
                    'private_dns'      => 'PrivateDnsName',
                ));
                $object->state = $entry['State']['Name'];
                $object->monitoring_state = $entry['Monitoring']['State'];

                $this->extractTags($entry, $object);
            }
        }

        return $this->sortByName($objects);
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

    protected function sortByName($objects)
    {
        usort($objects, array($this, 'compareName'));
        return $objects;
    }

    protected function extractAttributes($entry, $required, $optional = array(), $subkey = null)
    {
        $result = (object) array();
        if ($subkey !== null) {
            $entry = $entry[$subkey];
        }

        foreach ($required as $alias => $key) {
            $result->$alias = $entry[$key];
        }

        foreach ($optional as $alias => $key) {
            if (array_key_exists($key, $entry)) {
                $result->$alias = $entry[$key];
            } else {
                $result->$alias = null;
            }
        }

        return $result;
    }

    protected function extractTags($entry, $result)
    {
        $result->tags = (object) array();
        if (! array_key_exists('Tags', $entry)) {
            return;
        }

        foreach ($entry['Tags'] as $t) {
            $result->tags->{$t['Key']} = $t['Value'];
            // so we can use these tags in the expected way in import filters via tags.attribute
            $result->{'tags.'.$t['Key']} = $t['Value'];

        }
    }

    protected function compareName($a, $b)
    {
        return strcmp($a->name, $b->name);
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
