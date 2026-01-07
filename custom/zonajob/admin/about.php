<?php
/* Copyright (C) 2025 ZonaJob Dev
 *
 * About page for ZonaJob module
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

// Load language files
$langs->loadLangs(array("admin", "zonajob@zonajob"));

// Security check
if (!$user->admin) {
    accessforbidden();
}

/*
 * View
 */

$page_name = "ZonaJobAbout";
llxHeader('', $langs->trans($page_name), '');

// Build tabs
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Setup tabs
$head = array();
$head[0][0] = dol_buildpath('/zonajob/admin/setup.php', 1);
$head[0][1] = $langs->trans("Settings");
$head[0][2] = 'settings';

$head[1][0] = dol_buildpath('/zonajob/admin/about.php', 1);
$head[1][1] = $langs->trans("About");
$head[1][2] = 'about';

print dol_get_fiche_head($head, 'about', $langs->trans("ZonaJobSetup"), -1, 'zonajob@zonajob');

print '<div class="div-table-responsive-no-min">';

// Module info
print '<div class="fichecenter">';
print '<div class="fichehalfleft">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans("ModuleInfo").'</th></tr>';

print '<tr class="oddeven">';
print '<td class="titlefield">'.$langs->trans("Name").'</td>';
print '<td>ZonaJob - Pedidos</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("Version").'</td>';
print '<td>1.0.0</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("ModuleId").'</td>';
print '<td>6000020</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("Description").'</td>';
print '<td>'.$langs->trans("ModuleZonaJobDesc").'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("Author").'</td>';
print '<td>ZonaJob Development Team</td>';
print '</tr>';

print '</table>';
print '</div>';

print '<div class="fichehalfright">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans("Features").'</th></tr>';

print '<tr class="oddeven">';
print '<td class="titlefield">'.$langs->trans("ResponsiveDesign").'</td>';
print '<td><span class="badge badge-status4">✓</span></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("OrderViewing").'</td>';
print '<td><span class="badge badge-status4">✓</span></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("PhotoUpload").'</td>';
print '<td><span class="badge badge-status4">✓</span></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("ClientSignature").'</td>';
print '<td><span class="badge badge-status4">✓</span></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("WhatsAppIntegration").'</td>';
print '<td><span class="badge badge-status4">✓</span></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("EmailSending").'</td>';
print '<td><span class="badge badge-status4">✓</span></td>';
print '</tr>';

print '</table>';
print '</div>';

print '</div>';

// Requirements
print '<br>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans("Requirements").'</th></tr>';

print '<tr class="oddeven">';
print '<td class="titlefield">Dolibarr</td>';
print '<td>>= 18.0</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>PHP</td>';
print '<td>>= 8.0</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("RequiredModules").'</td>';
print '<td>Commande, ZonaEmpleado</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("OptionalModules").'</td>';
print '<td>WhatsApp (para envío WA)</td>';
print '</tr>';

print '</table>';

// Changelog
print '<br>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans("Changelog").'</th></tr>';

print '<tr class="oddeven">';
print '<td class="titlefield">v1.0.0</td>';
print '<td>';
print '<ul>';
print '<li>Versión inicial</li>';
print '<li>Vista responsive de pedidos</li>';
print '<li>Subida de fotos con geolocalización</li>';
print '<li>Firma de cliente con canvas</li>';
print '<li>Envío por WhatsApp y Email</li>';
print '<li>Creación rápida de contactos</li>';
print '<li>Cambio de estado tras firma</li>';
print '</ul>';
print '</td>';
print '</tr>';

print '</table>';

print '</div>';

print dol_get_fiche_end();

// Footer
llxFooter();
$db->close();
