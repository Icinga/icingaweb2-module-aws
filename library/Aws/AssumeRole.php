<?php

namespace Icinga\Module\Aws;

class AssumeRole
{
    protected $arn;

    protected $session;

    public static function create($arn, $session)
    {
        $assumeRole = new static();

        $assumeRole->arn = $arn;
        $assumeRole->session = $session;

        return $assumeRole;
    }

    public function getParams()
    {
        return [
            'RoleArn'         => $this->arn,
            'RoleSessionName' => $this->session
        ];
    }
}
