<?php

// SPDX-FileCopyrightText: 2016 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Aws;

use Aws\Credentials\Credentials;
use Icinga\Application\Config;

class AwsKey
{
    protected $credentials;

    protected $id;

    protected $key;

    public function __construct($id, $key)
    {
        $this->id  = $id;
        $this->key = $key;
    }

    public function getCredentials()
    {
        if ($this->credentials === null) {
            $this->credentials = new Credentials($this->id, $this->key);
        }

        return $this->credentials;
    }

    public static function load($name = null)
    {
        if ($name === null) {
            return self::loadDefault();
        } else {
            return self::loadByName($name);
        }
    }

    public static function loadDefault()
    {
        return static::loadByName(current(self::listNames()));
    }

    public static function loadByName($name)
    {
        $config = static::config();
        return new static(
            $config->get($name, 'access_key_id'),
            $config->get($name, 'secret_access_key')
        );
    }

    public static function listNames()
    {
        return static::config()->keys();
    }

    public static function enumKeyNames()
    {
        $names = static::listNames();
        $labels = array_map(function ($name) { return $name . ' (Key)'; }, $names);
        return array_combine($names, $labels);
    }

    protected static function config()
    {
        return Config::module('aws', 'keys');
    }

    public function getId()
    {
        return $this->id;
    }

    public function getKey()
    {
        return $this->key;
    }
}
