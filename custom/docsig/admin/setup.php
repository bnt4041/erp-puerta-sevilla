<?php
/* Copyright (C) 2026 Document Signature Module
 * Admin configuration page
 */

$res = 0;
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) $res = include __DIR__.'/../../../main.inc.php';
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) $res = include __DIR__.'/../../main.inc.php';
if (!$res) die("Main include failed");

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once __DIR__.'/../lib/docsig.lib.php';

// Translations
$langs->loadLangs(array("admin", "docsig@docsig"));

// Access control
if (!$user->admin) accessforbidden();

// Parameters
$action = GETPOST('action', 'aZ09');
$value = GETPOST('value', 'alpha');

/*
 * Actions
 */

if ($action == 'set') {
	$constname = GETPOST('const', 'alpha');
	$constvalue = GETPOST('value', 'alpha');
	
	dolibarr_set_const($db, $constname, $constvalue, 'chaine', 0, '', $conf->entity);
	
	setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	header("Location: ".$_SERVER["PHP_SELF"]);
	exit;
}

/*
 * View
 */

$page_name = "DocsigSetup";
llxHeader('', $langs->trans($page_name));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

$head = docsig_admin_prepare_head();
print dol_get_fiche_head($head, 'settings', $langs->trans("Module500000Name"), -1, 'docsig@docsig');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="set">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '</tr>';

// Signature mode
print '<tr class="oddeven">';
print '<td>'.$langs->trans("DefaultSignatureMode").'</td>';
print '<td>';
print '<select name="value" class="flat">';
print '<option value="parallel"'.($conf->global->DOCSIG_SIGNATURE_MODE == 'parallel' ? ' selected' : '').'>Parallel</option>';
print '<option value="ordered"'.($conf->global->DOCSIG_SIGNATURE_MODE == 'ordered' ? ' selected' : '').'>Ordered</option>';
print '</select>';
print '<input type="hidden" name="const" value="DOCSIG_SIGNATURE_MODE">';
print ' <input type="submit" class="button button-edit" value="'.$langs->trans("Modify").'">';
print '</td>';
print '</tr>';

// Expiration days
print '<tr class="oddeven">';
print '<td>'.$langs->trans("DefaultExpirationDays").'</td>';
print '<td>';
print '<input type="number" name="value" value="'.$conf->global->DOCSIG_EXPIRATION_DAYS.'" min="1" max="365">';
print '<input type="hidden" name="const" value="DOCSIG_EXPIRATION_DAYS">';
print ' <input type="submit" class="button button-edit" value="'.$langs->trans("Modify").'">';
print '</td>';
print '</tr>';

// OTP expiry minutes
print '<tr class="oddeven">';
print '<td>'.$langs->trans("OTPExpiryMinutes").'</td>';
print '<td>';
print '<input type="number" name="value" value="'.$conf->global->DOCSIG_OTP_EXPIRY_MINUTES.'" min="5" max="60">';
print '<input type="hidden" name="const" value="DOCSIG_OTP_EXPIRY_MINUTES">';
print ' <input type="submit" class="button button-edit" value="'.$langs->trans("Modify").'">';
print '</td>';
print '</tr>';

// OTP max attempts
print '<tr class="oddeven">';
print '<td>'.$langs->trans("OTPMaxAttempts").'</td>';
print '<td>';
print '<input type="number" name="value" value="'.$conf->global->DOCSIG_OTP_MAX_ATTEMPTS.'" min="3" max="10">';
print '<input type="hidden" name="const" value="DOCSIG_OTP_MAX_ATTEMPTS">';
print ' <input type="submit" class="button button-edit" value="'.$langs->trans("Modify").'">';
print '</td>';
print '</tr>';

print '</table>';
print '</form>';

print dol_get_fiche_end();

// TSA Settings
print '<br>';
print load_fiche_titre($langs->trans("TSASettings"), '', '');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="set">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '</tr>';

// Enable TSA
print '<tr class="oddeven">';
print '<td>'.$langs->trans("EnableTSA").'</td>';
print '<td>';
if (!empty($conf->global->DOCSIG_ENABLE_TSA)) {
	print '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?action=set&token='.newToken().'&const=DOCSIG_ENABLE_TSA&value=0">';
	print img_picto($langs->trans("Enabled"), 'switch_on');
	print '</a>';
} else {
	print '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?action=set&token='.newToken().'&const=DOCSIG_ENABLE_TSA&value=1">';
	print img_picto($langs->trans("Disabled"), 'switch_off');
	print '</a>';
}
print '</td>';
print '</tr>';

// TSA URL
print '<tr class="oddeven">';
print '<td>'.$langs->trans("TSA_URL").'<br><small>Example: http://timestamp.digicert.com</small></td>';
print '<td>';
print '<input type="text" name="value" value="'.$conf->global->DOCSIG_TSA_URL.'" size="60">';
print '<input type="hidden" name="const" value="DOCSIG_TSA_URL">';
print ' <input type="submit" class="button button-edit" value="'.$langs->trans("Modify").'">';
print '</td>';
print '</tr>';

// TSA User
print '<tr class="oddeven">';
print '<td>'.$langs->trans("TSA_User").'<br><small>(if required)</small></td>';
print '<td>';
print '<input type="text" name="value" value="'.$conf->global->DOCSIG_TSA_USER.'" size="40">';
print '<input type="hidden" name="const" value="DOCSIG_TSA_USER">';
print ' <input type="submit" class="button button-edit" value="'.$langs->trans("Modify").'">';
print '</td>';
print '</tr>';

// TSA Password
print '<tr class="oddeven">';
print '<td>'.$langs->trans("TSA_Password").'<br><small>(if required)</small></td>';
print '<td>';
print '<input type="password" name="value" value="" size="40" placeholder="Leave empty to keep current">';
print '<input type="hidden" name="const" value="DOCSIG_TSA_PASSWORD">';
print ' <input type="submit" class="button button-edit" value="'.$langs->trans("Modify").'">';
print '</td>';
print '</tr>';

// TSA Policy
print '<tr class="oddeven">';
print '<td>'.$langs->trans("TSA_Policy_OID").'<br><small>(optional)</small></td>';
print '<td>';
print '<input type="text" name="value" value="'.$conf->global->DOCSIG_TSA_POLICY.'" size="40">';
print '<input type="hidden" name="const" value="DOCSIG_TSA_POLICY">';
print ' <input type="submit" class="button button-edit" value="'.$langs->trans("Modify").'">';
print '</td>';
print '</tr>';

print '</table>';
print '</form>';

// Signature Display Settings
print '<br>';
print load_fiche_titre($langs->trans("SignatureDisplay"), '', '');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="set">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '</tr>';

// Visible signature
print '<tr class="oddeven">';
print '<td>'.$langs->trans("EnableVisibleSignature").'</td>';
print '<td>';
if (!empty($conf->global->DOCSIG_VISIBLE_SIGNATURE)) {
	print '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?action=set&token='.newToken().'&const=DOCSIG_VISIBLE_SIGNATURE&value=0">';
	print img_picto($langs->trans("Enabled"), 'switch_on');
	print '</a>';
} else {
	print '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?action=set&token='.newToken().'&const=DOCSIG_VISIBLE_SIGNATURE&value=1">';
	print img_picto($langs->trans("Disabled"), 'switch_off');
	print '</a>';
}
print '</td>';
print '</tr>';

// Signature position
print '<tr class="oddeven">';
print '<td>'.$langs->trans("DefaultSignaturePosition").'</td>';
print '<td>';
print '<select name="value" class="flat">';
$positions = array('bottom-left', 'bottom-right', 'top-left', 'top-right', 'center');
foreach ($positions as $pos) {
	print '<option value="'.$pos.'"'.($conf->global->DOCSIG_SIGNATURE_POSITION == $pos ? ' selected' : '').'>'.$pos.'</option>';
}
print '</select>';
print '<input type="hidden" name="const" value="DOCSIG_SIGNATURE_POSITION">';
print ' <input type="submit" class="button button-edit" value="'.$langs->trans("Modify").'">';
print '</td>';
print '</tr>';

print '</table>';
print '</form>';

// System Certificate Info
print '<br>';
print load_fiche_titre($langs->trans("SystemCertificate"), '', '');

$sql = "SELECT * FROM ".MAIN_DB_PREFIX."docsig_key";
$sql .= " WHERE key_type = 'signing' AND is_active = 1";
$sql .= " AND entity = ".$conf->entity;
$sql .= " ORDER BY rowid DESC LIMIT 1";

$resql = $db->query($sql);
if ($resql && $db->num_rows($resql) > 0) {
	$obj = $db->fetch_object($resql);
	
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>Property</td><td>Value</td>';
	print '</tr>';
	
	print '<tr><td>Algorithm</td><td>'.$obj->key_algorithm.'</td></tr>';
	print '<tr><td>Serial</td><td>'.$obj->certificate_serial.'</td></tr>';
	print '<tr><td>Subject</td><td>'.$obj->certificate_subject.'</td></tr>';
	print '<tr><td>Valid From</td><td>'.dol_print_date($db->jdate($obj->certificate_valid_from), 'day').'</td></tr>';
	print '<tr><td>Valid To</td><td>'.dol_print_date($db->jdate($obj->certificate_valid_to), 'day').'</td></tr>';
	print '<tr><td>Usage Count</td><td>'.$obj->usage_count.'</td></tr>';
	
	print '</table>';
} else {
	print '<div class="warning">No system certificate found. Please reinstall the module.</div>';
}

llxFooter();
$db->close();
