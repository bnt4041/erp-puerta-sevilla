<?php
require_once __DIR__ . '/../../main.inc.php';

$sql = "SELECT rowid, code, type, libelle FROM ".MAIN_DB_PREFIX."c_actioncomm WHERE code = 'AC_WA'";
$resql = $db->query($sql);

if ($resql && $db->num_rows($resql) > 0) {
    echo "Event Type Found:\n";
    $obj = $db->fetch_object($resql);
    print_r($obj);
} else {
    echo "Event Type NOT Found. Attempting to create...\n";
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."c_actioncomm (code, type, libelle, module, active, position) VALUES ('AC_WA', 'whatsapp', 'WhatsApp message sent', 'whatsapp', 1, 10)";
    if ($db->query($sql)) {
        echo "Event Type Created Successfully.\n";
    } else {
        echo "Failed to create event type: " . $db->error();
    }
}
