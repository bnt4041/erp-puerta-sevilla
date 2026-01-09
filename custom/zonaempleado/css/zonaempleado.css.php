<?php
/* Copyright (C) 2025 Zona Empleado Dev
 * CSS for Employee Zone - Responsive & Dolibarr Theme Integrated
 */

// Minimal setup for CSS file - Allow DB access to read theme colors
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

// Load Dolibarr environment
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

// Set CSS headers
header('Content-type: text/css');
if (empty($dolibarr_nocache)) {
    header('Cache-Control: max-age=10800, public, must-revalidate');
}

// Get Dolibarr theme colors - Try multiple sources
$colorbackhmenu1 = '173,15,15'; // Default color

// Method 1: From global config
if (!empty($conf->global->THEME_ELDY_TOPMENU_BACK1)) {
    $colorbackhmenu1 = $conf->global->THEME_ELDY_TOPMENU_BACK1;
} 
// Method 2: From database directly (Dolibarr 14.0.5 compatible)
elseif (!empty($db) && defined('MAIN_DB_PREFIX')) {
    $sql = "SELECT value FROM ".MAIN_DB_PREFIX."const WHERE name = 'THEME_ELDY_TOPMENU_BACK1' AND entity IN (0, ".((int) $conf->entity).") ORDER BY entity DESC LIMIT 1";
    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        $obj = $db->fetch_object($resql);
        if (!empty($obj->value)) {
            $colorbackhmenu1 = $obj->value;
        }
        $db->free($resql);
    }
}

// Convert color string to RGB format
if (function_exists('colorStringToArray')) {
    $tmp = colorStringToArray($colorbackhmenu1);
    if (is_array($tmp) && count($tmp) >= 3) {
        $colorbackhmenu1 = join(',', $tmp);
    }
}

// Clean any extra spaces
$colorbackhmenu1 = trim(str_replace(' ', '', $colorbackhmenu1));

?>

/* ================================================================
   ZONA EMPLEADO - RESPONSIVE DESIGN
   Integrado con tema Dolibarr
   ================================================================ */

/* Variables CSS con colores de Dolibarr */
:root {
    --ze-primary-color: rgb(<?php echo $colorbackhmenu1; ?>);
    --ze-background: #f5f5f5;
    --ze-card-bg: #ffffff;
    --ze-text-color: #333;
    --ze-text-light: #666;
    --ze-border-color: #ddd;
    --ze-shadow: 0 2px 8px rgba(0,0,0,0.08);
    --ze-shadow-hover: 0 4px 16px rgba(0,0,0,0.12);
    --ze-radius: 8px;
    --ze-transition: all 0.3s ease;
}

/* Back link (no margin bottom) */
.employee-back-wrapper { margin: 0; padding: 0; }
.employee-back-link {
    display: inline-block;
    font-size: 0.875rem;
    color: var(--ze-text-color);
    text-decoration: none;
    background: #fff;
    border: 1px solid var(--ze-border-color);
    padding: 4px 10px;
    border-radius: 4px;
    transition: var(--ze-transition);
}
.employee-back-link:hover { background: #f5f5f5; }

/* Reset y body */
.employee-zone-body {
    background: var(--ze-background) !important;
    margin: 0 !important;
    padding: 0 !important;
    min-height: 100vh;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

/* ================================================================
   HEADER
   ================================================================ */

.employee-header {
    background: var(--ze-primary-color);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    position: sticky;
    top: 0;
    z-index: 1000;
}

.employee-header .container-fluid {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 1rem;
}

.header-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 0;
    min-height: 60px;
    gap: 1rem;
}

.header-brand {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 0 0 auto;
}

.company-logo {
    height: 40px;
    max-width: 150px;
    object-fit: contain;
}

.company-name,
.zone-title {
    color: white;
    font-weight: 600;
}

.zone-title {
    font-size: 0.875rem;
    opacity: 0.9;
    font-weight: 400;
}

/* Navigation Desktop */
.main-nav {
    flex: 1;
}

.main-nav .nav-list {
    display: flex;
    list-style: none;
    margin: 0;
    padding: 0;
    gap: 0.5rem;
    justify-content: center;
}

.main-nav .nav-link {
    display: block;
    padding: 0.625rem 1rem;
    color: rgba(255,255,255,0.9);
    text-decoration: none;
    border-radius: 6px;
    transition: var(--ze-transition);
    font-size: 0.95rem;
}

.main-nav .nav-link:hover {
    background: rgba(255,255,255,0.15);
    color: white;
}

.main-nav .nav-item.active .nav-link {
    background: rgba(255,255,255,0.2);
    color: white;
    font-weight: 600;
}



/* User Menu */
.user-menu {
    position: relative;
    margin-left: auto;
    <!-- display: flex; -->
    align-items: center;
}

@media (orientation: landscape) {
    .user-menu {
        display: contents;
    }
}

/* Dropdown base */
.dropdown {
    position: relative;
}

.dropdown-menu {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    min-width: 260px;
    background: #fff;
    border: 1px solid var(--ze-border-color);
    border-radius: 8px;
    box-shadow: var(--ze-shadow);
    display: none;
    z-index: 1100;
    padding: 0.5rem 0;
}

.dropdown-menu.show {
    display: block;
}

.dropdown-divider {
    height: 1px;
    margin: 0.5rem 0;
    background: #eee;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.5rem 0.75rem;
    color: var(--ze-text-color);
    text-decoration: none;
}

.dropdown-item:hover {
    background: #f7f7f7;
}

.dropdown-menu-section {
    padding: 0.75rem 0;
}

.dropdown-section-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0 0.75rem 0.5rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--ze-text-light);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.dropdown-section-title i {
    color: var(--ze-primary-color);
}

.dropdown-section-links .dropdown-item {
    font-size: 0.9rem;
    color: var(--ze-text-color);
}

.dropdown-section-links .dropdown-item:hover {
    color: var(--ze-primary-color);
}

.user-menu-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0;
    background: transparent;
    border: none;
    color: white;
    cursor: pointer;
    transition: var(--ze-transition);
}

.user-menu-btn:hover .user-avatar {
    transform: scale(1.05);
}

.user-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: var(--ze-primary-color);
    border: 3px solid rgba(255,255,255,0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.875rem;
    transition: var(--ze-transition);
}

.dropdown {
    position: relative;
}

.dropdown-menu {
    position: absolute;
    top: calc(100% + 0.5rem);
    left: 0;
    background: white;
    border: 1px solid var(--ze-border-color);
    border-radius: var(--ze-radius);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    min-width: 220px;
    display: none;
    z-index: 9999;
    opacity: 0;
    transform: translateY(-10px);
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.dropdown-menu-right {
    right: 0;
    left: auto;
}

.dropdown-menu.show {
    display: block;
    opacity: 1;
    transform: translateY(0);
}

/* User Dropdown Specific Styles */
.user-dropdown-menu {
    min-width: 280px;
    max-width: 350px;
    width: max-content;
    padding: 0;
}

@media (max-width: 576px) {
    .user-dropdown-menu {
        min-width: 260px;
        max-width: calc(100vw - 2rem);
        right: 1rem !important;
    }
}

.user-info-card {
    background: var(--ze-primary-color);
    padding: 1.25rem 1rem;
    display: flex;
    align-items: flex-start;
    gap: 0.875rem;
    border-radius: var(--ze-radius) var(--ze-radius) 0 0;
}

@media (max-width: 576px) {
    .user-info-card {
        padding: 1rem 0.75rem;
        gap: 0.75rem;
    }
}

.user-info-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    border: 2px solid rgba(255,255,255,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.25rem;
    flex-shrink: 0;
}

@media (max-width: 576px) {
    .user-info-avatar {
        width: 50px;
        height: 50px;
        font-size: 1.1rem;
    }
}

.user-info-details {
    flex: 1;
    color: white;
}

.user-info-name {
    font-size: 0.875rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

@media (max-width: 576px) {
    .user-info-name {
        font-size: 0.8rem;
    }
}

.user-info-name i {
    color: rgba(255,255,255,0.9);
}

.user-role {
    font-weight: 400;
    opacity: 0.9;
    font-size: 0.8rem;
}

.user-info-dates {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    font-size: 0.75rem;
    opacity: 0.95;
}

.user-info-date {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.user-info-date i {
    width: 14px;
    font-size: 0.7rem;
}

/* Help Button */
.help-button-container {
    padding: 0.75rem 0.875rem;
    border-bottom: 1px solid var(--ze-border-color);
    text-align: center;
}

.help-button {
    display: inline-block;
    width: auto;
    max-width: 100%;
    padding: 0.5rem 0.875rem;
    background: #4A90E2;
    color: white;
    text-align: center;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 500;
    font-size: 0.813rem;
    transition: var(--ze-transition);
    white-space: nowrap;
    box-sizing: border-box;
}

.help-button:hover {
    background: #357ABD;
    color: white;
}

@media (max-width: 576px) {
    .help-button {
        font-size: 0.75rem;
        padding: 0.438rem 0.75rem;
    }
    
    .help-button-container {
        padding: 0.625rem 0.75rem;
    }
}

/* Dropdown Menu Options */
.dropdown-menu-options {
    padding: 0.5rem 0;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    color: var(--ze-text-color);
    text-decoration: none;
    transition: var(--ze-transition);
    position: relative;
}

.dropdown-item i {
    width: 20px;
    text-align: center;
    color: var(--ze-text-light);
    transition: var(--ze-transition);
}

.dropdown-item:hover {
    background: var(--ze-primary-color);
}

.dropdown-item:active,
.dropdown-item:focus {
    background: var(--ze-primary-color);
    outline: none;
}

.dropdown-item-expandable {
    cursor: pointer;
}

.dropdown-item-expandable:hover {
    background: var(--ze-primary-color);
}

.dropdown-divider {
    height: 1px;
    background: var(--ze-border-color);
    margin: 0.5rem 0;
}

/* Mobile Menu Toggle */
.mobile-menu-toggle {
    display: none;
    background: rgba(255,255,255,0.1);
    border: none;
    color: white;
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    cursor: pointer;
    font-size: 1.25rem;
}

.mobile-menu-toggle:hover {
    background: rgba(255,255,255,0.2);
}

/* Mobile Navigation */
.mobile-nav {
    display: none;
    background: rgba(0,0,0,0.05);
    border-top: 1px solid rgba(255,255,255,0.1);
}

.mobile-nav.show {
    display: block;
    animation: slideDown 0.3s ease;
}

.mobile-nav-list {
    list-style: none;
    margin: 0;
    padding: 0.5rem 0;
}

.mobile-nav-link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
    color: rgba(255,255,255,0.9);
    text-decoration: none;
    transition: var(--ze-transition);
}

.mobile-nav-link:hover,
.mobile-nav-item.active .mobile-nav-link {
    background: rgba(255,255,255,0.15);
    color: white;
}

/* ================================================================
   MAIN CONTENT
   ================================================================ */

.employee-main {
    min-height: calc(100vh - 60px);
    padding: 2rem 0;
}

.employee-main .container-fluid {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 1rem;
}

.employee-zone {
    width: 100%;
}

/* Welcome Section */
.welcome-section {
    background: white;
    padding: 2rem;
    border-radius: var(--ze-radius);
    box-shadow: var(--ze-shadow);
    margin-bottom: 2rem;
}

.welcome-section h1 {
    margin: 0 0 0.5rem 0;
    color: var(--ze-text-color);
    font-size: 1.75rem;
    font-weight: 600;
}

.user-greeting {
    margin: 0;
    color: var(--ze-text-light);
    font-size: 1.125rem;
}

/* Dashboard Grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

/* Dashboard Cards */
.dashboard-card {
    background: var(--ze-card-bg);
    border-radius: var(--ze-radius);
    box-shadow: var(--ze-shadow);
    overflow: hidden;
    transition: var(--ze-transition);
}

.dashboard-card:hover {
    box-shadow: var(--ze-shadow-hover);
    transform: translateY(-2px);
}

.card-header {
    background: linear-gradient(135deg, var(--ze-primary-color), rgba(<?php echo $colorbackhmenu1; ?>, 0.8));
    color: white;
    padding: 1.25rem 1.5rem;
    border-bottom: none;
}

.card-header h3 {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.card-content {
    padding: 1.5rem;
}

/* User Info Card */
.user-info p {
    margin: 0.75rem 0;
    color: var(--ze-text-color);
    font-size: 0.95rem;
}

.user-info strong {
    color: var(--ze-text-color);
    font-weight: 600;
    display: inline-block;
    min-width: 140px;
}

/* Quick Links */
.quick-links {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.quick-link-button {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
    background: #f8f9fa;
    color: var(--ze-text-color);
    text-decoration: none;
    border-radius: 6px;
    transition: var(--ze-transition);
    font-weight: 500;
}

.quick-link-button:hover {
    background: var(--ze-primary-color);
    color: white;
    transform: translateX(4px);
}

/* TPV Link special styling */
.quick-link-button.tpv-link {
    background: linear-gradient(135deg, #d21b2c 0%, #a01620 100%);
    color: white;
    font-weight: 600;
    border: none;
    box-shadow: 0 4px 12px rgba(210, 27, 44, 0.3);
}

.quick-link-button.tpv-link:hover {
    background: linear-gradient(135deg, #a01620 0%, #d21b2c 100%);
    transform: translateX(4px) translateY(-2px);
    box-shadow: 0 6px 16px rgba(210, 27, 44, 0.4);
}

.quick-link-button.tpv-link i {
    font-size: 1.25rem;
}

.quick-link-button.tpv-link span {
    font-size: 1rem;
}

/* Extensions List */
.extensions-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.extension-item {
    border-bottom: 1px solid #f0f0f0;
    padding-bottom: 0.75rem;
}

.extension-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.extension-link {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    text-decoration: none;
    color: var(--ze-text-color);
    transition: var(--ze-transition);
}

.extension-link:hover {
    color: var(--ze-primary-color);
}

.extension-name {
    font-weight: 600;
    font-size: 1rem;
}

.extension-desc {
    font-size: 0.875rem;
    color: var(--ze-text-light);
}

/* Activity List */
.activity-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.activity-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 6px;
    border-left: 3px solid var(--ze-primary-color);
}

.activity-date {
    font-size: 0.813rem;
    color: var(--ze-text-light);
    font-weight: 500;
}

.activity-text {
    color: var(--ze-text-color);
    font-size: 0.938rem;
}

/* Empty States */
.opacitymedium {
    opacity: 0.6;
    text-align: center;
    <!-- padding: 2rem; -->
    color: var(--ze-text-light);
}

/* Pictofixedwidth */
.pictofixedwidth {
    margin-right: 0.5rem;
}

/* ================================================================
   PROFILE PAGE
   ================================================================ */

.profile-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.profile-info-section,
.profile-stats-section,
.profile-actions-section,
.profile-preferences-section {
    background: white;
    padding: 1.5rem;
    border-radius: var(--ze-radius);
    box-shadow: var(--ze-shadow);
}

.profile-info-section h2,
.profile-stats-section h2,
.profile-actions-section h2 {
    margin: 0 0 1.5rem 0;
    color: var(--ze-text-color);
    font-size: 1.25rem;
    font-weight: 600;
    border-bottom: 2px solid var(--ze-primary-color);
    padding-bottom: 0.5rem;
}

.info-group {
    margin-bottom: 1.25rem;
}

.info-label {
    font-weight: 600;
    color: var(--ze-text-light);
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.25rem;
}

.info-value {
    color: var(--ze-text-color);
    font-size: 1rem;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    padding: 0.875rem;
    background: #f8f9fa;
    border-radius: 6px;
    margin-bottom: 0.75rem;
}

.stat-label {
    color: var(--ze-text-color);
    font-weight: 500;
}

.stat-value {
    color: var(--ze-primary-color);
    font-weight: 700;
    font-size: 1.125rem;
}

.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.action-btn {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1.25rem;
    background: var(--ze-primary-color);
    color: white;
    text-decoration: none;
    border-radius: 6px;
    transition: var(--ze-transition);
    font-weight: 500;
    text-align: center;
    justify-content: center;
}

.action-btn:hover {
    filter: brightness(1.1);
    transform: translateY(-2px);
    box-shadow: var(--ze-shadow-hover);
}

/* ================================================================
   ANIMATIONS
   ================================================================ */

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideDown {
    from {
        max-height: 0;
        opacity: 0;
    }
    to {
        max-height: 500px;
        opacity: 1;
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.dashboard-card {
    animation: fadeInUp 0.4s ease forwards;
}

.dashboard-card:nth-child(1) { animation-delay: 0.1s; }
.dashboard-card:nth-child(2) { animation-delay: 0.2s; }
.dashboard-card:nth-child(3) { animation-delay: 0.3s; }
.dashboard-card:nth-child(4) { animation-delay: 0.4s; }

/* ================================================================
   RESPONSIVE DESIGN
   ================================================================ */

/* Tablets (768px - 1024px) */
@media (max-width: 1024px) {
    .dashboard-grid {
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.25rem;
    }
    
    .profile-container {
        grid-template-columns: 1fr;
    }
}

/* Mobile (max 768px) */
@media (max-width: 768px) {
    /* Header mobile */
    .main-nav {
        display: none !important;
    }
    
    .mobile-menu-toggle {
        display: block;
    }
    
    .zone-title {
        display: none;
    }
    
    .company-logo {
        height: 35px;
        max-width: 120px;
    }
    
    .user-name {
        display: none !important;
    }
    
    .user-menu-btn {
        padding: 0.5rem;
        border-radius: 50%;
        min-width: 40px;
        justify-content: center;
    }
    
    /* Content mobile */
    .employee-main {
        padding: 1rem 0;
    }
    
    .welcome-section {
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    
    .welcome-section h1 {
        font-size: 1.5rem;
    }
    
    .user-greeting {
        font-size: 1rem;
    }
    
    .dashboard-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .card-header {
        padding: 1rem 1.25rem;
    }
    
    .card-header h3 {
        font-size: 1rem;
    }
    
    .card-content {
        padding: 1.25rem;
    }
    
    .user-info strong {
        min-width: 120px;
        font-size: 0.875rem;
    }
}

/* Small Mobile (max 480px) */
@media (max-width: 480px) {
    .header-content {
        padding: 0.5rem 0;
    }
    
    .header-brand {
        gap: 0.5rem;
    }
    
    .company-name {
        font-size: 1rem;
    }
    
    .welcome-section {
        padding: 1.25rem;
    }
    
    .welcome-section h1 {
        font-size: 1.25rem;
    }
    
    .card-header,
    .card-content {
        padding: 1rem;
    }
    
    .user-info p {
        font-size: 0.875rem;
    }
    
    .user-info strong {
        display: block;
        margin-bottom: 0.25rem;
    }
    
    .dashboard-grid {
        gap: 0.875rem;
    }
}

/* Landscape Mobile */
@media (max-width: 768px) and (orientation: landscape) {
    .employee-main {
        padding: 1rem 0;
    }
    
    .welcome-section {
        padding: 1rem 1.5rem;
    }
}

/* ================================================================
   USER CARD PAGE
   ================================================================ */

.usercard-page {
    width: 100%;
}

.usercard-container {
    max-width: 1200px;
    margin: 0 auto;
}

/* Back Button */
.usercard-back-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1.25rem;
    background: white;
    color: var(--ze-text-color);
    text-decoration: none;
    border-radius: var(--ze-radius);
    box-shadow: var(--ze-shadow);
    transition: var(--ze-transition);
    font-size: 0.9375rem;
    font-weight: 500;
}

.usercard-back-btn:hover {
    background: var(--ze-primary-color);
    color: white;
    box-shadow: var(--ze-shadow-hover);
    transform: translateX(-3px);
}

/* User Card Header */
.usercard-header {
    background: white;
    border-radius: var(--ze-radius);
    box-shadow: var(--ze-shadow);
    padding: 2rem;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 2rem;
}

.usercard-avatar-section {
    flex-shrink: 0;
}

.usercard-avatar {
    width: 120px;
    height: 120px;
    position: relative;
}

.usercard-avatar-img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid var(--ze-primary-color);
}

.usercard-avatar-placeholder {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: var(--ze-primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2.5rem;
    font-weight: 700;
    border: 4px solid rgba(0,0,0,0.1);
}

.usercard-header-info {
    flex: 1;
}

.usercard-name {
    margin: 0 0 0.5rem 0;
    font-size: 1.75rem;
    color: var(--ze-text-color);
    font-weight: 600;
}

.usercard-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.375rem 0.875rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
}

.usercard-badge-admin {
    background: #fef3c7;
    color: #d97706;
}

/* User Card Grid */
.usercard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.usercard-card {
    background: white;
    border-radius: var(--ze-radius);
    box-shadow: var(--ze-shadow);
    overflow: hidden;
    transition: var(--ze-transition);
}

.usercard-card:hover {
    box-shadow: var(--ze-shadow-hover);
}

.usercard-card-header {
    background: linear-gradient(135deg, var(--ze-primary-color), rgba(<?php echo $colorbackhmenu1; ?>, 0.8));
    padding: 1.25rem 1.5rem;
    border-bottom: 3px solid rgba(0,0,0,0.05);
}

.usercard-card-header h3 {
    margin: 0;
    color: white;
    font-size: 1.125rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.usercard-card-body {
    padding: 1.5rem;
}

.usercard-info-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 1rem 0;
    border-bottom: 1px solid #f0f0f0;
    gap: 1rem;
}

.usercard-info-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.usercard-info-row:first-child {
    padding-top: 0;
}

.usercard-info-label {
    font-weight: 500;
    color: var(--ze-text-light);
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
    min-width: 120px;
}

.usercard-info-label i {
    width: 20px;
    text-align: center;
    color: var(--ze-primary-color);
}

.usercard-info-value {
    color: var(--ze-text-color);
    font-size: 0.9375rem;
    text-align: right;
    word-break: break-word;
}

.usercard-info-value a {
    color: var(--ze-primary-color);
    text-decoration: none;
    transition: var(--ze-transition);
}

.usercard-info-value a:hover {
    text-decoration: underline;
}

.usercard-no-data {
    color: var(--ze-text-light);
    font-style: italic;
    text-align: center;
    padding: 1.5rem;
    margin: 0;
}

.usercard-no-data i {
    margin-right: 0.5rem;
}

/* Responsive User Card */
@media (max-width: 768px) {
    .usercard-header {
        flex-direction: column;
        text-align: center;
        padding: 1.5rem;
    }
    
    .usercard-avatar {
        width: 100px;
        height: 100px;
    }
    
    .usercard-avatar-placeholder {
        font-size: 2rem;
    }
    
    .usercard-name {
        font-size: 1.5rem;
    }
    
    .usercard-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .usercard-info-row {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .usercard-info-label {
        min-width: auto;
    }
    
    .usercard-info-value {
        text-align: left;
    }
}

@media (max-width: 576px) {
    .usercard-card-header,
    .usercard-card-body {
        padding: 1rem;
    }
    
    .usercard-card-header h3 {
        font-size: 1rem;
    }
}

/* Print Styles */
@media print {
    .employee-header,
    .mobile-menu-toggle,
    .user-menu,
    .mobile-nav {
        display: none !important;
    }
    
    .employee-main {
        padding: 0;
    }
    
    .dashboard-card,
    .usercard-card {
        break-inside: avoid;
        box-shadow: none;
        border: 1px solid #ddd;
    }
}
