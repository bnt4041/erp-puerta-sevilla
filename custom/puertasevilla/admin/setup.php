<?php
/* Copyright (C) 2024 PuertaSevilla
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       puertasevilla/admin/setup.php
 * \ingroup    puertasevilla
 * \brief      PuertaSevilla setup page.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
    $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
    $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

global $langs, $user;

// Libraries
require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once '../lib/puertasevilla.lib.php';

// Translations
$langs->loadLangs(array("admin", "puertasevilla@puertasevilla"));

// Access control
if (!$user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

$arrayofparameters = array(
    'PUERTASEVILLA_ENABLE_AUTO_INVOICE' => array('type' => 'yesno', 'enabled' => 1),
    'PUERTASEVILLA_INVOICE_TEMPLATE_ID' => array('type' => 'string', 'enabled' => 1),
);

/*
 * Actions
 */

include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

/*
 * View
 */

$page_name = "PuertaSevillaSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration header
$head = puertasevillaAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans("ModulePuertaSevillaName"), -1, 'puertasevilla@puertasevilla');

// Setup page goes here
print info_admin($langs->trans("PuertaSevillaSetupPage"));

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print "</tr>\n";

foreach ($arrayofparameters as $key => $val) {
    print '<tr class="oddeven"><td>';
    $tooltiphelp = (($tooltiphelp = $langs->trans($key.'Tooltip')) != $key.'Tooltip') ? $tooltiphelp : '';
    print $form->textwithpicto($langs->trans($key), $tooltiphelp);
    print '</td><td>';

    if ($val['type'] == 'yesno') {
        print ajax_constantonoff($key);
    } elseif ($val['type'] == 'string') {
        print '<input name="'.$key.'" class="flat '.(empty($val['css']) ? 'minwidth200' : $val['css']).'" value="'.$conf->global->$key.'">';
    }

    print '</td></tr>';
}

print '</table>';

print $form->buttonsSaveCancel("Modify", '');

print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();
