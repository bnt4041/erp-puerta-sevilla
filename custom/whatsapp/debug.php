<?php
echo "DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "__FILE__: " . __FILE__ . "<br>";
echo "dirname(__FILE__): " . dirname(__FILE__) . "<br>";
echo "DOL_DOCUMENT_ROOT: " . (defined('DOL_DOCUMENT_ROOT') ? DOL_DOCUMENT_ROOT : 'Not defined') . "<br>";
include_once '../../main.inc.php';
echo "DOL_DOCUMENT_ROOT after main.inc.php: " . (defined('DOL_DOCUMENT_ROOT') ? DOL_DOCUMENT_ROOT : 'Not defined') . "<br>";
