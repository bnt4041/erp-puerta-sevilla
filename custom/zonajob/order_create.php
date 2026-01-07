<?php
/* Copyright (C) 2025 ZonaJob Dev
 *
 * Create new order - Responsive interface
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
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/zonaempleado/lib/zonaempleado.lib.php';

// Load translations
$langs->loadLangs(array("orders", "companies", "products", "zonajob@zonajob", "zonaempleado@zonaempleado"));

// Check permission
if (empty($user->rights->commande->creer)) {
    accessforbidden();
}

// Get parameters
$action = GETPOST('action', 'aZ09');
$socid = GETPOSTINT('socid');
$projectid = GETPOSTINT('projectid');

// Initialize order object
$order = new Commande($db);
$form = new Form($db);

/*
 * Actions
 */

if ($action == 'create') {
    $error = 0;
    
    // Get form data
    $socid = GETPOSTINT('socid');
    $projectid = GETPOSTINT('projectid');
    $ref_client = GETPOST('ref_client', 'alpha');
    $date_commande = dol_mktime(0, 0, 0, GETPOSTINT('date_commandemonth'), GETPOSTINT('date_commandeday'), GETPOSTINT('date_commandeyear'));
    $date_livraison = dol_mktime(0, 0, 0, GETPOSTINT('date_livraisonmonth'), GETPOSTINT('date_livraisonday'), GETPOSTINT('date_livraisonyear'));
    $note_public = GETPOST('note_public', 'restricthtml');
    $note_private = GETPOST('note_private', 'restricthtml');
    
    // Validate required fields
    if (empty($socid)) {
        setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Customer')), null, 'errors');
        $error++;
    }
    
    if (!$error) {
        $db->begin();
        
        // Create order
        $order->socid = $socid;
        $order->fk_project = $projectid > 0 ? $projectid : 0;
        $order->ref_client = $ref_client;
        $order->date = $date_commande ? $date_commande : dol_now();
        $order->date_livraison = $date_livraison;
        $order->note_public = $note_public;
        $order->note_private = $note_private;
        $order->cond_reglement_id = GETPOSTINT('cond_reglement_id');
        $order->mode_reglement_id = GETPOSTINT('mode_reglement_id');
        
        $result = $order->create($user);
        
        if ($result > 0) {
            // Add lines if any
            $line_products = GETPOST('line_product', 'array');
            $line_descriptions = GETPOST('line_description', 'array');
            $line_qtys = GETPOST('line_qty', 'array');
            $line_prices = GETPOST('line_price', 'array');
            $line_vats = GETPOST('line_vat', 'array');
            $line_discounts = GETPOST('line_discount', 'array');
            
            if (!empty($line_products) && is_array($line_products)) {
                foreach ($line_products as $i => $fk_product) {
                    $fk_product = intval($fk_product);
                    $description = isset($line_descriptions[$i]) ? $line_descriptions[$i] : '';
                    $qty = isset($line_qtys[$i]) ? price2num($line_qtys[$i]) : 1;
                    $price = isset($line_prices[$i]) ? price2num($line_prices[$i]) : 0;
                    $vat = isset($line_vats[$i]) ? price2num($line_vats[$i]) : 0;
                    $discount = isset($line_discounts[$i]) ? price2num($line_discounts[$i]) : 0;
                    
                    if ($qty > 0) {
                        // Get product info if selected
                        if ($fk_product > 0) {
                            $product = new Product($db);
                            $product->fetch($fk_product);
                            if (empty($description)) {
                                $description = $product->description;
                            }
                            if ($price <= 0) {
                                $price = $product->price;
                            }
                            if (empty($vat)) {
                                $vat = $product->tva_tx;
                            }
                        }
                        
                        $order->addline(
                            $description,
                            $price,
                            $qty,
                            $vat,
                            0, 0,
                            $fk_product,
                            $discount,
                            0, 0, 'HT', 0,
                            0, 0, 0, -1,
                            0, 0, 0, 0,
                            '', null, 0, '', 0, 0, ''
                        );
                    }
                }
            }
            
            $db->commit();
            
            setEventMessages($langs->trans('OrderCreated'), null, 'mesgs');
            header('Location: '.DOL_URL_ROOT.'/custom/zonajob/order_card.php?id='.$order->id);
            exit;
        } else {
            $db->rollback();
            setEventMessages($order->error, $order->errors, 'errors');
            $error++;
        }
    }
    
    $action = '';
}

/*
 * View
 */

$title = $langs->trans('NewOrder');

// Ask zonaempleado header to load ZonaJob assets
$GLOBALS['zonaempleado_extra_css'] = array('/custom/zonajob/css/zonajob.css.php');
$GLOBALS['zonaempleado_extra_js'] = array('/custom/zonajob/js/zonajob.js.php');

zonaempleado_print_header($title);
?>

<div class="zonajob-container">
    <!-- Header -->
    <div class="zonajob-header">
        <div class="header-content">
            <a href="<?php echo DOL_URL_ROOT; ?>/custom/zonajob/orders.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> <?php echo $langs->trans('BackToList'); ?>
            </a>
            <h1><i class="fas fa-plus-circle"></i> <?php echo $langs->trans('NewOrder'); ?></h1>
        </div>
    </div>

    <!-- Create Order Form -->
    <div class="order-create-form">
        <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" id="create-order-form">
            <input type="hidden" name="token" value="<?php echo newToken(); ?>">
            <input type="hidden" name="action" value="create">
            
            <!-- Customer Section -->
            <div class="form-section">
                <h3><i class="fas fa-building"></i> <?php echo $langs->trans('Customer'); ?></h3>
                <div class="form-row">
                    <div class="form-group full-width">
                        <label><?php echo $langs->trans('Customer'); ?> *</label>
                        <?php 
                        $selected = $socid > 0 ? $socid : '';
                        echo $form->select_company($selected, 'socid', 's.client IN (1,2,3)', 'SelectThirdParty', 0, 0, array(), 0, 'minwidth300');
                        ?>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><?php echo $langs->trans('RefCustomer'); ?></label>
                        <input type="text" name="ref_client" value="<?php echo GETPOST('ref_client', 'alpha'); ?>" placeholder="<?php echo $langs->trans('RefCustomer'); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php echo $langs->trans('Project'); ?></label>
                        <?php 
                        $formproject = null;
                        if (file_exists(DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php')) {
                            require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
                            $formproject = new FormProjets($db);
                            echo $formproject->select_projects($socid, $projectid, 'projectid', 0, 0, 1, 1, 0, 0, 0, '', 1, 0, 'minwidth200');
                        } else {
                            echo '<input type="text" name="projectid" value="">';
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Dates Section -->
            <div class="form-section">
                <h3><i class="fas fa-calendar-alt"></i> <?php echo $langs->trans('Dates'); ?></h3>
                <div class="form-row">
                    <div class="form-group">
                        <label><?php echo $langs->trans('OrderDate'); ?></label>
                        <?php echo $form->selectDate(dol_now(), 'date_commande', 0, 0, 0, '', 1, 1); ?>
                    </div>
                    <div class="form-group">
                        <label><?php echo $langs->trans('DeliveryDate'); ?></label>
                        <?php echo $form->selectDate(-1, 'date_livraison', 0, 0, 1, '', 1, 1); ?>
                    </div>
                </div>
            </div>
            
            <!-- Payment Section -->
            <div class="form-section">
                <h3><i class="fas fa-credit-card"></i> <?php echo $langs->trans('Payment'); ?></h3>
                <div class="form-row">
                    <div class="form-group">
                        <label><?php echo $langs->trans('PaymentConditions'); ?></label>
                        <?php echo $form->getSelectConditionsPaiements(0, 'cond_reglement_id', -1, 1); ?>
                    </div>
                    <div class="form-group">
                        <label><?php echo $langs->trans('PaymentMode'); ?></label>
                        <?php echo $form->select_types_paiements(0, 'mode_reglement_id', '', 0, 1, 0, 0, 1, 'minwidth200', 1); ?>
                    </div>
                </div>
            </div>
            
            <!-- Lines Section -->
            <div class="form-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3><i class="fas fa-list"></i> <?php echo $langs->trans('OrderLines'); ?></h3>
                    <button type="button" class="btn-add-line" onclick="addOrderLine()">
                        <i class="fas fa-plus"></i> <?php echo $langs->trans('AddLine'); ?>
                    </button>
                </div>
                
                <div id="order-lines-container">
                    <!-- Lines will be added here dynamically -->
                </div>
                
                <div class="lines-total" id="lines-total" style="display: none;">
                    <div class="total-row">
                        <span><?php echo $langs->trans('TotalHT'); ?></span>
                        <span id="total_ht">0.00 €</span>
                    </div>
                    <div class="total-row">
                        <span><?php echo $langs->trans('TotalVAT'); ?></span>
                        <span id="total_vat">0.00 €</span>
                    </div>
                    <div class="total-row total-main">
                        <span><?php echo $langs->trans('TotalTTC'); ?></span>
                        <span id="total_ttc">0.00 €</span>
                    </div>
                </div>
            </div>
            
            <!-- Notes Section -->
            <div class="form-section">
                <h3><i class="fas fa-sticky-note"></i> <?php echo $langs->trans('Notes'); ?></h3>
                <div class="form-row">
                    <div class="form-group full-width">
                        <label><?php echo $langs->trans('NotePublic'); ?></label>
                        <textarea name="note_public" rows="3" placeholder="<?php echo $langs->trans('NotePublic'); ?>"><?php echo GETPOST('note_public', 'restricthtml'); ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group full-width">
                        <label><?php echo $langs->trans('NotePrivate'); ?></label>
                        <textarea name="note_private" rows="3" placeholder="<?php echo $langs->trans('NotePrivate'); ?>"><?php echo GETPOST('note_private', 'restricthtml'); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Submit Buttons -->
            <div class="form-actions">
                <button type="submit" class="btn-primary btn-large">
                    <i class="fas fa-save"></i> <?php echo $langs->trans('CreateOrder'); ?>
                </button>
                <a href="<?php echo DOL_URL_ROOT; ?>/custom/zonajob/orders.php" class="btn-secondary btn-large">
                    <i class="fas fa-times"></i> <?php echo $langs->trans('Cancel'); ?>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Line Template (hidden) -->
<template id="line-template">
    <div class="order-line-item">
        <div class="line-number"><span class="line-num">1</span></div>
        <div class="line-content-create">
            <div class="form-row">
                <div class="form-group" style="flex: 2;">
                    <label><?php echo $langs->trans('Product'); ?></label>
                    <select name="line_product[]" class="line-product-select" onchange="updateLineFromProduct(this)">
                        <option value="0"><?php echo $langs->trans('FreeText'); ?></option>
                        <?php
                        // Get active products
                        $sql = "SELECT p.rowid, p.ref, p.label, p.price, p.tva_tx, p.fk_product_type";
                        $sql .= " FROM ".MAIN_DB_PREFIX."product as p";
                        $sql .= " WHERE p.entity IN (".getEntity('product').")";
                        $sql .= " AND p.tosell = 1";
                        $sql .= " ORDER BY p.ref ASC";
                        $sql .= " LIMIT 500";
                        $resql = $db->query($sql);
                        if ($resql) {
                            while ($obj = $db->fetch_object($resql)) {
                                $prod_type = ($obj->fk_product_type == 0) ? $langs->trans('Product') : $langs->trans('Service');
                                echo '<option value="'.$obj->rowid.'" data-price="'.$obj->price.'" data-vat="'.$obj->tva_tx.'" data-desc="'.dol_escape_htmltag($obj->label).'">';
                                echo dol_escape_htmltag($obj->ref.' - '.$obj->label.' ('.$prod_type.')');
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
                    <textarea name="line_description[]" rows="2" class="line-description"></textarea>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label><?php echo $langs->trans('Qty'); ?> *</label>
                    <input type="number" name="line_qty[]" value="1" min="0" step="0.01" class="line-qty" onchange="calculateLineTotals()">
                </div>
                <div class="form-group">
                    <label><?php echo $langs->trans('UnitPriceHT'); ?> *</label>
                    <input type="number" name="line_price[]" value="0" min="0" step="0.01" class="line-price" onchange="calculateLineTotals()">
                </div>
                <div class="form-group">
                    <label><?php echo $langs->trans('VAT'); ?> (%)</label>
                    <select name="line_vat[]" class="line-vat" onchange="calculateLineTotals()">
                        <option value="0">0%</option>
                        <option value="4">4%</option>
                        <option value="10">10%</option>
                        <option value="21" selected>21%</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><?php echo $langs->trans('Discount'); ?> (%)</label>
                    <input type="number" name="line_discount[]" value="0" min="0" max="100" step="0.01" class="line-discount" onchange="calculateLineTotals()">
                </div>
            </div>
            
            <div class="line-total-display">
                <span><?php echo $langs->trans('LineTotal'); ?>:</span>
                <span class="line-total-value">0.00 €</span>
            </div>
        </div>
        <button type="button" class="btn-remove-line" onclick="removeOrderLine(this)" title="<?php echo $langs->trans('Delete'); ?>">
            <i class="fas fa-trash"></i>
        </button>
    </div>
</template>

<script>
let lineCounter = 0;

// Add first line on page load
document.addEventListener('DOMContentLoaded', function() {
    addOrderLine();
});

function addOrderLine() {
    lineCounter++;
    const template = document.getElementById('line-template');
    const clone = template.content.cloneNode(true);
    
    // Update line number
    clone.querySelector('.line-num').textContent = lineCounter;
    
    document.getElementById('order-lines-container').appendChild(clone);
    
    // Show totals section
    document.getElementById('lines-total').style.display = 'block';
}

function removeOrderLine(btn) {
    const lineItem = btn.closest('.order-line-item');
    lineItem.remove();
    
    // Renumber lines
    const lines = document.querySelectorAll('.order-line-item');
    lines.forEach((line, index) => {
        line.querySelector('.line-num').textContent = index + 1;
    });
    
    // Hide totals if no lines
    if (lines.length === 0) {
        document.getElementById('lines-total').style.display = 'none';
    }
    
    calculateLineTotals();
}

function updateLineFromProduct(select) {
    const lineItem = select.closest('.order-line-item');
    const option = select.options[select.selectedIndex];
    
    if (option.value > 0) {
        const price = option.getAttribute('data-price');
        const vat = option.getAttribute('data-vat');
        const desc = option.getAttribute('data-desc');
        
        if (price) lineItem.querySelector('.line-price').value = price;
        if (vat) lineItem.querySelector('.line-vat').value = vat;
        if (desc) lineItem.querySelector('.line-description').value = desc;
    }
    
    calculateLineTotals();
}

function calculateLineTotals() {
    let totalHT = 0;
    let totalVAT = 0;
    
    const lines = document.querySelectorAll('.order-line-item');
    lines.forEach(line => {
        const qty = parseFloat(line.querySelector('.line-qty').value) || 0;
        const price = parseFloat(line.querySelector('.line-price').value) || 0;
        const vat = parseFloat(line.querySelector('.line-vat').value) || 0;
        const discount = parseFloat(line.querySelector('.line-discount').value) || 0;
        
        const lineHT = qty * price * (1 - discount/100);
        const lineVAT = lineHT * (vat/100);
        const lineTTC = lineHT + lineVAT;
        
        line.querySelector('.line-total-value').textContent = lineTTC.toFixed(2) + ' €';
        
        totalHT += lineHT;
        totalVAT += lineVAT;
    });
    
    const totalTTC = totalHT + totalVAT;
    
    document.getElementById('total_ht').textContent = totalHT.toFixed(2) + ' €';
    document.getElementById('total_vat').textContent = totalVAT.toFixed(2) + ' €';
    document.getElementById('total_ttc').textContent = totalTTC.toFixed(2) + ' €';
}
</script>

<?php
zonaempleado_print_footer();
