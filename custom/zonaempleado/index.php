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
 * \file    zonaempleado/index.php
 * \ingroup zonaempleado
 * \brief   Employee Zone main page
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
$zonaempleado_class_path = __DIR__.'/class/zonaempleado.class.php';
$zonaempleado_lib_path = __DIR__.'/lib/zonaempleado.lib.php';

if (!file_exists($zonaempleado_class_path)) {
    die("Error: ZonaEmpleado class file not found at: ".$zonaempleado_class_path);
}
if (!file_exists($zonaempleado_lib_path)) {
    die("Error: ZonaEmpleado library file not found at: ".$zonaempleado_lib_path);
}

require_once $zonaempleado_class_path;
require_once $zonaempleado_lib_path;

// Load translation files required by the page
$langs->loadLangs(array("zonaempleado@zonaempleado"));

// Get parameters
$action = GETPOST('action', 'aZ09');
$myparam = GETPOST('myparam', 'alpha');

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
$hookmanager->initHooks(array('zonaempleadoindex'));

/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

/*
 * View
 */

$title = 'Zona de Empleado';
$help_url = '';

// Recopilar enlaces rápidos disponibles para mostrarlos también en el header
$quickLinks = zonaempleado_get_quick_links();

// Usar helper estándar de Zona Empleado
zonaempleado_print_header($title, array(), '', $quickLinks);

// Get available extensions for the employee zone
$extensions = zonaempleado_get_extensions();

print '<div class="employee-zone">';

// Welcome section
print '<div class="welcome-section">';
print '<h1>Hola <strong>'.$user->getFullName($langs).'</strong></h1>';
print '</div>';

// Dashboard grid
print '<div class="dashboard-grid">';

// User profile card
print '<div class="dashboard-card profile-card">';
print '<div class="card-header">';
print '<h3>'.img_picto('', 'user', 'class="pictofixedwidth"').' Perfil</h3>';
print '</div>';
print '<div class="card-content">';
print '<div class="user-info">';
print '<p><strong>Login:</strong> '.$user->login.'</p>';
print '<p><strong>Correo:</strong> '.$user->email.'</p>';
if ($user->datec) {
    print '<p><strong>Fecha de creación:</strong> '.dol_print_date($user->datec, 'day').'</p>';
}
if ($user->datelastlogin) {
    print '<p><strong>Última conexión:</strong> '.dol_print_date($user->datelastlogin, 'dayhour').'</p>';
}
print '</div>';
print '</div>';
print '</div>';

// Quick access card
print '<div class="dashboard-card quickaccess-card">';
print '<div class="card-header">';
print '<h3>'.img_picto('', 'object_action', 'class="pictofixedwidth"').' Accesos Rápidos</h3>';
print '</div>';
print '<div class="card-content">';
print '<div class="quick-links">';

if (!empty($quickLinks)) {
    foreach ($quickLinks as $link) {
        print '<a href="'.$link['url'].'" class="quick-link-button"';
        if (isset($link['target'])) {
            print ' target="'.$link['target'].'"';
        }
        print '>';
        if (!empty($link['icon'])) {
            print zonaempleado_render_icon($link['icon']);
        }
        print $link['label'];
        print '</a>';
    }
}

if (empty($quickLinks)) {
    print '<p class="opacitymedium" style="margin-top: 10px;">Otros módulos se mostrarán aquí cuando estén disponibles</p>';
}

print '</div>';
print '</div>';
print '</div>';

// Extensions card
if (!empty($extensions)) {
    print '<div class="dashboard-card extensions-card">';
    print '<div class="card-header">';
    print '<h3>'.img_picto('', 'technic', 'class="pictofixedwidth"').' Extensiones disponibles</h3>';
    print '</div>';
    print '<div class="card-content">';
    print '<div class="extensions-list">';
    
    foreach ($extensions as $extension) {
        if ($extension['enabled']) {
            print '<div class="extension-item">';
            print '<a href="'.$extension['url'].'" class="extension-link">';
            if (isset($extension['icon'])) {
                print img_picto('', $extension['icon'], 'class="pictofixedwidth"');
            }
            print '<span class="extension-name">'.$extension['name'].'</span>';
            if (isset($extension['description'])) {
                print '<span class="extension-desc">'.$extension['description'].'</span>';
            }
            print '</a>';
            print '</div>';
        }
    }
    
    print '</div>';
    print '</div>';
    print '</div>';
}

// Recent activity card (placeholder for future development)
print '<div class="dashboard-card activity-card">';
print '<div class="card-header">';
print '<h3>'.img_picto('', 'calendar', 'class="pictofixedwidth"').' Actividad Reciente</h3>';
print '</div>';
print '<div class="card-content">';

// Hook to allow modules to add recent activity
$activities = array();
$parameters = array('activities' => &$activities, 'user' => $user);
$reshook = $hookmanager->executeHooks('getRecentActivity', $parameters);

if (empty($activities)) {
    print '<p class="opacitymedium">No hay actividad reciente</p>';
} else {
    // Sort activities by date (most recent first)
    usort($activities, function($a, $b) {
        $date_a = isset($a['date']) ? $a['date'] : 0;
        $date_b = isset($b['date']) ? $b['date'] : 0;
        return $date_b - $date_a; // Descending order
    });
    
    // Limit to 10 most recent
    $activities = array_slice($activities, 0, 10);
    
    print '<div class="activity-list">';
    foreach ($activities as $activity) {
        print '<div class="activity-item">';
        if (isset($activity['icon'])) {
            print '<i class="fas '.$activity['icon'].' pictofixedwidth"></i> ';
        }
        print '<span class="activity-date">'.dol_print_date($activity['date'], 'dayhour').'</span>';
        print '<span class="activity-text">'.$activity['text'].'</span>';
        print '</div>';
    }
    print '</div>';
}

print '</div>';
print '</div>';

print '</div>'; // End dashboard-grid

print '</div>'; // End employee-zone

// Execute hook for additional content
$parameters = array();
$reshook = $hookmanager->executeHooks('addEmployeeZoneContent', $parameters);
if ($reshook > 0) {
    print $hookmanager->resPrint;
}

// Footer estándar Zona Empleado
zonaempleado_print_footer();