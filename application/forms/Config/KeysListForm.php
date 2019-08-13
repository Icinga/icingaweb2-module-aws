<?php

namespace Icinga\Module\Aws\Forms\Config;

use Icinga\Application\Config;
use Icinga\Web\Form;
use Icinga\Web\Notification;

/**
 * Form for listing AWS keys
 */
class KeysListForm extends Form
{
    /**
     * Initialize this form
     */
    public function init()
    {
        $this->setName('form_config_list_aws_keys');
        $this->setViewScript('config/keys-list.phtml');
    }

    /**
     * Get the keys config
     *
     * @return Config
     */
    public function getConfig()
    {
        return Config::module('aws', 'keys');
    }
}
