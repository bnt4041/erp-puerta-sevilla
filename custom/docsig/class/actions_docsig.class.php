<?php
/**
 * Proxy loader for DocSig hooks.
 *
 * Dolibarr expects the module folder to match the module name ("docsig"),
 * but the actual implementation lives in /custom/signDol.
 * This bridge simply includes the real hook class so HookManager can load it.
 */

$realFile = __DIR__.'/../../signDol/class/actions_docsig.class.php';

if (file_exists($realFile)) {
    require_once $realFile;
}
