<?php
/* Copyright (C) 2025 ZonaJob Dev
 * CSS for ZonaJob module - Responsive Design
 */

if (!defined('NOREQUIREUSER')) define('NOREQUIREUSER', '1');
if (!defined('NOREQUIRESOC')) define('NOREQUIRESOC', '1');
if (!defined('NOREQUIRETRAN')) define('NOREQUIRETRAN', '1');
if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', 1);
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', 1);
if (!defined('NOLOGIN')) define('NOLOGIN', 1);
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', 1);
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', 1);
if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', '1');

session_cache_limiter('public');

$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    header('Content-type: text/css');
    echo "/* Error: Cannot load Dolibarr */";
    exit;
}

header('Content-type: text/css');
if (empty($dolibarr_nocache)) {
    header('Cache-Control: max-age=10800, public, must-revalidate');
}

// Get theme colors
$colorbackhmenu1 = '173,15,15';
if (!empty($conf->global->THEME_ELDY_TOPMENU_BACK1)) {
    $colorbackhmenu1 = $conf->global->THEME_ELDY_TOPMENU_BACK1;
}

if (function_exists('colorStringToArray')) {
    $tmp = colorStringToArray($colorbackhmenu1);
    if (is_array($tmp) && count($tmp) >= 3) {
        $colorbackhmenu1 = join(',', $tmp);
    }
}
$colorbackhmenu1 = trim(str_replace(' ', '', $colorbackhmenu1));

?>

/* ================================================================
   ZONAJOB - RESPONSIVE DESIGN
   ================================================================ */

:root {
    --zj-primary: rgb(<?php echo $colorbackhmenu1; ?>);
    --zj-primary-light: rgba(<?php echo $colorbackhmenu1; ?>, 0.1);
    --zj-success: #28a745;
    --zj-warning: #ffc107;
    --zj-danger: #dc3545;
    --zj-info: #17a2b8;
    --zj-bg: #f5f5f5;
    --zj-card-bg: #ffffff;
    --zj-text: #333;
    --zj-text-light: #666;
    --zj-border: #ddd;
    --zj-shadow: 0 2px 8px rgba(0,0,0,0.08);
    --zj-radius: 8px;
    --zj-transition: all 0.3s ease;
}

/* ================================================================
   CONTAINER
   ================================================================ */

.zonajob-container,
.zonajob-order-card {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1rem;
}

/* ================================================================
   HEADER
   ================================================================ */

.zonajob-header {
    text-align: center;
    padding: 1.5rem 0;
    margin-bottom: 1rem;
}

.zonajob-header h1 {
    font-size: 1.75rem;
    color: var(--zj-text);
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.zonajob-header h1 i {
    color: var(--zj-primary);
}

.zonajob-header .subtitle {
    color: var(--zj-text-light);
    margin: 0;
    font-size: 0.9rem;
}

/* ================================================================
   FILTERS
   ================================================================ */

.zonajob-filters {
    background: var(--zj-card-bg);
    border-radius: var(--zj-radius);
    padding: 1rem;
    margin-bottom: 1rem;
    box-shadow: var(--zj-shadow);
}

.filter-form .filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    align-items: flex-end;
}

.filter-group {
    flex: 1;
    min-width: 150px;
}

.filter-group label {
    display: block;
    font-size: 0.75rem;
    color: var(--zj-text-light);
    margin-bottom: 0.25rem;
    text-transform: uppercase;
}

.filter-group input,
.filter-group select {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid var(--zj-border);
    border-radius: 4px;
    font-size: 0.9rem;
}

.filter-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-search,
.btn-reset {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.9rem;
    transition: var(--zj-transition);
}

.btn-search {
    background: var(--zj-primary);
    color: white;
}

.btn-search:hover {
    opacity: 0.9;
}

.btn-reset {
    background: #e9ecef;
    color: var(--zj-text);
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* ================================================================
   QUICK SEARCH AUTOCOMPLETE
   ================================================================ */

.zonajob-quick-search {
    margin-bottom: 1rem;
}

.autocomplete-container {
    position: relative;
    max-width: 100%;
}

.autocomplete-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--zj-text-light);
    font-size: 1rem;
    z-index: 1;
}

.autocomplete-input {
    width: 85%;
    padding: 0.9rem 1rem 0.9rem 2.75rem;
    border: 2px solid var(--zj-border);
    border-radius: var(--zj-radius);
    font-size: 1rem;
    background: var(--zj-card-bg);
    transition: var(--zj-transition);
    box-shadow: var(--zj-shadow);
}

.autocomplete-input:focus {
    outline: none;
    border-color: var(--zj-primary);
    box-shadow: 0 0 0 3px var(--zj-primary-light);
}

.autocomplete-input::placeholder {
    color: var(--zj-text-light);
}

.autocomplete-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--zj-card-bg);
    border: 1px solid var(--zj-border);
    border-radius: var(--zj-radius);
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    max-height: 350px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
    margin-top: 4px;
}

.autocomplete-item {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    text-decoration: none;
    color: var(--zj-text);
    border-bottom: 1px solid var(--zj-border);
    transition: var(--zj-transition);
}

.autocomplete-item:last-child {
    border-bottom: none;
}

.autocomplete-item:hover {
    background: var(--zj-primary-light);
}

.autocomplete-item .ac-main {
    flex: 1;
    min-width: 150px;
}

.autocomplete-item .ac-ref {
    font-weight: 600;
    color: var(--zj-primary);
}

.autocomplete-item .ac-ref-client {
    font-size: 0.85rem;
    color: var(--zj-text-light);
    margin-left: 0.25rem;
}

.autocomplete-item .ac-details {
    display: flex;
    gap: 1rem;
    font-size: 0.85rem;
    color: var(--zj-text-light);
}

.autocomplete-item .ac-details i {
    margin-right: 0.25rem;
}

.autocomplete-item .ac-status {
    font-size: 0.7rem;
    padding: 0.2rem 0.5rem;
    border-radius: 10px;
    text-transform: uppercase;
    font-weight: 500;
}

.autocomplete-item .status-0 { background: #fff3cd; color: #856404; }
.autocomplete-item .status-1 { background: #d4edda; color: #155724; }
.autocomplete-item .status-2 { background: #cce5ff; color: #004085; }
.autocomplete-item .status-3 { background: #e2e3e5; color: #383d41; }
.autocomplete-item .status--1 { background: #f8d7da; color: #721c24; }

.autocomplete-no-results {
    padding: 1rem;
    text-align: center;
    color: var(--zj-text-light);
    font-style: italic;
}

/* Filters Toggle */
.zonajob-filters-toggle {
    margin-bottom: 0.75rem;
}

.btn-toggle-filters {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: var(--zj-card-bg);
    border: 1px solid var(--zj-border);
    border-radius: var(--zj-radius);
    color: var(--zj-text-light);
    cursor: pointer;
    font-size: 0.85rem;
    transition: var(--zj-transition);
}

.btn-toggle-filters:hover {
    background: var(--zj-primary-light);
    color: var(--zj-primary);
}

.filter-chevron {
    transition: transform 0.3s ease;
}

.filter-chevron.open {
    transform: rotate(180deg);
}

/* ================================================================
   STATS
   ================================================================ */

.zonajob-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.stat-card {
    background: var(--zj-card-bg);
    border-radius: var(--zj-radius);
    padding: 1rem;
    text-align: center;
    box-shadow: var(--zj-shadow);
    border-left: 3px solid var(--zj-border);
}

.stat-card i {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
    display: block;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: bold;
    display: block;
}

.stat-label {
    font-size: 0.75rem;
    color: var(--zj-text-light);
    text-transform: uppercase;
}

.stat-draft { border-left-color: #6c757d; }
.stat-draft i, .stat-draft .stat-value { color: #6c757d; }

.stat-validated { border-left-color: var(--zj-info); }
.stat-validated i, .stat-validated .stat-value { color: var(--zj-info); }

.stat-shipped { border-left-color: var(--zj-warning); }
.stat-shipped i, .stat-shipped .stat-value { color: var(--zj-warning); }

.stat-closed { border-left-color: var(--zj-success); }
.stat-closed i, .stat-closed .stat-value { color: var(--zj-success); }

/* ================================================================
   ORDERS LIST
   ================================================================ */

.zonajob-orders-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.order-card {
    background: var(--zj-card-bg);
    border-radius: var(--zj-radius);
    box-shadow: var(--zj-shadow);
    cursor: pointer;
    transition: var(--zj-transition);
    border-left: 4px solid var(--zj-border);
    overflow: hidden;
}

.order-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    transform: translateY(-2px);
}

.order-card.status-draft { border-left-color: #6c757d; }
.order-card.status-validated { border-left-color: var(--zj-info); }
.order-card.status-shipped { border-left-color: var(--zj-warning); }
.order-card.status-closed { border-left-color: var(--zj-success); }
.order-card.status-canceled { border-left-color: var(--zj-danger); }

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 1rem;
    background: #fafafa;
    border-bottom: 1px solid #eee;
}

.order-ref .ref-number {
    font-weight: bold;
    font-size: 1rem;
    color: var(--zj-text);
}

.order-ref .ref-client {
    font-size: 0.8rem;
    color: var(--zj-text-light);
    margin-left: 0.5rem;
}

.order-body {
    padding: 0.75rem 1rem;
}

.order-customer {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.order-customer i {
    color: var(--zj-primary);
}

.order-project {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    font-size: 0.85rem;
    color: var(--zj-text-light);
}

.order-project i {
    color: var(--zj-info);
}

.project-ref {
    font-weight: 500;
    color: var(--zj-text);
}

.project-title {
    font-style: italic;
}

.order-info-row {
    display: flex;
    gap: 1rem;
    font-size: 0.85rem;
    color: var(--zj-text-light);
    margin-bottom: 0.5rem;
}

.order-info-row i {
    margin-right: 0.25rem;
}

.order-amount {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 0.5rem;
}

.order-amount .amount-label {
    font-size: 0.8rem;
    color: var(--zj-text-light);
}

.order-amount .amount-value {
    font-weight: bold;
    font-size: 1.1rem;
    color: var(--zj-primary);
}

.order-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 1rem;
    background: #fafafa;
    border-top: 1px solid #eee;
}

.order-actions .badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
}

.badge-success { background: var(--zj-success); color: white; }
.badge-warning { background: var(--zj-warning); color: #333; }
.badge-danger { background: var(--zj-danger); color: white; }

.order-arrow {
    color: var(--zj-text-light);
}

/* No orders */
.no-orders {
    text-align: center;
    padding: 3rem;
    background: var(--zj-card-bg);
    border-radius: var(--zj-radius);
}

.no-orders i {
    font-size: 3rem;
    color: var(--zj-border);
    margin-bottom: 1rem;
}

.no-orders p {
    color: var(--zj-text-light);
    margin: 0;
}

/* ================================================================
   PAGINATION
   ================================================================ */

.zonajob-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: var(--zj-card-bg);
    border-radius: var(--zj-radius);
    margin-top: 1rem;
}

.pagination-info {
    font-size: 0.85rem;
    color: var(--zj-text-light);
}

.pagination-buttons {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pagination-btn {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--zj-primary);
    color: white;
    border-radius: 4px;
    text-decoration: none;
}

.pagination-current {
    padding: 0 0.5rem;
    font-size: 0.9rem;
}

/* ================================================================
   ORDER CARD PAGE
   ================================================================ */

.order-back {
    margin-bottom: 1rem;
}

.btn-back {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: var(--zj-card-bg);
    border: 1px solid var(--zj-border);
    border-radius: var(--zj-radius);
    text-decoration: none;
    color: var(--zj-text);
    font-size: 0.9rem;
    transition: var(--zj-transition);
}

.btn-back:hover {
    background: #f5f5f5;
}

.order-main-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    background: var(--zj-card-bg);
    padding: 1rem;
    border-radius: var(--zj-radius);
    box-shadow: var(--zj-shadow);
    margin-bottom: 1rem;
}

.order-title h1 {
    margin: 0;
    font-size: 1.5rem;
    color: var(--zj-text);
}

.order-title .ref-client {
    font-size: 0.85rem;
    color: var(--zj-text-light);
    display: block;
    margin-top: 0.25rem;
}

/* Project Info in Header */
.project-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: var(--zj-text-light);
    margin-top: 0.35rem;
    padding: 0.35rem 0.75rem;
    background: #e3f2fd;
    border-radius: var(--zj-radius);
    width: fit-content;
}

.project-info i {
    color: #1565c0;
}

.project-info .project-ref {
    font-weight: 600;
    color: #1565c0;
}

.project-info .project-title {
    color: var(--zj-text);
}

/* ================================================================
   TABS
   ================================================================ */

.tabs-container {
    position: relative;
    display: flex;
    align-items: stretch;
    margin-bottom: 1rem;
}

.tab-scroll-indicator {
    display: none;
    align-items: center;
    justify-content: center;
    width: 32px;
    min-width: 32px;
    background: var(--zj-card-bg);
    border: none;
    cursor: pointer;
    color: var(--zj-text-light);
    font-size: 1rem;
    z-index: 10;
    transition: var(--zj-transition);
    box-shadow: var(--zj-shadow);
}

.tab-scroll-indicator:hover {
    background: var(--zj-primary-light);
    color: var(--zj-primary);
}

.tab-scroll-left {
    border-radius: var(--zj-radius) 0 0 var(--zj-radius);
}

.tab-scroll-right {
    border-radius: 0 var(--zj-radius) var(--zj-radius) 0;
}

.tab-scroll-indicator.visible {
    display: flex;
}

.order-tabs {
    display: flex;
    flex: 1;
    overflow-x: auto;
    background: var(--zj-card-bg);
    border-radius: var(--zj-radius);
    box-shadow: var(--zj-shadow);
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE/Edge */
    scroll-behavior: smooth;
}

.tabs-container .order-tabs {
    margin-bottom: 0;
}

.order-tabs::-webkit-scrollbar {
    display: none; /* Chrome/Safari */
}

.tab-link {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 0.4rem;
    padding: 0.75rem 1rem;
    text-decoration: none;
    color: var(--zj-text-light);
    font-size: 0.85rem;
    white-space: nowrap;
    border-bottom: 3px solid transparent;
    transition: var(--zj-transition);
    flex-shrink: 0;
}

.tab-link i {
    font-size: 1rem;
}

.tab-link span {
    display: inline;
}

.tab-link:hover {
    color: var(--zj-primary);
    background: var(--zj-primary-light);
}

.tab-link.active {
    color: var(--zj-primary);
    border-bottom-color: var(--zj-primary);
    background: var(--zj-primary-light);
}

/* ================================================================
   TAB CONTENT
   ================================================================ */

.order-tab-content {
    background: var(--zj-card-bg);
    border-radius: var(--zj-radius);
    box-shadow: var(--zj-shadow);
    padding: 1rem;
}

.info-section {
    margin-bottom: 1.5rem;
}

.info-section h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1rem;
    color: var(--zj-text);
    margin: 0 0 0.75rem 0;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--zj-border);
}

.info-section h3 i {
    color: var(--zj-primary);
}

.info-card {
    background: #fafafa;
    border-radius: var(--zj-radius);
    padding: 1rem;
}

.customer-name {
    font-weight: bold;
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
}

.customer-address,
.customer-phone,
.customer-email {
    margin-top: 0.5rem;
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
}

.customer-phone a,
.customer-email a {
    color: var(--zj-primary);
    text-decoration: none;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 0.75rem;
}

.info-item {
    background: #fafafa;
    padding: 0.75rem;
    border-radius: var(--zj-radius);
}

.info-item label {
    display: block;
    font-size: 0.75rem;
    color: var(--zj-text-light);
    text-transform: uppercase;
    margin-bottom: 0.25rem;
}

.info-item span {
    font-weight: 500;
}

/* Totals Card */
.totals-card {
    background: #fafafa;
    border-radius: var(--zj-radius);
    padding: 1rem;
}

.total-row {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid #eee;
}

.total-row:last-child {
    border-bottom: none;
}

.total-row.total-main {
    margin-top: 0.5rem;
    padding-top: 0.75rem;
    border-top: 2px solid var(--zj-primary);
    font-weight: bold;
    font-size: 1.1rem;
}

.total-row.total-main .total-value {
    color: var(--zj-primary);
}

/* Extrafields */
.extrafields-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.5rem;
}

.extrafield-item {
    background: #fafafa;
    padding: 0.5rem 0.75rem;
    border-radius: 4px;
}

.extrafield-item label {
    display: block;
    font-size: 0.75rem;
    color: var(--zj-text-light);
    margin-bottom: 0.25rem;
}

/* Notes */
.note-card {
    background: #fafafa;
    border-radius: var(--zj-radius);
    padding: 1rem;
    margin-bottom: 0.75rem;
}

.note-card label {
    display: block;
    font-size: 0.75rem;
    color: var(--zj-text-light);
    text-transform: uppercase;
    margin-bottom: 0.5rem;
}

.note-content {
    line-height: 1.5;
}

.note-private {
    border-left: 3px solid var(--zj-warning);
}

/* Contacts */
.contacts-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 0.75rem;
}

.contact-card {
    background: #fafafa;
    border-radius: var(--zj-radius);
    padding: 0.75rem;
}

.contact-name {
    font-weight: 500;
    margin-bottom: 0.25rem;
}

.contact-name i {
    color: var(--zj-primary);
    margin-right: 0.25rem;
}

.contact-role {
    font-size: 0.8rem;
    color: var(--zj-text-light);
    margin-bottom: 0.5rem;
}

.contact-email,
.contact-phone {
    display: block;
    font-size: 0.85rem;
    color: var(--zj-primary);
    text-decoration: none;
    margin-top: 0.25rem;
}

.btn-add-contact {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: var(--zj-primary);
    color: white;
    border: none;
    border-radius: var(--zj-radius);
    cursor: pointer;
    margin-top: 1rem;
    font-size: 0.9rem;
}

.add-contact-form {
    margin-top: 1rem;
    padding: 1rem;
    background: #fafafa;
    border-radius: var(--zj-radius);
}

/* Notifications Section */
.notifications-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.notification-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 0.75rem;
    background: #fafafa;
    border-radius: var(--zj-radius);
}

.notification-item .notif-email {
    flex: 1;
    font-size: 0.9rem;
}

.notification-item .notif-type {
    font-size: 0.7rem;
    padding: 0.2rem 0.5rem;
    border-radius: 10px;
    text-transform: uppercase;
}

.notification-item .badge-tocontact {
    background: #e3f2fd;
    color: #1565c0;
}

.notification-item .badge-touser {
    background: #f3e5f5;
    color: #7b1fa2;
}

.notification-item .badge-tofixedemail {
    background: #fff3e0;
    color: #e65100;
}

.notif-info {
    font-size: 0.8rem;
    color: var(--zj-text-light);
    margin-top: 0.75rem;
    padding: 0.5rem;
    background: #e3f2fd;
    border-radius: var(--zj-radius);
}

.notif-info i {
    color: #1565c0;
    margin-right: 0.25rem;
}

/* ================================================================
   LINES TAB
   ================================================================ */

.order-lines {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.line-card {
    display: flex;
    background: #fafafa;
    border-radius: var(--zj-radius);
    overflow: hidden;
    position: relative;
}

.line-number {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    background: var(--zj-primary);
    color: white;
    font-weight: bold;
}

.line-content {
    flex: 1;
    padding: 0.75rem;
}

.line-header {
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.line-ref {
    display: inline-block;
    background: var(--zj-primary-light);
    color: var(--zj-primary);
    padding: 0.125rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
    margin-right: 0.5rem;
}

.line-label {
    font-weight: 500;
}

.line-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-icon {
    padding: 0.25rem 0.5rem;
    background: transparent;
    border: 1px solid var(--zj-border);
    border-radius: 4px;
    cursor: pointer;
    transition: var(--zj-transition);
    color: var(--zj-text);
}

.btn-icon:hover {
    background: var(--zj-primary);
    color: white;
    border-color: var(--zj-primary);
}

.btn-icon.btn-delete:hover {
    background: var(--zj-danger);
    border-color: var(--zj-danger);
}

.line-description {
    font-size: 0.85rem;
    color: var(--zj-text-light);
    margin-bottom: 0.5rem;
    padding: 0.5rem;
    background: white;
    border-radius: 4px;
}

.line-details {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    font-size: 0.85rem;
}

.line-details > div label {
    font-size: 0.7rem;
    color: var(--zj-text-light);
    text-transform: uppercase;
    margin-right: 0.25rem;
}

.line-total {
    margin-left: auto;
    font-weight: 500;
}

.lines-total {
    margin-top: 1rem;
    padding: 1rem;
    background: #fafafa;
    border-radius: var(--zj-radius);
}

/* Add/Edit Line Forms */
.add-line-form,
.edit-line-form {
    background: var(--zj-card-bg);
    border: 1px solid var(--zj-border);
    border-radius: var(--zj-radius);
    padding: 1rem;
    margin-bottom: 1rem;
}

.edit-line-form {
    margin-top: 1rem;
}

.btn-add-line {
    padding: 0.5rem 1rem;
    background: var(--zj-success);
    color: white;
    border: none;
    border-radius: var(--zj-radius);
    cursor: pointer;
    transition: var(--zj-transition);
}

.btn-add-line:hover {
    opacity: 0.9;
}

/* ================================================================
   PHOTOS TAB
   ================================================================ */

.photo-upload-form {
    margin-bottom: 1.5rem;
}

.upload-area {
    border: 2px dashed var(--zj-border);
    border-radius: var(--zj-radius);
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: var(--zj-transition);
}

.upload-area:hover {
    border-color: var(--zj-primary);
    background: var(--zj-primary-light);
}

.upload-area i {
    font-size: 2.5rem;
    color: var(--zj-primary);
    margin-bottom: 0.5rem;
}

.upload-area p {
    margin: 0;
    color: var(--zj-text-light);
}

#photo-preview-container {
    margin-top: 1rem;
    padding: 1rem;
    background: #fafafa;
    border-radius: var(--zj-radius);
}

#photo-preview {
    max-width: 100%;
    max-height: 200px;
    border-radius: var(--zj-radius);
    margin-bottom: 1rem;
}

.photos-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 0.75rem;
}

.photo-item {
    position: relative;
    background: #fafafa;
    border-radius: var(--zj-radius);
    overflow: hidden;
}

.photo-item img {
    width: 100%;
    aspect-ratio: 1;
    object-fit: cover;
    cursor: pointer;
}

.photo-info {
    padding: 0.5rem;
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
}

.photo-type {
    background: var(--zj-primary-light);
    color: var(--zj-primary);
    padding: 0.125rem 0.5rem;
    border-radius: 4px;
}

.photo-date {
    color: var(--zj-text-light);
}

.photo-description {
    padding: 0 0.5rem 0.5rem;
    font-size: 0.8rem;
    color: var(--zj-text-light);
}

.photo-delete {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(220, 53, 69, 0.9);
    color: white;
    border-radius: 50%;
    text-decoration: none;
    opacity: 0;
    transition: var(--zj-transition);
}

.photo-item:hover .photo-delete {
    opacity: 1;
}

/* Photo Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.9);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

.modal-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    font-size: 2rem;
    color: white;
    cursor: pointer;
}

#modal-image {
    max-width: 90%;
    max-height: 90%;
}

/* ================================================================
   SIGNATURE TAB
   ================================================================ */

.existing-signatures {
    margin-bottom: 1.5rem;
}

.existing-signatures h4 {
    font-size: 0.9rem;
    margin: 0 0 0.75rem 0;
    color: var(--zj-text-light);
}

.signature-record {
    background: #fafafa;
    border-radius: var(--zj-radius);
    padding: 1rem;
    margin-bottom: 0.75rem;
    border-left: 3px solid var(--zj-border);
}

.signature-record.signed {
    border-left-color: var(--zj-success);
}

.signature-record.pending {
    border-left-color: var(--zj-warning);
}

.sig-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.sig-ref {
    font-weight: bold;
}

.sig-info p {
    margin: 0.25rem 0;
    font-size: 0.9rem;
}

.sig-image {
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid #eee;
}

.sig-image img {
    max-width: 200px;
    max-height: 100px;
    border: 1px solid var(--zj-border);
    border-radius: 4px;
    background: white;
}

.new-signature-form h4 {
    font-size: 0.9rem;
    margin: 0 0 0.75rem 0;
    color: var(--zj-text);
}

.signature-canvas-container {
    margin: 1rem 0;
}

.signature-canvas-container label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

#signature-canvas {
    width: 100%;
    height: 200px;
    border: 2px solid var(--zj-border);
    border-radius: var(--zj-radius);
    background: white;
    touch-action: none;
}

.canvas-controls {
    margin-top: 0.5rem;
}

.btn-clear {
    padding: 0.5rem 1rem;
    background: #e9ecef;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.85rem;
}

/* Post-signature options */
.post-signature-options {
    background: #f8f9fa;
    border: 1px solid var(--zj-border);
    border-radius: var(--zj-radius);
    padding: 1rem;
    margin: 1rem 0;
}

.post-signature-options h4 {
    margin: 0 0 1rem 0;
    font-size: 1rem;
    color: var(--zj-text);
}

.form-check {
    margin-bottom: 0.75rem;
}

.form-check label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}

.form-check input[type="checkbox"] {
    width: 18px !important;
    height: 18px !important;
    cursor: pointer;
    opacity: 1 !important;
    position: relative !important;
    appearance: auto !important;
    -webkit-appearance: checkbox !important;
    -moz-appearance: auto !important;
    display: inline-block !important;
}

/* ================================================================
   SEND TAB
   ================================================================ */

/* Document Selection Grid */
.send-documents-select {
    background: var(--zj-card-bg);
    border-radius: var(--zj-radius);
    padding: 1.25rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--zj-shadow);
}

.send-documents-select h4 {
    margin: 0 0 1rem 0;
    font-size: 1rem;
    color: var(--zj-text);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.send-documents-select h4 i {
    color: var(--zj-primary);
}

.docs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.doc-card {
    position: relative;
    cursor: pointer;
    display: block;
}

.doc-card input[type="checkbox"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.doc-card-inner {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: #f8f9fa;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.doc-card:hover .doc-card-inner {
    border-color: var(--zj-primary);
    background: #fff;
}

.doc-card.selected .doc-card-inner {
    border-color: var(--zj-success);
    background: rgba(40, 167, 69, 0.08);
}

.doc-icon {
    position: relative;
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    border-radius: 8px;
    flex-shrink: 0;
}

.doc-icon i {
    font-size: 1.5rem;
}

.doc-icon .text-danger { color: #dc3545; }
.doc-icon .text-primary { color: #007bff; }
.doc-icon .text-secondary { color: #6c757d; }

.doc-check {
    position: absolute;
    bottom: -4px;
    right: -4px;
    width: 18px;
    height: 18px;
    background: var(--zj-success);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transform: scale(0.5);
    transition: all 0.2s ease;
}

.doc-check i {
    font-size: 0.6rem;
    color: #fff;
}

.doc-card.selected .doc-check {
    opacity: 1;
    transform: scale(1);
}

.doc-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.doc-name {
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--zj-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.doc-meta {
    font-size: 0.75rem;
    color: var(--zj-text-light);
}

.docs-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid #e9ecef;
}

.btn-select-all,
.btn-select-none {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.75rem;
    font-size: 0.8rem;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    background: #fff;
    color: var(--zj-text);
    cursor: pointer;
    transition: all 0.2s;
}

.btn-select-all:hover {
    background: var(--zj-success);
    border-color: var(--zj-success);
    color: #fff;
}

.btn-select-none:hover {
    background: #f8f9fa;
    border-color: #adb5bd;
}

.docs-count {
    margin-left: auto;
    font-size: 0.85rem;
    color: var(--zj-text-light);
}

.docs-count span {
    font-weight: 600;
    color: var(--zj-primary);
}

.send-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.send-option {
    background: #fafafa;
    border-radius: var(--zj-radius);
    padding: 1rem;
}

.send-option h4 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 1rem 0;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #eee;
}

.send-option h4 .fa-whatsapp {
    color: #25d366;
}

.send-option h4 .fa-envelope {
    color: var(--zj-primary);
}

.btn-whatsapp {
    width: 100%;
    padding: 0.75rem;
    background: #25d366;
    color: white;
    border: none;
    border-radius: var(--zj-radius);
    cursor: pointer;
    font-size: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.btn-email {
    width: 100%;
    padding: 0.75rem;
    background: var(--zj-primary);
    color: white;
    border: none;
    border-radius: var(--zj-radius);
    cursor: pointer;
    font-size: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.send-history h4 {
    font-size: 0.9rem;
    margin: 0 0 0.75rem 0;
    color: var(--zj-text);
}

.history-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.history-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: #fafafa;
    border-radius: var(--zj-radius);
    border-left: 3px solid var(--zj-border);
}

.history-item.success { border-left-color: var(--zj-success); }
.history-item.failed { border-left-color: var(--zj-danger); }
.history-item.pending { border-left-color: var(--zj-warning); }

.history-type {
    font-size: 1.25rem;
}

.history-type .fa-whatsapp { color: #25d366; }
.history-type .fa-envelope { color: var(--zj-primary); }

.history-info {
    flex: 1;
}

.history-recipient {
    display: block;
    font-weight: 500;
}

.history-date {
    display: block;
    font-size: 0.8rem;
    color: var(--zj-text-light);
}

.history-error {
    display: block;
    font-size: 0.8rem;
    color: var(--zj-danger);
}

/* ================================================================
   DOCUMENTS TAB
   ================================================================ */

.documents-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.document-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: #fafafa;
    border-radius: var(--zj-radius);
}

.document-icon {
    font-size: 1.5rem;
    color: var(--zj-danger);
}

.document-info {
    flex: 1;
}

.document-name {
    display: block;
    font-weight: 500;
}

.document-size,
.document-date {
    font-size: 0.8rem;
    color: var(--zj-text-light);
    margin-right: 1rem;
}

.document-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-download,
.btn-view {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--zj-primary);
    color: white;
    border-radius: 4px;
    text-decoration: none;
}

.btn-view {
    background: var(--zj-info);
}

.generate-pdf {
    margin-top: 1rem;
}

.btn-generate {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: var(--zj-danger);
    color: white;
    border-radius: var(--zj-radius);
    text-decoration: none;
}

/* ================================================================
   FORMS
   ================================================================ */

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.form-group {
    margin-bottom: 0.75rem;
}

.form-group label {
    display: block;
    font-size: 0.85rem;
    color: var(--zj-text);
    margin-bottom: 0.25rem;
}

.form-group input,
.form-group select,
.form-group textarea {
    <!-- width: 100%; -->
    padding: 0.5rem;
    border: 1px solid var(--zj-border);
    border-radius: 4px;
    font-size: 0.9rem;
    font-family: inherit;
}

.form-group textarea {
    resize: vertical;
}

.form-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
}

.btn-primary {
    padding: 0.75rem 1.5rem;
    background: var(--zj-primary);
    color: white;
    border: none;
    border-radius: var(--zj-radius);
    cursor: pointer;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-secondary {
    padding: 0.75rem 1.5rem;
    background: #e9ecef;
    color: var(--zj-text);
    border: none;
    border-radius: var(--zj-radius);
    cursor: pointer;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

/* Alerts */
.alert {
    padding: 1rem;
    border-radius: var(--zj-radius);
    margin-bottom: 1rem;
}

.alert-warning {
    background: rgba(255, 193, 7, 0.1);
    border: 1px solid var(--zj-warning);
    color: #856404;
}

.alert i {
    margin-right: 0.5rem;
}

.no-data {
    text-align: center;
    padding: 2rem;
    color: var(--zj-text-light);
}

/* ================================================================
   ORDER CREATE FORM
   ================================================================ */

.order-create-form {
    background: var(--zj-card-bg);
    border-radius: var(--zj-radius);
    box-shadow: var(--zj-shadow);
    padding: 1.5rem;
}

.form-section {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--zj-border);
}

.form-section:last-child {
    border-bottom: none;
}

.form-section h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 1rem 0;
    font-size: 1.1rem;
    color: var(--zj-text);
}

.form-section h3 i {
    color: var(--zj-primary);
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    font-weight: 500;
    margin-bottom: 0.5rem;
    color: var(--zj-text);
}

.form-group input[type="text"],
.form-group input[type="number"],
.form-group input[type="email"],
.form-group input[type="tel"],
.form-group textarea,
.form-group select {
    padding: 0.75rem;
    border: 1px solid var(--zj-border);
    border-radius: 4px;
    font-size: 1rem;
    transition: var(--zj-transition);
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--zj-primary);
    box-shadow: 0 0 0 3px var(--zj-primary-light);
}

.form-group textarea {
    resize: vertical;
    font-family: inherit;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 2px solid var(--zj-border);
}

.btn-large {
    padding: 1rem 2rem;
    font-size: 1.1rem;
    font-weight: 500;
}

.btn-back {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: transparent;
    color: var(--zj-primary);
    border: 1px solid var(--zj-primary);
    border-radius: 4px;
    text-decoration: none;
    transition: var(--zj-transition);
    margin-bottom: 1rem;
}

.btn-back:hover {
    background: var(--zj-primary);
    color: white;
}

/* Order Lines in Create Form */
.order-line-item {
    display: flex;
    gap: 1rem;
    background: #fafafa;
    border-radius: var(--zj-radius);
    padding: 1rem;
    margin-bottom: 1rem;
    position: relative;
}

.line-content-create {
    flex: 1;
}

.line-content-create .form-row {
    margin-bottom: 0.75rem;
}

.line-content-create .form-row:last-child {
    margin-bottom: 0;
}

.btn-remove-line {
    position: absolute;
    top: 1rem;
    right: 1rem;
    padding: 0.5rem;
    background: transparent;
    border: 1px solid var(--zj-danger);
    color: var(--zj-danger);
    border-radius: 4px;
    cursor: pointer;
    transition: var(--zj-transition);
}

.btn-remove-line:hover {
    background: var(--zj-danger);
    color: white;
}

.line-total-display {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 0.5rem;
    padding-top: 0.5rem;
    margin-top: 0.5rem;
    border-top: 1px solid #ddd;
    font-weight: 500;
}

.line-total-value {
    font-size: 1.1rem;
    color: var(--zj-primary);
}

.header-content {
    display: flex;
    <!-- flex-direction: column; -->
    align-items: center;
    gap: 1rem;
}

/* ================================================================
   NOTES TAB
   ================================================================ */

.notes-section {
    padding: 0.5rem;
}

.notes-section h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 1rem 0;
    font-size: 1.1rem;
    color: var(--zj-text);
}

.note-edit-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    border-left: 4px solid #6c757d;
}

.note-edit-card.note-public {
    border-left-color: var(--zj-primary);
    background: rgba(0, 123, 255, 0.05);
}

.note-edit-card.note-private {
    border-left-color: #fd7e14;
    background: rgba(253, 126, 20, 0.05);
}

.note-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
    flex-wrap: wrap;
}

.note-header i {
    font-size: 1rem;
}

.note-edit-card.note-public .note-header i {
    color: var(--zj-primary);
}

.note-edit-card.note-private .note-header i {
    color: #fd7e14;
}

.note-header label {
    font-weight: 600;
    font-size: 0.95rem;
    color: var(--zj-text);
}

.note-hint {
    font-size: 0.75rem;
    color: var(--zj-text-light);
    margin-left: auto;
}

.note-edit-card textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 0.9rem;
    resize: vertical;
    min-height: 100px;
    font-family: inherit;
}

.note-edit-card textarea:focus {
    border-color: var(--zj-primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
}

.note-edit-card .note-content {
    padding: 0.75rem;
    background: #fff;
    border-radius: 6px;
    min-height: 60px;
}

/* ================================================================
   EXTRAFIELDS FORM
   ================================================================ */

.extrafields-form .extrafields-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.extrafields-form .extrafield-item {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.extrafields-form .extrafield-item label {
    font-weight: 500;
    font-size: 0.85rem;
    color: var(--zj-text);
}

.extrafields-form .extrafield-item input,
.extrafields-form .extrafield-item select,
.extrafields-form .extrafield-item textarea {
    padding: 0.5rem 0.75rem;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 0.9rem;
    width: 100%;
}

.extrafields-form .extrafield-item input:focus,
.extrafields-form .extrafield-item select:focus,
.extrafields-form .extrafield-item textarea:focus {
    border-color: var(--zj-primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
}

/* ================================================================
   RESPONSIVE
   ================================================================ */

@media (max-width: 768px) {
    .zonajob-container,
    .zonajob-order-card {
        padding: 0.5rem;
    }

    .zonajob-header h1 {
        font-size: 1.25rem;
    }
    
    /* Tabs responsive - single line with icons only on very small */
    .order-tabs {
        gap: 0;
        padding: 0;
    }
    
    .tab-link {
        padding: 0.6rem 0.75rem;
        font-size: 0.8rem;
    }
    
    .tab-link i {
        font-size: 0.95rem;
    }
    
    .order-create-form {
        padding: 1rem;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn-large {
        width: 100%;
    }
    
    .order-line-item {
        flex-direction: column;
    }
    
    .btn-remove-line {
        position: static;
        width: 100%;
        margin-top: 0.5rem;
    }

    .filter-form .filter-row {
        flex-direction: column;
    }

    .filter-group {
        min-width: 100%;
    }

    .filter-actions {
        width: 100%;
        justify-content: space-between;
    }

    .filter-actions .btn-search {
        flex: 1;
    }

    .zonajob-stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .stat-card {
        padding: 0.75rem;
    }

    .stat-value {
        font-size: 1.25rem;
    }

    .order-main-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .order-title h1 {
        font-size: 1.25rem;
    }

    .tab-link {
        padding: 0.5rem;
        min-width: 60px;
    }

    .tab-link i {
        font-size: 1rem;
    }

    .tab-link span {
        font-size: 0.7rem;
    }

    .info-grid {
        grid-template-columns: 1fr;
    }

    .contacts-list {
        grid-template-columns: 1fr;
    }

    .line-details {
        flex-direction: column;
        gap: 0.5rem;
    }

    .line-total {
        margin-left: 0;
    }

    .photos-gallery {
        grid-template-columns: repeat(2, 1fr);
    }

    .send-options {
        grid-template-columns: 1fr;
    }

    .zonajob-pagination {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    /* Notes responsive */
    .note-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }
    
    .note-hint {
        margin-left: 0;
    }
    
    /* Extrafields responsive */
    .extrafields-form .extrafields-grid {
        grid-template-columns: 1fr;
    }
    
    /* Docs grid responsive */
    .docs-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .zonajob-stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .order-info-row {
        flex-direction: column;
        gap: 0.25rem;
    }

    .photos-gallery {
        grid-template-columns: 1fr;
    }
    
    /* Tabs: show only icons on very small screens */
    .tab-link {
        padding: 0.6rem 0.5rem;
        flex-direction: column;
        gap: 0.15rem;
    }
    
    .tab-link span {
        font-size: 0.65rem;
        display: block;
    }
    
    .tab-link i {
        font-size: 1.1rem;
    }
}
/* ================================================================
   PRODUCT AUTOCOMPLETE
   ================================================================ */

.product-autocomplete {
    font-size: 1rem;
    width: 100% !important;
    max-width: 100%;
}

.autocomplete-dropdown {
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    max-height: 300px;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 0 2px 8px rgba(0,0,0,0.12);
}

.autocomplete-dropdown > div {
    padding: 0.75rem;
    cursor: pointer;
    border-bottom: 1px solid #eee;
    transition: background-color 0.2s ease;
}

.autocomplete-dropdown > div:last-child {
    border-bottom: none;
}

.autocomplete-dropdown > div:hover {
    background-color: #f5f5f5;
}