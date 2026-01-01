<?php

if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', '1');
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if (!defined('NOLOGIN')) define('NOLOGIN', '1');
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');

// Load API environment
require '../../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

// Log webhook headers and body for debugging
$headers = getallheaders();
$body = file_get_contents('php://input');
dol_syslog("WhatsApp Webhook Header: " . print_r($headers, true), LOG_DEBUG);
dol_syslog("WhatsApp Webhook Body: " . $body, LOG_DEBUG);

// Verify Secret (optional but recommended)
// $conf->global->WHATSAPP_GOWA_SECRET ...

$data = json_decode($body, true);

if (!$data) {
    http_response_code(400);
    echo "Invalid JSON";
    exit;
}

// Handle Message Event
if (isset($data['type']) && $data['type'] == 'message') {
    $payload = $data['payload'];
    
    // Ignore updates, only new messages
    // goWA might send various events.
    
    // Check if it's an incoming message (not from me)
    if (!$payload['fromMe'] && !empty($payload['text'])) {
        $from = $payload['from']; // e.g. 34600123456@s.whatsapp.net
        $text = $payload['text'];
        $pushName = isset($payload['pushName']) ? $payload['pushName'] : '';
        
        // Clean phone number
        $phone = explode('@', $from)[0];
        
        // Find Third Party / Contact by phone
        // This logic can be complex. For now, we just create the event.
        
        $socid = 0;
        $contactid = 0;
        
        // Try to find thirdparty by phone
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE phone = '".$db->escape($phone)."' OR phone = '+".$db->escape($phone)."'";
        $resql = $db->query($sql);
        if ($resql && $db->num_rows($resql) > 0) {
            $obj = $db->fetch_object($resql);
            $socid = $obj->rowid;
        } else {
            // Try contact
             $sql = "SELECT rowid, fk_soc FROM ".MAIN_DB_PREFIX."socpeople WHERE phone = '".$db->escape($phone)."' OR phone = '+".$db->escape($phone)."' OR phone_mobile = '".$db->escape($phone)."' OR phone_mobile = '+".$db->escape($phone)."'";
             $resql = $db->query($sql);
             if ($resql && $db->num_rows($resql) > 0) {
                $obj = $db->fetch_object($resql);
                $contactid = $obj->rowid;
                $socid = $obj->fk_soc;
             }
        }
        
        // Create ActionComm
        $user = new User($db);
        // We need a user to assign this to. typically admin or a specific user.
        // For now, let's pick the first internal user or admin
        $user->fetch(1); // Default to superadmin
        
        $actioncomm = new ActionComm($db);
        $actioncomm->type_code = 'AC_WA'; // WhatsApp type
        $actioncomm->label = "WhatsApp Incoming: $pushName";
        $actioncomm->note = $text;
        $actioncomm->datep = time();
        $actioncomm->datef = time();
        $actioncomm->percentage = 100; // Done
        $actioncomm->socid = $socid;
        $actioncomm->contactid = $contactid;
        $actioncomm->userownerid = $user->id;
        $actioncomm->authorid = $user->id; // System or Admin
        
        $id = $actioncomm->create($user);
        
        if ($id > 0) {
            dol_syslog("WhatsApp Webhook: Event created ID $id");
        } else {
            dol_syslog("WhatsApp Webhook: Failed to create event " . $actioncomm->error, LOG_ERR);
        }
    }
}

echo "OK";
