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

            // Obtener TODOS los envelopes activos para este objeto
            $existingEnvelopes = docsig_get_all_envelopes($db, $element, $id);
            
            // Obtener paths de PDFs ya en uso
            $usedPdfPaths = docsig_get_used_pdf_paths($existingEnvelopes);
            
            // Obtener PDFs disponibles (excluyendo los que ya tienen envelope)
            $availablePdfs = docsig_get_all_pdfs($element, $id, $usedPdfPaths);
            
            // Para compatibilidad con código antiguo
            $existingEnvelope = !empty($existingEnvelopes) ? $existingEnvelopes[0] : null;
            $pdfInfo = !empty($availablePdfs) ? $availablePdfs[0] : null;

            // Obtener tercero y contactos vinculados al objeto
            $thirdpartyContacts = docsig_get_object_contacts($db, $objectInfo, $element, $id);

            // Generar HTML del modal
            $html = docsig_generate_modal_html($objectInfo, $existingEnvelopes, $availablePdfs, $thirdpartyContacts, $element, $id);

            $response = array(
                'success' => true,
                'html' => $html,
                'hasExisting' => !empty($existingEnvelopes),
                'envelopeCount' => count($existingEnvelopes),
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
 * Obtiene TODOS los envelopes activos para un objeto
 * @param DoliDB $db Database handler
 * @param string $element Tipo de elemento
 * @param int $objectId ID del objeto
 * @return DocSigEnvelope[] Array de envelopes activos
 */
function docsig_get_all_envelopes($db, $element, $objectId)
{
    global $conf;

    $envelopes = array();

    $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."docsig_envelope";
    $sql .= " WHERE element = '".$db->escape($element)."'";
    $sql .= " AND fk_object = ".(int)$objectId;
    $sql .= " AND entity = ".(int)$conf->entity;
    $sql .= " AND status NOT IN (4, 5)"; // No cancelados ni expirados
    $sql .= " ORDER BY date_creation DESC";

    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $envelope = new DocSigEnvelope($db);
            $envelope->fetch($obj->rowid);
            $envelopes[] = $envelope;
        }
    }

    return $envelopes;
}

/**
 * Obtiene los paths de PDFs que ya tienen envelope activo
 * @param DocSigEnvelope[] $envelopes Array de envelopes
 * @return array Array de paths de archivos ya en uso
 */
function docsig_get_used_pdf_paths($envelopes)
{
    $usedPaths = array();
    foreach ($envelopes as $envelope) {
        if (!empty($envelope->file_path)) {
            $usedPaths[] = $envelope->file_path;
        }
    }
    return $usedPaths;
}

/**
 * Obtiene TODOS los PDFs disponibles para un objeto (excluyendo los que ya tienen envelope)
 * @param string $element Tipo de elemento
 * @param int $id ID del objeto
 * @param array $excludePaths Paths a excluir (ya tienen envelope)
 * @return array Array de PDFs con info de cada uno
 */
function docsig_get_all_pdfs($element, $id, $excludePaths = array())
{
    global $conf;

    $pdfs = array();

    // Obtener referencia del objeto para el subdirectorio
    $objectInfo = docsig_get_object_info($element, $id);
    if (!$objectInfo) {
        return $pdfs;
    }

    $ref = dol_sanitizeFileName($objectInfo['ref']);
    $dir = $conf->$element->dir_output.'/'.$ref;

    if (!is_dir($dir)) {
        return $pdfs;
    }

    // Buscar todos los PDFs en el directorio
    $files = dol_dir_list($dir, 'files', 0, '\.pdf$', '', 'date', SORT_DESC);
    
    foreach ($files as $file) {
        // Excluir PDFs firmados y certificados
        if (preg_match('/_signed\.pdf$/i', $file['name']) || 
            preg_match('/^compliance_certificate/i', $file['name']) ||
            preg_match('/^signature_addendum/i', $file['name'])) {
            continue;
        }
        
        // Excluir PDFs que ya tienen un envelope activo
        if (in_array($file['fullname'], $excludePaths)) {
            continue;
        }
        
        $pdfs[] = array(
            'name' => $file['name'],
            'path' => $file['fullname'],
            'relativepath' => $file['relativename'] ?? $file['name'],
            'size' => dol_print_size($file['size']),
            'size_raw' => $file['size'],
            'date' => dol_print_date($file['date'], 'dayhour'),
            'date_raw' => $file['date'],
        );
    }

    return $pdfs;
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
 * @param array $objectInfo Información del objeto
 * @param DocSigEnvelope|null $existingEnvelope Envelope existente
 * @param array|null $pdfInfo PDF principal (compatibilidad)
 * @param array $thirdpartyContacts Contactos
 * @param string $element Tipo de elemento
 * @param int $id ID del objeto
 * @param array $allPdfs Todos los PDFs disponibles
 */
function docsig_generate_modal_html($objectInfo, $existingEnvelopes, $availablePdfs, $thirdpartyContacts, $element, $id)
{
    global $langs, $conf, $user;

    $html = '';

    // ============================================================
    // SECCIÓN 1: Mostrar envelopes existentes (si los hay)
    // ============================================================
    if (!empty($existingEnvelopes)) {
        $html .= '<div class="docsig-existing-envelopes-section">';
        $html .= '<h3><span class="fa fa-folder-open"></span> '.$langs->trans('DocSigExistingEnvelopes').' ('.count($existingEnvelopes).')</h3>';
        $html .= '<table class="border centpercent">';
        $html .= '<tr class="liste_titre">';
        $html .= '<th>'.$langs->trans('Ref').'</th>';
        $html .= '<th>'.$langs->trans('Document').'</th>';
        $html .= '<th>'.$langs->trans('Status').'</th>';
        $html .= '<th>'.$langs->trans('DateCreation').'</th>';
        $html .= '<th class="right">'.$langs->trans('Actions').'</th>';
        $html .= '</tr>';

        foreach ($existingEnvelopes as $envelope) {
            // Obtener nombre del documento
            $pdfName = basename($envelope->pdf_path);
            
            $html .= '<tr class="oddeven">';
            $html .= '<td>'.$envelope->getNomUrl(1).'</td>';
            $html .= '<td><span class="fa fa-file-pdf-o" style="color:#dc3545;"></span> '.dol_escape_htmltag($pdfName).'</td>';
            $html .= '<td>'.$envelope->getLibStatut(4).'</td>';
            $html .= '<td>'.dol_print_date($envelope->date_creation, 'dayhour').'</td>';
            $html .= '<td class="right nowraponall">';
            $html .= '<a href="'.dol_buildpath('/signDol/card.php', 1).'?id='.$envelope->id.'" class="button small">';
            $html .= '<span class="fa fa-eye"></span>';
            $html .= '</a>';
            if ($envelope->status < 3 && $user->hasRight('docsig', 'envelope', 'delete')) {
                $html .= ' <button type="button" class="button button-cancel small docsig-cancel-envelope-btn" data-id="'.$envelope->id.'" title="'.$langs->trans('CancelEnvelope').'">';
                $html .= '<span class="fa fa-times"></span>';
                $html .= '</button>';
            }
            $html .= '</td>';
            $html .= '</tr>';
            
            // Fila de firmantes (colapsable)
            if (!empty($envelope->signers)) {
                $html .= '<tr class="docsig-signers-row">';
                $html .= '<td colspan="5" style="padding-left:30px; background:#f9f9f9;">';
                $html .= '<small><strong>'.$langs->trans('Signers').':</strong> ';
                $signerNames = array();
                foreach ($envelope->signers as $signer) {
                    $statusIcon = $signer->status == 1 ? '<span class="fa fa-check" style="color:green;"></span>' : '<span class="fa fa-clock-o" style="color:orange;"></span>';
                    $signerNames[] = $statusIcon.' '.dol_escape_htmltag($signer->getFullName());
                }
                $html .= implode(' | ', $signerNames);
                $html .= '</small></td>';
                $html .= '</tr>';
            }
        }
        $html .= '</table>';
        $html .= '</div>';
        
        // Separador si hay PDFs disponibles para nuevo envelope
        if (!empty($availablePdfs)) {
            $html .= '<hr style="margin: 20px 0; border-top: 1px dashed #ccc;">';
        }
    }

    // ============================================================
    // SECCIÓN 2: Formulario para crear nuevo envelope (si hay PDFs disponibles)
    // ============================================================
    if (empty($availablePdfs)) {
        if (empty($existingEnvelopes)) {
            $html .= '<div class="warning">';
            $html .= '<span class="fa fa-exclamation-triangle"></span> '.$langs->trans('NoPDFAvailable');
            $html .= '</div>';
        } else {
            $html .= '<div class="info">';
            $html .= '<span class="fa fa-info-circle"></span> '.$langs->trans('DocSigAllPDFsHaveEnvelope');
            $html .= '</div>';
        }
        return $html;
    }

    // Título del formulario
    $html .= '<div class="docsig-new-envelope-section">';
    if (!empty($existingEnvelopes)) {
        $html .= '<h3><span class="fa fa-plus-circle"></span> '.$langs->trans('DocSigCreateNewEnvelope').'</h3>';
    }

    $html .= '<form id="docsig-create-form" class="docsig-create-form">';
    $html .= '<input type="hidden" name="element" value="'.dol_escape_htmltag($element).'">';
    $html .= '<input type="hidden" name="object_id" value="'.dol_escape_htmltag($id).'">';
    $html .= '<input type="hidden" name="token" value="'.newToken().'">';

    // ============================================================
    // 1) Selección de documento PDF (UN solo PDF)
    // ============================================================
    $html .= '<div class="docsig-section docsig-document-section">';
    $html .= '<h4><span class="fa fa-file-pdf-o"></span> '.$langs->trans('SelectDocument').'</h4>';
    
    if (count($availablePdfs) == 1) {
        // Un solo PDF disponible - mostrar como info
        $pdf = $availablePdfs[0];
        $html .= '<input type="hidden" name="pdf_file" value="'.dol_escape_htmltag($pdf['path']).'">';
        $html .= '<div class="docsig-pdf-info">';
        $html .= '<span class="fa fa-file-pdf-o fa-2x" style="color:#dc3545; margin-right:10px;"></span>';
        $html .= '<div class="docsig-pdf-details">';
        $html .= '<strong>'.dol_escape_htmltag($pdf['name']).'</strong><br>';
        $html .= '<small class="opacitymedium">'.$pdf['size'].' - '.$pdf['date'].'</small>';
        $html .= '</div>';
        $html .= '</div>';
    } else {
        // Múltiples PDFs disponibles - mostrar lista con radio buttons
        $html .= '<div class="docsig-help-text opacitymedium small" style="margin-bottom:8px;">';
        $html .= '<span class="fa fa-info-circle"></span> '.$langs->trans('DocSigSelectOnePDF');
        $html .= '</div>';
        $html .= '<div class="docsig-pdf-list" style="max-height:180px; overflow-y:auto; border:1px solid #ddd; border-radius:4px; padding:8px;">';
        
        foreach ($availablePdfs as $index => $pdf) {
            $checked = ($index == 0) ? ' checked' : ''; // El primero seleccionado por defecto
            $uniqueId = 'pdf_'.md5($pdf['path']);
            
            $html .= '<div class="docsig-pdf-item" style="display:flex; align-items:center; padding:6px; margin-bottom:4px; background:#f9f9f9; border-radius:3px;">';
            $html .= '<input type="radio" name="pdf_file" value="'.dol_escape_htmltag($pdf['path']).'" id="'.$uniqueId.'"'.$checked.' class="docsig-pdf-radio">';
            $html .= '<label for="'.$uniqueId.'" style="flex:1; display:flex; align-items:center; margin-left:8px; cursor:pointer;">';
            $html .= '<span class="fa fa-file-pdf-o" style="color:#dc3545; margin-right:8px;"></span>';
            $html .= '<div style="flex:1;">';
            $html .= '<strong style="display:block;">'.dol_escape_htmltag($pdf['name']).'</strong>';
            $html .= '<small class="opacitymedium">'.$pdf['size'].' - '.$pdf['date'].'</small>';
            $html .= '</div>';
            $html .= '</label>';
            $html .= '</div>';
        }
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
    $html .= '<button type="submit" class="button button-primary" id="docsig-submit-btn">';
    $html .= '<span class="fa fa-paper-plane"></span> '.$langs->trans('SendSignatureRequest');
    $html .= '</button>';
    $html .= '</div>';

    $html .= '</form>';
    $html .= '</div>'; // end docsig-new-envelope-section

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
