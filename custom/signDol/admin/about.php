<?php
/* Copyright (C) 2026 DocSig Module
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    htdocs/custom/signDol/admin/about.php
 * \ingroup docsig
 * \brief   Página "Acerca de" del módulo DocSig
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--;
    $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
    $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
    $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
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
require_once '../lib/docsig.lib.php';

// Translations
$langs->loadLangs(array("admin", "docsig@signDol"));

// Access control
if (!$user->admin) {
    accessforbidden();
}

/*
 * View
 */

$page_name = "DocSigAbout";
llxHeader('', $langs->trans($page_name), '', '', 0, 0, '', '', '', 'mod-docsig page-admin-about');

// Subheader
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Tabs
$head = docsigAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $langs->trans("ModuleDocSigName"), -1, 'fa-file-signature');

print '<div class="fichecenter">';

print '<div class="underbanner clearboth"></div>';

print '<table class="border centpercent tableforfield">';

print '<tr class="liste_titre">';
print '<td colspan="2">'.$langs->trans("ModuleInformation").'</td>';
print '</tr>';

print '<tr><td class="titlefield">'.$langs->trans("Name").'</td><td>DocSig - Document Signature Dolibarr</td></tr>';
print '<tr><td>'.$langs->trans("Version").'</td><td>1.0.0</td></tr>';
print '<tr><td>'.$langs->trans("ModuleId").'</td><td>60000010</td></tr>';
print '<tr><td>'.$langs->trans("Author").'</td><td>DocSig Module</td></tr>';

print '</table>';

print '<br>';

print '<table class="border centpercent tableforfield">';

print '<tr class="liste_titre">';
print '<td colspan="2">'.$langs->trans("Description").'</td>';
print '</tr>';

print '<tr><td colspan="2">';
print '<p>'.$langs->trans("DocSigAboutDescription").'</p>';
print '<ul>';
print '<li>'.$langs->trans("DocSigFeature1").'</li>';
print '<li>'.$langs->trans("DocSigFeature2").'</li>';
print '<li>'.$langs->trans("DocSigFeature3").'</li>';
print '<li>'.$langs->trans("DocSigFeature4").'</li>';
print '<li>'.$langs->trans("DocSigFeature5").'</li>';
print '</ul>';
print '</td></tr>';

print '</table>';

print '<br>';

print '<table class="border centpercent tableforfield">';

print '<tr class="liste_titre">';
print '<td colspan="2">'.$langs->trans("Requirements").'</td>';
print '</tr>';

// PHP Version
$phpVersion = phpversion();
$phpOk = version_compare($phpVersion, '8.1.0', '>=');
print '<tr><td class="titlefield">PHP Version</td><td>'.$phpVersion.' ';
print $phpOk ? '<span class="badge badge-status4">OK</span>' : '<span class="badge badge-status8">Requiere 8.1+</span>';
print '</td></tr>';

// OpenSSL
$opensslLoaded = extension_loaded('openssl');
print '<tr><td>OpenSSL Extension</td><td>';
print $opensslLoaded ? '<span class="badge badge-status4">'.$langs->trans("Enabled").'</span>' : '<span class="badge badge-status8">'.$langs->trans("Disabled").'</span>';
print '</td></tr>';

// GD (para procesamiento de imágenes de firma)
$gdLoaded = extension_loaded('gd');
print '<tr><td>GD Extension</td><td>';
print $gdLoaded ? '<span class="badge badge-status4">'.$langs->trans("Enabled").'</span>' : '<span class="badge badge-status1">'.$langs->trans("Optional").'</span>';
print '</td></tr>';

// cURL (para TSA)
$curlLoaded = extension_loaded('curl');
print '<tr><td>cURL Extension</td><td>';
print $curlLoaded ? '<span class="badge badge-status4">'.$langs->trans("Enabled").'</span>' : '<span class="badge badge-status8">'.$langs->trans("Required").'</span>';
print '</td></tr>';

print '</table>';

print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
