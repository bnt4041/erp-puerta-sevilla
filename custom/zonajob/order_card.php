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
require_once DOL_DOCUMENT_ROOT.'/custom/zonaempleado/lib/zonaempleado.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/zonajob/class/zonajobsignature.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/zonajob/class/zonajobphoto.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/zonajob/class/zonajobsender.class.php';

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

// Load extrafields
$extrafields = new ExtraFields($db);
$extrafields->fetch_name_optionals_label($order->table_element);

// Load linked objects
$order->fetchObjectLinked();

/*
 * Actions
 */

// Upload photo
if ($action == 'upload_photo' && !empty($user->rights->zonajob->photo->upload)) {
    if (!empty($_FILES['photo']['tmp_name'])) {
        $photoObj = new ZonaJobPhoto($db);
        $result = $photoObj->uploadPhoto(
            $order->id,
            $_FILES['photo'],
            $user,
            GETPOST('photo_type', 'alpha'),
            GETPOST('photo_description', 'restricthtml'),
            GETPOST('latitude', 'alpha'),
            GETPOST('longitude', 'alpha')
        );
        
        if ($result > 0) {
            setEventMessages($langs->trans('PhotoUploaded'), null, 'mesgs');
        } else {
            setEventMessages($photoObj->error, null, 'errors');
        }
    }
    $action = '';
}

// Delete photo
if ($action == 'delete_photo' && GETPOSTINT('photo_id') > 0) {
    $photoObj = new ZonaJobPhoto($db);
    $photoObj->fetch(GETPOSTINT('photo_id'));
    if ($photoObj->id > 0 && ($user->rights->zonajob->photo->upload || $photoObj->fk_user_creat == $user->id)) {
        $result = $photoObj->delete($user);
        if ($result > 0) {
            setEventMessages($langs->trans('PhotoDeleted'), null, 'mesgs');
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
            
            // Check if status change/validation requested
            $validate_order = GETPOSTINT('validate_order');
            $new_status = GETPOSTINT('new_status');
            
            // Validate order if requested and user has permission
            if ($validate_order && $order->statut == Commande::STATUS_DRAFT && !empty($user->rights->commande->valider)) {
                $result_valid = $order->valid($user);
                if ($result_valid > 0) {
                    setEventMessages($langs->trans('OrderValidated'), null, 'mesgs');
                    // Reload order to get new status
                    $order->fetch($order->id);
                } else {
                    setEventMessages($order->error, $order->errors, 'errors');
                }
            }
            
            // Change status if requested
            if ($new_status > 0 && $new_status != $order->statut) {
                if ($new_status == Commande::STATUS_SHIPMENTONPROCESS && $order->statut == Commande::STATUS_VALIDATED) {
                    $result_status = $order->setStatut(Commande::STATUS_SHIPMENTONPROCESS);
                    if ($result_status > 0) {
                        setEventMessages($langs->trans('StatusChanged'), null, 'mesgs');
                    } else {
                        setEventMessages($order->error, $order->errors, 'errors');
                    }
                } elseif ($new_status == Commande::STATUS_CLOSED && $order->statut >= Commande::STATUS_VALIDATED) {
                    $result_close = $order->cloture($user);
                    if ($result_close > 0) {
                        setEventMessages($langs->trans('OrderClosed'), null, 'mesgs');
                    } else {
                        setEventMessages($order->error, $order->errors, 'errors');
                    }
                } elseif ($new_status == Commande::STATUS_VALIDATED && $order->statut == Commande::STATUS_DRAFT) {
                    // If new_status is validated but order wasn't validated yet
                    if (empty($validate_order) && !empty($user->rights->commande->valider)) {
                        $result_valid = $order->valid($user);
                        if ($result_valid > 0) {
                            setEventMessages($langs->trans('OrderValidated'), null, 'mesgs');
                        } else {
                            setEventMessages($order->error, $order->errors, 'errors');
                        }
                    }
                }
            }
            
            // Redirect to confirm send
            header('Location: '.$_SERVER['PHP_SELF'].'?id='.$order->id.'&tab=send&signed=1');
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
    
    if (!empty($phone) && !empty($message)) {
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
        </div>
        <div class="order-status-badge">
            <?php echo $order->getLibStatut(5); ?>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="order-tabs">
        <a href="?id=<?php echo $id; ?>&tab=info" class="tab-link <?php echo ($tab == 'info') ? 'active' : ''; ?>">
            <i class="fas fa-info-circle"></i>
            <span><?php echo $langs->trans('Info'); ?></span>
        </a>
        <a href="?id=<?php echo $id; ?>&tab=lines" class="tab-link <?php echo ($tab == 'lines') ? 'active' : ''; ?>">
            <i class="fas fa-list"></i>
            <span><?php echo $langs->trans('Lines'); ?></span>
        </a>
        <a href="?id=<?php echo $id; ?>&tab=photos" class="tab-link <?php echo ($tab == 'photos') ? 'active' : ''; ?>">
            <i class="fas fa-camera"></i>
            <span><?php echo $langs->trans('Photos'); ?> (<?php echo count($photos); ?>)</span>
        </a>
        <a href="?id=<?php echo $id; ?>&tab=signature" class="tab-link <?php echo ($tab == 'signature') ? 'active' : ''; ?>">
            <i class="fas fa-signature"></i>
            <span><?php echo $langs->trans('Signature'); ?></span>
        </a>
        <a href="?id=<?php echo $id; ?>&tab=send" class="tab-link <?php echo ($tab == 'send') ? 'active' : ''; ?>">
            <i class="fas fa-paper-plane"></i>
            <span><?php echo $langs->trans('Send'); ?></span>
        </a>
        <a href="?id=<?php echo $id; ?>&tab=documents" class="tab-link <?php echo ($tab == 'documents') ? 'active' : ''; ?>">
            <i class="fas fa-file-pdf"></i>
            <span><?php echo $langs->trans('Documents'); ?></span>
        </a>
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

            <!-- Extrafields -->
            <?php if (!empty($order->array_options)) { ?>
            <div class="info-section">
                <h3><i class="fas fa-th-list"></i> <?php echo $langs->trans('ExtraFields'); ?></h3>
                <div class="extrafields-grid">
                    <?php
                    foreach ($order->array_options as $key => $value) {
                        if (empty($value)) continue;
                        $keyWithoutPrefix = preg_replace('/^options_/', '', $key);
                        $label = !empty($extrafields->attributes[$order->table_element]['label'][$keyWithoutPrefix]) 
                            ? $extrafields->attributes[$order->table_element]['label'][$keyWithoutPrefix] 
                            : $keyWithoutPrefix;
                        ?>
                        <div class="extrafield-item">
                            <label><?php echo $langs->trans($label); ?></label>
                            <span><?php echo dol_escape_htmltag($value); ?></span>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
            <?php } ?>

            <!-- Public/Private Notes -->
            <?php if (!empty($order->note_public) || !empty($order->note_private)) { ?>
            <div class="info-section">
                <h3><i class="fas fa-sticky-note"></i> <?php echo $langs->trans('Notes'); ?></h3>
                <?php if (!empty($order->note_public)) { ?>
                <div class="note-card note-public">
                    <label><?php echo $langs->trans('NotePublic'); ?></label>
                    <div class="note-content"><?php echo dol_htmlentitiesbr($order->note_public); ?></div>
                </div>
                <?php } ?>
                <?php if (!empty($order->note_private)) { ?>
                <div class="note-card note-private">
                    <label><?php echo $langs->trans('NotePrivate'); ?></label>
                    <div class="note-content"><?php echo dol_htmlentitiesbr($order->note_private); ?></div>
                </div>
                <?php } ?>
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
                                <select name="fk_product" id="fk_product" onchange="updateProductInfo(this)">
                                    <option value="0"><?php echo $langs->trans('FreeText'); ?></option>
                                    <?php
                                    require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
                                    $productstatic = new Product($db);
                                    // Get active products and services
                                    $sql_prod = "SELECT p.rowid, p.ref, p.label, p.price, p.tva_tx, p.fk_product_type";
                                    $sql_prod .= " FROM ".MAIN_DB_PREFIX."product as p";
                                    $sql_prod .= " WHERE p.entity IN (".getEntity('product').")";
                                    $sql_prod .= " AND p.tosell = 1";
                                    $sql_prod .= " ORDER BY p.ref ASC";
                                    $sql_prod .= " LIMIT 500"; // Limit for performance
                                    $res_prod = $db->query($sql_prod);
                                    if ($res_prod) {
                                        while ($obj_prod = $db->fetch_object($res_prod)) {
                                            $prod_type = ($obj_prod->fk_product_type == 0) ? $langs->trans('Product') : $langs->trans('Service');
                                            echo '<option value="'.$obj_prod->rowid.'" data-price="'.$obj_prod->price.'" data-vat="'.$obj_prod->tva_tx.'" data-desc="'.dol_escape_htmltag($obj_prod->label).'">';
                                            echo dol_escape_htmltag($obj_prod->ref.' - '.$obj_prod->label.' ('.$prod_type.')');
                                            echo '</option>';
                                        }
                                    }
                                    ?>
                                </select>
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
                                    <?php
                                    require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
                                    $tvas = get_default_tva($mysoc, $order->thirdparty);
                                    $vat_rates = array(0, 4, 10, 21); // Common Spanish VAT rates
                                    foreach ($vat_rates as $vat) {
                                        $selected = ($vat == $tvas['tva_tx']) ? 'selected' : '';
                                        echo '<option value="'.$vat.'" '.$selected.'>'.$vat.'%</option>';
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
                
                <!-- Existing Signatures -->
                <?php if (!empty($signatures)) { ?>
                <div class="existing-signatures">
                    <h4><?php echo $langs->trans('SignatureHistory'); ?></h4>
                    <?php foreach ($signatures as $sig) { ?>
                    <div class="signature-record <?php echo ($sig->status == 1) ? 'signed' : 'pending'; ?>">
                        <div class="sig-header">
                            <span class="sig-ref"><?php echo dol_escape_htmltag($sig->ref); ?></span>
                            <span class="sig-status"><?php echo $sig->getLibStatut(2); ?></span>
                        </div>
                        <div class="sig-info">
                            <p><strong><?php echo $langs->trans('SignerName'); ?>:</strong> <?php echo dol_escape_htmltag($sig->signer_name); ?></p>
                            <?php if (!empty($sig->signer_email)) { ?>
                            <p><strong><?php echo $langs->trans('Email'); ?>:</strong> <?php echo dol_escape_htmltag($sig->signer_email); ?></p>
                            <?php } ?>
                            <?php if ($sig->date_signature) { ?>
                            <p><strong><?php echo $langs->trans('SignedOn'); ?>:</strong> <?php echo dol_print_date($sig->date_signature, 'dayhour'); ?></p>
                            <?php } ?>
                        </div>
                        <?php if ($sig->status == 1 && !empty($sig->signature_file)) { ?>
                        <div class="sig-image">
                            <img src="<?php echo DOL_URL_ROOT; ?>/custom/zonajob/viewsignature.php?file=<?php echo urlencode($sig->signature_file); ?>" alt="Signature">
                        </div>
                        <?php } ?>
                    </div>
                    <?php } ?>
                </div>
                <?php } ?>
                
                <!-- New Signature Form -->
                <?php if (!empty($user->rights->zonajob->signature->request)) { ?>
                <div class="new-signature-form">
                    <h4><?php echo $langs->trans('NewSignature'); ?></h4>
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
                            
                            <?php if ($order->statut == Commande::STATUS_DRAFT && !empty($user->rights->commande->valider)) { ?>
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
                
                <!-- Send Options -->
                <div class="send-options">
                    <?php if (getDolGlobalString('ZONAJOB_SEND_WHATSAPP') && !empty($conf->whatsapp->enabled)) { ?>
                    <!-- WhatsApp Send -->
                    <div class="send-option">
                        <h4><i class="fab fa-whatsapp"></i> <?php echo $langs->trans('SendByWhatsApp'); ?></h4>
                        <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?id=<?php echo $id; ?>&tab=send">
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
                        <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?id=<?php echo $id; ?>&tab=send">
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
</script>

<?php
zonaempleado_print_footer();
