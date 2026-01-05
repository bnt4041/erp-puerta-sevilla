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
 * \file    htdocs/custom/signDol/index.php
 * \ingroup docsig
 * \brief   Página principal del módulo DocSig
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
if (!$res && file_exists("../main.inc.php")) {
    $res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

global $langs, $user, $conf, $db;

// Libraries
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once './lib/docsig.lib.php';

// Translations
$langs->loadLangs(array("docsig@signDol"));

// Access control
if (!$user->hasRight('docsig', 'envelope', 'read')) {
    accessforbidden();
}

/*
 * View
 */

$title = $langs->trans('DocSig');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-docsig page-index');

print load_fiche_titre($title, '', 'fa-file-signature');

print '<div class="fichecenter">';

// Dashboard / Statistics
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';

// Get statistics
$sql = "SELECT status, COUNT(*) as total FROM ".MAIN_DB_PREFIX."docsig_envelope";
$sql .= " WHERE entity = ".(int)$conf->entity;
$sql .= " GROUP BY status";

$stats = array(
    0 => 0, // Draft
    1 => 0, // Sent
    2 => 0, // Partial
    3 => 0, // Completed
    4 => 0, // Canceled
    5 => 0, // Expired
);

$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $stats[$obj->status] = $obj->total;
    }
}

$statuses = docsig_get_envelope_statuses();

print '<tr class="liste_titre">';
print '<td colspan="2">'.$langs->trans('Statistics').'</td>';
print '</tr>';

$total = array_sum($stats);
print '<tr><td class="titlefield">'.$langs->trans('Total').'</td><td><strong>'.$total.'</strong></td></tr>';

foreach ($statuses as $code => $info) {
    print '<tr class="oddeven">';
    print '<td><span class="badge badge-'.$info['color'].'">'.$info['label'].'</span></td>';
    print '<td>'.$stats[$code].'</td>';
    print '</tr>';
}

print '</table>';
print '</div>';

print '<br>';

// Quick actions
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('QuickActions').'</td>';
print '</tr>';
print '<tr class="oddeven">';
print '<td>';
print '<a class="button" href="'.dol_buildpath('/signDol/list.php', 1).'">';
print '<span class="fa fa-list"></span> '.$langs->trans('DocSigList');
print '</a> ';

if ($user->hasRight('docsig', 'envelope', 'write')) {
    print '<a class="button" href="'.dol_buildpath('/signDol/card.php?action=create', 1).'">';
    print '<span class="fa fa-plus"></span> '.$langs->trans('NewEnvelope');
    print '</a> ';
}

if ($user->admin) {
    print '<a class="button" href="'.dol_buildpath('/signDol/admin/setup.php', 1).'">';
    print '<span class="fa fa-cog"></span> '.$langs->trans('DocSigSetup');
    print '</a>';
}
print '</td>';
print '</tr>';
print '</table>';
print '</div>';

print '<br>';

// Recent envelopes
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td colspan="5">'.$langs->trans('RecentSignatureRequests').'</td>';
print '</tr>';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Ref').'</td>';
print '<td>'.$langs->trans('Document').'</td>';
print '<td>'.$langs->trans('Status').'</td>';
print '<td>'.$langs->trans('DateCreation').'</td>';
print '<td></td>';
print '</tr>';

$sql = "SELECT e.rowid, e.ref, e.file_path, e.status, e.date_creation, e.element, e.fk_object";
$sql .= " FROM ".MAIN_DB_PREFIX."docsig_envelope as e";
$sql .= " WHERE e.entity = ".(int)$conf->entity;
$sql .= " ORDER BY e.date_creation DESC";
$sql .= " LIMIT 10";

$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);
    if ($num > 0) {
        while ($obj = $db->fetch_object($resql)) {
            print '<tr class="oddeven">';
            print '<td><a href="'.dol_buildpath('/signDol/card.php?id='.$obj->rowid, 1).'">'.$obj->ref.'</a></td>';
            print '<td>'.basename($obj->file_path).'</td>';
            print '<td>'.docsig_get_status_badge($obj->status, 'envelope').'</td>';
            print '<td>'.dol_print_date($db->jdate($obj->date_creation), 'dayhour').'</td>';
            print '<td class="right">';
            print '<a href="'.dol_buildpath('/signDol/card.php?id='.$obj->rowid, 1).'" class="button">';
            print '<span class="fa fa-eye"></span>';
            print '</a>';
            print '</td>';
            print '</tr>';
        }
    } else {
        print '<tr class="oddeven"><td colspan="5" class="opacitymedium">'.$langs->trans('NoRecordFound').'</td></tr>';
    }
    $db->free($resql);
} else {
    dol_print_error($db);
}

print '</table>';
print '</div>';

print '</div>'; // fichecenter

llxFooter();
$db->close();
