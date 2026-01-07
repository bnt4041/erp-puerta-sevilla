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

/**
 * Helper function to get download URL for a file
 * Converts absolute path to proper document.php URL with correct modulepart
 *
 * @param string $filepath Full absolute path to file
 * @param int $entity Entity ID
 * @return string HTML link or empty if file not found
 */
function getDocSigFileDownloadUrl($filepath, $entity = 1) {
    global $conf;
    
    if (empty($filepath) || !file_exists($filepath)) {
        return '';
    }
    
    // Get DOL_DATA_ROOT (documents directory)
    $dataRoot = DOL_DATA_ROOT;
    
    // Extract relative path from DOL_DATA_ROOT
    if (strpos($filepath, $dataRoot) === 0) {
        // Remove DOL_DATA_ROOT prefix and leading slash
        $relativePath = substr($filepath, strlen($dataRoot));
        $relativePath = ltrim($relativePath, '/');
        
        // Extract modulepart from path (first directory)
        $parts = explode('/', $relativePath);
        if (count($parts) >= 2) {
            $modulepart = $parts[0]; // e.g., 'contract', 'facture', etc.
            $file = implode('/', array_slice($parts, 1)); // rest of the path
            
            return DOL_URL_ROOT.'/document.php?modulepart='.urlencode($modulepart).'&file='.urlencode($file).'&entity='.$entity;
        }
    }
    
    // Fallback: use original basename method (won't work but prevents error)
    return DOL_URL_ROOT.'/document.php?modulepart=docsig&file='.urlencode(basename($filepath)).'&entity='.$entity;
}

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
$formcompany = new FormCompany($db);

// Load object (only if not creating new)
if (($id > 0 || !empty($ref)) && $action != 'create') {
    $result = $object->fetch($id, $ref);
    if ($result <= 0) {
        setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
        header('Location: '.dol_buildpath('/signDol/list.php', 1));
        exit;
    }
}

// Security check - allow create without read permission for envelope
if ($action != 'create' && $action != 'add' && !$user->hasRight('docsig', 'envelope', 'read')) {
    accessforbidden();
}
if (($action == 'create' || $action == 'add') && !$user->hasRight('docsig', 'envelope', 'write')) {
    accessforbidden();
}

/*
 * Actions
 */

// Action to add new envelope with uploaded PDF
if ($action == 'add' && $user->hasRight('docsig', 'envelope', 'write')) {
    $error = 0;
    
    // Get form data
    $label = GETPOST('label', 'alphanohtml');
    $expire_days = GETPOSTINT('expire_days') ?: 30;
    $signers_json = GETPOST('signers_json', 'restricthtml');
    
    // Parse signers JSON
    $signers_data = [];
    if (!empty($signers_json)) {
        $signers_data = json_decode($signers_json, true);
        if (!is_array($signers_data)) {
            $signers_data = [];
        }
    }
    
    // Validate
    if (empty($_FILES['pdf_file']['name'])) {
        setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('File')), null, 'errors');
        $error++;
    }
    if (empty($signers_data)) {
        setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('Signers')), null, 'errors');
        $error++;
    }
    
    // Validate each signer has email
    foreach ($signers_data as $idx => $sdata) {
        if (empty($sdata['email'])) {
            setEventMessages($langs->trans('ErrorSignerNeedsEmail', $sdata['name'] ?? ($idx + 1)), null, 'errors');
            $error++;
        }
    }
    
    if (!$error) {
        // Handle file upload
        $upload_dir = DOL_DATA_ROOT.'/docsig/standalone';
        if (!is_dir($upload_dir)) {
            dol_mkdir($upload_dir);
        }
        
        $uploadedFile = $_FILES['pdf_file'];
        $originalName = dol_sanitizeFileName($uploadedFile['name']);
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        
        if (strtolower($extension) != 'pdf') {
            setEventMessages($langs->trans('ErrorOnlyPDFAllowed'), null, 'errors');
            $error++;
        } else {
            // Generate unique filename
            $newFilename = date('YmdHis').'_'.uniqid().'_'.$originalName;
            $destPath = $upload_dir.'/'.$newFilename;
            
            if (move_uploaded_file($uploadedFile['tmp_name'], $destPath)) {
                // Create envelope
                $object->label = $label ?: pathinfo($originalName, PATHINFO_FILENAME);
                $object->element = 'standalone';
                $object->element_id = 0;
                $object->status = DocSigEnvelope::STATUS_PENDING;
                $object->expire_date = dol_time_plus_duree(dol_now(), $expire_days, 'd');
                $object->fk_user_creat = $user->id;
                
                $result = $object->create($user);
                
                if ($result > 0) {
                    // Add document to envelope
                    $docResult = $object->addDocument($destPath, $user, $label ?: $originalName);
                    if ($docResult < 0) {
                        $error++;
                        setEventMessages($object->error, null, 'errors');
                    }
                    
                    // Add signers from JSON data
                    $signOrder = 1;
                    require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
                    require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
                    
                    foreach ($signers_data as $sdata) {
                        $signer = new DocSigSigner($db);
                        $signer->fk_envelope = $object->id;
                        $signer->sign_order = $signOrder++;
                        $signer->status = DocSigSigner::STATUS_PENDING;
                        $signer->token = bin2hex(random_bytes(32));
                        
                        // Set email and phone from form (may be modified by user)
                        $signer->email = trim($sdata['email']);
                        $signer->phone = trim($sdata['phone'] ?? '');
                        
                        if ($sdata['type'] == 'contact' && !empty($sdata['id'])) {
                            // Existing contact
                            $contact = new Contact($db);
                            if ($contact->fetch($sdata['id']) > 0) {
                                $signer->firstname = $contact->firstname;
                                $signer->lastname = $contact->lastname;
                                $signer->fk_contact = $contact->id;
                                $signer->fk_soc = $contact->socid;
                                
                                // Update contact email/phone if modified
                                if ($signer->email != $contact->email || $signer->phone != ($contact->phone_mobile ?: $contact->phone_pro)) {
                                    // Store original in signer, don't update contact
                                }
                            }
                        } elseif ($sdata['type'] == 'thirdparty' && !empty($sdata['id'])) {
                            // Existing third party
                            $thirdparty = new Societe($db);
                            if ($thirdparty->fetch($sdata['id']) > 0) {
                                $signer->firstname = '';
                                $signer->lastname = $thirdparty->name;
                                $signer->fk_soc = $thirdparty->id;
                            }
                        } else {
                            // New manual signer
                            $signer->firstname = trim($sdata['firstname'] ?? '');
                            $signer->lastname = trim($sdata['lastname'] ?? $sdata['name'] ?? '');
                            $signer->fk_contact = 0;
                            $signer->fk_soc = 0;
                            
                            // Create contact if requested
                            if (!empty($sdata['saveContact'])) {
                                $newContact = new Contact($db);
                                $newContact->firstname = $signer->firstname;
                                $newContact->lastname = $signer->lastname;
                                $newContact->email = $signer->email;
                                $newContact->phone_mobile = $signer->phone;
                                $newContact->statut = 1;
                                
                                $contactResult = $newContact->create($user);
                                if ($contactResult > 0) {
                                    $signer->fk_contact = $newContact->id;
                                }
                            }
                        }
                        
                        $signerResult = $signer->create($user);
                        if ($signerResult < 0) {
                            $error++;
                            setEventMessages($signer->error, null, 'errors');
                        }
                    }
                    
                    if (!$error) {
                        // Send notifications
                        dol_include_once('/signDol/class/docsignotification.class.php');
                        $notificationService = new DocSigNotificationService($db);
                        
                        // Reload envelope with signers
                        $object->fetch($object->id);
                        
                        foreach ($object->signers as $s) {
                            $signUrl = docsig_get_public_sign_url($s->token);
                            $notificationService->sendSignatureRequest($object, $s, $signUrl);
                        }
                        
                        $object->logEvent('ENVELOPE_CREATED', 'Standalone envelope created with '.count($object->signers).' signers');
                        
                        setEventMessages($langs->trans('EnvelopeCreatedSuccessfully'), null, 'mesgs');
                        header('Location: '.dol_buildpath('/signDol/card.php', 1).'?id='.$object->id);
                        exit;
                    }
                } else {
                    setEventMessages($object->error, $object->errors, 'errors');
                    $error++;
                    // Clean up uploaded file
                    @unlink($destPath);
                }
            } else {
                setEventMessages($langs->trans('ErrorFileUploadFailed'), null, 'errors');
                $error++;
            }
        }
    }
    
    if ($error) {
        $action = 'create';
    }
}

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

            // Enviar notificación
            dol_include_once('/signDol/class/docsignotification.class.php');
            $notificationService = new DocSigNotificationService($db);
            $signUrl = docsig_get_public_sign_url($newToken);
            $result = $notificationService->sendReminder($object, $signer, $signUrl);

            if ($result > 0) {
                $object->logEvent('REMINDER_SENT', 'Reminder sent to '.$signer->email);
                setEventMessages($langs->trans('ReminderSentSuccessfully'), null, 'mesgs');
            } else {
                setEventMessages($langs->trans('ErrorSendingReminder').': '.$notificationService->error, null, 'errors');
            }
        }
    }
    $action = '';
}

// Regenerate signed PDF (for testing stamps)
if ($action == 'regenerate_signed_pdf' && $user->hasRight('docsig', 'envelope', 'write')) {
    // Check if there are signed signers
    $hasSignedSigners = false;
    foreach ($object->signers as $signer) {
        if ($signer->status == DocSigSigner::STATUS_SIGNED) {
            $hasSignedSigners = true;
            break;
        }
    }
    
    if ($hasSignedSigners) {
        // Force regenerate signed PDF
        $result = $object->generateSignedPdf();
        if ($result > 0) {
            // Update database with new signed path
            $sql = "UPDATE ".MAIN_DB_PREFIX."docsig_envelope SET signed_file_path = '".$db->escape($object->signed_file_path)."' WHERE rowid = ".(int)$object->id;
            $db->query($sql);
            
            setEventMessages($langs->trans('SignedPDFRegeneratedSuccessfully'), null, 'mesgs');
            $object->logEvent('PDF_REGENERATED', 'Signed PDF regenerated manually: '.$object->signed_file_path);
        } else {
            setEventMessages($langs->trans('ErrorRegeneratingSignedPDF').': '.$object->error, null, 'errors');
        }
    } else {
        setEventMessages($langs->trans('ErrorNoSignedSigners'), null, 'errors');
    }
    $action = '';
}

/*
 * View
 */

if ($action == 'create') {
    $title = $langs->trans('CreateEnvelope');
} else {
    $title = $langs->trans('DocSigEnvelope').' - '.$object->ref;
}

llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-docsig page-card');

// Form for creating a new standalone envelope
if ($action == 'create') {
    print load_fiche_titre($langs->trans('CreateEnvelope'), '', 'fa-file-signature');
    
    print '<form name="createenvelope" action="'.$_SERVER["PHP_SELF"].'" method="POST" enctype="multipart/form-data">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="add">';
    
    print dol_get_fiche_head(array(), 'card', '', -1, '');
    
    print '<table class="border centpercent tableforfield">';
    
    // Label
    print '<tr><td class="fieldrequired titlefieldcreate">'.$langs->trans('Label').'</td>';
    print '<td><input type="text" name="label" size="60" maxlength="255" value="'.GETPOST('label', 'alphanohtml').'"></td></tr>';
    
    // PDF File to sign
    print '<tr><td class="fieldrequired">'.$langs->trans('PDFFileToSign').'</td>';
    print '<td><input type="file" name="pdf_file" accept=".pdf" required></td></tr>';
    
    // Expiration days
    print '<tr><td>'.$langs->trans('ExpirationDays').'</td>';
    print '<td><input type="number" name="expire_days" value="30" min="1" max="365"> '.$langs->trans('Days').'</td></tr>';
    
    // Enhanced signer selection section
    print '<tr><td class="fieldrequired tdtop">'.$langs->trans('Signers').'</td>';
    print '<td>';
    
    print '<div class="docsig-create-signers-section">';
    
    // Search box
    print '<div class="docsig-search-box">';
    print '<div class="docsig-search-input-wrapper">';
    print '<span class="fa fa-search"></span>';
    print '<input type="text" id="docsig-signer-search" class="flat minwidth300" placeholder="'.$langs->trans('SearchContactOrThirdParty').'" autocomplete="off">';
    print '</div>';
    print '<div id="docsig-search-dropdown" class="docsig-search-dropdown" style="display:none;"></div>';
    print '</div>';
    
    // Add new contact link
    print '<div class="docsig-create-contact-toggle">';
    print '<a href="#" id="docsig-toggle-create-contact"><span class="fa fa-plus-circle"></span> '.$langs->trans('CreateNewContact').'</a>';
    print '</div>';
    
    // Create new contact form (hidden by default)
    print '<div id="docsig-create-contact-form" class="docsig-create-contact-form" style="display:none;">';
    print '<div class="docsig-form-header">';
    print '<span class="fa fa-user-plus"></span> '.$langs->trans('NewSigner');
    print '<a href="#" class="docsig-close-form" id="docsig-close-create-contact"><span class="fa fa-times"></span></a>';
    print '</div>';
    print '<div class="docsig-form-body">';
    print '<div class="docsig-form-row">';
    print '<input type="text" id="new-signer-firstname" class="flat" placeholder="'.$langs->trans('FirstName').'">';
    print '<input type="text" id="new-signer-lastname" class="flat" placeholder="'.$langs->trans('LastName').' *">';
    print '</div>';
    print '<div class="docsig-form-row">';
    print '<input type="email" id="new-signer-email" class="flat" placeholder="'.$langs->trans('Email').' *">';
    print '<input type="text" id="new-signer-phone" class="flat" placeholder="'.$langs->trans('Phone').'">';
    print '</div>';
    print '<div class="docsig-form-row">';
    print '<input type="text" id="new-signer-dni" class="flat" placeholder="'.$langs->trans('DNI').'">';
    print '<select id="new-signer-save" class="flat">';
    print '<option value="0">'.$langs->trans('DoNotSaveContact').'</option>';
    print '<option value="1">'.$langs->trans('SaveAsNewContact').'</option>';
    print '</select>';
    print '</div>';
    print '<div class="docsig-form-actions">';
    print '<button type="button" class="button" id="docsig-add-manual-signer"><span class="fa fa-plus"></span> '.$langs->trans('AddSigner').'</button>';
    print '</div>';
    print '</div>';
    print '</div>';
    
    // Selected signers list
    print '<div class="docsig-selected-signers-header" id="docsig-signers-header" style="display:none;">';
    print '<span class="fa fa-users"></span> '.$langs->trans('SelectedSigners').' (<span id="docsig-signers-count">0</span>)';
    print '</div>';
    print '<div id="docsig-selected-signers" class="docsig-selected-signers"></div>';
    
    // Hidden input to store signers JSON
    print '<input type="hidden" name="signers_json" id="signers_json" value="">';
    
    print '</div>'; // end docsig-create-signers-section
    
    print '</td></tr>';
    
    // Note
    print '<tr><td class="tdtop">'.$langs->trans('Note').'</td>';
    print '<td><textarea name="note" rows="3" cols="60">'.GETPOST('note', 'restricthtml').'</textarea></td></tr>';
    
    print '</table>';
    
    print dol_get_fiche_end();
    
    print '<div class="center">';
    print '<input type="submit" class="button button-save" name="save" value="'.$langs->trans('CreateAndSend').'">';
    print ' &nbsp; ';
    print '<input type="button" class="button button-cancel" value="'.$langs->trans('Cancel').'" onclick="history.back();">';
    print '</div>';
    
    print '</form>';
    
    // JavaScript for enhanced signer selection
    print '<script type="text/javascript">
    document.addEventListener("DOMContentLoaded", function() {
        var signers = [];
        var searchTimeout = null;
        
        var searchInput = document.getElementById("docsig-signer-search");
        var searchDropdown = document.getElementById("docsig-search-dropdown");
        var signersContainer = document.getElementById("docsig-selected-signers");
        var signersHeader = document.getElementById("docsig-signers-header");
        var signersCountEl = document.getElementById("docsig-signers-count");
        var signersJsonInput = document.getElementById("signers_json");
        
        var createContactForm = document.getElementById("docsig-create-contact-form");
        var toggleCreateLink = document.getElementById("docsig-toggle-create-contact");
        var closeCreateBtn = document.getElementById("docsig-close-create-contact");
        var addManualBtn = document.getElementById("docsig-add-manual-signer");
        
        // Toggle create contact form
        toggleCreateLink.addEventListener("click", function(e) {
            e.preventDefault();
            createContactForm.style.display = createContactForm.style.display === "none" ? "block" : "none";
        });
        
        closeCreateBtn.addEventListener("click", function(e) {
            e.preventDefault();
            createContactForm.style.display = "none";
            clearNewSignerForm();
        });
        
        // Search contacts/thirdparties
        searchInput.addEventListener("input", function() {
            var query = this.value.trim();
            clearTimeout(searchTimeout);
            
            if (query.length < 2) {
                searchDropdown.style.display = "none";
                return;
            }
            
            searchTimeout = setTimeout(function() {
                doSearch(query);
            }, 300);
        });
        
        searchInput.addEventListener("focus", function() {
            if (this.value.trim().length >= 2) {
                searchDropdown.style.display = "block";
            }
        });
        
        document.addEventListener("click", function(e) {
            if (!e.target.closest(".docsig-search-box")) {
                searchDropdown.style.display = "none";
            }
        });
        
        function doSearch(query) {
            var url = "'.dol_buildpath('/signDol/ajax/search_signers.php', 1).'?q=" + encodeURIComponent(query);
            
            fetch(url)
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    renderSearchResults(data);
                })
                .catch(function(error) {
                    console.error("Search error:", error);
                });
        }
        
        function renderSearchResults(data) {
            var html = "";
            
            // Thirdparties
            if (data.thirdparties && data.thirdparties.length > 0) {
                html += \'<div class="docsig-search-group"><div class="docsig-search-group-header"><span class="fa fa-building"></span> '.$langs->trans('ThirdParties').'</div>\';
                data.thirdparties.forEach(function(tp) {
                    html += renderSearchItem(tp, "thirdparty");
                });
                html += "</div>";
            }
            
            // Contacts
            if (data.contacts && data.contacts.length > 0) {
                html += \'<div class="docsig-search-group"><div class="docsig-search-group-header"><span class="fa fa-user"></span> '.$langs->trans('Contacts').'</div>\';
                data.contacts.forEach(function(c) {
                    html += renderSearchItem(c, "contact");
                });
                html += "</div>";
            }
            
            if (!html) {
                html = \'<div class="docsig-search-no-results"><span class="fa fa-info-circle"></span> '.$langs->trans('NoResultsFound').'</div>\';
            }
            
            searchDropdown.innerHTML = html;
            searchDropdown.style.display = "block";
            
            // Add click handlers
            searchDropdown.querySelectorAll(".docsig-search-item").forEach(function(item) {
                item.addEventListener("click", function() {
                    var signerData = JSON.parse(this.dataset.signer);
                    addSigner(signerData);
                    searchDropdown.style.display = "none";
                    searchInput.value = "";
                });
            });
        }
        
        function renderSearchItem(item, type) {
            var alreadyAdded = signers.some(function(s) {
                return s.type === type && s.id === item.id;
            });
            
            var signerData = JSON.stringify({
                id: item.id,
                type: type,
                name: item.name,
                email: item.email || "",
                phone: item.phone || "",
                company: item.company || "",
                dni: item.dni || ""
            });
            
            var disabledClass = alreadyAdded ? " docsig-search-item-disabled" : "";
            var icon = type === "thirdparty" ? "fa-building" : "fa-user";
            
            var html = \'<div class="docsig-search-item\' + disabledClass + \'" data-signer="\' + signerData.replace(/"/g, "&quot;") + \'">\';
            html += \'<div class="docsig-search-item-main">\';
            html += \'<span class="fa \' + icon + \'"></span>\';
            html += \'<span class="docsig-search-item-name">\' + escapeHtml(item.name) + \'</span>\';
            if (item.company) {
                html += \'<span class="docsig-search-item-company">(\' + escapeHtml(item.company) + \')</span>\';
            }
            html += \'</div>\';
            html += \'<div class="docsig-search-item-details">\';
            if (item.email) {
                html += \'<span class="docsig-search-item-email"><span class="fa fa-envelope"></span> \' + escapeHtml(item.email) + \'</span>\';
            } else {
                html += \'<span class="docsig-search-item-no-email"><span class="fa fa-exclamation-triangle"></span> '.$langs->trans('NoEmailAddress').'</span>\';
            }
            if (item.phone) {
                html += \'<span class="docsig-search-item-phone"><span class="fa fa-phone"></span> \' + escapeHtml(item.phone) + \'</span>\';
            }
            html += \'</div>\';
            if (alreadyAdded) {
                html += \'<span class="docsig-search-item-added"><span class="fa fa-check"></span> '.$langs->trans('AlreadyAdded').'</span>\';
            }
            html += \'</div>\';
            
            return html;
        }
        
        function addSigner(data) {
            // Check if already exists
            var exists = signers.some(function(s) {
                return s.type === data.type && s.id === data.id;
            });
            if (exists) return;
            
            // Generate unique ID for new contacts
            if (!data.id) {
                data.id = "new_" + Date.now();
                data.type = "new";
            }
            
            signers.push(data);
            updateSignersUI();
        }
        
        function removeSigner(index) {
            signers.splice(index, 1);
            updateSignersUI();
        }
        
        function updateSignerField(index, field, value) {
            if (signers[index]) {
                signers[index][field] = value;
                updateSignersJson();
            }
        }
        
        function updateSignersUI() {
            signersHeader.style.display = signers.length > 0 ? "block" : "none";
            signersCountEl.textContent = signers.length;
            
            var html = "";
            signers.forEach(function(signer, index) {
                html += renderSignerCard(signer, index);
            });
            
            signersContainer.innerHTML = html;
            updateSignersJson();
            
            // Add event handlers
            signersContainer.querySelectorAll(".docsig-signer-remove").forEach(function(btn) {
                btn.addEventListener("click", function(e) {
                    e.preventDefault();
                    var idx = parseInt(this.dataset.index);
                    removeSigner(idx);
                });
            });
            
            signersContainer.querySelectorAll(".docsig-signer-input").forEach(function(input) {
                input.addEventListener("change", function() {
                    var idx = parseInt(this.dataset.index);
                    var field = this.dataset.field;
                    updateSignerField(idx, field, this.value);
                });
            });
        }
        
        function renderSignerCard(signer, index) {
            var iconClass = signer.type === "thirdparty" ? "fa-building" : (signer.type === "new" ? "fa-user-plus" : "fa-user");
            var badgeClass = signer.type === "thirdparty" ? "badge-primary" : (signer.type === "new" ? "badge-info" : "badge-secondary");
            var typeLabel = signer.type === "thirdparty" ? "'.$langs->trans('ThirdParty').'" : (signer.type === "new" ? "'.$langs->trans('New').'" : "'.$langs->trans('Contact').'");
            
            var html = \'<div class="docsig-signer-card">\';
            html += \'<div class="docsig-signer-card-header">\';
            html += \'<div class="docsig-signer-card-title">\';
            html += \'<span class="fa \' + iconClass + \'"></span>\';
            html += \'<span class="docsig-signer-name">\' + escapeHtml(signer.name) + \'</span>\';
            html += \'<span class="badge \' + badgeClass + \'">\' + typeLabel + \'</span>\';
            html += \'</div>\';
            html += \'<a href="#" class="docsig-signer-remove" data-index="\' + index + \'" title="'.$langs->trans('Remove').'"><span class="fa fa-times"></span></a>\';
            html += \'</div>\';
            html += \'<div class="docsig-signer-card-body">\';
            html += \'<div class="docsig-signer-field">\';
            html += \'<label><span class="fa fa-envelope"></span> '.$langs->trans('Email').'</label>\';
            html += \'<input type="email" class="flat docsig-signer-input" data-index="\' + index + \'" data-field="email" value="\' + escapeHtml(signer.email) + \'" placeholder="email@ejemplo.com">\';
            html += \'</div>\';
            html += \'<div class="docsig-signer-field">\';
            html += \'<label><span class="fa fa-phone"></span> '.$langs->trans('Phone').'</label>\';
            html += \'<input type="text" class="flat docsig-signer-input" data-index="\' + index + \'" data-field="phone" value="\' + escapeHtml(signer.phone || "") + \'" placeholder="612345678">\';
            html += \'</div>\';
            html += \'</div>\';
            html += \'</div>\';
            
            return html;
        }
        
        function updateSignersJson() {
            signersJsonInput.value = JSON.stringify(signers);
        }
        
        function clearNewSignerForm() {
            document.getElementById("new-signer-firstname").value = "";
            document.getElementById("new-signer-lastname").value = "";
            document.getElementById("new-signer-email").value = "";
            document.getElementById("new-signer-phone").value = "";
            document.getElementById("new-signer-dni").value = "";
            document.getElementById("new-signer-save").value = "0";
        }
        
        // Add manual signer
        addManualBtn.addEventListener("click", function() {
            var firstname = document.getElementById("new-signer-firstname").value.trim();
            var lastname = document.getElementById("new-signer-lastname").value.trim();
            var email = document.getElementById("new-signer-email").value.trim();
            var phone = document.getElementById("new-signer-phone").value.trim();
            var dni = document.getElementById("new-signer-dni").value.trim();
            var saveContact = document.getElementById("new-signer-save").value;
            
            if (!lastname) {
                alert("'.$langs->trans('ErrorFieldRequired', $langs->trans('LastName')).'");
                return;
            }
            if (!email) {
                alert("'.$langs->trans('ErrorFieldRequired', $langs->trans('Email')).'");
                return;
            }
            
            var name = firstname ? firstname + " " + lastname : lastname;
            
            addSigner({
                id: null,
                type: "new",
                name: name,
                firstname: firstname,
                lastname: lastname,
                email: email,
                phone: phone,
                dni: dni,
                saveContact: saveContact === "1"
            });
            
            clearNewSignerForm();
            createContactForm.style.display = "none";
        });
        
        function escapeHtml(text) {
            if (!text) return "";
            var div = document.createElement("div");
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        }
    });
    </script>';

} elseif ($object->id > 0) {
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

    // Documents - check if we have multiple documents
    $object->fetchDocuments();
    $documentCount = count($object->documents);
    
    if ($documentCount > 1) {
        // Multiple documents - show list
        print '<tr><td>'.$langs->trans('DocumentsInEnvelope').'</td><td>';
        print '<span class="badge badge-info">'.$documentCount.' '.$langs->trans('Documents').'</span>';
        print '</td></tr>';
    } else {
        // Single document or legacy mode - show original file_path
        print '<tr><td>'.$langs->trans('Document').'</td><td>';
        $downloadUrl = getDocSigFileDownloadUrl($object->file_path, $conf->entity);
        if ($downloadUrl) {
            print '<a href="'.$downloadUrl.'" target="_blank">';
            print img_mime(basename($object->file_path)).' '.basename($object->file_path);
            print '</a>';
        } else {
            print '<span class="opacitymedium">'.$langs->trans('FileNotFound').'</span>';
        }
        print '</td></tr>';
    }

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
            
            // Show copy URL button (always for pending)
            print '<a class="paddingleft paddingright docsig-copy-url-link" href="#" data-signerid="'.$signer->id.'" title="'.$langs->trans('CopySignUrl').'" style="margin-right:5px;">';
            print '<i class="fas fa-copy" style="font-size:16px; color:#0066cc;"></i>';
            print '</a>';
            
            // Show resend button if user has write permissions
            if ($user->hasRight('docsig', 'envelope', 'write')) {
                print '<a class="paddingleft paddingright docsig-resend-link" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=resend&signerid='.$signer->id.'&token='.newToken().'" title="'.$langs->trans('ResendNotification').'" style="margin-right:5px;">';
                print '<i class="fas fa-envelope" style="font-size:16px; color:#28a745;"></i>';
                print '</a>';
            }
            
            // Signed date
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

    // Documents list (for envelopes with multiple documents)
    if ($documentCount > 0) {
        print '<br>';
        print '<div class="div-table-responsive">';
        print '<table class="border centpercent tableforfield">';
        print '<tr class="liste_titre">';
        print '<th>'.$langs->trans('Document').'</th>';
        print '<th>'.$langs->trans('Status').'</th>';
        print '<th>'.$langs->trans('DownloadOriginal').'</th>';
        if ($object->status == DocSigEnvelope::STATUS_COMPLETED) {
            print '<th>'.$langs->trans('DownloadSigned').'</th>';
        }
        print '</tr>';

        foreach ($object->documents as $doc) {
            print '<tr class="oddeven">';
            print '<td>'.img_mime($doc->original_filename).' '.dol_escape_htmltag($doc->label ?: $doc->original_filename).'</td>';
            print '<td>'.$doc->getLibStatut(2).'</td>';
            
            // Original file
            print '<td>';
            $docDownloadUrl = getDocSigFileDownloadUrl($doc->file_path, $conf->entity);
            if ($docDownloadUrl && $user->hasRight('docsig', 'document', 'download')) {
                print '<a href="'.$docDownloadUrl.'" target="_blank">';
                print '<span class="fa fa-download"></span> '.basename($doc->original_filename);
                print '</a>';
            } else {
                print '<span class="opacitymedium">'.$langs->trans('NotAvailable').'</span>';
            }
            print '</td>';
            
            // Signed file
            if ($object->status == DocSigEnvelope::STATUS_COMPLETED) {
                print '<td>';
                $signedDownloadUrl = getDocSigFileDownloadUrl($doc->signed_file_path, $conf->entity);
                if ($signedDownloadUrl && $user->hasRight('docsig', 'document', 'download')) {
                    print '<a href="'.$signedDownloadUrl.'" target="_blank" class="butAction small">';
                    print '<span class="fa fa-file-signature"></span> '.$langs->trans('DownloadSigned');
                    print '</a>';
                } else {
                    print '<span class="opacitymedium">'.$langs->trans('NotAvailable').'</span>';
                }
                print '</td>';
            }
            print '</tr>';
        }

        print '</table>';
        print '</div>';
    }

    // Signed document (legacy mode - single document)
    if ($object->status == DocSigEnvelope::STATUS_COMPLETED && $object->signed_file_path && $documentCount <= 1) {
        print '<br>';
        print '<div class="div-table-responsive">';
        print '<table class="border centpercent tableforfield">';
        print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('SignedDocuments').'</th></tr>';

        // Signed PDF
        print '<tr class="oddeven">';
        print '<td>'.$langs->trans('SignedPDF').'</td>';
        print '<td>';
        $signedPdfUrl = getDocSigFileDownloadUrl($object->signed_file_path, $conf->entity);
        if ($signedPdfUrl && $user->hasRight('docsig', 'document', 'download')) {
            print '<a href="'.$signedPdfUrl.'" target="_blank">';
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
            $certUrl = getDocSigFileDownloadUrl($object->compliance_cert_path, $conf->entity);
            if ($certUrl && $user->hasRight('docsig', 'document', 'download')) {
                print '<a href="'.$certUrl.'" target="_blank">';
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

    // Regenerate signed PDF button (for testing/debugging)
    $hasSignedSigners = false;
    foreach ($object->signers as $signer) {
        if ($signer->status == DocSigSigner::STATUS_SIGNED) {
            $hasSignedSigners = true;
            break;
        }
    }
    if ($hasSignedSigners && $user->hasRight('docsig', 'envelope', 'write')) {
        print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=regenerate_signed_pdf&token='.newToken().'">';
        print '<span class="fa fa-refresh"></span> '.$langs->trans('RegenerateSignedPDF');
        print '</a>';
    }

    if ($object->status < DocSigEnvelope::STATUS_COMPLETED && $user->hasRight('docsig', 'envelope', 'delete')) {
        print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=cancel&token='.newToken().'">'.$langs->trans('CancelEnvelope').'</a>';
    }

    print '</div>';

    // JavaScript for copy URL
    ?>
    <style>
    .docsig-copy-url-link,
    .docsig-copy-url-link img,
    .docsig-resend-link,
    .docsig-resend-link img {
        display: inline-block !important;
        padding: 2px 4px !important;
        margin: 0 2px !important;
        vertical-align: middle !important;
    }
    
    .docsig-copy-url-link img,
    .docsig-resend-link img {
        width: 16px !important;
        height: 16px !important;
        min-width: 16px !important;
        min-height: 16px !important;
    }
    
    td.right.nowraponall {
        white-space: nowrap !important;
        overflow: visible !important;
    }
    </style>
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
