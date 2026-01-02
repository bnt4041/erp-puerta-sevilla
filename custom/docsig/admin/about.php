<?php
/* Copyright (C) 2026 Document Signature Module
 * About page
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) $res = include '../../main.inc.php';
if (!$res) die("Main include failed");

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once __DIR__.'/../lib/docsig.lib.php';

$langs->loadLangs(array("admin", "docsig@docsig"));

if (!$user->admin) accessforbidden();

$page_name = "DocsigAbout";
llxHeader('', $langs->trans($page_name));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

$head = docsig_admin_prepare_head();
print dol_get_fiche_head($head, 'about', $langs->trans("Module500000Name"), -1, 'docsig@docsig');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';

print '<table class="border centpercent tableforfield">';

print '<tr><td class="titlefield">'.$langs->trans("Version").'</td><td>1.0.0</td></tr>';
print '<tr><td>'.$langs->trans("Author").'</td><td>Docsig Team</td></tr>';
print '<tr><td>'.$langs->trans("License").'</td><td>GPL v3+</td></tr>';
print '<tr><td>'.$langs->trans("Description").'</td><td>Complete document signature solution with PAdES, TSA RFC3161, double authentication, and compliance certificates</td></tr>';

print '</table>';

print '<br><h3>Features</h3>';
print '<ul>';
print '<li>Multi-signer envelopes (parallel/ordered)</li>';
print '<li>Double authentication (DNI + Email OTP)</li>';
print '<li>Handwritten signature capture</li>';
print '<li>PAdES-compliant PDF signing</li>';
print '<li>TSA RFC3161 timestamp support</li>';
print '<li>Immutable audit trail</li>';
print '<li>Compliance certificate generation</li>';
print '<li>Notification tracking per contact</li>';
print '<li>Rate limiting and security</li>';
print '</ul>';

print '<br><h3>Technical Stack</h3>';
print '<ul>';
print '<li>PHP 8.1+</li>';
print '<li>MariaDB/MySQL</li>';
print '<li>TCPDF for PDF manipulation</li>';
print '<li>OpenSSL for cryptography</li>';
print '<li>Signature Pad library for canvas</li>';
print '</ul>';

print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
