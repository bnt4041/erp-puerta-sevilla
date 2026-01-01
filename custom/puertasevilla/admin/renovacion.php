<?php
/* Copyright (C) 2024 PuertaSevilla
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    admin/renovacion.php
 * \ingroup puertasevilla
 * \brief   Página de configuración de renovación de contratos
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, $i + 1)."/main.inc.php")) {
	$res = @include substr($tmp, 0, $i + 1)."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, $i + 1))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, $i + 1))."/main.inc.php";
}
// Try main.inc.php in parent directory
if (!$res) {
	$res = @include "../../main.inc.php";
}
if (!$res) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

// Libraries
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';

// Translations
$langs->loadLangs(array("admin", "puertasevilla@puertasevilla"));

// Access control
if (!$user->admin) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$value = GETPOST('value', 'alpha');

/*
 * Actions
 */
if ($action == 'setipc') {
	$ipc_value = GETPOST('PSV_IPC_DEFAULT', 'float');
	
	if ($ipc_value < 0) {
		$ipc_value = 0;
	}
	if ($ipc_value > 100) {
		$ipc_value = 100;
	}
	
	dolibarr_set_const($db, 'PSV_IPC_DEFAULT', $ipc_value, 'float', 0, '', $conf->entity);
	
	setEventMessages("IPC por defecto actualizado a: ".$ipc_value."%", null, 'mesgs');
}

/*
 * View
 */
$form = new Form($db);

llxHeader();

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("PuertaSevilla").' - '.$langs->trans("Renovación de Contratos"), $linkback, 'title_setup');

// Afficher les onglets
$head = array();
$h = 0;

$head[$h][0] = dol_buildpath('/custom/puertasevilla/admin/renovacion.php', 1);
$head[$h][1] = $langs->trans("General");
$head[$h][2] = 'renovacion';
$h++;

print dol_get_fiche_head($head, 'renovacion', $langs->trans("Parameters"), -1, '');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="setipc">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td colspan="3">'.$langs->trans("IPC Configuration").'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td width="60%">'.$langs->trans("Default IPC Value").'</td>';
print '<td>';
$ipc_value = getDolGlobalFloat('PSV_IPC_DEFAULT', 2.4);
print '<input type="number" name="PSV_IPC_DEFAULT" value="'.$ipc_value.'" min="0" max="100" step="0.01" class="flat">';
print ' %';
print '</td>';
print '<td style="text-align: right;">';
print '<button type="submit" class="butAction">'.$langs->trans("Modify").'</button>';
print '</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td colspan="3">';
print '<small>'.$langs->trans("This value is used when renewing contracts if the automatic IPC API fails.").'</small>';
print '</td>';
print '</tr>';

print '</table>';
print '</form>';

// Help section
print '<div class="info" style="margin-top: 20px;">';
print '<h3>Ayuda: Renovación de Contratos</h3>';
print '<ul>';
print '<li><strong>IPC Actual:</strong> Se obtiene automáticamente de APIs públicas (FRED). Si falla, se usa el valor por defecto.</li>';
print '<li><strong>Renovación por IPC:</strong> Aplica un porcentaje de aumento al precio actual de las líneas.</li>';
print '<li><strong>Renovación por Importe:</strong> Fija un nuevo importe unitario para las líneas.</li>';
print '<li><strong>Factura Recurrente:</strong> Se actualiza automáticamente con los nuevos precios y fechas.</li>';
print '<li><strong>Permisos:</strong> Se requieren permisos de edición de contratos para renovar.</li>';
print '</ul>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
