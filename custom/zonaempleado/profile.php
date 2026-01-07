<?php
/* Copyright (C) 2025 Zona Empleado Dev
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    zonaempleado/profile.php
 * \ingroup zonaempleado
 * \brief   Employee profile page
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

// Security check - Check if user is authenticated
if (empty($user) || !$user->id) {
    // Redirect to login page
    header("Location: ".DOL_URL_ROOT."/");
    exit;
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';

// Load zonaempleado files with error handling
$zonaempleado_class_path = DOL_DOCUMENT_ROOT.'/custom/zonaempleado/class/zonaempleado.class.php';
$zonaempleado_lib_path = DOL_DOCUMENT_ROOT.'/custom/zonaempleado/lib/zonaempleado.lib.php';

if (!file_exists($zonaempleado_class_path)) {
    die("Error: ZonaEmpleado class file not found at: ".$zonaempleado_class_path);
}
if (!file_exists($zonaempleado_lib_path)) {
    die("Error: ZonaEmpleado library file not found at: ".$zonaempleado_lib_path);
}

require_once $zonaempleado_class_path;
require_once $zonaempleado_lib_path;

// Load translation files required by the page
$langs->loadLangs(array("zonaempleado@zonaempleado", "users"));

// Get parameters
$action = GETPOST('action', 'aZ09');

// Security check - Check if user has permission to access employee zone
// By default, all authenticated users have access unless explicitly denied
$hasAccess = true;
if (isset($user->rights->zonaempleado) && isset($user->rights->zonaempleado->access) && isset($user->rights->zonaempleado->access->read)) {
    $hasAccess = $user->rights->zonaempleado->access->read;
}

if (!$hasAccess) {
    accessforbidden($langs->trans('ZonaEmpleadoAccessDenied'));
}

// Initialize technical objects
$object = null;

// Ensure hookmanager is initialized
if (empty($hookmanager)) {
    require_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
    $hookmanager = new HookManager($db);
}
$hookmanager->initHooks(array('zonaempleadoprofile'));

/*
 * Actions
 */

if ($action == 'update_preferences') {
    // Handle preference updates (placeholder for future implementation)
    $timezone = GETPOST('timezone', 'alpha');
    $language = GETPOST('language', 'alpha');
    $theme = GETPOST('theme', 'alpha');
    
    // TODO: Implement preference saving
    
    setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
}

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

/*
 * View
 */

$title = $langs->trans('ZonaEmpleadoProfile');
$help_url = '';

// Usar helper estándar de Zona Empleado
zonaempleado_print_header($title);

print '<div class="employee-zone profile-page">';

// Breadcrumb
print '<div class="breadcrumb-section">';
$breadcrumb_items = array(
    array('label' => $langs->trans('ZonaEmpleadoHome'), 'url' => '/custom/zonaempleado/index.php'),
    array('label' => $langs->trans('ZonaEmpleadoProfile'))
);
print zonaempleado_breadcrumb($breadcrumb_items);
print '</div>';

print '<div class="profile-container">';

// Profile Header
print '<div class="profile-header">';
print '<div class="profile-avatar">';
// TODO: Implement user avatar functionality
print '<div class="avatar-placeholder">';
$initials = '';
if ($user->firstname) $initials .= strtoupper(substr($user->firstname, 0, 1));
if ($user->lastname) $initials .= strtoupper(substr($user->lastname, 0, 1));
if (empty($initials)) $initials = strtoupper(substr($user->login, 0, 2));
print $initials;
print '</div>';
print '</div>';
print '<div class="profile-info">';
print '<h1>' . $user->getFullName($langs) . '</h1>';
print '<p class="profile-role">' . $user->login . '</p>';
if ($user->email) {
    print '<p class="profile-email"><i class="fas fa-envelope"></i> ' . $user->email . '</p>';
}
print '</div>';
print '</div>';

print '<div class="profile-content row">';

// Left Column - User Information
print '<div class="col-md-8">';

// Basic Information Card
print '<div class="profile-card">';
print '<div class="card-header">';
print '<h3><i class="fas fa-user"></i> ' . $langs->trans('UserInfo') . '</h3>';
print '</div>';
print '<div class="card-content">';

print '<div class="info-grid">';

// Login
print '<div class="info-item">';
print '<label>' . $langs->trans('Login') . ':</label>';
print '<span>' . $user->login . '</span>';
print '</div>';

// Full Name
print '<div class="info-item">';
print '<label>' . $langs->trans('Name') . ':</label>';
print '<span>' . $user->getFullName($langs) . '</span>';
print '</div>';

// Email
print '<div class="info-item">';
print '<label>' . $langs->trans('Email') . ':</label>';
print '<span>' . ($user->email ? $user->email : $langs->trans('NotDefined')) . '</span>';
print '</div>';

// Phone
if ($user->office_phone || $user->user_mobile) {
    print '<div class="info-item">';
    print '<label>' . $langs->trans('Phone') . ':</label>';
    print '<span>';
    if ($user->office_phone) print $user->office_phone;
    if ($user->office_phone && $user->user_mobile) print ' / ';
    if ($user->user_mobile) print $user->user_mobile;
    print '</span>';
    print '</div>';
}

// Job title
if ($user->job) {
    print '<div class="info-item">';
    print '<label>' . $langs->trans('JobTitle') . ':</label>';
    print '<span>' . $user->job . '</span>';
    print '</div>';
}

// Department
if (!empty($user->arrays['department'])) {
    print '<div class="info-item">';
    print '<label>' . $langs->trans('Department') . ':</label>';
    print '<span>' . $user->arrays['department'] . '</span>';
    print '</div>';
}

// Last login
if ($user->datelastlogin) {
    print '<div class="info-item">';
    print '<label>' . $langs->trans('ZonaEmpleadoLoginTime') . ':</label>';
    print '<span>' . dol_print_date($user->datelastlogin, 'dayhour') . '</span>';
    print '</div>';
}

// Member since
if ($user->datec) {
    print '<div class="info-item">';
    print '<label>' . $langs->trans('DateCreation') . ':</label>';
    print '<span>' . dol_print_date($user->datec, 'day') . '</span>';
    print '</div>';
}

print '</div>'; // End info-grid
print '</div>'; // End card-content
print '</div>'; // End profile-card

// Statistics Card (placeholder for future development)
print '<div class="profile-card">';
print '<div class="card-header">';
print '<h3><i class="fas fa-chart-bar"></i> ' . $langs->trans('Statistics') . '</h3>';
print '</div>';
print '<div class="card-content">';

// Hook to allow modules to add statistics
$stats = array();
$parameters = array('user' => $user, 'stats' => &$stats);
$reshook = $hookmanager->executeHooks('getUserProfileStats', $parameters);

if (empty($stats)) {
    print '<p class="opacitymedium">' . $langs->trans('NoDataAvailable') . '</p>';
} else {
    print '<div class="stats-grid">';
    foreach ($stats as $stat) {
        print '<div class="stat-item">';
        print '<div class="stat-value">' . $stat['value'] . '</div>';
        print '<div class="stat-label">' . $stat['label'] . '</div>';
        print '</div>';
    }
    print '</div>';
}

print '</div>';
print '</div>';

print '</div>'; // End col-md-8

// Right Column - Actions & Preferences
print '<div class="col-md-4">';

// Quick Actions Card
print '<div class="profile-card">';
print '<div class="card-header">';
print '<h3><i class="fas fa-bolt"></i> ' . $langs->trans('QuickActions') . '</h3>';
print '</div>';
print '<div class="card-content">';

$quick_actions = array();

// Hook to allow modules to add quick actions
$parameters = array('user' => $user, 'actions' => &$quick_actions);
$reshook = $hookmanager->executeHooks('getUserProfileActions', $parameters);

if (empty($quick_actions)) {
    print '<p class="opacitymedium">' . $langs->trans('ZonaEmpleadoNoModules') . '</p>';
} else {
    print '<div class="action-buttons">';
    foreach ($quick_actions as $action) {
        print '<a href="' . $action['url'] . '" class="action-btn">';
        if (isset($action['icon'])) {
            print '<i class="' . $action['icon'] . '"></i> ';
        }
        print $action['label'];
        print '</a>';
    }
    print '</div>';
}

print '</div>';
print '</div>';

// Preferences Card (placeholder for future implementation)
print '<div class="profile-card">';
print '<div class="card-header">';
print '<h3><i class="fas fa-cog"></i> ' . $langs->trans('Preferences') . '</h3>';
print '</div>';
print '<div class="card-content">';

print '<form method="post" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="action" value="update_preferences">';

// Language preference
print '<div class="form-group">';
print '<label for="language">' . $langs->trans('Language') . ':</label>';
print '<select name="language" id="language" class="form-control" disabled>';
print '<option value="' . $langs->defaultlang . '">' . $langs->trans($langs->defaultlang) . '</option>';
print '</select>';
print '<small class="form-text text-muted">' . $langs->trans('ContactAdminToChange') . '</small>';
print '</div>';

// Theme preference (placeholder)
print '<div class="form-group">';
print '<label for="theme">' . $langs->trans('Theme') . ':</label>';
print '<select name="theme" id="theme" class="form-control" disabled>';
print '<option value="default">' . $langs->trans('Default') . '</option>';
print '</select>';
print '<small class="form-text text-muted">' . $langs->trans('ComingSoon') . '</small>';
print '</div>';

// Timezone preference (placeholder)
print '<div class="form-group">';
print '<label for="timezone">' . $langs->trans('Timezone') . ':</label>';
print '<select name="timezone" id="timezone" class="form-control" disabled>';
print '<option value="' . date_default_timezone_get() . '">' . date_default_timezone_get() . '</option>';
print '</select>';
print '<small class="form-text text-muted">' . $langs->trans('ComingSoon') . '</small>';
print '</div>';

print '<button type="submit" class="btn btn-primary" disabled>' . $langs->trans('Save') . '</button>';
print '</form>';

print '</div>';
print '</div>';

print '</div>'; // End col-md-4

print '</div>'; // End profile-content
print '</div>'; // End profile-container

print '</div>'; // End employee-zone

// Execute hook for additional content
$parameters = array();
$reshook = $hookmanager->executeHooks('addEmployeeProfileContent', $parameters);
if ($reshook > 0) {
    print $hookmanager->resPrint;
}

// Footer estándar Zona Empleado
zonaempleado_print_footer();