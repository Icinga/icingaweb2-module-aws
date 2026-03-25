<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

require_once __DIR__ . '/vendor/autoload.php';

/** @var $this \Icinga\Application\Modules\Module */
$this->provideHook('director/ImportSource');
