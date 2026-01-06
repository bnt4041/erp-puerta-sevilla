<?php
/* Copyright (C) 2026 DocSig Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/signDol/ajax/modal.php
 * \ingroup docsig
 * \brief   AJAX endpoint para cargar el modal de firma
 */

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}

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

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/signDol/class/docsigenvelope.class.php');
dol_include_once('/signDol/lib/docsig.lib.php');

// Security check
if (!isModEnabled('docsig')) {
    http_response_code(403);
    print json_encode(array('error' => 'Module not enabled'));
    exit;
}

$langs->loadLangs(array('docsig@signDol', 'companies', 'contacts'));

// Get parameters
$action = GETPOST('action', 'aZ09');
$element = GETPOST('element', 'aZ09');
$id = GETPOSTINT('id');
$filepath = GETPOST('filepath', 'alpha');

header('Content-Type: application/json; charset=UTF-8');

$response = array('success' => false);

try {
    switch ($action) {
        case 'getmodal':
            // Verificar permisos
            if (!$user->hasRight('docsig', 'envelope', 'read')) {
                throw new Exception($langs->trans('NotEnoughPermissions'));
            }

            // Verificar parámetros
            if (empty($element) || empty($id)) {
                throw new Exception($langs->trans('ErrorFieldRequired', 'element/id'));
            }

            // Obtener información del objeto
            $objectInfo = docsig_get_object_info($element, $id);
            if (!$objectInfo) {
                throw new Exception($langs->trans('ErrorRecordNotFound'));
            }

            // Verificar si ya existe un envelope activo
            $existingEnvelope = docsig_get_existing_envelope($db, $element, $id);

            // Obtener el PDF (único, el que viene en filepath o el más reciente)
            $pdfInfo = docsig_get_pdf_info($element, $id, $filepath);

            // Obtener tercero y contactos vinculados al objeto
            $thirdpartyContacts = docsig_get_object_contacts($db, $objectInfo, $element, $id);

            // Generar HTML del modal
            $html = docsig_generate_modal_html($objectInfo, $existingEnvelope, $pdfInfo, $thirdpartyContacts, $element, $id);

            $response = array(
                'success' => true,
                'html' => $html,
                'hasExisting' => !empty($existingEnvelope),
                'envelopeId' => $existingEnvelope ? $existingEnvelope->id : null,
            );
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    $response = array(
        'success' => false,
        'error' => $e->getMessage(),
    );
}

print json_encode($response);
exit;

/**
 * Obtiene información del objeto Dolibarr
 */
function docsig_get_object_info($element, $id)
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
        return false;
    }

    require_once DOL_DOCUMENT_ROOT.$classMap[$element]['file'];
    $className = $classMap[$element]['class'];
    $object = new $className($db);

    if ($object->fetch($id) <= 0) {
        return false;
    }

    return array(
        'id' => $object->id,
        'ref' => $object->ref,
        'element' => $element,
        'socid' => $object->socid ?? $object->fk_soc ?? 0,
        'label' => method_exists($object, 'getNomUrl') ? $object->getNomUrl(1) : $object->ref,
        'object' => $object,
    );
}

/**
 * Obtiene envelope existente para un objeto
 */
function docsig_get_existing_envelope($db, $element, $objectId)
{
    global $conf;

    $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."docsig_envelope";
    $sql .= " WHERE element = '".$db->escape($element)."'";
    $sql .= " AND fk_object = ".(int)$objectId;
    $sql .= " AND entity = ".(int)$conf->entity;
    $sql .= " AND status NOT IN (4, 5)"; // No cancelados ni expirados
    $sql .= " ORDER BY rowid DESC LIMIT 1";

    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        $obj = $db->fetch_object($resql);
        $envelope = new DocSigEnvelope($db);
        $envelope->fetch($obj->rowid);
        return $envelope;
    }

    return null;
}

/**
 * Obtiene información del PDF específico o el más reciente
 */
function docsig_get_pdf_info($element, $id, $requestedPath = '')
{
    global $conf;

    // Determinar directorio de documentos
    $dirMap = array(
        'facture' => 'facture',
        'commande' => 'commande',
        'propal' => 'propale',
        'contrat' => 'contract',
        'fichinter' => 'ficheinter',
    );

    $subdir = $dirMap[$element] ?? $element;

    // Obtener referencia del objeto para el subdirectorio
    $objectInfo = docsig_get_object_info($element, $id);
    if (!$objectInfo) {
        return null;
    }

    $ref = dol_sanitizeFileName($objectInfo['ref']);
    $dir = $conf->$element->dir_output.'/'.$ref;

    if (!is_dir($dir)) {
        return null;
    }

    // Si se solicitó un PDF específico, buscarlo
    if (!empty($requestedPath)) {
        $requestedPath = urldecode($requestedPath);
        // El path puede venir como relativepath o como nombre de archivo
        $fullPath = $dir.'/'.$requestedPath;
        if (file_exists($fullPath) && preg_match('/\.pdf$/i', $fullPath)) {
            $stat = stat($fullPath);
            return array(
                'name' => basename($fullPath),
                'path' => $fullPath,
                'relativepath' => $requestedPath,
                'size' => dol_print_size($stat['size']),
                'date' => dol_print_date($stat['mtime'], 'dayhour'),
            );
        }
        // Intentar buscar el archivo por nombre en el directorio
        $files = dol_dir_list($dir, 'files', 0, '\.pdf$', '', 'date', SORT_DESC);
        foreach ($files as $file) {
            if ($file['name'] === basename($requestedPath) || $file['relativename'] === $requestedPath) {
                return array(
                    'name' => $file['name'],
                    'path' => $file['fullname'],
                    'relativepath' => $file['relativename'] ?? $file['name'],
                    'size' => dol_print_size($file['size']),
                    'date' => dol_print_date($file['date'], 'dayhour'),
                );
            }
        }
    }

    // Si no se especificó o no se encontró, devolver el más reciente
    $files = dol_dir_list($dir, 'files', 0, '\.pdf$', '', 'date', SORT_DESC);
    if (!empty($files)) {
        $file = $files[0];
        return array(
            'name' => $file['name'],
            'path' => $file['fullname'],
            'relativepath' => $file['relativename'] ?? $file['name'],
            'size' => dol_print_size($file['size']),
            'date' => dol_print_date($file['date'], 'dayhour'),
        );
    }

    return null;
}

/**
 * Obtiene el tercero y los contactos vinculados al objeto (via element_contact)
 * También obtiene contactos del tercero si existe
 */
function docsig_get_object_contacts($db, $objectInfo, $element, $objectId)
{
    global $conf, $langs;

    $result = array(
        'thirdparty' => null,
        'object_contacts' => array(), // Contactos vinculados al objeto
        'thirdparty_contacts' => array(), // Contactos del tercero
    );

    $socid = $objectInfo['socid'] ?? 0;
    $addedExternalContactIds = array(); // Para evitar duplicados (solo externos)

    // 1) Obtener datos del tercero si existe
    if (!empty($socid)) {
        $societe = new Societe($db);
        if ($societe->fetch($socid) > 0) {
            $result['thirdparty'] = array(
                'id' => $societe->id,
                'name' => $societe->name,
                'email' => $societe->email,
                'phone' => $societe->phone,
                'type' => 'thirdparty',
            );
        }
    }

    // 2) Obtener contactos VINCULADOS al objeto via element_contact
    // Incluye contactos externos (socpeople) e internos (user) en la misma tabla.
    // Mapeo de element a element_type en la tabla element_contact
    $elementTypeMap = array(
        'facture' => 'facture',
        'commande' => 'commande',
        'propal' => 'propal',
        'contrat' => 'contrat',
        'fichinter' => 'fichinter',
    );
    $elementType = $elementTypeMap[$element] ?? $element;

    $sql = "SELECT DISTINCT";
    $sql .= " ec.fk_socpeople as linkid, tc.source, tc.libelle as role_label,";
    $sql .= " CASE WHEN tc.source = 'external' THEN c.firstname ELSE u.firstname END as firstname,";
    $sql .= " CASE WHEN tc.source = 'external' THEN c.lastname ELSE u.lastname END as lastname,";
    $sql .= " CASE WHEN tc.source = 'external' THEN c.email ELSE u.email END as email,";
    $sql .= " CASE WHEN tc.source = 'external' THEN (CASE WHEN c.phone_mobile <> '' THEN c.phone_mobile ELSE c.phone END)";
    $sql .= " ELSE (CASE WHEN u.user_mobile <> '' THEN u.user_mobile ELSE u.office_phone END) END as phone,";
    $sql .= " c.poste, c.fk_soc, s.nom as socname";
    $sql .= " FROM ".MAIN_DB_PREFIX."element_contact as ec";
    $sql .= " INNER JOIN ".MAIN_DB_PREFIX."c_type_contact as tc ON tc.rowid = ec.fk_c_type_contact";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople as c ON (tc.source = 'external' AND c.rowid = ec.fk_socpeople)";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON (tc.source = 'internal' AND u.rowid = ec.fk_socpeople)";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = c.fk_soc";
    $sql .= " WHERE ec.element_id = ".(int)$objectId;
    $sql .= " AND tc.element = '".$db->escape($elementType)."'";
    $sql .= " AND ((tc.source = 'external' AND c.statut = 1 AND c.entity IN (".getEntity('contact')."))";
    $sql .= " OR (tc.source = 'internal' AND u.statut = 1 AND u.entity IN (".getEntity('user').")))";
    $sql .= " ORDER BY tc.source ASC, lastname, firstname"; // internal primero (ASC), luego apellidos

    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $isInternal = ($obj->source === 'internal');
            $sourceLabel = $isInternal ? $langs->trans('Internal') : $langs->trans('External');

            if (!$isInternal) {
                $addedExternalContactIds[] = (int) $obj->linkid;
            }

            $result['object_contacts'][] = array(
                'id' => (int) $obj->linkid,
                'firstname' => $obj->firstname,
                'lastname' => $obj->lastname,
                'name' => trim($obj->firstname.' '.$obj->lastname),
                'email' => $obj->email,
                'phone' => $obj->phone,
                'poste' => $obj->poste,
                'role' => $obj->role_label,
                'source' => $sourceLabel,
                'socid' => (int) $obj->fk_soc,
                'socname' => $obj->socname,
                'type' => $isInternal ? 'internal_user' : 'object_contact',
            );
        }
    }

    // 3) Obtener otros contactos del tercero (que no estén ya vinculados)
    if (!empty($socid)) {
        $sql = "SELECT c.rowid, c.firstname, c.lastname, c.email, c.phone_mobile, c.phone, c.poste,";
        $sql .= " c.fk_soc, s.nom as socname";
        $sql .= " FROM ".MAIN_DB_PREFIX."socpeople as c";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = c.fk_soc";
        $sql .= " WHERE c.fk_soc = ".(int)$socid;
        $sql .= " AND c.statut = 1";
        $sql .= " AND c.entity IN (".getEntity('contact').")";
        if (!empty($addedExternalContactIds)) {
            $sql .= " AND c.rowid NOT IN (".implode(',', array_map('intval', $addedExternalContactIds)).")";
        }
        $sql .= " ORDER BY c.lastname, c.firstname";

        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $result['thirdparty_contacts'][] = array(
                    'id' => $obj->rowid,
                    'firstname' => $obj->firstname,
                    'lastname' => $obj->lastname,
                    'name' => trim($obj->firstname.' '.$obj->lastname),
                    'email' => $obj->email,
                    'phone' => $obj->phone_mobile ?: $obj->phone,
                    'poste' => $obj->poste,
                    'socid' => $obj->fk_soc,
                    'socname' => $obj->socname,
                    'type' => 'thirdparty_contact',
                );
            }
        }
    }

    return $result;
}

/**
 * Genera HTML del modal
 */
function docsig_generate_modal_html($objectInfo, $existingEnvelope, $pdfInfo, $thirdpartyContacts, $element, $id)
{
    global $langs, $conf, $user;

    $html = '';

    // Si hay envelope existente, mostrar estado
    if ($existingEnvelope) {
        $html .= '<div class="docsig-envelope-status">';
        $html .= '<h3>'.$langs->trans('DocSigExistingEnvelope').'</h3>';
        $html .= '<table class="border centpercent">';
        $html .= '<tr><td class="titlefield">'.$langs->trans('Ref').'</td>';
        $html .= '<td>'.$existingEnvelope->getNomUrl(1).'</td></tr>';
        $html .= '<tr><td>'.$langs->trans('Status').'</td>';
        $html .= '<td>'.$existingEnvelope->getLibStatut(4).'</td></tr>';
        $html .= '<tr><td>'.$langs->trans('DateCreation').'</td>';
        $html .= '<td>'.dol_print_date($existingEnvelope->date_creation, 'dayhour').'</td></tr>';
        $html .= '<tr><td>'.$langs->trans('ExpireDate').'</td>';
        $html .= '<td>'.dol_print_date($existingEnvelope->expire_date, 'dayhour').'</td></tr>';
        $html .= '</table>';

        // Lista de firmantes
        if (!empty($existingEnvelope->signers)) {
            $html .= '<h4>'.$langs->trans('Signers').'</h4>';
            $html .= '<table class="border centpercent">';
            $html .= '<tr class="liste_titre">';
            $html .= '<th>'.$langs->trans('Name').'</th>';
            $html .= '<th>'.$langs->trans('Email').'</th>';
            $html .= '<th>'.$langs->trans('Status').'</th>';
            $html .= '<th>'.$langs->trans('DateSigned').'</th>';
            $html .= '<th></th>';
            $html .= '</tr>';

            foreach ($existingEnvelope->signers as $signer) {
                $html .= '<tr>';
                $html .= '<td>'.$signer->getFullName().'</td>';
                $html .= '<td>'.$signer->email.'</td>';
                $html .= '<td>'.$signer->getLibStatut(2).'</td>';
                $html .= '<td>'.($signer->date_signed ? dol_print_date($signer->date_signed, 'dayhour') : '-').'</td>';
                $html .= '<td class="right nowraponall">';
                if ($signer->status == 0 && $user->hasRight('docsig', 'envelope', 'write')) {
                    $html .= '<a href="#" class="docsig-resend-btn paddingright" data-signer-id="'.$signer->id.'" title="'.$langs->trans('ResendNotification').'">';
                    $html .= '<span class="fa fa-paper-plane"></span>';
                    $html .= '</a>';
                    $html .= '<a href="#" class="docsig-copy-url-btn" data-signer-id="'.$signer->id.'" title="'.$langs->trans('CopySignUrl').'">';
                    $html .= '<span class="fa fa-copy"></span>';
                    $html .= '</a>';
                }
                $html .= '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
        }

        // Botones de acción
        $html .= '<div class="docsig-modal-actions">';
        if ($existingEnvelope->status < 3 && $user->hasRight('docsig', 'envelope', 'delete')) {
            $html .= '<button type="button" class="button button-cancel" id="docsig-cancel-envelope" data-id="'.$existingEnvelope->id.'">';
            $html .= $langs->trans('CancelEnvelope');
            $html .= '</button>';
        }
        $html .= '<a href="'.dol_buildpath('/signDol/card.php', 1).'?id='.$existingEnvelope->id.'" class="button">';
        $html .= $langs->trans('ViewDetails');
        $html .= '</a>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    // ============================================================
    // Formulario para crear nuevo envelope
    // ============================================================
    $html .= '<form id="docsig-create-form" class="docsig-create-form">';
    $html .= '<input type="hidden" name="element" value="'.dol_escape_htmltag($element).'">';
    $html .= '<input type="hidden" name="object_id" value="'.dol_escape_htmltag($id).'">';
    $html .= '<input type="hidden" name="token" value="'.newToken().'">';

    // ============================================================
    // 1) Documento PDF (único, no seleccionable)
    // ============================================================
    $html .= '<div class="docsig-section docsig-document-section">';
    $html .= '<h4><span class="fa fa-file-pdf-o"></span> '.$langs->trans('Document').'</h4>';
    if (empty($pdfInfo)) {
        $html .= '<div class="warning">'.$langs->trans('NoPDFAvailable').'</div>';
    } else {
        $html .= '<input type="hidden" name="pdf_file" value="'.dol_escape_htmltag($pdfInfo['path']).'">';
        $html .= '<div class="docsig-pdf-info">';
        $html .= '<span class="fa fa-file-pdf-o fa-2x" style="color:#dc3545; margin-right:10px;"></span>';
        $html .= '<div class="docsig-pdf-details">';
        $html .= '<strong>'.dol_escape_htmltag($pdfInfo['name']).'</strong><br>';
        $html .= '<small class="opacitymedium">'.$pdfInfo['size'].' - '.$pdfInfo['date'].'</small>';
        $html .= '</div>';
        $html .= '</div>';
    }
    $html .= '</div>';

    // ============================================================
    // 2) Firmantes - Contactos del objeto y del tercero
    // ============================================================
    $html .= '<div class="docsig-section docsig-signers-section">';
    $html .= '<h4><span class="fa fa-users"></span> '.$langs->trans('Signers').'</h4>';

    // Lista de firmantes
    $html .= '<div id="docsig-signers-list" class="docsig-signers-list">';

    $hasAnyContact = false;

    // 2.1) Contactos vinculados al objeto (los más relevantes, checked por defecto)
    if (!empty($thirdpartyContacts['object_contacts'])) {
        $html .= '<div class="docsig-signers-group">';
        $html .= '<div class="docsig-group-header"><span class="fa fa-link"></span> '.$langs->trans('LinkedContacts').'</div>';
        foreach ($thirdpartyContacts['object_contacts'] as $contact) {
            $hasAnyContact = true;
            $roleInfo = !empty($contact['role']) ? $contact['role'].' ('.$contact['source'].')' : $contact['source'];

            if (($contact['type'] ?? '') === 'internal_user') {
                $uniqueId = 'user_'.$contact['id'];
                $html .= docsig_render_signer_item(
                    $uniqueId,
                    $contact['name'],
                    $contact['email'],
                    $contact['phone'],
                    $roleInfo,
                    'internal_user',
                    0,
                    !empty($contact['email'])
                );
            } else {
                $uniqueId = 'contact_'.$contact['id'];
                $html .= docsig_render_signer_item(
                    $uniqueId,
                    $contact['name'],
                    $contact['email'],
                    $contact['phone'],
                    $roleInfo,
                    'object_contact',
                    $contact['id'],
                    !empty($contact['email'])
                );
            }
        }
        $html .= '</div>';
    }

    // 2.2) Tercero (empresa)
    if (!empty($thirdpartyContacts['thirdparty'])) {
        $tp = $thirdpartyContacts['thirdparty'];
        $html .= '<div class="docsig-signers-group">';
        $html .= '<div class="docsig-group-header"><span class="fa fa-building"></span> '.$langs->trans('ThirdParty').'</div>';
        $hasAnyContact = true;
        $html .= docsig_render_signer_item(
            'thirdparty_'.$tp['id'],
            $tp['name'],
            $tp['email'],
            $tp['phone'],
            $langs->trans('Company'),
            'thirdparty',
            $tp['id'],
            !empty($tp['email']) // Checked si tiene email
        );
        $html .= '</div>';
    }

    // 2.3) Otros contactos del tercero (no vinculados directamente)
    if (!empty($thirdpartyContacts['thirdparty_contacts'])) {
        $html .= '<div class="docsig-signers-group">';
        $html .= '<div class="docsig-group-header"><span class="fa fa-address-book"></span> '.$langs->trans('OtherContacts').'</div>';
        foreach ($thirdpartyContacts['thirdparty_contacts'] as $contact) {
            $hasAnyContact = true;
            $html .= docsig_render_signer_item(
                'contact_'.$contact['id'],
                $contact['name'],
                $contact['email'],
                $contact['phone'],
                $contact['poste'],
                'thirdparty_contact',
                $contact['id'],
                false // No checked por defecto
            );
        }
        $html .= '</div>';
    }

    // Mensaje si no hay contactos
    if (!$hasAnyContact) {
        $html .= '<div class="docsig-no-contacts">';
        $html .= '<span class="fa fa-info-circle"></span> '.$langs->trans('NoContactsAvailable');
        $html .= '</div>';
    }

    $html .= '</div>'; // end signers-list

    // Mensaje si no hay firmantes seleccionados
    $html .= '<p class="opacitymedium docsig-no-signers-msg" id="docsig-no-signers" style="display:none;">'.$langs->trans('NoSignersSelected').'</p>';

    // ============================================================
    // 3) Autobúsqueda de contactos
    // ============================================================
    $html .= '<div class="docsig-search-section">';
    $html .= '<label><span class="fa fa-search"></span> '.$langs->trans('SearchOtherContact').'</label>';
    $html .= '<div class="docsig-contact-search-wrapper">';
    $html .= '<input type="text" id="docsig-contact-search" class="flat minwidth300" placeholder="'.$langs->trans('TypeToSearchContact').'" autocomplete="off">';
    $html .= '<div id="docsig-search-results" class="docsig-search-results" style="display:none;"></div>';
    $html .= '</div>';
    $html .= '</div>';

    // ============================================================
    // 4) Crear contacto nuevo sin tercero
    // ============================================================
    $html .= '<div class="docsig-new-contact-section">';
    $html .= '<a href="#" id="docsig-toggle-new-contact" class="small">';
    $html .= '<span class="fa fa-plus-circle"></span> '.$langs->trans('CreateNewContact');
    $html .= '</a>';
    $html .= '<div id="docsig-new-contact-form" class="docsig-new-contact-form" style="display:none;">';
    $html .= '<div class="docsig-form-grid">';
    $html .= '<div class="docsig-form-row">';
    $html .= '<input type="text" id="new-contact-firstname" class="flat" placeholder="'.$langs->trans('FirstName').'">';
    $html .= '<input type="text" id="new-contact-lastname" class="flat" placeholder="'.$langs->trans('LastName').'">';
    $html .= '</div>';
    $html .= '<div class="docsig-form-row">';
    $html .= '<input type="email" id="new-contact-email" class="flat minwidth200" placeholder="'.$langs->trans('Email').' *">';
    $html .= '<input type="text" id="new-contact-phone" class="flat" placeholder="'.$langs->trans('Phone').'">';
    $html .= '</div>';
    $html .= '<div class="docsig-form-row">';
    $html .= '<input type="text" id="new-contact-dni" class="flat" placeholder="'.$langs->trans('DNI').'">';
    $html .= '<select id="new-contact-save" class="flat">';
    $html .= '<option value="0">'.$langs->trans('DoNotSaveContact').'</option>';
    $html .= '<option value="1">'.$langs->trans('SaveAsNewContact').'</option>';
    $html .= '</select>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="docsig-form-actions">';
    $html .= '<button type="button" class="button small" id="docsig-add-new-contact">'.$langs->trans('AddSigner').'</button>';
    $html .= '<button type="button" class="button small button-cancel" id="docsig-cancel-new-contact">'.$langs->trans('Cancel').'</button>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '</div>'; // end signers-section

    // ============================================================
    // Modo de firma
    // ============================================================
    $html .= '<div class="docsig-section docsig-mode-section">';
    $html .= '<h4><span class="fa fa-cogs"></span> '.$langs->trans('SignatureMode').'</h4>';
    $defaultMode = getDolGlobalString('DOCSIG_SIGNATURE_MODE', 'parallel');
    $html .= '<select name="signature_mode" id="docsig-mode-select" class="flat">';
    $html .= '<option value="parallel"'.($defaultMode == 'parallel' ? ' selected' : '').'>'.$langs->trans('ParallelMode').' - '.$langs->trans('ParallelModeDesc').'</option>';
    $html .= '<option value="sequential"'.($defaultMode == 'sequential' ? ' selected' : '').'>'.$langs->trans('SequentialMode').' - '.$langs->trans('SequentialModeDesc').'</option>';
    $html .= '</select>';
    $html .= '</div>';

    // ============================================================
    // Mensaje personalizado (opcional)
    // ============================================================
    $html .= '<div class="docsig-section">';
    $html .= '<h4><span class="fa fa-envelope"></span> '.$langs->trans('CustomMessage').' <span class="opacitymedium">('.$langs->trans('Optional').')</span></h4>';
    $html .= '<textarea name="custom_message" id="docsig-message" class="flat centpercent" rows="3" placeholder="'.$langs->trans('CustomMessagePlaceholder').'"></textarea>';
    $html .= '</div>';

    // ============================================================
    // Botones de acción
    // ============================================================
    $html .= '<div class="docsig-modal-actions">';
    $html .= '<button type="button" class="button button-cancel" onclick="DocSig.closeModal();">'.$langs->trans('Cancel').'</button>';
    $html .= '<button type="submit" class="button button-primary" id="docsig-submit-btn"'.(!empty($pdfInfo) ? '' : ' disabled').'>';
    $html .= '<span class="fa fa-paper-plane"></span> '.$langs->trans('SendSignatureRequest');
    $html .= '</button>';
    $html .= '</div>';

    $html .= '</form>';

    return $html;
}

/**
 * Genera HTML de un item de firmante editable con diseño mejorado
 */
function docsig_render_signer_item($uniqueId, $name, $email, $phone, $poste = '', $type = 'contact', $contactId = 0, $checked = false)
{
    global $langs;

    $checkedAttr = $checked ? ' checked' : '';
    $disabledClass = empty($email) ? ' docsig-signer-no-email' : '';
    $disabledAttr = empty($email) ? ' disabled' : '';
    
    // Mapeo de tipos a etiquetas e iconos
    $typeConfig = array(
        'thirdparty' => array('label' => $langs->trans('ThirdParty'), 'badge' => 'badge-primary', 'icon' => 'fa-building'),
        'object_contact' => array('label' => $langs->trans('LinkedContact'), 'badge' => 'badge-success', 'icon' => 'fa-link'),
        'internal_user' => array('label' => $langs->trans('Internal'), 'badge' => 'badge-warning', 'icon' => 'fa-user'),
        'thirdparty_contact' => array('label' => $langs->trans('Contact'), 'badge' => 'badge-secondary', 'icon' => 'fa-user'),
        'contact' => array('label' => $langs->trans('Contact'), 'badge' => 'badge-secondary', 'icon' => 'fa-user'),
        'new' => array('label' => $langs->trans('New'), 'badge' => 'badge-info', 'icon' => 'fa-plus'),
    );
    $config = $typeConfig[$type] ?? $typeConfig['contact'];

    $html = '<div class="docsig-signer-item'.$disabledClass.'" data-id="'.dol_escape_htmltag($uniqueId).'">';
    
    // Checkbox
    $html .= '<div class="docsig-signer-select">';
    $html .= '<input type="checkbox" name="signers_selected[]" value="'.dol_escape_htmltag($uniqueId).'" ';
    $html .= 'id="signer-'.dol_escape_htmltag($uniqueId).'"'.$checkedAttr.$disabledAttr.'>';
    $html .= '</div>';
    
    // Info principal
    $html .= '<div class="docsig-signer-main">';
    $html .= '<label for="signer-'.dol_escape_htmltag($uniqueId).'" class="docsig-signer-label">';
    $html .= '<span class="fa '.$config['icon'].' docsig-signer-icon"></span>';
    $html .= '<span class="docsig-signer-name">'.dol_escape_htmltag($name).'</span>';
    if (!empty($poste)) {
        $html .= '<span class="docsig-signer-role">'.dol_escape_htmltag($poste).'</span>';
    }
    $html .= '</label>';
    $html .= '<span class="badge '.$config['badge'].'">'.dol_escape_htmltag($config['label']).'</span>';
    $html .= '</div>';
    
    // Campos editables
    $html .= '<div class="docsig-signer-fields">';
    
    // Email
    $html .= '<div class="docsig-field-group">';
    $html .= '<span class="fa fa-envelope docsig-field-icon"></span>';
    $html .= '<input type="email" name="signers['.$uniqueId.'][email]" value="'.dol_escape_htmltag($email).'" ';
    $html .= 'class="docsig-input" placeholder="'.$langs->trans('Email').'">';
    $html .= '</div>';
    
    // Teléfono
    $html .= '<div class="docsig-field-group">';
    $html .= '<span class="fa fa-phone docsig-field-icon"></span>';
    $html .= '<input type="text" name="signers['.$uniqueId.'][phone]" value="'.dol_escape_htmltag($phone).'" ';
    $html .= 'class="docsig-input" placeholder="'.$langs->trans('Phone').'">';
    $html .= '</div>';
    
    $html .= '</div>';
    
    // Campos ocultos
    $html .= '<input type="hidden" name="signers['.$uniqueId.'][name]" value="'.dol_escape_htmltag($name).'">';
    $html .= '<input type="hidden" name="signers['.$uniqueId.'][type]" value="'.dol_escape_htmltag($type).'">';
    $html .= '<input type="hidden" name="signers['.$uniqueId.'][contact_id]" value="'.dol_escape_htmltag($contactId).'">';
    
    // Aviso si no tiene email
    if (empty($email)) {
        $html .= '<div class="docsig-signer-warning">';
        $html .= '<span class="fa fa-exclamation-triangle"></span> '.$langs->trans('NoEmailAddress');
        $html .= '</div>';
    }
    
    $html .= '</div>';

    return $html;
}
