<?php
/* Copyright (C) 2026 DocSig Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/signDol/card.php
 * \ingroup docsig
 * \brief   Ficha de solicitud de firma
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

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
dol_include_once('/signDol/class/docsigenvelope.class.php');
dol_include_once('/signDol/class/docsigsigner.class.php');
dol_include_once('/signDol/lib/docsig.lib.php');

// Load translation files
$langs->loadLangs(array('docsig@signDol', 'other'));

// Security check
if (!isModEnabled('docsig')) {
    accessforbidden('Module not enabled');
}

// Get parameters
$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

// Initialize objects
$object = new DocSigEnvelope($db);
$form = new Form($db);
$formfile = new FormFile($db);

// Load object
if ($id > 0 || !empty($ref)) {
    $result = $object->fetch($id, $ref);
    if ($result <= 0) {
        setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
        header('Location: '.dol_buildpath('/signDol/list.php', 1));
        exit;
    }
}

// Security check
if (!$user->hasRight('docsig', 'envelope', 'read')) {
    accessforbidden();
}

/*
 * Actions
 */

// Cancel envelope
if ($action == 'confirm_cancel' && $confirm == 'yes' && $user->hasRight('docsig', 'envelope', 'delete')) {
    $reason = GETPOST('cancel_reason', 'restricthtml');
    $result = $object->cancel($user, $reason);
    if ($result > 0) {
        setEventMessages($langs->trans('EnvelopeCanceledSuccessfully'), null, 'mesgs');
    } else {
        setEventMessages($object->error, $object->errors, 'errors');
    }
    $action = '';
}

// Resend notification to signer
if ($action == 'resend' && $user->hasRight('docsig', 'envelope', 'write')) {
    $signerId = GETPOSTINT('signerid');
    if ($signerId > 0) {
        $signer = new DocSigSigner($db);
        if ($signer->fetch($signerId) > 0 && $signer->status == 0) {
            // Regenerar token
            $newToken = $signer->regenerateToken();

            // Enviar email
            dol_include_once('/signDol/class/docsignotification.class.php');
            $notificationService = new DocSigNotificationService($db);
            $signUrl = docsig_get_public_sign_url($newToken);
            $result = $notificationService->sendReminder($object, $signer, $signUrl);

            if ($result['success']) {
                $object->logEvent('REMINDER_SENT', 'Reminder sent to '.$signer->email);
                setEventMessages($langs->trans('ReminderSentSuccessfully'), null, 'mesgs');
            } else {
                setEventMessages($langs->trans('ErrorSendingReminder').': '.$result['error'], null, 'errors');
            }
        }
    }
    $action = '';
}

/*
 * View
 */

$title = $langs->trans('DocSigEnvelope').' - '.$object->ref;

llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-docsig page-card');

if ($object->id > 0) {
    // Confirmation dialogs
    if ($action == 'cancel') {
        $formquestion = array(
            array('type' => 'text', 'name' => 'cancel_reason', 'label' => $langs->trans('Reason'), 'value' => '', 'size' => 50),
        );
        print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('CancelEnvelope'), $langs->trans('ConfirmCancelEnvelope'), 'confirm_cancel', $formquestion, 'yes', 1);
    }

    // Title
    print dol_get_fiche_head(array(), 'card', $langs->trans('DocSigEnvelope'), -1, 'fa-file-signature');

    // Object banner
    $linkback = '<a href="'.dol_buildpath('/signDol/list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';

    dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', '', '', 0, '', '', 1);

    print '<div class="fichecenter">';
    print '<div class="fichehalfleft">';
    print '<div class="underbanner clearboth"></div>';

    print '<table class="border centpercent tableforfield">';

    // Element type
    print '<tr><td class="titlefield">'.$langs->trans('Type').'</td><td>';
    $elementLabels = array(
        'facture' => $langs->trans('Invoice'),
        'commande' => $langs->trans('Order'),
        'propal' => $langs->trans('Proposal'),
        'contrat' => $langs->trans('Contract'),
        'fichinter' => $langs->trans('Intervention'),
    );
    print $elementLabels[$object->element_type] ?? $object->element_type;
    print '</td></tr>';

    // Linked object
    print '<tr><td>'.$langs->trans('LinkedObject').'</td><td>';
    print docsig_get_object_link_card($object->element_type, $object->fk_object);
    print '</td></tr>';

    // Signature mode
    print '<tr><td>'.$langs->trans('SignatureMode').'</td><td>';
    print $object->signature_mode == 'parallel' ? $langs->trans('ParallelMode') : $langs->trans('SequentialMode');
    print '</td></tr>';

    // File path
    print '<tr><td>'.$langs->trans('Document').'</td><td>';
    if (file_exists($object->file_path)) {
        print '<a href="'.DOL_URL_ROOT.'/document.php?modulepart=docsig&file='.urlencode(basename($object->file_path)).'&entity='.$conf->entity.'" target="_blank">';
        print img_mime(basename($object->file_path)).' '.basename($object->file_path);
        print '</a>';
    } else {
        print '<span class="opacitymedium">'.$langs->trans('FileNotFound').'</span>';
    }
    print '</td></tr>';

    // File hash
    print '<tr><td>'.$langs->trans('FileHash').'</td><td>';
    print '<span class="small opacitymedium">'.dol_trunc($object->file_hash, 32).'</span>';
    print '</td></tr>';

    // Date creation
    print '<tr><td>'.$langs->trans('DateCreation').'</td><td>';
    print dol_print_date($object->date_creation, 'dayhour');
    print '</td></tr>';

    // Expire date
    print '<tr><td>'.$langs->trans('ExpireDate').'</td><td>';
    $isExpired = $object->expire_date < dol_now() && $object->status < DocSigEnvelope::STATUS_COMPLETED;
    print '<span'.($isExpired ? ' class="badge badge-danger"' : '').'>'.dol_print_date($object->expire_date, 'dayhour').'</span>';
    print '</td></tr>';

    // Status
    print '<tr><td>'.$langs->trans('Status').'</td><td>';
    print $object->getLibStatut(4);
    print '</td></tr>';

    // Created by
    print '<tr><td>'.$langs->trans('CreatedBy').'</td><td>';
    if ($object->fk_user_creat > 0) {
        $usertmp = new User($db);
        $usertmp->fetch($object->fk_user_creat);
        print $usertmp->getNomUrl(1);
    }
    print '</td></tr>';

    print '</table>';

    print '</div>'; // fichehalfleft

    print '<div class="fichehalfright">';

    // Signers
    print '<div class="underbanner clearboth"></div>';
    print '<table class="border centpercent tableforfield">';
    print '<tr class="liste_titre"><th colspan="5">'.$langs->trans('Signers').'</th></tr>';
    print '<tr class="liste_titre">';
    print '<th>'.$langs->trans('Order').'</th>';
    print '<th>'.$langs->trans('Name').'</th>';
    print '<th>'.$langs->trans('Email').'</th>';
    print '<th class="center">'.$langs->trans('Status').'</th>';
    print '<th class="right">'.$langs->trans('Actions').'</th>';
    print '</tr>';

    if (!empty($object->signers)) {
        foreach ($object->signers as $signer) {
            print '<tr class="oddeven">';
            print '<td class="center">'.$signer->sign_order.'</td>';
            print '<td>'.$signer->getFullName();
            if ($signer->fk_contact > 0) {
                require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
                $contact = new Contact($db);
                if ($contact->fetch($signer->fk_contact) > 0) {
                    print ' <span class="opacitymedium">('.$contact->getNomUrl(1).')</span>';
                }
            }
            print '</td>';
            print '<td><a href="mailto:'.$signer->email.'">'.$signer->email.'</a>';
            if ($signer->phone) {
                print '<br><span class="small opacitymedium">'.$signer->phone.'</span>';
            }
            print '</td>';
            print '<td class="center">'.$signer->getLibStatut(2).'</td>';
            print '<td class="right nowraponall">';
            if ($signer->status == 0 && $object->status < DocSigEnvelope::STATUS_COMPLETED && $user->hasRight('docsig', 'envelope', 'write')) {
                print '<a class="paddingleft paddingright" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=resend&signerid='.$signer->id.'&token='.newToken().'" title="'.$langs->trans('ResendNotification').'">';
                print img_picto($langs->trans('ResendNotification'), 'send');
                print '</a>';
                print '<a class="paddingleft paddingright docsig-copy-url-link" href="#" data-signerid="'.$signer->id.'" title="'.$langs->trans('CopySignUrl').'">';
                print img_picto($langs->trans('CopySignUrl'), 'copy');
                print '</a>';
            }
            if ($signer->date_signed) {
                print '<span class="small opacitymedium paddingleft">'.dol_print_date($signer->date_signed, 'dayhour').'</span>';
            }
            print '</td>';
            print '</tr>';

            // Show signature image if signed
            if ($signer->status == 1 && $signer->signature_image) {
                print '<tr class="oddeven">';
                print '<td></td>';
                print '<td colspan="4">';
                print '<div class="docsig-signature-preview">';
                print '<img src="'.$signer->signature_image.'" alt="Signature" style="max-width: 200px; max-height: 80px; border: 1px solid #ddd; padding: 5px;">';
                print '</div>';
                print '</td>';
                print '</tr>';
            }
        }
    } else {
        print '<tr><td colspan="5" class="opacitymedium">'.$langs->trans('NoSigners').'</td></tr>';
    }

    print '</table>';

    print '</div>'; // fichehalfright

    print '</div>'; // fichecenter

    print '<div class="clearboth"></div>';

    // Audit trail / Events
    print '<br>';
    print '<div class="div-table-responsive">';
    print '<table class="border centpercent tableforfield">';
    print '<tr class="liste_titre"><th colspan="5">'.$langs->trans('AuditTrail').'</th></tr>';
    print '<tr class="liste_titre">';
    print '<th>'.$langs->trans('Date').'</th>';
    print '<th>'.$langs->trans('Event').'</th>';
    print '<th>'.$langs->trans('Description').'</th>';
    print '<th>'.$langs->trans('IPAddress').'</th>';
    print '<th>'.$langs->trans('UserAgent').'</th>';
    print '</tr>';

    $events = $object->getEvents();
    if (!empty($events)) {
        foreach ($events as $event) {
            print '<tr class="oddeven">';
            print '<td class="nowraponall">'.dol_print_date($event['date'], 'dayhour').'</td>';
            print '<td><span class="badge badge-secondary">'.$event['type'].'</span></td>';
            print '<td>'.$event['description'].'</td>';
            print '<td class="small">'.$event['ip_address'].'</td>';
            print '<td class="small tdoverflowmax200" title="'.dol_escape_htmltag($event['user_agent']).'">'.$event['user_agent'].'</td>';
            print '</tr>';
        }
    } else {
        print '<tr><td colspan="5" class="opacitymedium">'.$langs->trans('NoEvents').'</td></tr>';
    }

    print '</table>';
    print '</div>';

    // Signed document
    if ($object->status == DocSigEnvelope::STATUS_COMPLETED && $object->signed_file_path) {
        print '<br>';
        print '<div class="div-table-responsive">';
        print '<table class="border centpercent tableforfield">';
        print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('SignedDocuments').'</th></tr>';

        // Signed PDF
        print '<tr class="oddeven">';
        print '<td>'.$langs->trans('SignedPDF').'</td>';
        print '<td>';
        if (file_exists($object->signed_file_path) && $user->hasRight('docsig', 'document', 'download')) {
            print '<a href="'.DOL_URL_ROOT.'/document.php?modulepart=docsig&file='.urlencode(basename($object->signed_file_path)).'&entity='.$conf->entity.'" target="_blank">';
            print img_mime(basename($object->signed_file_path)).' '.basename($object->signed_file_path);
            print '</a>';
        } else {
            print '<span class="opacitymedium">'.$langs->trans('NotAvailable').'</span>';
        }
        print '</td></tr>';

        // Compliance certificate
        if ($object->compliance_cert_path) {
            print '<tr class="oddeven">';
            print '<td>'.$langs->trans('ComplianceCertificate').'</td>';
            print '<td>';
            if (file_exists($object->compliance_cert_path) && $user->hasRight('docsig', 'document', 'download')) {
                print '<a href="'.DOL_URL_ROOT.'/document.php?modulepart=docsig&file='.urlencode(basename($object->compliance_cert_path)).'&entity='.$conf->entity.'" target="_blank">';
                print img_mime(basename($object->compliance_cert_path)).' '.basename($object->compliance_cert_path);
                print '</a>';
            } else {
                print '<span class="opacitymedium">'.$langs->trans('NotAvailable').'</span>';
            }
            print '</td></tr>';
        }

        print '</table>';
        print '</div>';
    }

    print dol_get_fiche_end();

    // Actions buttons
    print '<div class="tabsAction">';

    if ($object->status < DocSigEnvelope::STATUS_COMPLETED && $user->hasRight('docsig', 'envelope', 'delete')) {
        print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=cancel&token='.newToken().'">'.$langs->trans('CancelEnvelope').'</a>';
    }

    print '</div>';

    // JavaScript for copy URL
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.docsig-copy-url-link').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                var signerId = this.dataset.signerid;
                
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '<?php echo dol_buildpath('/signDol/ajax/get_sign_url.php', 1); ?>', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                if (navigator.clipboard && navigator.clipboard.writeText) {
                                    navigator.clipboard.writeText(response.url).then(function() {
                                        alert('<?php echo $langs->trans('UrlCopiedToClipboard'); ?>:\n\n' + response.url);
                                    }).catch(function() {
                                        prompt('<?php echo $langs->trans('CopySignUrl'); ?>:', response.url);
                                    });
                                } else {
                                    prompt('<?php echo $langs->trans('CopySignUrl'); ?>:', response.url);
                                }
                            } else {
                                alert('Error: ' + response.error);
                            }
                        } catch(e) {
                            alert('Error al procesar la respuesta');
                        }
                    }
                };

                xhr.send('signer_id=' + encodeURIComponent(signerId) + 
                         '&regenerate=1' +
                         '&token=<?php echo newToken(); ?>');
            });
        });
    });
    </script>
    <?php

} else {
    // No object found
    print '<div class="error">'.$langs->trans('ErrorRecordNotFound').'</div>';
}

// End of page
llxFooter();
$db->close();

/**
 * Obtiene el enlace al objeto vinculado
 */
function docsig_get_object_link_card($element, $objectId)
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
