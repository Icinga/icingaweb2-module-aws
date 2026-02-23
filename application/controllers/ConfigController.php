<?php

// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Aws\Controllers;

use Icinga\Forms\ConfirmRemovalForm;
use Icinga\Module\Aws\Forms\Config\KeysForm;
use Icinga\Module\Aws\Forms\Config\KeysListForm;
use Icinga\Web\Controller;
use Icinga\Web\Notification;

class ConfigController extends Controller
{
    public function init()
    {
        $this->assertPermission('config/modules');
        parent::init();
    }

    public function keysAction()
    {
        $this->view->form = $form = new KeysListForm();
        $form->handleRequest();

        $this->view->config = $this->Config('keys');
        $this->view->tabs = $this->Module()->getConfigTabs()->activate('keys');
    }

    public function removekeyAction()
    {
        $keyName = $this->params->getRequired('key');

        $keysForm = new KeysForm();
        $keysForm->setIniConfig($this->Config('keys'));
        $form = new ConfirmRemovalForm();
        $form->setRedirectUrl('aws/config/keys');
        $form->setTitle(sprintf($this->translate('Remove Key %s'), $keyName));
        $form->info(
            $this->translate(
                'If you still have any import sources referring to this key, '
                . 'you won\'t be able to use them without changing the key.'
            ),
            false
        );
        $form->setOnSuccess(function (ConfirmRemovalForm $form) use ($keysForm, $keyName) {
            try {
                $keysForm->delete($keyName);
            } catch (Exception $e) {
                $form->error($e->getMessage());
                return false;
            }

            if ($keysForm->save()) {
                Notification::success(sprintf(t('Key "%s" successfully removed'), $keyName));
                return true;
            }

            return false;
        });
        $form->handleRequest();

        $this->view->form = $form;
        $this->render('form');
    }

    public function editkeyAction()
    {
        $keyName = $this->params->getRequired('key');

        $form = new KeysForm();
        $form->setRedirectUrl('aws/config/keys');
        $form->setTitle(sprintf($this->translate('Edit Key %s'), $keyName));
        $form->setIniConfig($this->Config('keys'));
        $form->setOnSuccess(function (KeysForm $form) use ($keyName) {
            try {
                $form->edit($keyName, array_map(
                    function ($v) {
                        return $v !== '' ? $v : null;
                    },
                    $form->getValues()
                ));
            } catch (Exception $e) {
                $form->error($e->getMessage());
                return false;
            }

            if ($form->save()) {
                Notification::success(sprintf(t('Key "%s" successfully updated'), $keyName));
                return true;
            }

            return false;
        });

        try {
            $form->load($keyName);
            $form->handleRequest();
        } catch (NotFoundError $_) {
            $this->httpNotFound(sprintf($this->translate('Key "%s" not found'), $keyName));
        }

        $this->view->form = $form;
        $this->render('form');
    }

    public function createkeyAction()
    {
        $form = new KeysForm();
        $form->setRedirectUrl('aws/config/keys');
        $form->setTitle($this->translate('Create New Key'));
        $form->setIniConfig($this->Config('keys'));
        $form->setOnSuccess(function (KeysForm $form) {
            try {
                $form->add($form::transformEmptyValuesToNull($form->getValues()));
            } catch (Exception $e) {
                $form->error($e->getMessage());
                return false;
            }

            if ($form->save()) {
                Notification::success(t('Key successfully created'));
                return true;
            }

            return false;
        });
        $form->handleRequest();

        $this->view->form = $form;
        $this->render('form');
    }
}