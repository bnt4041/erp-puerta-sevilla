<?php
/* Copyright (C) 2026 DocSig Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/signDol/list.php
 * \ingroup docsig
 * \brief   Lista de solicitudes de firma
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../main.inc.php")) {
    $res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
dol_include_once('/signDol/class/docsigenvelope.class.php');
dol_include_once('/signDol/lib/docsig.lib.php');

// Load translation files
$langs->loadLangs(array('docsig@signDol', 'other'));

// Security check
if (!isModEnabled('docsig')) {
    accessforbidden('Module not enabled');
}
if (!$user->hasRight('docsig', 'envelope', 'read')) {
    accessforbidden();
}

// Get parameters
$action = GETPOST('action', 'aZ09');
$massaction = GETPOST('massaction', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$toselect = GETPOST('toselect', 'array');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'docsiglist';
$backtopage = GETPOST('backtopage', 'alpha');

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');

// Search parameters
$search_ref = GETPOST('search_ref', 'alpha');
$search_element = GETPOST('search_element', 'alpha');
$search_status = GETPOST('search_status', 'intcomma');
$search_date_creation_start = dol_mktime(0, 0, 0, GETPOSTINT('search_date_creation_startmonth'), GETPOSTINT('search_date_creation_startday'), GETPOSTINT('search_date_creation_startyear'));
$search_date_creation_end = dol_mktime(23, 59, 59, GETPOSTINT('search_date_creation_endmonth'), GETPOSTINT('search_date_creation_endday'), GETPOSTINT('search_date_creation_endyear'));

// Pagination
$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
    $page = 0;
}
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

if (!$sortfield) {
    $sortfield = 'e.date_creation';
}
if (!$sortorder) {
    $sortorder = 'DESC';
}

// Initialize objects
$object = new DocSigEnvelope($db);
$form = new Form($db);
$formcompany = new FormCompany($db);

// Build and execute select
$sql = "SELECT e.rowid, e.ref, e.element, e.fk_object, e.status, e.signature_mode,";
$sql .= " e.date_creation, e.expire_date, e.fk_user_creat,";
$sql .= " u.login as user_login, u.firstname as user_firstname, u.lastname as user_lastname,";
$sql .= " (SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."docsig_signer WHERE fk_envelope = e.rowid) as nb_signers,";
$sql .= " (SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."docsig_signer WHERE fk_envelope = e.rowid AND status = 1) as nb_signed";
$sql .= " FROM ".MAIN_DB_PREFIX."docsig_envelope as e";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON e.fk_user_creat = u.rowid";
$sql .= " WHERE e.entity = ".(int)$conf->entity;

// Filters
if ($search_ref) {
    $sql .= natural_search('e.ref', $search_ref);
}
if ($search_element) {
    $sql .= natural_search('e.element', $search_element);
}
if ($search_status !== '' && $search_status >= 0) {
    $sql .= " AND e.status = ".(int)$search_status;
}
if ($search_date_creation_start) {
    $sql .= " AND e.date_creation >= '".$db->idate($search_date_creation_start)."'";
}
if ($search_date_creation_end) {
    $sql .= " AND e.date_creation <= '".$db->idate($search_date_creation_end)."'";
}

// Count total
$sqlcount = preg_replace('/SELECT .* FROM/', 'SELECT COUNT(*) as total FROM', $sql);
$sqlcount = preg_replace('/LEFT JOIN.*WHERE/', 'WHERE', $sqlcount);
$resqlcount = $db->query($sqlcount);
$nbtotalofrecords = 0;
if ($resqlcount) {
    $objcount = $db->fetch_object($resqlcount);
    $nbtotalofrecords = $objcount->total;
}

// Order and limit
$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if (!$resql) {
    dol_print_error($db);
    exit;
}

$num = $db->num_rows($resql);

// Actions
if ($action == 'confirm_delete' && $confirm == 'yes' && $user->hasRight('docsig', 'envelope', 'delete')) {
    $objecttmp = new DocSigEnvelope($db);
    if ($objecttmp->fetch($id) > 0) {
        $result = $objecttmp->delete($user);
        if ($result > 0) {
            setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
        } else {
            setEventMessages($objecttmp->error, $objecttmp->errors, 'errors');
        }
    }
}

/*
 * View
 */

$title = $langs->trans('DocSigEnvelopeList');

llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-docsig page-list');

$arrayofselected = is_array($toselect) ? $toselect : array();

// Page header
$param = '';
if ($search_ref) $param .= '&search_ref='.urlencode($search_ref);
if ($search_element) $param .= '&search_element='.urlencode($search_element);
if ($search_status !== '') $param .= '&search_status='.urlencode($search_status);
if ($limit > 0 && $limit != $conf->liste_limit) $param .= '&limit='.((int)$limit);

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" name="formfilter">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';

print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $nbtotalofrecords, 'fa-file-signature', 0, '', '', $limit, 0, 0, 1);

// Search filters
print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste'.($moreforfilter ? " listwithfilterbefore" : "").'">';

// Header line
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre"><input type="text" class="flat maxwidth100" name="search_ref" value="'.dol_escape_htmltag($search_ref).'"></td>';
print '<td class="liste_titre">';
$elementTypes = array(
    '' => '',
    'facture' => $langs->trans('Invoice'),
    'commande' => $langs->trans('Order'),
    'propal' => $langs->trans('Proposal'),
    'contrat' => $langs->trans('Contract'),
    'fichinter' => $langs->trans('Intervention'),
);
print $form->selectarray('search_element', $elementTypes, $search_element, 0, 0, 0, '', 0, 0, 0, '', 'maxwidth150');
print '</td>';
print '<td class="liste_titre"></td>'; // Object ref
print '<td class="liste_titre center">';
$statuses = array(
    '-1' => '',
    '0' => $langs->trans('Draft'),
    '1' => $langs->trans('Sent'),
    '2' => $langs->trans('PartialSigned'),
    '3' => $langs->trans('Signed'),
    '4' => $langs->trans('Canceled'),
    '5' => $langs->trans('Expired'),
);
print $form->selectarray('search_status', $statuses, $search_status, 0, 0, 0, '', 0, 0, 0, '', 'minwidth75');
print '</td>';
print '<td class="liste_titre center">';
print $form->selectDate($search_date_creation_start, 'search_date_creation_start', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('From'));
print '<br>';
print $form->selectDate($search_date_creation_end, 'search_date_creation_end', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('to'));
print '</td>';
print '<td class="liste_titre"></td>'; // Expire date
print '<td class="liste_titre"></td>'; // Signers
print '<td class="liste_titre"></td>'; // User
print '<td class="liste_titre center">';
print '<input type="image" class="liste_titre" name="button_search" src="'.img_picto($langs->trans("Search"), 'search.png', '', '', 1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
print '<input type="image" class="liste_titre" name="button_removefilter" src="'.img_picto($langs->trans("RemoveFilter"), 'searchclear.png', '', '', 1).'" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
print '</td>';
print '</tr>';

// Column titles
print '<tr class="liste_titre">';
print_liste_field_titre('Ref', $_SERVER["PHP_SELF"], 'e.ref', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Type', $_SERVER["PHP_SELF"], 'e.element', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Object', $_SERVER["PHP_SELF"], '', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Status', $_SERVER["PHP_SELF"], 'e.status', '', $param, 'class="center"', $sortfield, $sortorder);
print_liste_field_titre('DateCreation', $_SERVER["PHP_SELF"], 'e.date_creation', '', $param, 'class="center"', $sortfield, $sortorder);
print_liste_field_titre('ExpireDate', $_SERVER["PHP_SELF"], 'e.expire_date', '', $param, 'class="center"', $sortfield, $sortorder);
print_liste_field_titre('Signers', $_SERVER["PHP_SELF"], '', '', $param, 'class="center"', $sortfield, $sortorder);
print_liste_field_titre('CreatedBy', $_SERVER["PHP_SELF"], 'u.lastname', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('', $_SERVER["PHP_SELF"], '', '', $param, 'class="center"', $sortfield, $sortorder);
print '</tr>';

// Data rows
$i = 0;
while ($i < min($num, $limit)) {
    $obj = $db->fetch_object($resql);

    // Get linked object info
    $objectLink = docsig_get_object_link($obj->element, $obj->fk_object);

    print '<tr class="oddeven">';

    // Ref
    print '<td class="nowraponall">';
    $tmpobject = new DocSigEnvelope($db);
    $tmpobject->id = $obj->rowid;
    $tmpobject->ref = $obj->ref;
    $tmpobject->status = $obj->status;
    print $tmpobject->getNomUrl(1);
    print '</td>';

    // Type
    print '<td>';
    $elementLabels = array(
        'facture' => $langs->trans('Invoice'),
        'commande' => $langs->trans('Order'),
        'propal' => $langs->trans('Proposal'),
        'contrat' => $langs->trans('Contract'),
        'fichinter' => $langs->trans('Intervention'),
    );
    print $elementLabels[$obj->element] ?? $obj->element;
    print '</td>';

    // Object
    print '<td class="tdoverflowmax200">';
    print $objectLink;
    print '</td>';

    // Status
    print '<td class="center">';
    print $tmpobject->getLibStatut(4);
    print '</td>';

    // Date creation
    print '<td class="center nowraponall">';
    print dol_print_date($db->jdate($obj->date_creation), 'dayhour');
    print '</td>';

    // Expire date
    print '<td class="center nowraponall">';
    $expireDate = $db->jdate($obj->expire_date);
    $isExpired = $expireDate < dol_now() && $obj->status < 3;
    print '<span'.($isExpired ? ' class="badge badge-danger"' : '').'>'.dol_print_date($expireDate, 'day').'</span>';
    print '</td>';

    // Signers
    print '<td class="center">';
    print '<span class="badge badge-secondary">'.$obj->nb_signed.'/'.$obj->nb_signers.'</span>';
    print '</td>';

    // User
    print '<td>';
    if ($obj->fk_user_creat > 0) {
        $usertmp = new User($db);
        $usertmp->id = $obj->fk_user_creat;
        $usertmp->login = $obj->user_login;
        $usertmp->firstname = $obj->user_firstname;
        $usertmp->lastname = $obj->user_lastname;
        print $usertmp->getNomUrl(-1);
    }
    print '</td>';

    // Actions
    print '<td class="center nowraponall">';
    print '<a href="'.dol_buildpath('/signDol/card.php', 1).'?id='.$obj->rowid.'" title="'.$langs->trans('View').'">';
    print img_picto($langs->trans('View'), 'eye');
    print '</a>';
    print '</td>';

    print '</tr>';

    $i++;
}

if ($num == 0) {
    print '<tr><td colspan="9" class="opacitymedium">'.$langs->trans('NoRecordFound').'</td></tr>';
}

print '</table>';
print '</div>';
print '</form>';

// End of page
llxFooter();
$db->close();

/**
 * Obtiene el enlace al objeto vinculado
 */
function docsig_get_object_link($element, $objectId)
{
    global $db;

    $classMap = array(
        'facture' => array('class' => 'Facture', 'file' => '/compta/facture/class/facture.class.php'),
        'commande' => array('class' => 'Commande', 'file' => '/commande/class/commande.class.php'),
        'propal' => array('class' => 'Propal', 'file' => '/comm/propal/class/propal.class.php'),
        'contrat' => array('class' => 'Contrat', 'file' => '/contrat/class/contrat.class.php'),
        'fichinter' => array('class' => 'Fichinter', 'file' => '/fichinter/class/fichinter.class.php'),
    );

    if (!isset($classMap[$element])) {
        return $objectId;
    }

    require_once DOL_DOCUMENT_ROOT.$classMap[$element]['file'];
    $className = $classMap[$element]['class'];
    $object = new $className($db);

    if ($object->fetch($objectId) > 0) {
        return $object->getNomUrl(1);
    }

    return $objectId;
}
