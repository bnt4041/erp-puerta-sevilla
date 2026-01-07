<?php
/* Copyright (C) 2025 ZonaJob Dev
 *
 * Admin setup page for ZonaJob module
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formsetup.class.php';

// Load language files
$langs->loadLangs(array("admin", "zonajob@zonajob"));

// Security check
if (!$user->admin) {
    accessforbidden();
}

$action = GETPOST('action', 'aZ09');

// Module constants to configure
$arrayofparameters = array(
    'ZONAJOB_SHOW_DRAFT_ORDERS' => array(
        'type' => 'yesno',
        'label' => $langs->trans('ShowDraftOrders'),
        'default' => '1',
        'enabled' => 1,
    ),
    'ZONAJOB_SHOW_VALIDATED_ORDERS' => array(
        'type' => 'yesno',
        'label' => $langs->trans('ShowValidatedOrders'),
        'default' => '1',
        'enabled' => 1,
    ),
    'ZONAJOB_SHOW_ALL_STATUSES' => array(
        'type' => 'yesno',
        'label' => $langs->trans('ShowAllOrders'),
        'default' => '0',
        'enabled' => 1,
    ),
    'ZONAJOB_REQUIRE_SIGNATURE' => array(
        'type' => 'yesno',
        'label' => $langs->trans('RequireSignature'),
        'default' => '0',
        'enabled' => 1,
    ),
    'ZONAJOB_REQUIRE_PHOTO' => array(
        'type' => 'yesno',
        'label' => $langs->trans('RequirePhoto'),
        'default' => '0',
        'enabled' => 1,
    ),
    'ZONAJOB_AUTO_SEND_ON_SIGN' => array(
        'type' => 'yesno',
        'label' => $langs->trans('AutoSendOnSign'),
        'default' => '0',
        'enabled' => 1,
    ),
    'ZONAJOB_WHATSAPP_ENABLED' => array(
        'type' => 'yesno',
        'label' => $langs->trans('WhatsAppEnabled'),
        'default' => '1',
        'enabled' => 1,
    ),
    'ZONAJOB_EMAIL_ENABLED' => array(
        'type' => 'yesno',
        'label' => $langs->trans('EmailEnabled'),
        'default' => '1',
        'enabled' => 1,
    ),
    'ZONAJOB_ALLOW_STATUS_CHANGE' => array(
        'type' => 'yesno',
        'label' => $langs->trans('AllowStatusChange'),
        'default' => '1',
        'enabled' => 1,
    ),
    'ZONAJOB_MAX_PHOTOS' => array(
        'type' => 'string',
        'label' => $langs->trans('MaxPhotosPerOrder'),
        'default' => '10',
        'enabled' => 1,
    ),
    'ZONAJOB_MAX_PHOTO_SIZE' => array(
        'type' => 'string',
        'label' => $langs->trans('MaxPhotoSize'),
        'default' => '10',
        'enabled' => 1,
    ),
);

/*
 * Actions
 */

if ($action == 'update') {
    $error = 0;
    
    foreach ($arrayofparameters as $key => $val) {
        $value = GETPOST($key, 'alpha');
        $result = dolibarr_set_const($db, $key, $value, 'chaine', 0, '', $conf->entity);
        if ($result < 0) {
            $error++;
            setEventMessages($langs->trans("Error"), null, 'errors');
            break;
        }
    }
    
    if (!$error) {
        setEventMessages($langs->trans("SettingsSaved"), null, 'mesgs');
    }
}

/*
 * View
 */

$page_name = "ZonaJobSetup";
llxHeader('', $langs->trans($page_name), '');

// Build tabs
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Setup tabs
$head = array();
$head[0][0] = dol_buildpath('/zonajob/admin/setup.php', 1);
$head[0][1] = $langs->trans("Settings");
$head[0][2] = 'settings';

print dol_get_fiche_head($head, 'settings', $langs->trans("ZonaJobSetup"), -1, 'zonajob@zonajob');

// Form
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';

print '<tr class="liste_titre">';
print '<th>'.$langs->trans("Parameter").'</th>';
print '<th class="center">'.$langs->trans("Value").'</th>';
print '</tr>';

// Visibility options
print '<tr class="oddeven">';
print '<td colspan="2" class="titlefieldmiddle"><strong>'.$langs->trans("OrdersVisibility").'</strong></td>';
print '</tr>';

foreach ($arrayofparameters as $key => $val) {
    if (!in_array($key, array('ZONAJOB_SHOW_DRAFT_ORDERS', 'ZONAJOB_SHOW_VALIDATED_ORDERS', 'ZONAJOB_SHOW_ALL_STATUSES'))) {
        continue;
    }
    
    $value = getDolGlobalString($key, $val['default']);
    
    print '<tr class="oddeven">';
    print '<td class="titlefieldmiddle">'.$val['label'].'</td>';
    print '<td class="center">';
    
    if ($val['type'] == 'yesno') {
        print $form->selectyesno($key, $value, 1);
    } else {
        print '<input type="text" name="'.$key.'" value="'.$value.'" class="maxwidth200">';
    }
    
    print '</td>';
    print '</tr>';
}

// Signature and photo options
print '<tr class="oddeven">';
print '<td colspan="2" class="titlefieldmiddle"><strong>'.$langs->trans("SignatureAndPhotos").'</strong></td>';
print '</tr>';

foreach ($arrayofparameters as $key => $val) {
    if (!in_array($key, array('ZONAJOB_REQUIRE_SIGNATURE', 'ZONAJOB_REQUIRE_PHOTO', 'ZONAJOB_MAX_PHOTOS', 'ZONAJOB_MAX_PHOTO_SIZE'))) {
        continue;
    }
    
    $value = getDolGlobalString($key, $val['default']);
    
    print '<tr class="oddeven">';
    print '<td class="titlefieldmiddle">'.$val['label'].'</td>';
    print '<td class="center">';
    
    if ($val['type'] == 'yesno') {
        print $form->selectyesno($key, $value, 1);
    } else {
        print '<input type="text" name="'.$key.'" value="'.$value.'" class="maxwidth200">';
    }
    
    print '</td>';
    print '</tr>';
}

// Sending options
print '<tr class="oddeven">';
print '<td colspan="2" class="titlefieldmiddle"><strong>'.$langs->trans("SendingOptions").'</strong></td>';
print '</tr>';

foreach ($arrayofparameters as $key => $val) {
    if (!in_array($key, array('ZONAJOB_AUTO_SEND_ON_SIGN', 'ZONAJOB_WHATSAPP_ENABLED', 'ZONAJOB_EMAIL_ENABLED', 'ZONAJOB_ALLOW_STATUS_CHANGE'))) {
        continue;
    }
    
    $value = getDolGlobalString($key, $val['default']);
    
    print '<tr class="oddeven">';
    print '<td class="titlefieldmiddle">'.$val['label'].'</td>';
    print '<td class="center">';
    
    if ($val['type'] == 'yesno') {
        print $form->selectyesno($key, $value, 1);
    } else {
        print '<input type="text" name="'.$key.'" value="'.$value.'" class="maxwidth200">';
    }
    
    print '</td>';
    print '</tr>';
}

print '</table>';
print '</div>';

print '<div class="center">';
print '<input type="submit" class="button button-save" value="'.$langs->trans("Save").'">';
print '</div>';

print '</form>';

print dol_get_fiche_end();

// Information box
print '<br>';
print '<div class="info-box">';
print '<h3>'.$langs->trans("Information").'</h3>';
print '<p>'.$langs->trans("ModuleZonaJobDesc").'</p>';
print '<ul>';
print '<li><strong>'.$langs->trans("ShowDraftOrders").':</strong> '.$langs->trans("ShowDraftOrdersHelp").'</li>';
print '<li><strong>'.$langs->trans("ShowValidatedOrders").':</strong> '.$langs->trans("ShowValidatedOrdersHelp").'</li>';
print '<li><strong>'.$langs->trans("RequireSignature").':</strong> '.$langs->trans("RequireSignatureHelp").'</li>';
print '<li><strong>'.$langs->trans("WhatsAppEnabled").':</strong> '.$langs->trans("WhatsAppEnabledHelp").'</li>';
print '</ul>';
print '</div>';

// Footer
llxFooter();
$db->close();
