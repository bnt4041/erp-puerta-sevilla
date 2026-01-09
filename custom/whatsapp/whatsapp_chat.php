<?php

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("main.inc.php")) $res = @include "main.inc.php";
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once dol_buildpath('/whatsapp/class/gowaclient.class.php', 0);
require_once dol_buildpath('/whatsapp/lib/whatsapp.lib.php', 0);

$langs->loadLangs(array("whatsapp@whatsapp", "other", "agenda", "companies", "contacts"));

$id = GETPOST('id', 'int');
$objecttype = GETPOST('objecttype', 'alpha');

if (empty($id) || empty($objecttype)) {
	accessforbidden('Missing ID or Object Type');
}

// Fetch object
$core_path = DOL_DOCUMENT_ROOT;

// Map objecttype to file and class name
$classfile = $objecttype;
$classname = ucfirst($objecttype);
$classpath = $objecttype;

if ($objecttype == 'contact') {
    $classfile = 'socpeople';
    $classname = 'Contact';
    $classpath = 'contact';
} elseif ($objecttype == 'societe') {
    $classname = 'Societe';
    $classpath = 'societe';
} elseif ($objecttype == 'propal') {
    $classname = 'Propal';
    $classpath = 'comm/propal';
}

require_once $core_path.'/'.$classpath.'/class/'.$classfile.'.class.php';

$object = new $classname($db);
$object->fetch($id);

// Determine phone number
$phone = '';
if ($objecttype == 'societe') {
    $phone = $object->phone;
} elseif ($objecttype == 'contact') {
    $phone = $object->phone_mobile ? $object->phone_mobile : $object->phone_pro;
}

// Pre-clean phone number for GoWA
// 1. Remove non-digits
$cleanPhone = preg_replace('/\D/', '', $phone);
// 2. Handle Spanish numbers starting with 6, 7 or 8/9 (fixing country code 34)
if (preg_match('/^[6789]\d{8}$/', $cleanPhone)) {
    $cleanPhone = '34' . $cleanPhone;
}

// Add CSS and JS
$extra_head = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
$extra_head .= '<link rel="stylesheet" type="text/css" href="' . dol_buildpath('/whatsapp/css/chat.css', 1) . '?v=' . time() . '">';
$extra_head .= '<script>dolibarr_uri_base="'.DOL_URL_ROOT.'"</script>';
$extra_head .= '<script type="text/javascript" src="' . dol_buildpath('/whatsapp/js/chat.js', 1) . '?v=' . time() . '"></script>';

// Header
llxHeader('', 'WhatsApp Chat', '', '', '', '', array(), array(), $extra_head);

// Tabs
if ($objecttype == 'societe') {
    require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
    $head = societe_prepare_head($object);
    print dol_get_fiche_head($head, 'whatsapp', $langs->trans("ThirdParty"), -1, 'company');
} elseif ($objecttype == 'contact') {
    require_once DOL_DOCUMENT_ROOT.'/core/lib/contact.lib.php';
    if (function_exists('contact_prepare_head')) {
        $head = contact_prepare_head($object);
    } else {
        // Fallback if function doesn't exist in older Dolibarr versions
        $head = array();
        $head[0][0] = dol_buildpath('/contact/card.php', 1).'?id='.$object->id;
        $head[0][1] = $langs->trans("Card");
        $head[0][2] = 'card';
        
        $head[1][0] = dol_buildpath('/whatsapp/whatsapp_chat.php', 1).'?id='.$object->id.'&objecttype=contact';
        $head[1][1] = 'WhatsApp';
        $head[1][2] = 'whatsapp';
    }
    print dol_get_fiche_head($head, 'whatsapp', $langs->trans("Contact"), -1, 'contact');
}

/*
 * VIEW
 */

print '<div class="whatsapp-chat-container">';

    // Header
    print '<div class="whatsapp-chat-header">';
        print '<div class="wa-chat-contact-info">';
            print '<strong><i class="fab fa-whatsapp"></i> ' . dol_escape_htmltag($object->getFullName($langs)) . '</strong>';
            if (!empty($phone)) {
                print '<small><i class="fas fa-phone"></i> ' . dol_escape_htmltag($phone) . '</small>';
            } else {
                print '<small class="text-warning"><i class="fas fa-exclamation-triangle"></i> Sin número de teléfono</small>';
            }
        print '</div>';
        print '<div class="wa-chat-header-actions">';
            print '<button class="wa-header-btn" onclick="loadChatHistory()" title="Actualizar"><i class="fas fa-sync-alt"></i></button>';
        print '</div>';
    print '</div>';
    
    // Chat history
    print '<div id="wa-chat-history" class="whatsapp-chat-history">';
        print '<div class="wa-loading"><i class="fas fa-spinner"></i> ' . $langs->trans("Loading") . '...</div>';
    print '</div>';

    // Footer with input
    print '<div class="whatsapp-chat-footer">';
    
        // Attachment preview area
        print '<div class="wa-attachment-preview">';
            print '<div class="wa-attachment-thumb"></div>';
            print '<div class="wa-attachment-info">';
                print '<div class="wa-attachment-name"></div>';
                print '<div class="wa-attachment-size"></div>';
            print '</div>';
            print '<button class="wa-attachment-remove" title="Quitar archivo"><i class="fas fa-times"></i></button>';
        print '</div>';
        
        // Input form
        print '<div id="wa-chat-form">';
            print '<input type="hidden" id="wa-phone" value="' . dol_escape_htmltag($cleanPhone) . '">';
            print '<input type="hidden" id="wa-socid" value="' . ($objecttype == 'societe' ? $object->id : ($object->socid ?? 0)) . '">';
            print '<input type="hidden" id="wa-contactid" value="' . ($objecttype == 'contact' ? $object->id : 0) . '">';
            print '<input type="file" id="wa-file-input" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt">';
            
            // Action buttons
            print '<div class="wa-input-actions">';
                print '<button type="button" id="wa-attach-btn" class="wa-action-btn" title="Adjuntar archivo"><i class="fas fa-paperclip"></i></button>';
            print '</div>';
            
            // Message input
            print '<div class="wa-input-wrapper">';
                print '<textarea id="wa-message" placeholder="' . $langs->trans("TypeYourMessage") . '..." rows="1"></textarea>';
            print '</div>';
            
            // Send button
            print '<button id="wa-send-btn" title="Enviar mensaje">';
                print '<i class="fas fa-paper-plane"></i>';
            print '</button>';
        print '</div>';
    print '</div>';
    
print '</div>';

// Image modal for viewing full-size images
print '<div id="wa-image-modal" class="wa-image-modal">';
    print '<span class="wa-modal-close">&times;</span>';
    print '<img src="" alt="Imagen">';
print '</div>';

// Footer scripts parameters
print '<script type="text/javascript">
    var waContactPhone = "' . dol_escape_js($cleanPhone) . '";
    var waObjectId = "' . $id . '";
    var waObjectType = "' . $objecttype . '";
    var dolibarrToken = "' . (function_exists('newToken') ? newToken() : '') . '";
</script>';

print dol_get_fiche_end();

llxFooter();
$db->close();
