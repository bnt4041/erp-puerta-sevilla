<?php
/* Copyright (C) 2025 ZonaJob Dev
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    zonajob/order_card.php
 * \ingroup zonajob
 * \brief   Order card for employee zone - Responsive with signature, photos, sending
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../main.inc.php")) {
    $res = @include "../main.inc.php";
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

// Security check
if (empty($user) || !$user->id) {
    header("Location: ".DOL_URL_ROOT."/");
    exit;
}

require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/order.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/modules/commande/modules_commande.php';
require_once DOL_DOCUMENT_ROOT.'/custom/zonaempleado/lib/zonaempleado.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/zonajob/class/zonajobsignature.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/zonajob/class/zonajobphoto.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/zonajob/class/zonajobsender.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
if (isModEnabled('notification')) {
    require_once DOL_DOCUMENT_ROOT.'/core/class/notify.class.php';
}

// Load translations
$langs->loadLangs(array("orders", "companies", "products", "bills", "sendings", "zonajob@zonajob", "zonaempleado@zonaempleado"));

// Get parameters
$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$tab = GETPOST('tab', 'alpha');
$confirm = GETPOST('confirm', 'alpha');

if (empty($tab)) $tab = 'info';

// Check permission
if (empty($user->rights->zonajob->order->read) && empty($user->rights->commande->lire)) {
    accessforbidden();
}

// Load order
$order = new Commande($db);
if ($id > 0 || !empty($ref)) {
    $result = $order->fetch($id, $ref);
    if ($result <= 0) {
        setEventMessages($langs->trans('OrderNotFound'), null, 'errors');
        header('Location: '.DOL_URL_ROOT.'/custom/zonajob/orders.php');
        exit;
    }
    $order->fetch_thirdparty();
    $id = $order->id;
}

// Load project if linked
$project = null;
if (!empty($order->fk_projet)) {
    require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
    $project = new Project($db);
    $project->fetch($order->fk_projet);
}

// Load extrafields
$extrafields = new ExtraFields($db);

// PDF models available for orders
$pdfModels = ModelePDFCommandes::liste_modeles($db);
if (!is_array($pdfModels)) {
    $pdfModels = array();
}
$defaultPdfModel = !empty($order->model_pdf) ? $order->model_pdf : getDolGlobalString('COMMANDE_ADDON_PDF', 'zonajob');
if (!isset($pdfModels[$defaultPdfModel])) {
    $pdfModels[$defaultPdfModel] = $defaultPdfModel;
}
$extrafields->fetch_name_optionals_label($order->table_element);

// Load linked objects
$order->fetchObjectLinked();

/**
 * Helper function to get VAT rates from Dolibarr dictionary
 * Returns array with VAT rates as keys and percentages as display strings
 */
function getVatRates($db) {
    $vat_rates = array();
    
    // Try using getDictionary if available
    if (function_exists('getDictionary')) {
        $dict = getDictionary('c_tva');
        if (!empty($dict) && is_array($dict)) {
            return $dict;
        }
    }
    
    // Fallback: Query c_tva table directly
    $sql = "SELECT DISTINCT taux as rate FROM ".MAIN_DB_PREFIX."c_tva";
    $sql .= " WHERE active = 1 ORDER BY taux ASC";
    
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $rate = (float)$obj->rate;
            $vat_rates[$rate] = number_format($rate, 2, ',', '').'%';
        }
    }
    
    // If still empty, use standard rates
    if (empty($vat_rates)) {
        $vat_rates = array(
            0 => '0%',
            4 => '4%',
            10 => '10%',
            21 => '21%'
        );
    }
    
    return $vat_rates;
}

/**
 * Generate secure public download tokens for selected order documents
 * Returns array of associative arrays: [ 'name' => filename, 'url' => public_url ]
 */
function zonajob_generate_doc_tokens($db, $order, $selectedFiles) {
    global $conf;
    $links = array();
    if (empty($selectedFiles) || !is_array($selectedFiles)) return $links;

    $basePath = rtrim($conf->commande->dir_output, '/').'/'.$order->ref;
    $now = dol_now();
    // Default expiration: 30 days
    $expire = $now + (30 * 24 * 3600);

    foreach ($selectedFiles as $fname) {
        // Sanitize simple filename
        $safe = basename($fname);
        $fullpath = $basePath.'/'.$safe;
        if (!is_file($fullpath)) {
            continue;
        }
        // Generate unique token
        $token = bin2hex(random_bytes(32));

        // Insert token record (DATETIME fields + entity)
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."zonajob_doc_tokens (token, fk_commande, filename, filepath, date_creation, date_expiration, downloads, active, entity)
            VALUES ('".$db->escape($token)."', ".$order->id.", '".$db->escape($safe)."', '".$db->escape($fullpath)."', '".$db->idate($now)."', '".$db->idate($expire)."', 0, 1, ".(int) $conf->entity.")";
        $res = $db->query($sql);
        if ($res) {
            // Build full public URL with domain
            $publicUrl = DOL_MAIN_URL_ROOT.'/public/zonajob_download.php?token='.$token;
            $links[] = array('name' => $safe, 'url' => $publicUrl);
        }
    }
    return $links;
}

/*
 * Actions
 */

// Upload photo
if ($action == 'upload_photo' && !empty($user->rights->zonajob->photo->upload)) {
    if (!empty($_FILES['photo']['tmp_name'])) {
        // Create standard Dolibarr documents directory for order if not exists
        $upload_dir = $conf->commande->dir_output . '/' . $order->ref;
        if (!is_dir($upload_dir)) {
            dol_mkdir($upload_dir);
        }
        
        // Prepare file info
        $photo_type = GETPOST('photo_type', 'alpha');
        $photo_description = GETPOST('photo_description', 'restricthtml');
        
        // Sanitize filename
        $original_filename = $_FILES['photo']['name'];
        $file_ext = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
        
        // Allowed extensions for images
        $allowed_ext = array('jpg', 'jpeg', 'png', 'gif', 'webp');
        
        if (!in_array($file_ext, $allowed_ext)) {
            setEventMessages($langs->trans('ErrorInvalidFileType'), null, 'errors');
        } else {
            // Create unique filename with timestamp
            $filename = 'photo_' . $photo_type . '_' . dechex(time()) . '.' . $file_ext;
            $filepath = $upload_dir . '/' . $filename;
            
            // Move uploaded file to standard Dolibarr documents location
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $filepath)) {
                // Optional: Store metadata in zonaJob table for additional info
                // but the main file is in the standard Dolibarr location
                $photoObj = new ZonaJobPhoto($db);
                $photoObj->fk_commande = $order->id;
                $photoObj->filename = $filename;
                $photoObj->filepath = $filepath;
                $photoObj->filetype = $photo_type;
                $photoObj->description = $photo_description;
                $photoObj->latitude = GETPOST('latitude', 'alpha');
                $photoObj->longitude = GETPOST('longitude', 'alpha');
                $photoObj->fk_user_creat = $user->id;
                $photoObj->datec = dol_now();
                
                // Save metadata to database
                $result = $photoObj->create($user);
                
                if ($result > 0) {
                    // Log the action
                    dol_syslog("ZonaJob: Photo uploaded for order " . $order->ref . " - " . $filename, LOG_INFO);
                    setEventMessages($langs->trans('PhotoUploaded') . ' - ' . $langs->trans('VisibleInDocuments'), null, 'mesgs');
                } else {
                    setEventMessages($langs->trans('ErrorSavingPhotoMetadata'), null, 'warnings');
                }
            } else {
                setEventMessages($langs->trans('ErrorUploadingFile'), null, 'errors');
                dol_syslog("ZonaJob: Error moving uploaded file to " . $filepath, LOG_ERR);
            }
        }
    } else {
        setEventMessages($langs->trans('NoFileSelected'), null, 'errors');
    }
    $action = '';
}

// Delete photo
if ($action == 'delete_photo' && GETPOSTINT('photo_id') > 0) {
    $photoObj = new ZonaJobPhoto($db);
    $photoObj->fetch(GETPOSTINT('photo_id'));
    if ($photoObj->id > 0 && ($user->rights->zonajob->photo->upload || $photoObj->fk_user_creat == $user->id)) {
        // Delete physical file from standard Dolibarr location if exists
        if (!empty($photoObj->filepath) && file_exists($photoObj->filepath)) {
            if (!unlink($photoObj->filepath)) {
                dol_syslog("ZonaJob: Error deleting photo file " . $photoObj->filepath, LOG_ERR);
            }
        }
        
        // Delete metadata record
        $result = $photoObj->delete($user);
        if ($result > 0) {
            setEventMessages($langs->trans('PhotoDeleted'), null, 'mesgs');
        } else {
            setEventMessages($langs->trans('ErrorDeletingPhoto'), null, 'errors');
        }
    }
    $action = '';
}

// Save signature
if ($action == 'save_signature' && !empty($user->rights->zonajob->signature->request)) {
    $signature_data = GETPOST('signature_data', 'restricthtml');
    $signer_name = GETPOST('signer_name', 'alpha');
    
    if (!empty($signature_data) && !empty($signer_name)) {
        $sigObj = new ZonaJobSignature($db);
        $sigObj->fk_commande = $order->id;
        $sigObj->fk_soc = $order->socid;
        $sigObj->fk_socpeople = GETPOSTINT('fk_socpeople');
        $sigObj->signer_name = $signer_name;
        $sigObj->signer_email = GETPOST('signer_email', 'alpha');
        $sigObj->signer_phone = GETPOST('signer_phone', 'alpha');
        $sigObj->latitude = GETPOST('sig_latitude', 'alpha');
        $sigObj->longitude = GETPOST('sig_longitude', 'alpha');
        
        $result = $sigObj->create($user);
        if ($result > 0) {
            $sigObj->sign($signature_data, $signer_name, $user);
            setEventMessages($langs->trans('SignatureSaved'), null, 'mesgs');

            // Generate PDF with selected template
            $pdfModel = GETPOST('pdf_model', 'alpha');
            if (empty($pdfModel)) {
                $pdfModel = $defaultPdfModel;
            }

            $order->model_pdf = $pdfModel;
            $gendoc = $order->generateDocument($pdfModel, $langs, 0, 0, 0);
            if ($gendoc > 0) {
                setEventMessages($langs->trans('PDFGenerated'), null, 'mesgs');
            } else {
                setEventMessages($langs->trans('ErrorGeneratingPDF'), null, 'errors');
            }
            
            // Check if status change/validation requested
            $validate_order = GETPOSTINT('validate_order');
            $new_status = GETPOSTINT('new_status');
            
            // Reload order to get current status before any changes
            $order->fetch($order->id);
            $initial_status = $order->statut;
            
            dol_syslog("ZonaJob: Initial status=".$initial_status.", validate_order=".$validate_order.", new_status=".$new_status, LOG_INFO);
            
            // Determine target status
            $target_status = $new_status;
            if ($validate_order && $initial_status == Commande::STATUS_DRAFT && $new_status == 0) {
                $target_status = Commande::STATUS_VALIDATED;
            }
            
            // Process status change if needed
            // Nota: en ZonaJob puede firmar un usuario sin el permiso estándar commande->valider.
            // Intentamos el cambio y dejamos que el método devuelva el error real si no procede.
            if ($target_status > 0 && $target_status != $initial_status) {
                $status_changed = false;
                
                // Step 1: Validate if currently draft and target requires validation
                if ($initial_status == Commande::STATUS_DRAFT && $target_status >= Commande::STATUS_VALIDATED) {
                    $result_valid = $order->valid($user);
                    if ($result_valid > 0) {
                        setEventMessages($langs->trans('OrderValidated'), null, 'mesgs');
                        $order->fetch($order->id);
                        $status_changed = true;
                        dol_syslog("ZonaJob: Order validated successfully, new status=".$order->statut, LOG_DEBUG);
                    } else {
                        $errmsg = !empty($order->error) ? $order->error : 'Validation failed (code '.$result_valid.')';
                        setEventMessages($errmsg, $order->errors, 'errors');
                        dol_syslog("ZonaJob: Validation failed: ".$order->error, LOG_ERR);
                    }
                }
                
                // Step 2: Change to SHIPMENTONPROCESS if needed
                if ($target_status == Commande::STATUS_SHIPMENTONPROCESS && $order->statut == Commande::STATUS_VALIDATED) {
                    $result_ship = $order->setStatut(Commande::STATUS_SHIPMENTONPROCESS);
                    if ($result_ship > 0) {
                        setEventMessages($langs->trans('StatusChanged'), null, 'mesgs');
                        $order->fetch($order->id);
                        $status_changed = true;
                        dol_syslog("ZonaJob: Status changed to SHIPMENTONPROCESS", LOG_DEBUG);
                    } else {
                        $errmsg = !empty($order->error) ? $order->error : 'setStatut failed (code '.$result_ship.')';
                        setEventMessages($errmsg, $order->errors, 'errors');
                        dol_syslog("ZonaJob: setStatut failed: ".$order->error, LOG_ERR);
                    }
                }
                
                // Step 3: Close if target is CLOSED
                if ($target_status == Commande::STATUS_CLOSED && $order->statut >= Commande::STATUS_VALIDATED && $order->statut < Commande::STATUS_CLOSED) {
                    $result_close = $order->cloture($user);
                    if ($result_close > 0) {
                        setEventMessages($langs->trans('OrderClosed'), null, 'mesgs');
                        $status_changed = true;
                        dol_syslog("ZonaJob: Order closed successfully", LOG_DEBUG);
                    } else {
                        $errmsg = !empty($order->error) ? $order->error : 'cloture failed (code '.$result_close.')';
                        setEventMessages($errmsg, $order->errors, 'errors');
                        dol_syslog("ZonaJob: cloture failed: ".$order->error, LOG_ERR);
                    }
                }

                if (!$status_changed) {
                    dol_syslog('ZonaJob: No status change applied (target='.$target_status.', current='.$order->statut.')', LOG_WARNING);
                }
            }
            
            // Redirect to confirm send
            header('Location: '.$_SERVER['PHP_SELF'].'?id='.$order->id.'&tab=send&signed=1&pdf=1');
            exit;
        } else {
            setEventMessages($sigObj->error, null, 'errors');
        }
    } else {
        setEventMessages($langs->trans('SignatureOrNameMissing'), null, 'errors');
    }
    $action = '';
}

// Send via WhatsApp
if ($action == 'send_whatsapp' && !empty($user->rights->zonajob->send->execute)) {
    $phone = GETPOST('wa_phone', 'alpha');
    $message = GETPOST('wa_message', 'restricthtml');
    $fk_socpeople = GETPOSTINT('wa_contact_id');
    $selectedDocs = GETPOST('selected_docs', 'array');
    
    if (!empty($phone) && !empty($message)) {
        // Generate public links for selected documents
        $docLinks = zonajob_generate_doc_tokens($db, $order, $selectedDocs);
        if (!empty($docLinks)) {
            $message .= "\n\n".$langs->trans('Documents').":\n";
            foreach ($docLinks as $dl) {
                $message .= '- '.$dl['name'].' → '.$dl['url']."\n";
            }
        }
        $sender = new ZonaJobSender($db);
        $result = $sender->sendWhatsApp($order, $phone, $message, $user, $fk_socpeople);
        
        if ($result > 0) {
            setEventMessages($langs->trans('WhatsAppSent'), null, 'mesgs');
        } else {
            setEventMessages($sender->error, null, 'errors');
        }
    }
    $action = '';
}

// Send via Email
if ($action == 'send_email' && !empty($user->rights->zonajob->send->execute)) {
    $email = GETPOST('email_to', 'alpha');
    $subject = GETPOST('email_subject', 'restricthtml');
    $message = GETPOST('email_message', 'restricthtml');
    $fk_socpeople = GETPOSTINT('email_contact_id');
    $selectedDocs = GETPOST('selected_docs', 'array');
    
    if (!empty($email) && !empty($message)) {
        $sender = new ZonaJobSender($db);
        
        // Generate PDF if needed
        $pdfFile = '';
        if (GETPOST('attach_pdf', 'int')) {
            $pdfFile = $conf->commande->dir_output.'/'.$order->ref.'/'.$order->ref.'.pdf';
            if (!file_exists($pdfFile)) {
                // Generate PDF
                $order->generateDocument($order->model_pdf, $langs);
            }
        }
        
        // Generate public links for selected documents and append to message
        $docLinks = zonajob_generate_doc_tokens($db, $order, $selectedDocs);
        if (!empty($docLinks)) {
            $message .= "\n\n".$langs->trans('Documents').":\n";
            foreach ($docLinks as $dl) {
                $message .= '- '.$dl['name'].' → '.$dl['url']."\n";
            }
        }

        $result = $sender->sendEmail($order, $email, $subject, $message, $user, $fk_socpeople, $pdfFile);
        
        if ($result > 0) {
            setEventMessages($langs->trans('EmailSent'), null, 'mesgs');
        } else {
            setEventMessages($sender->error, null, 'errors');
        }
    }
    $action = '';
}

// Create contact
if ($action == 'create_contact' && !empty($user->rights->zonajob->contact->create)) {
    $contact = new Contact($db);
    $contact->socid = $order->socid;
    $contact->lastname = GETPOST('contact_lastname', 'alpha');
    $contact->firstname = GETPOST('contact_firstname', 'alpha');
    $contact->email = GETPOST('contact_email', 'alpha');
    $contact->phone_mobile = GETPOST('contact_phone', 'alpha');
    
    if (!empty($contact->lastname)) {
        $result = $contact->create($user);
        if ($result > 0) {
            setEventMessages($langs->trans('ContactCreated'), null, 'mesgs');
            // Link to order
            $order->add_contact($contact->id, 'CUSTOMER');
        } else {
            setEventMessages($contact->error, null, 'errors');
        }
    }
    $action = '';
}

// Add line to order
if ($action == 'add_line' && $order->statut == Commande::STATUS_DRAFT && !empty($user->rights->commande->creer)) {
    $fk_product = GETPOSTINT('fk_product');
    $description = GETPOST('description', 'restricthtml');
    $qty = price2num(GETPOST('qty', 'alpha'));
    $price = price2num(GETPOST('price', 'alpha'));
    $tva_tx = price2num(GETPOST('tva_tx', 'alpha'));
    $remise_percent = price2num(GETPOST('remise_percent', 'alpha'));
    
    if ($qty > 0) {
        // If product selected, fetch product info
        if ($fk_product > 0) {
            require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
            $product = new Product($db);
            $product->fetch($fk_product);
            if (empty($description)) {
                $description = $product->description;
            }
            if ($price <= 0) {
                $price = $product->price;
            }
            if (empty($tva_tx)) {
                $tva_tx = $product->tva_tx;
            }
        }
        
        // Add line using proper parameters
        $result = $order->addline(
            $description,           // desc
            $price,                 // pu_ht
            $qty,                   // qty
            $tva_tx,                // txtva
            0,                      // txlocaltax1
            0,                      // txlocaltax2
            $fk_product,            // fk_product
            $remise_percent,        // remise_percent
            0,                      // info_bits
            0,                      // fk_remise_except
            'HT',                   // price_base_type
            0,                      // pu_ttc
            0,                      // date_start
            0,                      // date_end
            0,                      // type
            -1,                     // rang
            0,                      // special_code
            0,                      // fk_parent_line
            0,                      // fk_fournprice
            0,                      // pa_ht
            '',                     // label
            null,                   // array_options
            0,                      // fk_unit
            '',                     // origin
            0,                      // origin_id
            0,                      // pu_ht_devise
            ''                      // ref_ext
        );
        
        if ($result > 0) {
            // Update order totals
            $order->update_price(1);
            setEventMessages($langs->trans('LineAdded'), null, 'mesgs');
            // Reload order
            $order->fetch($id);
        } else {
            setEventMessages($order->error ? $order->error : $langs->trans('ErrorAddLine'), $order->errors, 'errors');
            dol_syslog("Error adding line: ".$order->error, LOG_ERR);
        }
    } else {
        setEventMessages($langs->trans('InvalidQuantity'), null, 'errors');
    }
    $action = '';
}

// Update line
if ($action == 'update_line' && $order->statut == Commande::STATUS_DRAFT && !empty($user->rights->commande->creer)) {
    $lineid = GETPOSTINT('lineid');
    $description = GETPOST('description', 'restricthtml');
    $qty = price2num(GETPOST('qty', 'alpha'));
    $price = price2num(GETPOST('price', 'alpha'));
    $tva_tx = price2num(GETPOST('tva_tx', 'alpha'));
    $remise_percent = price2num(GETPOST('remise_percent', 'alpha'));
    
    if ($lineid > 0 && $qty > 0) {
        $result = $order->updateline(
            $lineid,
            $description,
            $price,
            $qty,
            $remise_percent,
            $tva_tx,
            0,  // localtax1_tx
            0,  // localtax2_tx
            'HT',
            0,  // info_bits
            0,  // date_start
            0,  // date_end
            0,  // type
            -1, // rang
            0,  // special_code
            0,  // fk_parent_line
            0,  // fk_fournprice
            0,  // pa_ht
            '',  // label
            0,  // product_type
            null, // array_options
            0,  // fk_unit
            0,  // pu_ht_devise
            ''  // ref_ext
        );
        
        if ($result > 0) {
            $order->update_price(1);
            setEventMessages($langs->trans('LineUpdated'), null, 'mesgs');
            $order->fetch($id);
        } else {
            setEventMessages($order->error ? $order->error : $langs->trans('ErrorUpdateLine'), $order->errors, 'errors');
        }
    } else {
        setEventMessages($langs->trans('InvalidQuantity'), null, 'errors');
    }
    $action = '';
}

// Delete line
if ($action == 'delete_line' && $order->statut == Commande::STATUS_DRAFT && !empty($user->rights->commande->creer)) {
    $lineid = GETPOSTINT('lineid');
    
    if ($lineid > 0) {
        $result = $order->deleteline($user, $lineid);
        
        if ($result > 0) {
            setEventMessages($langs->trans('LineDeleted'), null, 'mesgs');
        } else {
            setEventMessages($order->error, null, 'errors');
        }
    }
    $action = '';
}

// Save notes
if ($action == 'save_notes' && !empty($user->rights->commande->creer)) {
    $note_public = GETPOST('note_public', 'restricthtml');
    $note_private = GETPOST('note_private', 'restricthtml');
    
    $order->note_public = $note_public;
    $order->note_private = $note_private;
    
    $result = $order->update($user);
    if ($result > 0) {
        setEventMessages($langs->trans('NotesSaved'), null, 'mesgs');
    } else {
        setEventMessages($order->error, $order->errors, 'errors');
    }
    $action = '';
}

// Save extrafields
if ($action == 'save_extrafields' && !empty($user->rights->commande->creer)) {
    $extrafields->fetch_name_optionals_label($order->table_element);
    $ret = $extrafields->setOptionalsFromPost(null, $order);
    if ($ret >= 0) {
        $result = $order->insertExtraFields();
        if ($result > 0) {
            setEventMessages($langs->trans('ExtraFieldsSaved'), null, 'mesgs');
            $order->fetch($id); // Reload
        } else {
            setEventMessages($order->error, $order->errors, 'errors');
        }
    }
    $action = '';
}

/*
 * View
 */

$title = $langs->trans('Order').' '.$order->ref;

// Ask zonaempleado header to load ZonaJob assets in <head>
$GLOBALS['zonaempleado_extra_css'] = array('/custom/zonajob/css/zonajob.css.php');
$GLOBALS['zonaempleado_extra_js'] = array('/custom/zonajob/js/zonajob.js.php');

zonaempleado_print_header($title);

// Get signature info
$sigObj = new ZonaJobSignature($db);
$signatures = $sigObj->getSignaturesForOrder($order->id);
$lastSignature = !empty($signatures) ? $signatures[0] : null;

// Get photos
$photoObj = new ZonaJobPhoto($db);
$photos = $photoObj->getPhotosForOrder($order->id);

// Get recipients for sending
$sender = new ZonaJobSender($db);
$recipients = $sender->getRecipientsForOrder($order);

// Get send history
$sendHistory = $sender->getHistoryForOrder($order->id);

?>

<div class="zonajob-order-card">
    <!-- Back Button -->
    <div class="order-back">
        <a href="<?php echo DOL_URL_ROOT; ?>/custom/zonajob/orders.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> <?php echo $langs->trans('BackToList'); ?>
        </a>
    </div>

    <!-- Order Header -->
    <div class="order-main-header">
        <div class="order-title">
            <h1><?php echo dol_escape_htmltag($order->ref); ?></h1>
            <?php if (!empty($order->ref_client)) { ?>
                <span class="ref-client"><?php echo $langs->trans('RefCustomer'); ?>: <?php echo dol_escape_htmltag($order->ref_client); ?></span>
            <?php } ?>
            <?php if ($project && $project->id > 0) { ?>
                <div class="project-info">
                    <i class="fas fa-project-diagram"></i>
                    <span class="project-ref"><?php echo dol_escape_htmltag($project->ref); ?></span>
                    <?php if (!empty($project->title)) { ?>
                        <span class="project-title"> - <?php echo dol_escape_htmltag($project->title); ?></span>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
        <div class="order-status-badge">
            <?php echo $order->getLibStatut(5); ?>
        </div>
    </div>

    <!-- Tab Navigation with scroll indicators -->
    <div class="tabs-container">
        <div class="tab-scroll-indicator tab-scroll-left" onclick="scrollTabs(-150)">
            <i class="fas fa-chevron-left"></i>
        </div>
        <div class="order-tabs" id="orderTabs">
            <a href="?id=<?php echo $id; ?>&tab=info" class="tab-link <?php echo ($tab == 'info') ? 'active' : ''; ?>" title="<?php echo $langs->trans('Info'); ?>">
                <i class="fas fa-info-circle"></i>
                <span><?php echo $langs->trans('Info'); ?></span>
            </a>
            <a href="?id=<?php echo $id; ?>&tab=lines" class="tab-link <?php echo ($tab == 'lines') ? 'active' : ''; ?>" title="<?php echo $langs->trans('Lines'); ?>">
                <i class="fas fa-list"></i>
                <span><?php echo $langs->trans('Lines'); ?></span>
            </a>
            <a href="?id=<?php echo $id; ?>&tab=notes" class="tab-link <?php echo ($tab == 'notes') ? 'active' : ''; ?>" title="<?php echo $langs->trans('Notes'); ?>">
                <i class="fas fa-sticky-note"></i>
                <span><?php echo $langs->trans('Notes'); ?></span>
            </a>
            <a href="?id=<?php echo $id; ?>&tab=photos" class="tab-link <?php echo ($tab == 'photos') ? 'active' : ''; ?>" title="<?php echo $langs->trans('Photos'); ?>">
                <i class="fas fa-camera"></i>
                <span><?php echo $langs->trans('Photos'); ?> (<?php echo count($photos); ?>)</span>
            </a>
            <a href="?id=<?php echo $id; ?>&tab=signature" class="tab-link <?php echo ($tab == 'signature') ? 'active' : ''; ?>" title="<?php echo $langs->trans('Signature'); ?>">
                <i class="fas fa-signature"></i>
                <span><?php echo $langs->trans('Signature'); ?></span>
            </a>
            <a href="?id=<?php echo $id; ?>&tab=send" class="tab-link <?php echo ($tab == 'send') ? 'active' : ''; ?>" title="<?php echo $langs->trans('Send'); ?>">
                <i class="fas fa-paper-plane"></i>
                <span><?php echo $langs->trans('Send'); ?></span>
            </a>
            <a href="?id=<?php echo $id; ?>&tab=documents" class="tab-link <?php echo ($tab == 'documents') ? 'active' : ''; ?>" title="<?php echo $langs->trans('Documents'); ?>">
                <i class="fas fa-file-pdf"></i>
                <span><?php echo $langs->trans('Documents'); ?></span>
            </a>
        </div>
        <div class="tab-scroll-indicator tab-scroll-right" onclick="scrollTabs(150)">
            <i class="fas fa-chevron-right"></i>
        </div>
    </div>

    <!-- Tab Content -->
    <div class="order-tab-content">
        
        <?php if ($tab == 'info') { ?>
        <!-- INFO TAB -->
        <div class="tab-pane active" id="tab-info">
            <!-- Customer Info -->
            <div class="info-section">
                <h3><i class="fas fa-building"></i> <?php echo $langs->trans('Customer'); ?></h3>
                <div class="info-card">
                    <div class="customer-name"><?php echo dol_escape_htmltag($order->thirdparty->name); ?></div>
                    <?php if (!empty($order->thirdparty->address)) { ?>
                        <div class="customer-address">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo dol_nl2br(dol_escape_htmltag($order->thirdparty->address)); ?>
                            <?php if (!empty($order->thirdparty->zip) || !empty($order->thirdparty->town)) { ?>
                                <br><?php echo dol_escape_htmltag($order->thirdparty->zip.' '.$order->thirdparty->town); ?>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    <?php if (!empty($order->thirdparty->phone)) { ?>
                        <div class="customer-phone">
                            <a href="tel:<?php echo dol_escape_htmltag($order->thirdparty->phone); ?>">
                                <i class="fas fa-phone"></i> <?php echo dol_escape_htmltag($order->thirdparty->phone); ?>
                            </a>
                        </div>
                    <?php } ?>
                    <?php if (!empty($order->thirdparty->email)) { ?>
                        <div class="customer-email">
                            <a href="mailto:<?php echo dol_escape_htmltag($order->thirdparty->email); ?>">
                                <i class="fas fa-envelope"></i> <?php echo dol_escape_htmltag($order->thirdparty->email); ?>
                            </a>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <!-- Order Dates -->
            <div class="info-section">
                <h3><i class="fas fa-calendar-alt"></i> <?php echo $langs->trans('Dates'); ?></h3>
                <div class="info-grid">
                    <div class="info-item">
                        <label><?php echo $langs->trans('OrderDate'); ?></label>
                        <span><?php echo dol_print_date($order->date, 'day'); ?></span>
                    </div>
                    <?php if ($order->date_livraison) { ?>
                    <div class="info-item">
                        <label><?php echo $langs->trans('DeliveryDate'); ?></label>
                        <span><?php echo dol_print_date($order->date_livraison, 'day'); ?></span>
                    </div>
                    <?php } ?>
                    <div class="info-item">
                        <label><?php echo $langs->trans('DateCreation'); ?></label>
                        <span><?php echo dol_print_date($order->date_creation, 'dayhour'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Order Totals -->
            <div class="info-section">
                <h3><i class="fas fa-calculator"></i> <?php echo $langs->trans('Totals'); ?></h3>
                <div class="totals-card">
                    <div class="total-row">
                        <span class="total-label"><?php echo $langs->trans('TotalHT'); ?></span>
                        <span class="total-value"><?php echo price($order->total_ht, 1, $langs, 1, -1, -1, $conf->currency); ?></span>
                    </div>
                    <div class="total-row">
                        <span class="total-label"><?php echo $langs->trans('TotalVAT'); ?></span>
                        <span class="total-value"><?php echo price($order->total_tva, 1, $langs, 1, -1, -1, $conf->currency); ?></span>
                    </div>
                    <div class="total-row total-main">
                        <span class="total-label"><?php echo $langs->trans('TotalTTC'); ?></span>
                        <span class="total-value"><?php echo price($order->total_ttc, 1, $langs, 1, -1, -1, $conf->currency); ?></span>
                    </div>
                </div>
            </div>

            <!-- Extrafields - Editable -->
            <?php
            $extrafields->fetch_name_optionals_label($order->table_element);
            // Count visible extrafields first
            $visibleExtrafields = 0;
            if (!empty($extrafields->attributes[$order->table_element]['label'])) {
                foreach ($extrafields->attributes[$order->table_element]['label'] as $keyField => $labelField) {
                    $attrList = !empty($extrafields->attributes[$order->table_element]['list'][$keyField]) ? $extrafields->attributes[$order->table_element]['list'][$keyField] : 1;
                    if ($attrList != 0 && $attrList != 3) {
                        $visibleExtrafields++;
                    }
                }
            }
            // Only show section if there are visible extrafields
            if ($visibleExtrafields > 0) { ?>
            <div class="info-section">
                <h3><i class="fas fa-th-list"></i> <?php echo $langs->trans('ExtraFields'); ?></h3>
                <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?id=<?php echo $id; ?>&tab=info" class="extrafields-form">
                    <input type="hidden" name="token" value="<?php echo newToken(); ?>">
                    <input type="hidden" name="action" value="save_extrafields">
                    <div class="extrafields-grid">
                        <?php
                        foreach ($extrafields->attributes[$order->table_element]['label'] as $keyField => $labelField) {
                            $attrType = $extrafields->attributes[$order->table_element]['type'][$keyField];
                            $attrRequired = !empty($extrafields->attributes[$order->table_element]['required'][$keyField]);
                            $attrList = !empty($extrafields->attributes[$order->table_element]['list'][$keyField]) ? $extrafields->attributes[$order->table_element]['list'][$keyField] : 1;
                            // Skip hidden fields
                            if ($attrList == 0 || $attrList == 3) continue;
                            
                            $valueField = isset($order->array_options['options_'.$keyField]) ? $order->array_options['options_'.$keyField] : '';
                            $canEdit = !empty($user->rights->commande->creer);
                            ?>
                            <div class="extrafield-item">
                                <label><?php echo $langs->trans($labelField); ?><?php if ($attrRequired) echo ' *'; ?></label>
                                <?php if ($canEdit) {
                                    echo $extrafields->showInputField($keyField, $valueField, '', '', '', 0, $order->id, $order->table_element);
                                } else {
                                    echo '<span>'.dol_escape_htmltag($valueField).'</span>';
                                } ?>
                            </div>
                            <?php
                        }
                        ?>
                    </div>
                    <?php if (!empty($user->rights->commande->creer)) { ?>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary"><i class="fas fa-save"></i> <?php echo $langs->trans('SaveExtraFields'); ?></button>
                    </div>
                    <?php } ?>
                </form>
            </div>
            <?php } ?>

            <!-- Contacts -->
            <div class="info-section">
                <h3><i class="fas fa-users"></i> <?php echo $langs->trans('Contacts'); ?></h3>
                <?php
                $contacts = $order->liste_contact(-1, 'external');
                if (!empty($contacts)) {
                    ?>
                    <div class="contacts-list">
                        <?php foreach ($contacts as $contact) { ?>
                        <div class="contact-card">
                            <div class="contact-name">
                                <i class="fas fa-user"></i>
                                <?php echo dol_escape_htmltag($contact['firstname'].' '.$contact['lastname']); ?>
                            </div>
                            <div class="contact-role"><?php echo dol_escape_htmltag($contact['libelle']); ?></div>
                            <?php if (!empty($contact['email'])) { ?>
                            <a href="mailto:<?php echo dol_escape_htmltag($contact['email']); ?>" class="contact-email">
                                <i class="fas fa-envelope"></i> <?php echo dol_escape_htmltag($contact['email']); ?>
                            </a>
                            <?php } ?>
                            <?php 
                            $phone = !empty($contact['phone_mobile']) ? $contact['phone_mobile'] : $contact['phone'];
                            if (!empty($phone)) { ?>
                            <a href="tel:<?php echo dol_escape_htmltag($phone); ?>" class="contact-phone">
                                <i class="fas fa-phone"></i> <?php echo dol_escape_htmltag($phone); ?>
                            </a>
                            <?php } ?>
                        </div>
                        <?php } ?>
                    </div>
                    <?php
                } else {
                    echo '<p class="no-data">'.$langs->trans('NoContactAssigned').'</p>';
                }
                ?>
                
                <!-- Add Contact Button -->
                <?php if (!empty($user->rights->zonajob->contact->create)) { ?>
                <button type="button" class="btn-add-contact" onclick="toggleAddContactForm()">
                    <i class="fas fa-plus"></i> <?php echo $langs->trans('AddContact'); ?>
                </button>
                
                <div id="add-contact-form" class="add-contact-form" style="display: none;">
                    <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?id=<?php echo $id; ?>&tab=info">
                        <input type="hidden" name="token" value="<?php echo newToken(); ?>">
                        <input type="hidden" name="action" value="create_contact">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><?php echo $langs->trans('Firstname'); ?></label>
                                <input type="text" name="contact_firstname" required>
                            </div>
                            <div class="form-group">
                                <label><?php echo $langs->trans('Lastname'); ?> *</label>
                                <input type="text" name="contact_lastname" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label><?php echo $langs->trans('Email'); ?></label>
                                <input type="email" name="contact_email">
                            </div>
                            <div class="form-group">
                                <label><?php echo $langs->trans('PhoneMobile'); ?></label>
                                <input type="tel" name="contact_phone">
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-primary"><i class="fas fa-save"></i> <?php echo $langs->trans('Save'); ?></button>
                            <button type="button" class="btn-secondary" onclick="toggleAddContactForm()"><i class="fas fa-times"></i> <?php echo $langs->trans('Cancel'); ?></button>
                        </div>
                    </form>
                </div>
                <?php } ?>
            </div>

            <!-- Notifications -->
            <?php if (isModEnabled('notification')) { 
                $notify = new Notify($db);
                $notifList = $notify->getNotificationsArray('ORDER_VALIDATE', $order->socid, $order, 0);
            ?>
            <div class="info-section">
                <h3><i class="fas fa-bell"></i> <?php echo $langs->trans('Notifications'); ?></h3>
                <?php if (!empty($notifList) && is_array($notifList)) { ?>
                <div class="notifications-list">
                    <?php foreach ($notifList as $notif) { ?>
                    <div class="notification-item">
                        <i class="fas fa-envelope text-primary"></i>
                        <span class="notif-email"><?php echo dol_escape_htmltag($notif['email']); ?></span>
                        <span class="notif-type badge-<?php echo $notif['type']; ?>">
                            <?php 
                            if ($notif['type'] == 'tocontact') echo $langs->trans('Contact');
                            elseif ($notif['type'] == 'touser') echo $langs->trans('User');
                            else echo $langs->trans('FixedEmail');
                            ?>
                        </span>
                    </div>
                    <?php } ?>
                </div>
                <p class="notif-info"><i class="fas fa-info-circle"></i> <?php echo $langs->trans('NotificationsWillBeSentOnValidation'); ?></p>
                <?php } else { ?>
                <p class="no-data"><?php echo $langs->trans('NoNotificationsDefined'); ?></p>
                <?php } ?>
            </div>
            <?php } ?>
        </div>
        <?php } ?>

        <?php if ($tab == 'notes') { ?>
        <!-- NOTES TAB -->
        <div class="tab-pane active" id="tab-notes">
            <div class="notes-section">
                <h3><i class="fas fa-sticky-note"></i> <?php echo $langs->trans('Notes'); ?></h3>
                
                <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?id=<?php echo $id; ?>&tab=notes">
                    <input type="hidden" name="token" value="<?php echo newToken(); ?>">
                    <input type="hidden" name="action" value="save_notes">
                    
                    <!-- Public Note -->
                    <div class="note-edit-card note-public">
                        <div class="note-header">
                            <i class="fas fa-globe"></i>
                            <label><?php echo $langs->trans('NotePublic'); ?></label>
                            <span class="note-hint"><?php echo $langs->trans('NotePublicHint'); ?></span>
                        </div>
                        <?php if (!empty($user->rights->commande->creer)) { ?>
                        <textarea name="note_public" rows="5" placeholder="<?php echo $langs->trans('EnterPublicNote'); ?>"><?php echo dol_escape_htmltag($order->note_public); ?></textarea>
                        <?php } else { ?>
                        <div class="note-content"><?php echo !empty($order->note_public) ? dol_htmlentitiesbr($order->note_public) : '<em class="text-muted">'.$langs->trans('NoPublicNote').'</em>'; ?></div>
                        <?php } ?>
                    </div>
                    
                    <!-- Private Note -->
                    <div class="note-edit-card note-private">
                        <div class="note-header">
                            <i class="fas fa-lock"></i>
                            <label><?php echo $langs->trans('NotePrivate'); ?></label>
                            <span class="note-hint"><?php echo $langs->trans('NotePrivateHint'); ?></span>
                        </div>
                        <?php if (!empty($user->rights->commande->creer)) { ?>
                        <textarea name="note_private" rows="5" placeholder="<?php echo $langs->trans('EnterPrivateNote'); ?>"><?php echo dol_escape_htmltag($order->note_private); ?></textarea>
                        <?php } else { ?>
                        <div class="note-content"><?php echo !empty($order->note_private) ? dol_htmlentitiesbr($order->note_private) : '<em class="text-muted">'.$langs->trans('NoPrivateNote').'</em>'; ?></div>
                        <?php } ?>
                    </div>
                    
                    <?php if (!empty($user->rights->commande->creer)) { ?>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> <?php echo $langs->trans('SaveNotes'); ?>
                        </button>
                    </div>
                    <?php } ?>
                </form>
            </div>
        </div>
        <?php } ?>

        <?php if ($tab == 'lines') { ?>
        <!-- LINES TAB -->
        <div class="tab-pane active" id="tab-lines">
            <div class="lines-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3><i class="fas fa-list"></i> <?php echo $langs->trans('OrderLines'); ?></h3>
                    <?php if ($order->statut == Commande::STATUS_DRAFT && !empty($user->rights->commande->creer)) { ?>
                    <button type="button" class="btn-add-line" onclick="toggleAddLineForm()">
                        <i class="fas fa-plus"></i> <?php echo $langs->trans('AddLine'); ?>
                    </button>
                    <?php } ?>
                </div>
                
                <!-- Add Line Form -->
                <?php if ($order->statut == Commande::STATUS_DRAFT && !empty($user->rights->commande->creer)) { ?>
                <div id="add-line-form" class="add-line-form" style="display: none;">
                    <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?id=<?php echo $id; ?>&tab=lines">
                        <input type="hidden" name="token" value="<?php echo newToken(); ?>">
                        <input type="hidden" name="action" value="add_line">
                        
                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <label><?php echo $langs->trans('Product'); ?></label>
                                <input type="text" id="product_search" name="product_search" placeholder="<?php echo $langs->trans('SearchProduct'); ?>" class="product-autocomplete" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
                                <input type="hidden" name="fk_product" id="fk_product" value="0">
                                <div id="product_autocomplete" class="autocomplete-dropdown" style="display: none; position: absolute; background: white; border: 1px solid #ddd; border-radius: 4px; max-height: 300px; overflow-y: auto; z-index: 1000; width: 100%; margin-top: 0.5rem;"></div>
                                <small style="color: #999;"><?php echo $langs->trans('SearchOrSelectFreeText'); ?></small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <label><?php echo $langs->trans('Description'); ?></label>
                                <textarea name="description" id="line_description" rows="2"></textarea>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><?php echo $langs->trans('Qty'); ?> *</label>
                                <input type="number" name="qty" id="line_qty" value="1" min="0" step="0.01" required>
                            </div>
                            <div class="form-group">
                                <label><?php echo $langs->trans('UnitPriceHT'); ?> *</label>
                                <input type="number" name="price" id="line_price" value="0" min="0" step="0.01" required>
                            </div>
                            <div class="form-group">
                                <label><?php echo $langs->trans('VAT'); ?> (%)</label>
                                <select name="tva_tx" id="line_vat">
                                    <option value="">-- <?php echo $langs->trans('Select'); ?> --</option>
                                    <?php
                                    // Get VAT rates from helper function
                                    $vat_rates = getVatRates($db);
                                    $default_vat = 21; // Default Spanish VAT
                                    if (is_object($order->thirdparty) && !empty($order->thirdparty->tva_tx)) {
                                        $default_vat = $order->thirdparty->tva_tx;
                                    }
                                    
                                    foreach ($vat_rates as $tva_rate => $tva_label) {
                                        $rate_num = (float)$tva_rate;
                                        $selected = (abs($rate_num - $default_vat) < 0.01) ? 'selected' : '';
                                        echo '<option value="'.$rate_num.'" '.$selected.'>'.$tva_label.'</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><?php echo $langs->trans('Discount'); ?> (%)</label>
                                <input type="number" name="remise_percent" id="line_discount" value="0" min="0" max="100" step="0.01">
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save"></i> <?php echo $langs->trans('AddLine'); ?>
                            </button>
                            <button type="button" class="btn-secondary" onclick="toggleAddLineForm()">
                                <i class="fas fa-times"></i> <?php echo $langs->trans('Cancel'); ?>
                            </button>
                        </div>
                    </form>
                </div>
                <?php } ?>
                
                <?php if (!empty($order->lines)) { ?>
                <div class="order-lines">
                    <?php 
                    $i = 1;
                    $canEdit = ($order->statut == Commande::STATUS_DRAFT && !empty($user->rights->commande->creer));
                    foreach ($order->lines as $line) { 
                        $isProduct = ($line->fk_product > 0);
                        ?>
                    <div class="line-card" id="line-<?php echo $line->id; ?>">
                        <div class="line-number"><?php echo $i; ?></div>
                        <div class="line-content">
                            <div class="line-header">
                                <?php if ($isProduct) { ?>
                                    <span class="line-ref"><?php echo dol_escape_htmltag($line->ref); ?></span>
                                <?php } ?>
                                <span class="line-label"><?php echo dol_escape_htmltag($line->label ?: $line->product_label); ?></span>
                                <?php if ($canEdit) { ?>
                                <div class="line-actions">
                                    <button type="button" class="btn-icon" onclick="editLine(<?php echo $line->id; ?>)" title="<?php echo $langs->trans('Edit'); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>?id=<?php echo $id; ?>&tab=lines&action=delete_line&lineid=<?php echo $line->id; ?>&token=<?php echo newToken(); ?>" 
                                       class="btn-icon btn-delete" onclick="return confirm('<?php echo $langs->trans('ConfirmDeleteLine'); ?>')" title="<?php echo $langs->trans('Delete'); ?>">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                                <?php } ?>
                            </div>
                            <?php if (!empty($line->desc) && $line->desc != $line->label) { ?>
                            <div class="line-description">
                                <?php echo dol_htmlentitiesbr($line->desc); ?>
                            </div>
                            <?php } ?>
                            <div class="line-details">
                                <div class="line-qty">
                                    <label><?php echo $langs->trans('Qty'); ?>:</label>
                                    <span><?php echo $line->qty; ?></span>
                                </div>
                                <div class="line-price">
                                    <label><?php echo $langs->trans('UnitPriceHT'); ?>:</label>
                                    <span><?php echo price($line->subprice, 1, $langs, 1, -1, -1, $conf->currency); ?></span>
                                </div>
                                <?php if ($line->remise_percent > 0) { ?>
                                <div class="line-discount">
                                    <label><?php echo $langs->trans('Discount'); ?>:</label>
                                    <span><?php echo $line->remise_percent; ?>%</span>
                                </div>
                                <?php } ?>
                                <div class="line-total">
                                    <label><?php echo $langs->trans('TotalHT'); ?>:</label>
                                    <span><?php echo price($line->total_ht, 1, $langs, 1, -1, -1, $conf->currency); ?></span>
                                </div>
                            </div>
                            
                            <!-- Edit Line Form (hidden by default) -->
                            <?php if ($canEdit) { ?>
                            <div id="edit-line-form-<?php echo $line->id; ?>" class="edit-line-form" style="display: none;">
                                <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?id=<?php echo $id; ?>&tab=lines">
                                    <input type="hidden" name="token" value="<?php echo newToken(); ?>">
                                    <input type="hidden" name="action" value="update_line">
                                    <input type="hidden" name="lineid" value="<?php echo $line->id; ?>">
                                    
                                    <div class="form-group">
                                        <label><?php echo $langs->trans('Description'); ?></label>
                                        <textarea name="description" rows="2"><?php echo dol_escape_htmltag($line->desc); ?></textarea>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label><?php echo $langs->trans('Qty'); ?> *</label>
                                            <input type="number" name="qty" value="<?php echo $line->qty; ?>" min="0" step="0.01" required>
                                        </div>
                                        <div class="form-group">
                                            <label><?php echo $langs->trans('UnitPriceHT'); ?> *</label>
                                            <input type="number" name="price" value="<?php echo $line->subprice; ?>" min="0" step="0.01" required>
                                        </div>
                                        <div class="form-group">
                                            <label><?php echo $langs->trans('VAT'); ?> (%)</label>
                                            <select name="tva_tx">
                                                <option value="">-- <?php echo $langs->trans('Select'); ?> --</option>
                                                <?php
                                                $vat_rates = getVatRates($db);
                                                foreach ($vat_rates as $tva_rate => $tva_label) {
                                                    $rate_num = (float)$tva_rate;
                                                    $selected = (abs($rate_num - (float)$line->tva_tx) < 0.01) ? 'selected' : '';
                                                    echo '<option value="'.$rate_num.'" '.$selected.'>'.$tva_label.'</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label><?php echo $langs->trans('Discount'); ?> (%)</label>
                                            <input type="number" name="remise_percent" value="<?php echo $line->remise_percent; ?>" min="0" max="100" step="0.01">
                                        </div>
                                    </div>
                                    
                                    <div class="form-actions">
                                        <button type="submit" class="btn-primary">
                                            <i class="fas fa-save"></i> <?php echo $langs->trans('Save'); ?>
                                        </button>
                                        <button type="button" class="btn-secondary" onclick="cancelEditLine(<?php echo $line->id; ?>)">
                                            <i class="fas fa-times"></i> <?php echo $langs->trans('Cancel'); ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                    <?php 
                        $i++;
                    } 
                    ?>
                </div>
                
                <!-- Lines Total -->
                <div class="lines-total">
                    <div class="total-row">
                        <span><?php echo $langs->trans('TotalHT'); ?></span>
                        <span><?php echo price($order->total_ht, 1, $langs, 1, -1, -1, $conf->currency); ?></span>
                    </div>
                    <div class="total-row">
                        <span><?php echo $langs->trans('TotalVAT'); ?></span>
                        <span><?php echo price($order->total_tva, 1, $langs, 1, -1, -1, $conf->currency); ?></span>
                    </div>
                    <div class="total-row total-main">
                        <span><?php echo $langs->trans('TotalTTC'); ?></span>
                        <span><?php echo price($order->total_ttc, 1, $langs, 1, -1, -1, $conf->currency); ?></span>
                    </div>
                </div>
                <?php } else { ?>
                <p class="no-data"><?php echo $langs->trans('NoLines'); ?></p>
                <?php } ?>
            </div>
        </div>
        <?php } ?>

        <?php if ($tab == 'photos') { ?>
        <!-- PHOTOS TAB -->
        <div class="tab-pane active" id="tab-photos">
            <div class="photos-section">
                <h3><i class="fas fa-camera"></i> <?php echo $langs->trans('Photos'); ?></h3>
                
                <!-- Upload Form -->
                <?php if (!empty($user->rights->zonajob->photo->upload)) { ?>
                <div class="photo-upload-form">
                    <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?id=<?php echo $id; ?>&tab=photos" enctype="multipart/form-data">
                        <input type="hidden" name="token" value="<?php echo newToken(); ?>">
                        <input type="hidden" name="action" value="upload_photo">
                        <input type="hidden" name="latitude" id="photo_latitude" value="">
                        <input type="hidden" name="longitude" id="photo_longitude" value="">
                        
                        <div class="upload-area" onclick="document.getElementById('photo-input').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p><?php echo $langs->trans('ClickOrDragPhoto'); ?></p>
                            <input type="file" id="photo-input" name="photo" accept="image/*" capture="environment" style="display: none;" onchange="previewPhoto(this)">
                        </div>
                        
                        <div id="photo-preview-container" style="display: none;">
                            <img id="photo-preview" src="" alt="Preview">
                            <div class="form-group">
                                <label><?php echo $langs->trans('PhotoType'); ?></label>
                                <select name="photo_type">
                                    <option value="general"><?php echo $langs->trans('General'); ?></option>
                                    <option value="before"><?php echo $langs->trans('Before'); ?></option>
                                    <option value="after"><?php echo $langs->trans('After'); ?></option>
                                    <option value="delivery"><?php echo $langs->trans('Delivery'); ?></option>
                                    <option value="issue"><?php echo $langs->trans('Issue'); ?></option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><?php echo $langs->trans('Description'); ?></label>
                                <textarea name="photo_description" rows="2" placeholder="<?php echo $langs->trans('OptionalDescription'); ?>"></textarea>
                            </div>
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-upload"></i> <?php echo $langs->trans('Upload'); ?>
                            </button>
                        </div>
                    </form>
                </div>
                <?php } ?>
                
                <!-- Photos Gallery -->
                <?php if (!empty($photos)) { ?>
                <div class="photos-gallery">
                    <?php foreach ($photos as $photo) { ?>
                    <div class="photo-item">
                        <img src="<?php echo $photo->getThumbnailUrl(200, 200); ?>" 
                             alt="<?php echo dol_escape_htmltag($photo->filename); ?>"
                             onclick="openPhotoModal('<?php echo $photo->getPhotoUrl(); ?>')">
                        <div class="photo-info">
                            <span class="photo-type"><?php echo $langs->trans(ucfirst($photo->photo_type)); ?></span>
                            <span class="photo-date"><?php echo dol_print_date($photo->date_creation, 'dayhour'); ?></span>
                        </div>
                        <?php if (!empty($photo->description)) { ?>
                        <div class="photo-description"><?php echo dol_escape_htmltag($photo->description); ?></div>
                        <?php } ?>
                        <?php if ($user->rights->zonajob->photo->upload || $photo->fk_user_creat == $user->id) { ?>
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>?id=<?php echo $id; ?>&tab=photos&action=delete_photo&photo_id=<?php echo $photo->id; ?>&token=<?php echo newToken(); ?>" 
                           class="photo-delete" onclick="return confirm('<?php echo $langs->trans('ConfirmDelete'); ?>')">
                            <i class="fas fa-trash"></i>
                        </a>
                        <?php } ?>
                    </div>
                    <?php } ?>
                </div>
                <?php } else { ?>
                <p class="no-data"><?php echo $langs->trans('NoPhotos'); ?></p>
                <?php } ?>
            </div>
        </div>
        <?php } ?>

        <?php if ($tab == 'signature') { ?>
        <!-- SIGNATURE TAB -->
        <div class="tab-pane active" id="tab-signature">
            <div class="signature-section">
                <h3><i class="fas fa-signature"></i> <?php echo $langs->trans('ClientSignature'); ?></h3>
                
                <!-- New Signature Form -->
                <?php if (!empty($user->rights->zonajob->signature->request)) { ?>
                <div class="new-signature-form">
                    <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?id=<?php echo $id; ?>&tab=signature" id="signature-form">
                        <input type="hidden" name="token" value="<?php echo newToken(); ?>">
                        <input type="hidden" name="action" value="save_signature">
                        <input type="hidden" name="signature_data" id="signature_data" value="">
                        <input type="hidden" name="sig_latitude" id="sig_latitude" value="">
                        <input type="hidden" name="sig_longitude" id="sig_longitude" value="">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><?php echo $langs->trans('SignerName'); ?> *</label>
                                <input type="text" name="signer_name" id="signer_name" required placeholder="<?php echo $langs->trans('FullName'); ?>">
                            </div>
                            <div class="form-group">
                                <label><?php echo $langs->trans('Contact'); ?></label>
                                <select name="fk_socpeople" id="fk_socpeople" onchange="fillContactInfo(this)">
                                    <option value=""><?php echo $langs->trans('SelectContact'); ?></option>
                                    <?php foreach ($recipients as $r) { ?>
                                    <option value="<?php echo $r['id']; ?>" data-email="<?php echo dol_escape_htmltag($r['email']); ?>" data-phone="<?php echo dol_escape_htmltag($r['phone']); ?>" data-name="<?php echo dol_escape_htmltag($r['name']); ?>">
                                        <?php echo dol_escape_htmltag($r['label']); ?>
                                    </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><?php echo $langs->trans('Email'); ?></label>
                                <input type="email" name="signer_email" id="signer_email">
                            </div>
                            <div class="form-group">
                                <label><?php echo $langs->trans('Phone'); ?></label>
                                <input type="tel" name="signer_phone" id="signer_phone">
                            </div>
                        </div>

                        <div class="form-group">
                            <label><?php echo $langs->trans('PDFTemplate'); ?></label>
                            <select name="pdf_model">
                                <?php foreach ($pdfModels as $modelKey => $modelLabel) { 
                                    $selected = ($modelKey == $defaultPdfModel) ? 'selected' : '';
                                ?>
                                <option value="<?php echo dol_escape_htmltag($modelKey); ?>" <?php echo $selected; ?>><?php echo dol_escape_htmltag($modelLabel); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        
                        <!-- Signature Canvas -->
                        <div class="signature-canvas-container">
                            <label><?php echo $langs->trans('SignHere'); ?> *</label>
                            <canvas id="signature-canvas" width="100%" height="200"></canvas>
                            <div class="canvas-controls">
                                <button type="button" class="btn-clear" onclick="clearSignature()">
                                    <i class="fas fa-eraser"></i> <?php echo $langs->trans('Clear'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Post-Signature Options -->
                        <div class="post-signature-options">
                            <h4><?php echo $langs->trans('AfterSignature'); ?></h4>
                            
                            <?php if ($order->statut == Commande::STATUS_DRAFT) { ?>
                            <div class="form-check">
                                <label>
                                    <input type="checkbox" name="validate_order" value="1">
                                    <?php echo $langs->trans('ValidateOrderAfterSign'); ?>
                                </label>
                            </div>
                            <?php } ?>
                            
                            <div class="form-group">
                                <label><?php echo $langs->trans('ChangeStatusAfterSign'); ?></label>
                                <select name="new_status">
                                    <option value="0"><?php echo $langs->trans('NoChange'); ?></option>
                                    <?php if ($order->statut == Commande::STATUS_DRAFT) { ?>
                                    <option value="<?php echo Commande::STATUS_VALIDATED; ?>"><?php echo $langs->trans('StatusOrderValidated'); ?></option>
                                    <?php } ?>
                                    <?php if ($order->statut == Commande::STATUS_VALIDATED || $order->statut == Commande::STATUS_DRAFT) { ?>
                                    <option value="<?php echo Commande::STATUS_SHIPMENTONPROCESS; ?>"><?php echo $langs->trans('StatusOrderSent'); ?></option>
                                    <?php } ?>
                                    <?php if ($order->statut <= Commande::STATUS_SHIPMENTONPROCESS) { ?>
                                    <option value="<?php echo Commande::STATUS_CLOSED; ?>"><?php echo $langs->trans('StatusOrderDelivered'); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-primary btn-sign" onclick="return prepareSignature()">
                                <i class="fas fa-check"></i> <?php echo $langs->trans('SignAndSave'); ?>
                            </button>
                        </div>
                    </form>
                </div>
                <?php } ?>
            </div>
        </div>
        <?php } ?>

        <?php if ($tab == 'send') { ?>
        <!-- SEND TAB -->
        <div class="tab-pane active" id="tab-send">
            <div class="send-section">
                <h3><i class="fas fa-paper-plane"></i> <?php echo $langs->trans('SendOrder'); ?></h3>
                
                <!-- Document selection for sending -->
                <?php
                $upload_dir = $conf->commande->dir_output.'/'.$order->ref;
                $filearray_send = dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$', 'date', SORT_DESC, 1);
                if (!empty($filearray_send)) { ?>
                <div class="send-documents-select">
                    <h4><i class="fas fa-paperclip"></i> <?php echo $langs->trans('SelectDocumentsToSend'); ?></h4>
                    <div class="docs-grid">
                        <?php foreach ($filearray_send as $file) { 
                            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                            $isPdf = ($ext == 'pdf');
                            $isImage = in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'webp'));
                            $iconClass = $isPdf ? 'fa-file-pdf text-danger' : ($isImage ? 'fa-file-image text-primary' : 'fa-file text-secondary');
                        ?>
                        <label class="doc-card">
                            <input type="checkbox" class="doc-select" value="<?php echo dol_escape_htmltag($file['name']); ?>">
                            <div class="doc-card-inner">
                                <div class="doc-icon">
                                    <i class="fas <?php echo $iconClass; ?>"></i>
                                    <span class="doc-check"><i class="fas fa-check-circle"></i></span>
                                </div>
                                <div class="doc-info">
                                    <span class="doc-name" title="<?php echo dol_escape_htmltag($file['name']); ?>"><?php echo dol_trunc(dol_escape_htmltag($file['name']), 28); ?></span>
                                    <span class="doc-meta"><?php echo dol_print_size($file['size']); ?> &bull; <?php echo dol_print_date($file['date'], 'day'); ?></span>
                                </div>
                            </div>
                        </label>
                        <?php } ?>
                    </div>
                    <div class="docs-actions">
                        <button type="button" class="btn-select-all" onclick="toggleAllDocs(true)"><i class="fas fa-check-double"></i> <?php echo $langs->trans('SelectAll'); ?></button>
                        <button type="button" class="btn-select-none" onclick="toggleAllDocs(false)"><i class="fas fa-times"></i> <?php echo $langs->trans('DeselectAll'); ?></button>
                        <span class="docs-count"><span id="selected-docs-count">0</span> <?php echo $langs->trans('Selected'); ?></span>
                    </div>
                </div>
                <?php } ?>

                <!-- Send Options -->
                <div class="send-options">
                    <?php if (getDolGlobalString('ZONAJOB_SEND_WHATSAPP') && !empty($conf->whatsapp->enabled)) { ?>
                    <!-- WhatsApp Send -->
                    <div class="send-option">
                        <h4><i class="fab fa-whatsapp"></i> <?php echo $langs->trans('SendByWhatsApp'); ?></h4>
                        <form id="form-send-whatsapp" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?id=<?php echo $id; ?>&tab=send">
                            <input type="hidden" name="token" value="<?php echo newToken(); ?>">
                            <input type="hidden" name="action" value="send_whatsapp">
                            
                            <div class="form-group">
                                <label><?php echo $langs->trans('SelectRecipient'); ?></label>
                                <select name="wa_contact_id" onchange="fillWhatsAppPhone(this)">
                                    <option value=""><?php echo $langs->trans('SelectContact'); ?></option>
                                    <?php foreach ($recipients as $r) { 
                                        if (!empty($r['phone'])) { ?>
                                    <option value="<?php echo $r['id']; ?>" data-phone="<?php echo dol_escape_htmltag($r['phone']); ?>">
                                        <?php echo dol_escape_htmltag($r['label']); ?> - <?php echo dol_escape_htmltag($r['phone']); ?>
                                    </option>
                                    <?php }
                                    } ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><?php echo $langs->trans('PhoneNumber'); ?> *</label>
                                <input type="tel" name="wa_phone" id="wa_phone" required placeholder="+34612345678">
                            </div>
                            
                            <div class="form-group">
                                <label><?php echo $langs->trans('Message'); ?> *</label>
                                <textarea name="wa_message" rows="4" required><?php 
                                    echo sprintf($langs->trans('DefaultWhatsAppMessage'), $order->ref, $order->thirdparty->name, price($order->total_ttc, 1, $langs, 1, -1, -1, $conf->currency));
                                ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn-whatsapp">
                                <i class="fab fa-whatsapp"></i> <?php echo $langs->trans('SendWhatsApp'); ?>
                            </button>
                        </form>
                    </div>
                    <?php } ?>
                    
                    <?php if (getDolGlobalString('ZONAJOB_SEND_EMAIL')) { ?>
                    <!-- Email Send -->
                    <div class="send-option">
                        <h4><i class="fas fa-envelope"></i> <?php echo $langs->trans('SendByEmail'); ?></h4>
                        <form id="form-send-email" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?id=<?php echo $id; ?>&tab=send">
                            <input type="hidden" name="token" value="<?php echo newToken(); ?>">
                            <input type="hidden" name="action" value="send_email">
                            
                            <div class="form-group">
                                <label><?php echo $langs->trans('SelectRecipient'); ?></label>
                                <select name="email_contact_id" onchange="fillEmailAddress(this)">
                                    <option value=""><?php echo $langs->trans('SelectContact'); ?></option>
                                    <?php foreach ($recipients as $r) { 
                                        if (!empty($r['email'])) { ?>
                                    <option value="<?php echo $r['id']; ?>" data-email="<?php echo dol_escape_htmltag($r['email']); ?>">
                                        <?php echo dol_escape_htmltag($r['label']); ?> - <?php echo dol_escape_htmltag($r['email']); ?>
                                    </option>
                                    <?php }
                                    } ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><?php echo $langs->trans('Email'); ?> *</label>
                                <input type="email" name="email_to" id="email_to" required placeholder="email@example.com">
                            </div>
                            
                            <div class="form-group">
                                <label><?php echo $langs->trans('Subject'); ?> *</label>
                                <input type="text" name="email_subject" required value="<?php echo sprintf($langs->trans('DefaultEmailSubject'), $order->ref); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label><?php echo $langs->trans('Message'); ?> *</label>
                                <textarea name="email_message" rows="6" required><?php 
                                    echo sprintf($langs->trans('DefaultEmailMessage'), $order->thirdparty->name, $order->ref, price($order->total_ttc, 1, $langs, 1, -1, -1, $conf->currency));
                                ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="attach_pdf" value="1" checked>
                                    <?php echo $langs->trans('AttachPDF'); ?>
                                </label>
                            </div>
                            
                            <button type="submit" class="btn-email">
                                <i class="fas fa-envelope"></i> <?php echo $langs->trans('SendEmail'); ?>
                            </button>
                        </form>
                    </div>
                    <?php } ?>
                </div>
                
                <!-- Send History -->
                <?php if (!empty($sendHistory)) { ?>
                <div class="send-history">
                    <h4><i class="fas fa-history"></i> <?php echo $langs->trans('SendHistory'); ?></h4>
                    <div class="history-list">
                        <?php foreach ($sendHistory as $h) { ?>
                        <div class="history-item <?php echo ($h['status'] == 1) ? 'success' : (($h['status'] == -1) ? 'failed' : 'pending'); ?>">
                            <div class="history-type">
                                <?php if ($h['send_type'] == 'whatsapp') { ?>
                                    <i class="fab fa-whatsapp"></i>
                                <?php } else { ?>
                                    <i class="fas fa-envelope"></i>
                                <?php } ?>
                            </div>
                            <div class="history-info">
                                <span class="history-recipient"><?php echo dol_escape_htmltag($h['recipient']); ?></span>
                                <span class="history-date"><?php echo dol_print_date($h['date_creation'], 'dayhour'); ?></span>
                                <?php if ($h['status'] == -1 && !empty($h['error_message'])) { ?>
                                <span class="history-error"><?php echo dol_escape_htmltag($h['error_message']); ?></span>
                                <?php } ?>
                            </div>
                            <div class="history-status">
                                <?php if ($h['status'] == 1) { ?>
                                    <span class="badge-success"><?php echo $langs->trans('Sent'); ?></span>
                                <?php } elseif ($h['status'] == -1) { ?>
                                    <span class="badge-danger"><?php echo $langs->trans('Failed'); ?></span>
                                <?php } else { ?>
                                    <span class="badge-warning"><?php echo $langs->trans('Pending'); ?></span>
                                <?php } ?>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>
        <?php } ?>

        <?php if ($tab == 'documents') { ?>
        <!-- DOCUMENTS TAB -->
        <div class="tab-pane active" id="tab-documents">
            <div class="documents-section">
                <h3><i class="fas fa-file-pdf"></i> <?php echo $langs->trans('Documents'); ?></h3>
                
                <?php
                // List existing documents
                $upload_dir = $conf->commande->dir_output.'/'.$order->ref;
                $filearray = dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$', 'name', SORT_ASC, 1);
                
                if (!empty($filearray)) { ?>
                <div class="documents-list">
                    <?php foreach ($filearray as $file) { 
                        $isPdf = (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) == 'pdf');
                        ?>
                    <div class="document-item">
                        <div class="document-icon">
                            <?php if ($isPdf) { ?>
                                <i class="fas fa-file-pdf"></i>
                            <?php } else { ?>
                                <i class="fas fa-file"></i>
                            <?php } ?>
                        </div>
                        <div class="document-info">
                            <span class="document-name"><?php echo dol_escape_htmltag($file['name']); ?></span>
                            <span class="document-size"><?php echo dol_print_size($file['size']); ?></span>
                            <span class="document-date"><?php echo dol_print_date($file['date'], 'dayhour'); ?></span>
                        </div>
                        <div class="document-actions">
                            <a href="<?php echo DOL_URL_ROOT; ?>/document.php?modulepart=commande&file=<?php echo urlencode($order->ref.'/'.$file['name']); ?>" 
                               class="btn-download" target="_blank">
                                <i class="fas fa-download"></i>
                            </a>
                            <?php if ($isPdf) { ?>
                            <a href="<?php echo DOL_URL_ROOT; ?>/document.php?modulepart=commande&file=<?php echo urlencode($order->ref.'/'.$file['name']); ?>" 
                               class="btn-view" target="_blank">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php } ?>
                        </div>
                    </div>
                    <?php } ?>
                </div>
                <?php } else { ?>
                <p class="no-data"><?php echo $langs->trans('NoDocuments'); ?></p>
                <?php } ?>
                
                <!-- Generate PDF Button -->
                <div class="generate-pdf">
                    <a href="<?php echo DOL_URL_ROOT; ?>/commande/card.php?id=<?php echo $order->id; ?>&action=builddoc&token=<?php echo newToken(); ?>" 
                       class="btn-generate" target="_blank">
                        <i class="fas fa-file-pdf"></i> <?php echo $langs->trans('GeneratePDF'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php } ?>

    </div>
</div>

<!-- Photo Modal -->
<div id="photo-modal" class="modal" onclick="closePhotoModal()">
    <span class="modal-close">&times;</span>
    <img id="modal-image" src="" alt="">
</div>

<script>
// Tab scroll functionality
function scrollTabs(amount) {
    var tabsEl = document.getElementById('orderTabs');
    if (tabsEl) {
        tabsEl.scrollBy({ left: amount, behavior: 'smooth' });
    }
}

function updateTabScrollIndicators() {
    var tabsEl = document.getElementById('orderTabs');
    if (!tabsEl) return;
    
    var leftIndicator = document.querySelector('.tab-scroll-left');
    var rightIndicator = document.querySelector('.tab-scroll-right');
    
    if (!leftIndicator || !rightIndicator) return;
    
    var scrollLeft = tabsEl.scrollLeft;
    var scrollWidth = tabsEl.scrollWidth;
    var clientWidth = tabsEl.clientWidth;
    
    // Show left indicator if scrolled right
    if (scrollLeft > 5) {
        leftIndicator.classList.add('visible');
    } else {
        leftIndicator.classList.remove('visible');
    }
    
    // Show right indicator if can scroll more to the right
    if (scrollLeft + clientWidth < scrollWidth - 5) {
        rightIndicator.classList.add('visible');
    } else {
        rightIndicator.classList.remove('visible');
    }
}

// Initialize tab scroll indicators
document.addEventListener('DOMContentLoaded', function() {
    var tabsEl = document.getElementById('orderTabs');
    if (tabsEl) {
        updateTabScrollIndicators();
        tabsEl.addEventListener('scroll', updateTabScrollIndicators);
        window.addEventListener('resize', updateTabScrollIndicators);
    }
});

// Signature Canvas
var canvas, ctx, isDrawing = false, lastX, lastY;

document.addEventListener('DOMContentLoaded', function() {
    canvas = document.getElementById('signature-canvas');
    if (canvas) {
        ctx = canvas.getContext('2d');
        
        // Resize canvas to container width
        var container = canvas.parentElement;
        canvas.width = container.offsetWidth - 20;
        
        // Set drawing style
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        
        // Mouse events
        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);
        
        // Touch events
        canvas.addEventListener('touchstart', handleTouchStart, {passive: false});
        canvas.addEventListener('touchmove', handleTouchMove, {passive: false});
        canvas.addEventListener('touchend', stopDrawing);
    }
    
    // Get geolocation only when needed (on user interaction)
    function getGeolocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(pos) {
                var photoLat = document.getElementById('photo_latitude');
                var photoLng = document.getElementById('photo_longitude');
                var sigLat = document.getElementById('sig_latitude');
                var sigLng = document.getElementById('sig_longitude');
                
                if (photoLat) photoLat.value = pos.coords.latitude;
                if (photoLng) photoLng.value = pos.coords.longitude;
                if (sigLat) sigLat.value = pos.coords.latitude;
                if (sigLng) sigLng.value = pos.coords.longitude;
            }, function(error) {
                console.log('Geolocation error:', error.message);
            });
        }
    }
    
    // Trigger geolocation when user interacts with photo or signature tabs
    var photoTab = document.querySelector('[data-tab="photos"]');
    var sigTab = document.querySelector('[data-tab="signature"]');
    if (photoTab) photoTab.addEventListener('click', getGeolocation);
    if (sigTab) sigTab.addEventListener('click', getGeolocation);
});

function startDrawing(e) {
    isDrawing = true;
    var rect = canvas.getBoundingClientRect();
    lastX = e.clientX - rect.left;
    lastY = e.clientY - rect.top;
}

function draw(e) {
    if (!isDrawing) return;
    e.preventDefault();
    
    var rect = canvas.getBoundingClientRect();
    var x = e.clientX - rect.left;
    var y = e.clientY - rect.top;
    
    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
    ctx.lineTo(x, y);
    ctx.stroke();
    
    lastX = x;
    lastY = y;
}

function handleTouchStart(e) {
    e.preventDefault();
    var touch = e.touches[0];
    var rect = canvas.getBoundingClientRect();
    isDrawing = true;
    lastX = touch.clientX - rect.left;
    lastY = touch.clientY - rect.top;
}

function handleTouchMove(e) {
    if (!isDrawing) return;
    e.preventDefault();
    
    var touch = e.touches[0];
    var rect = canvas.getBoundingClientRect();
    var x = touch.clientX - rect.left;
    var y = touch.clientY - rect.top;
    
    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
    ctx.lineTo(x, y);
    ctx.stroke();
    
    lastX = x;
    lastY = y;
}

function stopDrawing() {
    isDrawing = false;
}

function clearSignature() {
    if (ctx) {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    }
}

function prepareSignature() {
    if (!canvas) return true;
    
    // Check if canvas has content
    var blank = document.createElement('canvas');
    blank.width = canvas.width;
    blank.height = canvas.height;
    
    if (canvas.toDataURL() === blank.toDataURL()) {
        alert('<?php echo $langs->trans('PleaseSign'); ?>');
        return false;
    }
    
    document.getElementById('signature_data').value = canvas.toDataURL('image/png');
    return true;
}

function fillContactInfo(select) {
    var option = select.options[select.selectedIndex];
    if (option.value) {
        document.getElementById('signer_name').value = option.dataset.name || '';
        document.getElementById('signer_email').value = option.dataset.email || '';
        document.getElementById('signer_phone').value = option.dataset.phone || '';
    }
}

function fillWhatsAppPhone(select) {
    var option = select.options[select.selectedIndex];
    if (option.dataset.phone) {
        document.getElementById('wa_phone').value = option.dataset.phone;
    }
}

function fillEmailAddress(select) {
    var option = select.options[select.selectedIndex];
    if (option.dataset.email) {
        document.getElementById('email_to').value = option.dataset.email;
    }
}

function toggleAddContactForm() {
    var form = document.getElementById('add-contact-form');
    form.style.display = (form.style.display === 'none') ? 'block' : 'none';
}

function previewPhoto(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('photo-preview').src = e.target.result;
            document.getElementById('photo-preview-container').style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function openPhotoModal(src) {
    document.getElementById('modal-image').src = src;
    document.getElementById('photo-modal').style.display = 'flex';
}

function closePhotoModal() {
    document.getElementById('photo-modal').style.display = 'none';
}

// Collect selected documents and attach to form before submit
function attachSelectedDocsToForm(formId) {
    var form = document.getElementById(formId);
    if (!form) return;
    // Remove previous hidden inputs
    var olds = form.querySelectorAll('input[name="selected_docs[]"]');
    olds.forEach(function(n){ n.parentNode.removeChild(n); });
    // Add current selections
    var checks = document.querySelectorAll('.doc-select:checked');
    checks.forEach(function(ch){
        var inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'selected_docs[]';
        inp.value = ch.value;
        form.appendChild(inp);
    });
}

// Toggle all document checkboxes
function toggleAllDocs(checked) {
    var checks = document.querySelectorAll('.doc-select');
    checks.forEach(function(ch){ ch.checked = checked; updateDocCard(ch); });
    updateSelectedCount();
}

// Update card visual state
function updateDocCard(checkbox) {
    var card = checkbox.closest('.doc-card');
    if (card) {
        if (checkbox.checked) {
            card.classList.add('selected');
        } else {
            card.classList.remove('selected');
        }
    }
}

// Update selected count
function updateSelectedCount() {
    var count = document.querySelectorAll('.doc-select:checked').length;
    var el = document.getElementById('selected-docs-count');
    if (el) el.textContent = count;
}

document.addEventListener('DOMContentLoaded', function(){
    var waForm = document.getElementById('form-send-whatsapp');
    var emForm = document.getElementById('form-send-email');
    if (waForm) {
        waForm.addEventListener('submit', function(){ attachSelectedDocsToForm('form-send-whatsapp'); });
    }
    if (emForm) {
        emForm.addEventListener('submit', function(){ attachSelectedDocsToForm('form-send-email'); });
    }
    
    // Add change listeners to doc checkboxes
    document.querySelectorAll('.doc-select').forEach(function(ch){
        ch.addEventListener('change', function(){ updateDocCard(this); updateSelectedCount(); });
    });
});
</script>

<?php
zonaempleado_print_footer();
