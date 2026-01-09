<?php
/* Copyright (C) 2025 ZonaJob Dev
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    zonajob/orders.php
 * \ingroup zonajob
 * \brief   Orders list for employee zone - Responsive
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

// Security check - must be logged in
if (empty($user) || !$user->id) {
    header("Location: ".DOL_URL_ROOT."/");
    exit;
}

require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/zonaempleado/lib/zonaempleado.lib.php';

// Load translations
$langs->loadLangs(array("orders", "companies", "zonajob@zonajob", "zonaempleado@zonaempleado"));

// Get parameters
$action = GETPOST('action', 'aZ09');
$search_ref = GETPOST('search_ref', 'alpha');
$search_soc = GETPOST('search_soc', 'alpha');
$search_project = GETPOST('search_project', 'alpha');
$filter_status = GETPOST('status', 'alpha');
$sortfield = GETPOST('sortfield', 'aZ09');
$sortorder = GETPOST('sortorder', 'aZ09');
$page = GETPOSTINT('page');
$limit = GETPOSTINT('limit') > 0 ? GETPOSTINT('limit') : 20;

if (empty($sortfield)) $sortfield = 'c.date_commande';
if (empty($sortorder)) $sortorder = 'DESC';
if ($page < 0) $page = 0;
$offset = $limit * $page;

// Check permission
if (empty($user->rights->zonajob->order->read) && empty($user->rights->commande->lire)) {
    accessforbidden();
}

// Get configuration for status filter
$show_draft = getDolGlobalString('ZONAJOB_SHOW_DRAFT_ORDERS', 1);
$show_validated = getDolGlobalString('ZONAJOB_SHOW_VALIDATED_ORDERS', 1);
$show_all = getDolGlobalString('ZONAJOB_SHOW_ALL_ORDERS', 0);

/*
 * Actions
 */

// None for now - handled in order_card.php

/*
 * View
 */

$title = $langs->trans('ZonaJobOrders');
$help_url = '';

// Ask zonaempleado header to load ZonaJob assets in <head>
$GLOBALS['zonaempleado_extra_css'] = array('/custom/zonajob/css/zonajob.css.php');
$GLOBALS['zonaempleado_extra_js'] = array('/custom/zonajob/js/zonajob.js.php');

// Print header
zonaempleado_print_header($title);

// Build SQL query
$sql = "SELECT c.rowid, c.ref, c.ref_client, c.date_commande, c.date_livraison,";
$sql .= " c.fk_statut, c.total_ht, c.total_ttc, c.note_public, c.fk_projet,";
$sql .= " s.rowid as socid, s.nom as socname, s.email, s.phone,";
$sql .= " p.ref as projet_ref, p.title as projet_title";
$sql .= " FROM ".MAIN_DB_PREFIX."commande as c";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON c.fk_soc = s.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet as p ON c.fk_projet = p.rowid";
$sql .= " WHERE c.entity IN (".getEntity('commande').")";

// Filter by user's permissions (commercial restriction)
if (!$user->rights->societe->client->voir && empty($socid)) {
    $sql .= " AND EXISTS (SELECT sc.fk_soc FROM ".MAIN_DB_PREFIX."societe_commerciaux as sc WHERE sc.fk_soc = c.fk_soc AND sc.fk_user = ".((int) $user->id).")";
}

// Status filter based on config
if (!$show_all) {
    $statusFilters = array();
    if ($show_draft) {
        $statusFilters[] = Commande::STATUS_DRAFT;
    }
    if ($show_validated) {
        $statusFilters[] = Commande::STATUS_VALIDATED;
        $statusFilters[] = Commande::STATUS_SHIPMENTONPROCESS;
        $statusFilters[] = Commande::STATUS_CLOSED;
    }
    if (!empty($statusFilters)) {
        $sql .= " AND c.fk_statut IN (".implode(',', $statusFilters).")";
    }
}

// Custom status filter from URL
if ($filter_status == 'draft') {
    $sql .= " AND c.fk_statut = ".Commande::STATUS_DRAFT;
} elseif ($filter_status == 'validated') {
    $sql .= " AND c.fk_statut = ".Commande::STATUS_VALIDATED;
}

// Search filters
if (!empty($search_ref)) {
    $sql .= " AND (";
    $sql .= natural_search(array('c.ref', 'c.ref_client', 'p.ref', 'p.title'), $search_ref);
    $sql .= ")";
}
if (!empty($search_soc)) {
    $sql .= natural_search('s.nom', $search_soc);
}
if (!empty($search_project)) {
    $sql .= " AND (";
    $sql .= natural_search(array('p.ref', 'p.title'), $search_project);
    $sql .= ")";
}

// Count total
$sqlcount = "SELECT COUNT(*) as total FROM (".$sql.") as subquery";
$resqlcount = $db->query($sqlcount);
$total = 0;
if ($resqlcount) {
    $objcount = $db->fetch_object($resqlcount);
    $total = $objcount->total;
}

// Add sorting and limit
$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);

?>

<div class="zonajob-container">
    <!-- Page Header -->
    <div class="zonajob-header">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
            <div>
                <h1><i class="fas fa-shopping-cart"></i> <?php echo $langs->trans('ZonaJobOrders'); ?></h1>
                <p class="subtitle"><?php echo $langs->trans('ZonaJobOrdersSubtitle'); ?></p>
            </div>
            <?php if (!empty($user->rights->commande->creer)) { ?>
            <div>
                <a href="<?php echo DOL_URL_ROOT; ?>/custom/zonajob/order_create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> <?php echo $langs->trans('NewOrder'); ?>
                </a>
            </div>
            <?php } ?>
        </div>
    </div>

    <!-- Quick Search Autocomplete -->
    <div class="zonajob-quick-search">
        <div class="autocomplete-container">
            <i class="fas fa-search autocomplete-icon"></i>
            <input type="text" 
                   id="quickSearch" 
                   class="autocomplete-input" 
                   placeholder="<?php echo $langs->trans('SearchOrderAutocomplete'); ?>" 
                   autocomplete="off">
            <div id="autocompleteResults" class="autocomplete-results"></div>
        </div>
    </div>

    <!-- Collapsible Filters -->
    <div class="zonajob-filters-toggle">
        <button type="button" class="btn-toggle-filters" onclick="toggleFilters()">
            <i class="fas fa-filter"></i> <?php echo $langs->trans('AdvancedFilters'); ?>
            <i class="fas fa-chevron-down filter-chevron"></i>
        </button>
    </div>
    
    <div class="zonajob-filters" id="advancedFilters" style="display: none;">
        <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="search_ref"><?php echo $langs->trans('Ref'); ?></label>
                    <input type="text" id="search_ref" name="search_ref" value="<?php echo dol_escape_htmltag($search_ref); ?>" placeholder="<?php echo $langs->trans('SearchRef'); ?>">
                </div>
                <div class="filter-group">
                    <label for="search_soc"><?php echo $langs->trans('ThirdParty'); ?></label>
                    <input type="text" id="search_soc" name="search_soc" value="<?php echo dol_escape_htmltag($search_soc); ?>" placeholder="<?php echo $langs->trans('SearchThirdParty'); ?>">
                </div>
                <div class="filter-group">
                    <label for="search_project"><?php echo $langs->trans('Project'); ?></label>
                    <input type="text" id="search_project" name="search_project" value="<?php echo dol_escape_htmltag($search_project); ?>" placeholder="<?php echo $langs->trans('SearchProject'); ?>">
                </div>
                <div class="filter-group">
                    <label for="status"><?php echo $langs->trans('Status'); ?></label>
                    <select id="status" name="status">
                        <option value=""><?php echo $langs->trans('All'); ?></option>
                        <option value="draft" <?php echo ($filter_status == 'draft') ? 'selected' : ''; ?>><?php echo $langs->trans('StatusOrderDraft'); ?></option>
                        <option value="validated" <?php echo ($filter_status == 'validated') ? 'selected' : ''; ?>><?php echo $langs->trans('StatusOrderValidated'); ?></option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn-search"><i class="fas fa-search"></i> <?php echo $langs->trans('Search'); ?></button>
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn-reset"><i class="fas fa-times"></i></a>
                </div>
            </div>
        </form>
    </div>

    <!-- Quick Stats -->
    <div class="zonajob-stats">
        <?php
        // Count by status
        $sqlStats = "SELECT c.fk_statut, COUNT(*) as count FROM ".MAIN_DB_PREFIX."commande c WHERE c.entity IN (".getEntity('commande').") GROUP BY c.fk_statut";
        $resStats = $db->query($sqlStats);
        $stats = array();
        while ($objStat = $db->fetch_object($resStats)) {
            $stats[$objStat->fk_statut] = $objStat->count;
        }
        ?>
        <div class="stat-card stat-draft">
            <i class="fas fa-file-alt"></i>
            <span class="stat-value"><?php echo isset($stats[Commande::STATUS_DRAFT]) ? $stats[Commande::STATUS_DRAFT] : 0; ?></span>
            <span class="stat-label"><?php echo $langs->trans('StatusOrderDraft'); ?></span>
        </div>
        <div class="stat-card stat-validated">
            <i class="fas fa-check-circle"></i>
            <span class="stat-value"><?php echo isset($stats[Commande::STATUS_VALIDATED]) ? $stats[Commande::STATUS_VALIDATED] : 0; ?></span>
            <span class="stat-label"><?php echo $langs->trans('StatusOrderValidated'); ?></span>
        </div>
        <div class="stat-card stat-shipped">
            <i class="fas fa-truck"></i>
            <span class="stat-value"><?php echo isset($stats[Commande::STATUS_SHIPMENTONPROCESS]) ? $stats[Commande::STATUS_SHIPMENTONPROCESS] : 0; ?></span>
            <span class="stat-label"><?php echo $langs->trans('StatusOrderSentShort'); ?></span>
        </div>
        <div class="stat-card stat-closed">
            <i class="fas fa-lock"></i>
            <span class="stat-value"><?php echo isset($stats[Commande::STATUS_CLOSED]) ? $stats[Commande::STATUS_CLOSED] : 0; ?></span>
            <span class="stat-label"><?php echo $langs->trans('StatusOrderDelivered'); ?></span>
        </div>
    </div>

    <!-- Orders List -->
    <div class="zonajob-orders-list">
        <?php
        if ($resql) {
            $num = $db->num_rows($resql);
            
            if ($num > 0) {
                $i = 0;
                while ($i < min($num, $limit)) {
                    $obj = $db->fetch_object($resql);
                    
                    $order = new Commande($db);
                    $order->id = $obj->rowid;
                    $order->ref = $obj->ref;
                    $order->ref_client = $obj->ref_client;
                    $order->statut = $obj->fk_statut;
                    $order->date = $db->jdate($obj->date_commande);
                    $order->date_livraison = $db->jdate($obj->date_livraison);
                    $order->total_ht = $obj->total_ht;
                    $order->total_ttc = $obj->total_ttc;
                    $order->socid = $obj->socid;
                    
                    // Get status class
                    $statusClass = 'status-draft';
                    if ($obj->fk_statut == Commande::STATUS_VALIDATED) $statusClass = 'status-validated';
                    elseif ($obj->fk_statut == Commande::STATUS_SHIPMENTONPROCESS) $statusClass = 'status-shipped';
                    elseif ($obj->fk_statut == Commande::STATUS_CLOSED) $statusClass = 'status-closed';
                    elseif ($obj->fk_statut == Commande::STATUS_CANCELED) $statusClass = 'status-canceled';
                    
                    // Check if has signature
                    $sqlSig = "SELECT rowid, status FROM ".MAIN_DB_PREFIX."zonajob_signature WHERE fk_commande = ".$obj->rowid." ORDER BY date_creation DESC LIMIT 1";
                    $resSig = $db->query($sqlSig);
                    $hasSignature = false;
                    $signatureStatus = 0;
                    if ($resSig && $db->num_rows($resSig) > 0) {
                        $objSig = $db->fetch_object($resSig);
                        $hasSignature = true;
                        $signatureStatus = $objSig->status;
                    }
                    ?>
                    <div class="order-card <?php echo $statusClass; ?>" onclick="window.location.href='<?php echo DOL_URL_ROOT; ?>/custom/zonajob/order_card.php?id=<?php echo $obj->rowid; ?>'">
                        <div class="order-header">
                            <div class="order-ref">
                                <span class="ref-number"><?php echo dol_escape_htmltag($obj->ref); ?></span>
                                <?php if (!empty($obj->ref_client)) { ?>
                                    <span class="ref-client"><?php echo dol_escape_htmltag($obj->ref_client); ?></span>
                                <?php } ?>
                            </div>
                            <div class="order-status">
                                <?php echo $order->getLibStatut(2); ?>
                            </div>
                        </div>
                        
                        <div class="order-body">
                            <div class="order-customer">
                                <i class="fas fa-building"></i>
                                <span><?php echo dol_escape_htmltag($obj->socname); ?></span>
                            </div>
                            
                            <?php if (!empty($obj->fk_projet)) { ?>
                            <div class="order-project">
                                <i class="fas fa-project-diagram"></i>
                                <span class="project-ref"><?php echo dol_escape_htmltag($obj->projet_ref); ?></span>
                                <?php if (!empty($obj->projet_title)) { ?>
                                    <span class="project-title"> - <?php echo dol_escape_htmltag($obj->projet_title); ?></span>
                                <?php } ?>
                            </div>
                            <?php } ?>
                            
                            <div class="order-info-row">
                                <div class="order-date">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo dol_print_date($order->date, 'day'); ?></span>
                                </div>
                                <?php if ($order->date_livraison) { ?>
                                <div class="order-delivery">
                                    <i class="fas fa-truck"></i>
                                    <span><?php echo dol_print_date($order->date_livraison, 'day'); ?></span>
                                </div>
                                <?php } ?>
                            </div>
                            
                            <div class="order-amount">
                                <span class="amount-label">Total TTC:</span>
                                <span class="amount-value"><?php echo price($obj->total_ttc, 1, $langs, 1, -1, -1, $conf->currency); ?></span>
                            </div>
                        </div>
                        
                        <div class="order-footer">
                            <div class="order-actions">
                                <?php if ($hasSignature && $signatureStatus == 1) { ?>
                                    <span class="badge badge-success"><i class="fas fa-check"></i> <?php echo $langs->trans('Signed'); ?></span>
                                <?php } elseif ($obj->fk_statut >= Commande::STATUS_VALIDATED) { ?>
                                    <span class="badge badge-warning"><i class="fas fa-signature"></i> <?php echo $langs->trans('PendingSignature'); ?></span>
                                <?php } ?>
                            </div>
                            <div class="order-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </div>
                    </div>
                    <?php
                    $i++;
                }
            } else {
                ?>
                <div class="no-orders">
                    <i class="fas fa-inbox"></i>
                    <p><?php echo $langs->trans('NoOrdersFound'); ?></p>
                </div>
                <?php
            }
            
            $db->free($resql);
        } else {
            dol_print_error($db);
        }
        ?>
    </div>

    <!-- Pagination -->
    <?php if ($total > $limit) { ?>
    <div class="zonajob-pagination">
        <?php
        $maxpage = ceil($total / $limit) - 1;
        $params = array();
        if (!empty($search_ref)) $params['search_ref'] = $search_ref;
        if (!empty($search_soc)) $params['search_soc'] = $search_soc;
        if (!empty($filter_status)) $params['status'] = $filter_status;
        $param = '';
        foreach ($params as $k => $v) {
            $param .= '&'.$k.'='.urlencode($v);
        }
        ?>
        <div class="pagination-info">
            <?php echo sprintf($langs->trans('Showing'), ($offset + 1), min($offset + $limit, $total), $total); ?>
        </div>
        <div class="pagination-buttons">
            <?php if ($page > 0) { ?>
                <a href="<?php echo $_SERVER['PHP_SELF'].'?page='.($page-1).$param; ?>" class="pagination-btn"><i class="fas fa-chevron-left"></i></a>
            <?php } ?>
            
            <span class="pagination-current"><?php echo ($page + 1); ?> / <?php echo ($maxpage + 1); ?></span>
            
            <?php if ($page < $maxpage) { ?>
                <a href="<?php echo $_SERVER['PHP_SELF'].'?page='.($page+1).$param; ?>" class="pagination-btn"><i class="fas fa-chevron-right"></i></a>
            <?php } ?>
        </div>
    </div>
    <?php } ?>
</div>

<script>
// Toggle advanced filters
function toggleFilters() {
    var filters = document.getElementById('advancedFilters');
    var chevron = document.querySelector('.filter-chevron');
    if (filters.style.display === 'none') {
        filters.style.display = 'block';
        chevron.classList.add('open');
    } else {
        filters.style.display = 'none';
        chevron.classList.remove('open');
    }
}

// Autocomplete functionality
var searchTimeout = null;
var quickSearchInput = document.getElementById('quickSearch');
var resultsContainer = document.getElementById('autocompleteResults');

if (quickSearchInput) {
    quickSearchInput.addEventListener('input', function() {
        var query = this.value.trim();
        
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        if (query.length < 2) {
            resultsContainer.style.display = 'none';
            resultsContainer.innerHTML = '';
            return;
        }
        
        searchTimeout = setTimeout(function() {
            fetchAutocomplete(query);
        }, 300);
    });
    
    quickSearchInput.addEventListener('focus', function() {
        if (this.value.length >= 2 && resultsContainer.innerHTML !== '') {
            resultsContainer.style.display = 'block';
        }
    });
    
    // Close autocomplete when clicking outside
    document.addEventListener('click', function(e) {
        if (!quickSearchInput.contains(e.target) && !resultsContainer.contains(e.target)) {
            resultsContainer.style.display = 'none';
        }
    });
}

function fetchAutocomplete(query) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '<?php echo DOL_URL_ROOT; ?>/custom/zonajob/ajax/search_orders.php?q=' + encodeURIComponent(query), true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                var response = JSON.parse(xhr.responseText);
                renderAutocompleteResults(response.results);
            } catch (e) {
                console.error('Error parsing response', e);
            }
        }
    };
    xhr.send();
}

function renderAutocompleteResults(results) {
    if (!results || results.length === 0) {
        resultsContainer.innerHTML = '<div class="autocomplete-no-results"><?php echo $langs->trans('NoResults'); ?></div>';
        resultsContainer.style.display = 'block';
        return;
    }
    
    var html = '';
    results.forEach(function(item) {
        var statusClass = 'status-' + item.status;
        html += '<a href="' + item.url + '" class="autocomplete-item">';
        html += '  <div class="ac-main">';
        html += '    <span class="ac-ref">' + escapeHtml(item.ref) + '</span>';
        if (item.ref_client) {
            html += '    <span class="ac-ref-client">(' + escapeHtml(item.ref_client) + ')</span>';
        }
        html += '  </div>';
        html += '  <div class="ac-details">';
        html += '    <span class="ac-soc"><i class="fas fa-building"></i> ' + escapeHtml(item.socname || '') + '</span>';
        if (item.projet_ref) {
            html += '    <span class="ac-project"><i class="fas fa-project-diagram"></i> ' + escapeHtml(item.projet_ref) + '</span>';
        }
        html += '  </div>';
        html += '  <span class="ac-status ' + statusClass + '">' + escapeHtml(item.status_label) + '</span>';
        html += '</a>';
    });
    
    resultsContainer.innerHTML = html;
    resultsContainer.style.display = 'block';
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}

// Show filters if any search param is active
<?php if (!empty($search_ref) || !empty($search_soc) || !empty($search_project) || !empty($filter_status)) { ?>
document.getElementById('advancedFilters').style.display = 'block';
document.querySelector('.filter-chevron').classList.add('open');
<?php } ?>
</script>

<?php

// Print footer
zonaempleado_print_footer();
