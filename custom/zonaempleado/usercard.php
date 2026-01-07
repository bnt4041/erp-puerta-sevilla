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
 * \file    zonaempleado/usercard.php
 * \ingroup zonaempleado
 * \brief   Employee user card - View only profile information
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
    header("Location: ".DOL_URL_ROOT."/");
    exit;
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';

// Load zonaempleado files
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

// Load translation files
$langs->loadLangs(array("zonaempleado@zonaempleado", "users", "admin"));

// Security check
$hasAccess = true;
if (isset($user->rights->zonaempleado) && isset($user->rights->zonaempleado->access) && isset($user->rights->zonaempleado->access->read)) {
    $hasAccess = $user->rights->zonaempleado->access->read;
}

if (!$hasAccess) {
    accessforbidden($langs->trans('ZonaEmpleadoAccessDenied'));
}

// Reload user object to get all data
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
$userobj = new User($db);
$result = $userobj->fetch($user->id);
if ($result > 0) {
    $user = $userobj;
}

// Initialize hookmanager
if (empty($hookmanager)) {
    require_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
    $hookmanager = new HookManager($db);
}
$hookmanager->initHooks(array('zonaempleadousercard'));

/*
 * View
 */

$title = 'Perfil';
$help_url = '';

// Usar helper estándar de Zona Empleado
zonaempleado_print_header($title);

print '<div class="employee-zone usercard-page">';

print '<div class="usercard-container">';

// Back button
print '<div class="employee-back-wrapper">';
print '<a href="'.__DIR__.'/index.php" class="employee-back-link">';
print '← Volver a Zona de Empleados';
print '</a>';
print '</div>';

// User Card Header
print '<div class="usercard-header">';
print '<div class="usercard-avatar-section">';
print '<div class="usercard-avatar">';
// Get user photo if exists
$photo = '';
if (!empty($user->photo)) {
    $photo = DOL_URL_ROOT.'/viewimage.php?modulepart=userphoto&entity='.$user->entity.'&file='.urlencode($user->id.'/photos/'.$user->photo);
} else {
    // Show initials
    $initials = '';
    if ($user->firstname) $initials .= strtoupper(substr($user->firstname, 0, 1));
    if ($user->lastname) $initials .= strtoupper(substr($user->lastname, 0, 1));
    if (empty($initials)) $initials = strtoupper(substr($user->login, 0, 2));
}

if ($photo) {
    print '<img src="'.$photo.'" alt="'.$user->getFullName($langs).'" class="usercard-avatar-img">';
} else {
    print '<div class="usercard-avatar-placeholder">'.$initials.'</div>';
}
print '</div>';
print '</div>';

print '<div class="usercard-header-info">';
print '<h1 class="usercard-name">'.$user->getFullName($langs).'</h1>';
if ($user->admin) {
    print '<span class="usercard-badge usercard-badge-admin"><i class="fas fa-shield-alt"></i> '.$langs->trans('Administrator').'</span>';
}
print '</div>';
print '</div>';

// User Information Cards
print '<div class="usercard-grid">';

// Personal Information Card
print '<div class="usercard-card">';
print '<div class="usercard-card-header">';
print '<h3><i class="fas fa-user"></i> Información Personal</h3>';
print '</div>';
print '<div class="usercard-card-body">';

$personal_info = array();

if (!empty($user->firstname)) {
    $personal_info[] = array(
        'label' => 'Nombre',
        'value' => $user->firstname,
        'icon' => 'fa-user'
    );
}

if (!empty($user->lastname)) {
    $personal_info[] = array(
        'label' => 'Apellidos',
        'value' => $user->lastname,
        'icon' => 'fa-user'
    );
}

if (!empty($user->login)) {
    $personal_info[] = array(
        'label' => 'Usuario',
        'value' => $user->login,
        'icon' => 'fa-sign-in-alt'
    );
}

// Try to get national ID from user extrafields or array_options
if (!empty($user->array_options['options_nationalid'])) {
    $personal_info[] = array(
        'label' => 'DNI/NIE',
        'value' => $user->array_options['options_nationalid'],
        'icon' => 'fa-id-card'
    );
} elseif (!empty($user->national_registration_number)) {
    $personal_info[] = array(
        'label' => 'DNI/NIE',
        'value' => $user->national_registration_number,
        'icon' => 'fa-id-card'
    );
}

// Try to get birth date
if (!empty($user->birth)) {
    $personal_info[] = array(
        'label' => 'Fecha de nacimiento',
        'value' => dol_print_date($user->birth, 'day'),
        'icon' => 'fa-birthday-cake'
    );
}

foreach ($personal_info as $info) {
    print '<div class="usercard-info-row">';
    print '<div class="usercard-info-label"><i class="fas '.$info['icon'].'"></i> '.$info['label'].'</div>';
    print '<div class="usercard-info-value">'.$info['value'].'</div>';
    print '</div>';
}

print '</div>';
print '</div>';

// Contact Information Card
print '<div class="usercard-card">';
print '<div class="usercard-card-header">';
print '<h3><i class="fas fa-address-book"></i> Información de Contacto</h3>';
print '</div>';
print '<div class="usercard-card-body">';

$contact_info = array();

if (!empty($user->email)) {
    $contact_info[] = array(
        'label' => 'Email',
        'value' => $user->email,
        'icon' => 'fa-envelope',
        'link' => 'mailto:'.$user->email
    );
}

if (!empty($user->office_phone)) {
    $contact_info[] = array(
        'label' => 'Teléfono Oficina',
        'value' => $user->office_phone,
        'icon' => 'fa-phone',
        'link' => 'tel:'.$user->office_phone
    );
}

if (!empty($user->user_mobile)) {
    $contact_info[] = array(
        'label' => 'Teléfono Móvil',
        'value' => $user->user_mobile,
        'icon' => 'fa-mobile-alt',
        'link' => 'tel:'.$user->user_mobile
    );
}

if (!empty($user->address)) {
    $contact_info[] = array(
        'label' => 'Dirección',
        'value' => $user->address,
        'icon' => 'fa-map-marker-alt'
    );
}

if (empty($contact_info)) {
    print '<p class="usercard-no-data"><i class="fas fa-info-circle"></i> No hay información de contacto disponible</p>';
} else {
    foreach ($contact_info as $info) {
        print '<div class="usercard-info-row">';
        print '<div class="usercard-info-label"><i class="fas '.$info['icon'].'"></i> '.$info['label'].'</div>';
        print '<div class="usercard-info-value">';
        if (!empty($info['link'])) {
            print '<a href="'.$info['link'].'">'.$info['value'].'</a>';
        } else {
            print $info['value'];
        }
        print '</div>';
        print '</div>';
    }
}

print '</div>';
print '</div>';

// Employment Information Card
print '<div class="usercard-card">';
print '<div class="usercard-card-header">';
print '<h3><i class="fas fa-briefcase"></i> Información Laboral</h3>';
print '</div>';
print '<div class="usercard-card-body">';

$employment_info = array();

if (!empty($user->job)) {
    $employment_info[] = array(
        'label' => 'Puesto / Función',
        'value' => $user->job,
        'icon' => 'fa-briefcase'
    );
}

if (!empty($user->datec)) {
    $employment_info[] = array(
        'label' => 'Cuenta creada',
        'value' => dol_print_date($user->datec, 'day'),
        'icon' => 'fa-calendar-plus'
    );
}

if (!empty($user->datec)) {
    $employment_info[] = array(
        'label' => 'Fecha y hora de creación',
        'value' => dol_print_date($user->datec, 'dayhour'),
        'icon' => 'fa-clock'
    );
}

if (empty($employment_info)) {
    print '<p class="usercard-no-data"><i class="fas fa-info-circle"></i> No hay información laboral disponible</p>';
} else {
    foreach ($employment_info as $info) {
        print '<div class="usercard-info-row">';
        print '<div class="usercard-info-label"><i class="fas '.$info['icon'].'"></i> '.$info['label'].'</div>';
        print '<div class="usercard-info-value">'.$info['value'].'</div>';
        print '</div>';
    }
}

print '</div>';
print '</div>';

print '</div>'; // End usercard-grid

// Hook to allow other modules to add content
$parameters = array('user' => $user);
$reshook = $hookmanager->executeHooks('printUserCardContent', $parameters);
if ($reshook > 0) {
    print $hookmanager->resPrint;
}

print '</div>'; // End usercard-container
print '</div>'; // End employee-zone

// Footer estándar Zona Empleado
zonaempleado_print_footer();
