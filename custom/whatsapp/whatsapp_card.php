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

$langs->loadLangs(array("whatsapp", "other"));

$id = GETPOST('id', 'int');
$objecttype = GETPOST('objecttype', 'alpha');
$action = GETPOST('action', 'alpha');
$phone = GETPOST('phone', 'alpha');
$message = GETPOST('message', 'restricthtml');

if (empty($id) || empty($objecttype)) {
	accessforbidden('Missing ID or Object Type');
}

// Fetch object
require_once DOL_DOCUMENT_ROOT.'/'.$objecttype.'/class/'.($objecttype == 'propal' ? 'propal' : $objecttype).'.class.php';
$classname = ($objecttype == 'propal' ? 'Propal' : ucfirst($objecttype));
if ($objecttype == 'societe') $classname = 'Societe';
if ($objecttype == 'contact') $classname = 'Contact';

$object = new $classname($db);
$object->fetch($id);

/*
 * Actions
 */

if ($action == 'send') {
	$error = 0;
	$waClient = new GoWAClient($db);
	$result = $waClient->sendMessage($phone, $message);

	if ($result['error'] == 0) {
		setEventMessages($langs->trans("WhatsAppSent"), null, 'mesgs');
		
		// Add entry to agenda (ActionComm) - Requirement #4
		require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
		$actioncomm = new ActionComm($db);
		$actioncomm->type_code = 'AC_WA';
		$actioncomm->code = 'AC_WA';
		$actioncomm->label = $langs->trans("WhatsAppAction") . ": " . $object->ref;
		$actioncomm->note_private = $langs->trans("WhatsAppMessage") . ":\n" . $message;
		$actioncomm->datep = dol_now();
		$actioncomm->datef = dol_now();
		$actioncomm->socid = (isset($object->socid) ? $object->socid : ($object->element == 'societe' ? $object->id : 0));
		$actioncomm->element_id = $object->id;
		$actioncomm->element_type = $object->element;
		$actioncomm->userownerid = $user->id;
		
		$res = $actioncomm->create($user);
		
		// Trigger for other modules - Requirement #2
		$result_trigger = $object->call_trigger('WHATSAPP_SENT', $user);

		header("Location: " . DOL_URL_ROOT . "/" . $objecttype . "/card.php?id=" . $id);
		exit;
	} else {
		setEventMessages($langs->trans("ErrorSendingWhatsApp") . ": " . $result['message'], null, 'errors');
	}
}

/*
 * View
 */

llxHeader('', $langs->trans("SendWhatsApp"));

$linkback = '<a href="' . DOL_URL_ROOT . '/' . $objecttype . '/card.php?id=' . $id . '">' . $langs->trans("Back") . '</a>';
print load_fiche_titre($langs->trans("SendWhatsApp"), $linkback, 'title_whatsapp');

print '<form method="post" action="' . $_SERVER["PHP_SELF"] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="send">';
print '<input type="hidden" name="id" value="' . $id . '">';
print '<input type="hidden" name="objecttype" value="' . $objecttype . '">';

print '<table class="noborder centertable" width="100%">';

// To (Phone)
print '<tr class="oddeven">';
print '<td class="titlefieldfield">' . $langs->trans("Recipient") . ' (Phone)</td>';
print '<td><input type="text" name="phone" value="' . dol_escape_htmltag($phone) . '" size="20"></td>';
print '</tr>';

// Message
print '<tr class="oddeven">';
print '<td>' . $langs->trans("WhatsAppMessage") . '</td>';
print '<td><textarea name="message" rows="10" cols="60" class="flat"></textarea></td>';
print '</tr>';

print '</table>';

print '<div class="center">';
print '<input type="submit" class="button" value="' . $langs->trans("Send") . '">';
print ' &nbsp; ';
print '<input type="button" class="button button-cancel" value="' . $langs->trans("Cancel") . '" onclick="history.back();">';
print '</div>';

print '</form>';

llxFooter();
$db->close();
