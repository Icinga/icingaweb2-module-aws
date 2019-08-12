<?php
namespace Icinga\Module\Aws {
    require_once __DIR__ . '/library/vendor/autoload.php';

    $this->provideHook('director/ImportSource');
}
