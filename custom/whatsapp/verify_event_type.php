<?php
// Script to verify and create AC_WA event type
define('NOCSRFCHECK', '1');
define('NOTOKENRENEWAL', '1');
define('NOREQUIREMENU', '1');
define('NOREQUIREHTML', '1');
define('NOREQUIREAJAX', '1');

require_once __DIR__ . '/../../main.inc.php';

// Check if AC_WA exists
$sql = "SELECT rowid, code, type, libelle, module, active FROM ".MAIN_DB_PREFIX."c_actioncomm WHERE code = 'AC_WA'";
$resql = $db->query($sql);

if ($resql && $db->num_rows($resql) > 0) {
    echo "✓ Event Type AC_WA already exists:\n";
    $obj = $db->fetch_object($resql);
    echo "  - ID: " . $obj->rowid . "\n";
    echo "  - Code: " . $obj->code . "\n";
    echo "  - Type: " . $obj->type . "\n";
    echo "  - Label: " . $obj->libelle . "\n";
    echo "  - Module: " . $obj->module . "\n";
    echo "  - Active: " . $obj->active . "\n";
} else {
    echo "✗ Event Type AC_WA does NOT exist. Creating...\n";
    
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."c_actioncomm (code, type, libelle, module, active, position) 
            VALUES ('AC_WA', 'whatsapp', 'WhatsApp Message', 'whatsapp', 1, 10)";
    
    if ($db->query($sql)) {
        echo "✓ Event Type AC_WA created successfully!\n";
        
        // Verify creation
        $sql = "SELECT rowid, code, type, libelle FROM ".MAIN_DB_PREFIX."c_actioncomm WHERE code = 'AC_WA'";
        $resql = $db->query($sql);
        if ($resql && $db->num_rows($resql) > 0) {
            $obj = $db->fetch_object($resql);
            echo "  - New ID: " . $obj->rowid . "\n";
        }
    } else {
        echo "✗ Failed to create event type: " . $db->lasterror() . "\n";
    }
}

echo "\n--- All Event Types ---\n";
$sql = "SELECT code, type, libelle, module FROM ".MAIN_DB_PREFIX."c_actioncomm WHERE module = 'whatsapp' OR code LIKE '%WA%' ORDER BY code";
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        echo "  " . $obj->code . " | " . $obj->type . " | " . $obj->libelle . " | " . $obj->module . "\n";
    }
}
