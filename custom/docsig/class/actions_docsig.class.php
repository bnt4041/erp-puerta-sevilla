<?php
/**
 * Wrapper hook file for DocSig module.
 *
 * Dolibarr's HookManager loads hooks from /<module>/class/actions_<module>.class.php
 * where <module> is the module id (lowercase, e.g. "docsig").
 *
 * This ERP instance keeps the module sources under /custom/signDol.
 * This wrapper bridges the naming mismatch by including the real hook class.
 */

// Load the real hook implementation
$res = dol_include_once('/signDol/class/actions_docsig.class.php');

// Ensure the expected class name exists for HookManager: ActionsDocsig
if ($res && class_exists('ActionsDocSig') && !class_exists('ActionsDocsig')) {
    class_alias('ActionsDocSig', 'ActionsDocsig');
}
