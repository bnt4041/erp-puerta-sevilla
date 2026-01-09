<?php
/**
 * AJAX endpoint to get WhatsApp chat history
 * Returns last 40 messages by default
 */

// Allow ajax without CSRF renewal, but keep session login
if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', '1');
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');

$res = 0;
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if (!$res) die("Include of main fails");

header('Content-Type: application/json');

if (!$user->id) {
    echo json_encode(array('error' => 'Unauthorized'));
    exit;
}

$phone = GETPOST('phone', 'alpha');
$objectid = GETPOST('objectid', 'int');
$objecttype = GETPOST('objecttype', 'alpha');
$limit = GETPOST('limit', 'int') ?: 40;

$messages = array();

// Helper: safe JSON error response and exit
function wa_json_error($msg)
{
    echo json_encode(array('error' => 1, 'message' => $msg));
    exit;
}

// Build WHERE clause based on object type
$where = "";
if ($objecttype == 'societe') {
    $where = " (a.fk_soc = " . (int)$objectid . ")";
} elseif ($objecttype == 'contact') {
    $where = " (a.fk_contact = " . (int)$objectid . ")";
} 

if (empty($where) && !empty($phone)) {
    // Fallback: search by phone suffix in title/note if IDs not provided or no matches
    $phoneSuffix = substr(preg_replace('/\D/', '', $phone), -9);
    $where = " (a.label LIKE '%" . $db->escape($phoneSuffix) . "%' OR a.note_private LIKE '%" . $db->escape($phoneSuffix) . "%')";
}

if (empty($where)) {
    echo json_encode(array());
    exit;
}

// Query messages with media info from extrafields if available
$sql = "SELECT a.rowid, a.type_code, a.label, a.note_private, a.datep, a.fk_element, a.elementtype";

// Check if extrafields table exists and collect available media columns
$hasExtrafields = false;
$mediaCols = array();

$sqlCheck = "SHOW TABLES LIKE '" . MAIN_DB_PREFIX . "actioncomm_extrafields'";
$resCheck = $db->query($sqlCheck);
if ($resCheck && $db->num_rows($resCheck) > 0) {
    $hasExtrafields = true;

    $availableCols = array();
    $sqlCols = "SHOW COLUMNS FROM " . MAIN_DB_PREFIX . "actioncomm_extrafields";
    $resCols = $db->query($sqlCols);
    if ($resCols) {
        while ($col = $db->fetch_object($resCols)) {
            $availableCols[$col->Field] = true;
        }
    }

    foreach (array('wa_media_type','wa_media_url','wa_media_filename','wa_media_size','wa_media_mime') as $c) {
        if (!empty($availableCols[$c])) {
            $mediaCols[] = $c;
        }
    }

    if (count($mediaCols) > 0) {
        foreach ($mediaCols as $c) {
            $sql .= ", ef." . $c;
        }
    } else {
        $hasExtrafields = false; // no media columns present
    }
}

$sql .= " FROM " . MAIN_DB_PREFIX . "actioncomm as a";
if ($hasExtrafields) {
    $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "actioncomm_extrafields as ef ON ef.fk_object = a.rowid";
}
$sql .= " WHERE " . $where;
$sql .= " AND a.type_code IN ('AC_WA', 'AC_WA_IN', 'AC_WA_MEDIA')";
$sql .= " ORDER BY a.datep DESC";
$sql .= " LIMIT " . (int)$limit;

$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $datep = $db->jdate($obj->datep);
        
        $message = array(
            'id' => $obj->rowid,
            'type' => ($obj->type_code == 'AC_WA' || $obj->type_code == 'AC_WA_MEDIA') ? 'sent' : 'received',
            'text' => $obj->note_private,
            'date' => dol_print_date($datep, 'dayhour'),
            'date_only' => dol_print_date($datep, 'day'),
            'time' => dol_print_date($datep, 'hour'),
            'status' => 'sent' // Default status
        );
        
        // Add media info if available
        if ($hasExtrafields && !empty($obj->wa_media_type)) {
            $message['media_type'] = $obj->wa_media_type;
            if (!empty($obj->wa_media_url)) $message['media_url'] = $obj->wa_media_url;
            if (!empty($obj->wa_media_filename)) $message['media_filename'] = $obj->wa_media_filename;
            if (isset($obj->wa_media_size)) $message['media_size'] = (int)$obj->wa_media_size;
            if (!empty($obj->wa_media_mime)) $message['media_mime'] = $obj->wa_media_mime;
        }
        
        // Check for document attachments in ECM
        if (!$hasExtrafields || empty($obj->wa_media_type)) {
            $sqlDoc = "SELECT ecmf.filepath, ecmf.filename, ecmf.filesize";
            $sqlDoc .= " FROM " . MAIN_DB_PREFIX . "ecm_files as ecmf";
            $sqlDoc .= " WHERE ecmf.src_object_type = 'actioncomm'";
            $sqlDoc .= " AND ecmf.src_object_id = " . (int)$obj->rowid;
            $sqlDoc .= " LIMIT 1";
            
            $resDoc = $db->query($sqlDoc);
            if ($resDoc && $objDoc = $db->fetch_object($resDoc)) {
                $message['media_type'] = 'document';
                $message['media_url'] = DOL_URL_ROOT . '/document.php?modulepart=actions&file=' . urlencode($objDoc->filepath . '/' . $objDoc->filename);
                $message['media_filename'] = $objDoc->filename;
                $message['media_size'] = (int)$objDoc->filesize;
                
                // Determine mime type from extension
                $ext = strtolower(pathinfo($objDoc->filename, PATHINFO_EXTENSION));
                $mimeTypes = array(
                    'pdf' => 'application/pdf',
                    'doc' => 'application/msword',
                    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'xls' => 'application/vnd.ms-excel',
                    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'ppt' => 'application/vnd.ms-powerpoint',
                    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'mp4' => 'video/mp4',
                    'mp3' => 'audio/mpeg',
                    'txt' => 'text/plain'
                );
                $message['media_mime'] = isset($mimeTypes[$ext]) ? $mimeTypes[$ext] : 'application/octet-stream';
                
                // Correct media type based on mime
                if (strpos($message['media_mime'], 'image/') === 0) {
                    $message['media_type'] = 'image';
                } elseif (strpos($message['media_mime'], 'video/') === 0) {
                    $message['media_type'] = 'video';
                } elseif (strpos($message['media_mime'], 'audio/') === 0) {
                    $message['media_type'] = 'audio';
                }
            }
        }
        
        $messages[] = $message;
    }
} else {
    wa_json_error($db->lasterror());
}

// Reverse to show oldest first (chronological order)
$messages = array_reverse($messages);

echo json_encode($messages);
