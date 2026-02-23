<?php

// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

$this->provideConfigTab('keys', array(
    'title' => $this->translate('Configure your AWS access keys'),
    'label' => $this->translate('AWS Keys'),
    'url' => 'config/keys'
));