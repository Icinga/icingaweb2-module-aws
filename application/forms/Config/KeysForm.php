<?php

namespace Icinga\Module\Aws\Forms\Config;

use InvalidArgumentException;
use Icinga\Exception\IcingaException;
use Icinga\Exception\NotFoundError;
use Icinga\Forms\ConfigForm;

/**
 * Form for managing AWS access keys
 */
class KeysForm extends ConfigForm
{
    /**
     * The key to load when displaying the form for the first time
     *
     * @var string
     */
    protected $keyToLoad;

    /**
     * @var bool
     */
    protected $validatePartial = true;

    /**
     * Initialize this form
     */
    public function init()
    {
        $this->setName('form_config_aws_keys');
        $this->setSubmitLabel($this->translate('Save Changes'));
    }

    /**
     * Populate the form with the given keys's config
     *
     * @param   string  $name
     *
     * @return  $this
     *
     * @throws  NotFoundError   In case no key with the given name is found
     */
    public function load($name)
    {
        if (! $this->config->hasSection($name)) {
            throw new NotFoundError('No key called "%s" found', $name);
        }

        $this->keyToLoad = $name;
        return $this;
    }

    /**
     * Add a new key
     *
     * The key to add is identified by the array-key `name'.
     *
     * @param   array   $data
     *
     * @return  $this
     *
     * @throws  InvalidArgumentException    In case $data does not contain a key name
     * @throws  IcingaException             In case a key with the same name already exists
     */
    public function add(array $data)
    {
        if (! isset($data['name'])) {
            throw new InvalidArgumentException('Key \'name\' missing');
        }

        $keyName = $data['name'];
        if ($this->config->hasSection($keyName)) {
            throw new IcingaException(
                $this->translate('A key with the name "%s" does already exist'),
                $keyName
            );
        }

        unset($data['name']);
        $this->config->setSection($keyName, $data);
        return $this;
    }

    /**
     * Edit an existing key
     *
     * @param   string  $name
     * @param   array   $data
     *
     * @return  $this
     *
     * @throws  NotFoundError   In case no key with the given name is found
     */
    public function edit($name, array $data)
    {
        if (! $this->config->hasSection($name)) {
            throw new NotFoundError('No key called "%s" found', $name);
        }

        $keyConfig = $this->config->getSection($name);
        if (isset($data['name'])) {
            if ($data['name'] !== $name) {
                $this->config->removeSection($name);
                $name = $data['name'];
            }

            unset($data['name']);
        }

        $keyConfig->merge($data);
        $this->config->setSection($name, $keyConfig);
        return $this;
    }

    /**
     * Remove a key
     *
     * @param   string  $name
     *
     * @return  $this
     */
    public function delete($name)
    {
        $this->config->removeSection($name);
        return $this;
    }

    /**
     * Create and add elements to this form
     *
     * @param   array   $formData
     */
    public function createElements(array $formData)
    {
        $this->addElements([
            [
                'text',
                'name',
                [
                    'required'      => true,
                    'label'         => $this->translate('Key Name'),
                    'description'   => $this->translate(
                        'The name of this key that is used to differentiate it from others'
                    )
                ],
            ],
            [
                'text',
                'access_key_id',
                [
                    'required'      => true,
                    'label'         => $this->translate('Access Key ID'),
                    'description'   => $this->translate('Your AWS access key')
                ]
            ],
            [
                'password',
                'secret_access_key',
                [
                    'required'          => true,
                    'renderPassword'    => true,
                    'label'             => $this->translate('Access Key Secret'),
                    'description'       => $this->translate('The access key\'s secret')
                ]
            ]
        ]);
    }

    /**
     * Populate the configuration of the key to load
     */
    public function onRequest()
    {
        if ($this->keyToLoad) {
            $data = $this->config->getSection($this->keyToLoad)->toArray();
            $data['name'] = $this->keyToLoad;
            $this->populate($data);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isValidPartial(array $formData)
    {
        $isValidPartial =  parent::isValidPartial($formData);

        $keyValidation = $this->getElement('key_validation');
        if ($keyValidation !== null && $this->isValid($formData)) {
            $this->info($this->translate('The configuration has been successfully validated.'));
        }

        return $isValidPartial;
    }
}
