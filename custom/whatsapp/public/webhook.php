<?php

if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', '1');
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if (!defined('NOLOGIN')) define('NOLOGIN', '1');
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');

// Load API environment
$res = 0;
if (!$res && file_exists(__DIR__ . '/../../../main.inc.php')) $res = @include_once __DIR__ . '/../../../main.inc.php';
if (!$res && file_exists(__DIR__ . '/../../../../main.inc.php')) $res = @include_once __DIR__ . '/../../../../main.inc.php';
if (!$res) die("Include of main fails");

$core_path = dirname(dirname(dirname(dirname(__FILE__))));

require_once $core_path . '/comm/action/class/actioncomm.class.php';
require_once $core_path . '/core/lib/files.lib.php';
require_once $core_path . '/core/lib/images.lib.php';

// Log webhook headers and body for debugging
if (function_exists('getallheaders')) {
    $headers = getallheaders();
} else {
    $headers = $_SERVER;
}
$body = file_get_contents('php://input');
dol_syslog("========== WhatsApp Webhook START ==========", LOG_DEBUG);
dol_syslog("WhatsApp Webhook Header: " . print_r($headers, true), LOG_DEBUG);
dol_syslog("WhatsApp Webhook Body: " . $body, LOG_DEBUG);

// Verify Secret (optional but recommended)
// $conf->global->WHATSAPP_GOWA_SECRET ...

$data = json_decode($body, true);

if (!$data) {
    dol_syslog("WhatsApp Webhook ERROR: Invalid JSON", LOG_ERR);
    http_response_code(400);
    echo "Invalid JSON";
    exit;
}

dol_syslog("WhatsApp Webhook: Data decoded successfully. Type: " . (isset($data['type']) ? $data['type'] : 'unknown'), LOG_DEBUG);

// Normalize data structure
$isMessage = false;
$payload = array();

if (isset($data['message']) && isset($data['from'])) {
    // New GoWA format: root object is the event
    $isMessage = true;
    $payload = $data;
} elseif (isset($data['type']) && $data['type'] == 'message' && isset($data['payload'])) {
    // Old GoWA format: { "type": "message", "payload": { ... } }
    $isMessage = true;
    $payload = $data['payload'];
} elseif (isset($data['event']) && $data['event'] == 'message' && isset($data['payload'])) {
    // Another GoWA format: { "event": "message", "payload": { ... } }
    $isMessage = true;
    $payload = $data['payload'];
}

if ($isMessage) {
    dol_syslog("WhatsApp Webhook: Processing message event", LOG_DEBUG);
    
    // Check if it's an incoming message (not from me)
    // In some formats fromMe is missing or in payload['message']['fromMe']
    $fromMe = false;
    if (isset($payload['fromMe'])) $fromMe = $payload['fromMe'];
    elseif (isset($payload['message']['fromMe'])) $fromMe = $payload['message']['fromMe'];
    
    if (!$fromMe) {
        $from = isset($payload['from']) ? $payload['from'] : '';
        $pushName = isset($payload['pushname']) ? $payload['pushname'] : (isset($payload['pushName']) ? $payload['pushName'] : '');
        
        // Clean phone number: Extract just digits from the beginning of the 'from' string
        // Format can be: 34600123456@s.whatsapp.net OR 34624230960:17@s.whatsapp.net ...
        $phone = '';
        if (preg_match('/^(\d+)/', $from, $matches)) {
            $phone = $matches[1];
        } else {
            $phone = explode('@', $from)[0];
            $phone = preg_replace('/\D/', '', $phone);
        }
        
        dol_syslog("WhatsApp Webhook: Incoming message from: $phone (Original: $from, Name: $pushName)", LOG_INFO);
        
        // Determine message type and content
        $messageText = '';
        $messageType = 'text';
        $mediaUrl = '';
        $mediaFilename = '';
        $mediaCaption = '';
        
        // 1. Check for text message
        if (isset($payload['message']['text']) && !empty($payload['message']['text'])) {
            $messageText = $payload['message']['text'];
        } elseif (!empty($payload['text'])) {
            $messageText = $payload['text'];
        }
        
        // 2. Check for media (New format uses keys like 'image', 'video', etc. at root of payload or inside message)
        $mediaData = null;
        if (isset($payload['image'])) { $mediaData = $payload['image']; $messageType = 'photo'; }
        elseif (isset($payload['video'])) { $mediaData = $payload['video']; $messageType = 'video'; }
        elseif (isset($payload['audio'])) { $mediaData = $payload['audio']; $messageType = 'audio'; }
        elseif (isset($payload['document'])) { $mediaData = $payload['document']; $messageType = 'document'; }
        elseif (isset($payload['sticker'])) { $mediaData = $payload['sticker']; $messageType = 'sticker'; }
        
        if ($mediaData) {
            // Get URL - GoWA might provide a relative path or full URL
            if (isset($mediaData['url'])) $mediaUrl = $mediaData['url'];
            elseif (isset($mediaData['media_path'])) {
                // Construct URL if it's a relative path. We need to know where GoWA is hosted.
                // For now, let's assume it might be full URL or we try to get it from settings
                $mediaUrl = $mediaData['media_path'];
                if (!preg_match('/^http/', $mediaUrl) && !empty($conf->global->WHATSAPP_GOWA_URL)) {
                    $mediaUrl = rtrim($conf->global->WHATSAPP_GOWA_URL, '/') . '/' . ltrim($mediaUrl, '/');
                }
            }
            
            $mediaCaption = isset($mediaData['caption']) ? $mediaData['caption'] : '';
            $mediaFilename = isset($mediaData['filename']) ? $mediaData['filename'] : '';
            
            // Set friendly message text if empty
            if (empty($messageText)) {
                switch ($messageType) {
                    case 'photo': $messageText = "📷 Foto recibida" . ($mediaCaption ? ": " . $mediaCaption : ""); break;
                    case 'video': $messageText = "🎥 Video recibido" . ($mediaCaption ? ": " . $mediaCaption : ""); break;
                    case 'audio': $messageText = "🎤 Audio recibido"; break;
                    case 'document': $messageText = "📄 Documento recibido" . ($mediaFilename ? ": " . $mediaFilename : ""); break;
                    case 'sticker': $messageText = "🎨 Sticker recibido"; break;
                }
            }
        }
        
        dol_syslog("WhatsApp Webhook: Extracted - Type: $messageType, Text: $messageText, Media: $mediaUrl", LOG_DEBUG);
        
        // Search for Third Party / Contact by phone
        $socid = 0;
        $contactid = 0;
        
        if (!empty($phone)) {
            // Robust phone search
            $phoneSuffix = substr($phone, -9); // Last 9 digits usually enough for Spain
            
            // Try to find contact FIRST (higher specificity)
            $sql = "SELECT rowid, fk_soc FROM ".MAIN_DB_PREFIX."socpeople";
            $sql.= " WHERE phone LIKE '%".$db->escape($phoneSuffix)."'";
            $sql.= " OR phone_mobile LIKE '%".$db->escape($phoneSuffix)."'";
            $sql.= " OR phone_perso LIKE '%".$db->escape($phoneSuffix)."'";
            $sql.= " LIMIT 1";
            
            $resql = $db->query($sql);
            if ($resql && ($obj = $db->fetch_object($resql))) {
                $contactid = $obj->rowid;
                $socid = $obj->fk_soc;
                dol_syslog("WhatsApp Webhook: Found contact ID: $contactid, Thirdparty ID: $socid", LOG_INFO);
            } else {
                // Try to find thirdparty
                $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe";
                $sql.= " WHERE phone LIKE '%".$db->escape($phoneSuffix)."'";
                $sql.= " LIMIT 1";
                
                $resql = $db->query($sql);
                if ($resql && ($obj = $db->fetch_object($resql))) {
                    $socid = $obj->rowid;
                    dol_syslog("WhatsApp Webhook: Found thirdparty ID: $socid", LOG_INFO);
                }
            }
        }
        
        if (!$socid && !$contactid) {
            dol_syslog("WhatsApp Webhook: No contact or thirdparty found for phone suffix: $phoneSuffix", LOG_WARNING);
        }
        
        // Create ActionComm
        $user = new User($db);
        $user->fetch($conf->global->WHATSAPP_INTERNAL_USER_ID ? $conf->global->WHATSAPP_INTERNAL_USER_ID : 1);
        
        $actioncomm = new ActionComm($db);
        $actioncomm->type_code = 'AC_WA_IN';
        $actioncomm->label = "WhatsApp de: " . ($pushName ? $pushName : $phone);
        $actioncomm->note_private = $messageText;
        if ($mediaCaption && $messageType != 'text' && stripos($messageText, $mediaCaption) === false) {
            $actioncomm->note_private .= "\n\nCaption: " . $mediaCaption;
        }
        
        $actioncomm->datep = time();
        $actioncomm->datef = time();
        $actioncomm->percentage = 100;
        $actioncomm->socid = $socid;
        $actioncomm->contactid = $contactid;
        $actioncomm->userownerid = $user->id;
        $actioncomm->authorid = $user->id;
        
        $id = $actioncomm->create($user);
        
        if ($id > 0) {
            dol_syslog("WhatsApp Webhook: ✅ Event created successfully! ID: $id", LOG_INFO);
            
            // Download and attach media if available
            if (!empty($mediaUrl)) {
                // Media attachment logic (simplified for brevity but functional)
                $upload_dir = $conf->agenda->multidir_output[$actioncomm->entity ? $actioncomm->entity : 1] . '/' . $id;
                if (!is_dir($upload_dir)) dol_mkdir($upload_dir);
                
                $filename = !empty($mediaFilename) ? dol_sanitizeFileName($mediaFilename) : 'whatsapp_' . date('Ymd_His') . '.' . ($messageType == 'photo' ? 'jpg' : ($messageType == 'video' ? 'mp4' : 'bin'));
                $destfile = $upload_dir . '/' . $filename;
                
                $fileContent = file_get_contents($mediaUrl);
                if ($fileContent !== false) {
                    if (file_put_contents($destfile, $fileContent) !== false) {
                        dolChmod($destfile);
                        $actioncomm->note_private .= "\n\n📎 Archivo adjunto: " . $filename;
                        $actioncomm->update($user, 1);
                        
                        require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
                        dol_add_file_process($upload_dir, 0, 1, $filename, '', null, '', 0);
                        dol_syslog("WhatsApp Webhook: ✅ Media attached: $filename", LOG_INFO);
                    }
                }
            }
        } else {
            dol_syslog("WhatsApp Webhook: ❌ FAILED to create event: " . $actioncomm->error, LOG_ERR);
        }
    } else {
        dol_syslog("WhatsApp Webhook: Outgoing message, ignoring", LOG_DEBUG);
    }
} else {
    dol_syslog("WhatsApp Webhook: Not a message event or unrecognized format", LOG_DEBUG);
}

dol_syslog("========== WhatsApp Webhook END ==========", LOG_DEBUG);
echo "OK";
