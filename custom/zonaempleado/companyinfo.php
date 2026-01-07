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
 * \file    zonaempleado/companyinfo.php
 * \ingroup zonaempleado
 * \brief   Company information page - View only
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
$langs->loadLangs(array("zonaempleado@zonaempleado", "companies", "admin"));

// Security check
$hasAccess = true;
if (isset($user->rights->zonaempleado) && isset($user->rights->zonaempleado->access) && isset($user->rights->zonaempleado->access->read)) {
    $hasAccess = $user->rights->zonaempleado->access->read;
}

if (!$hasAccess) {
    accessforbidden('No tienes permisos para acceder a esta página');
}

// Initialize hookmanager
if (empty($hookmanager)) {
    require_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
    $hookmanager = new HookManager($db);
}
$hookmanager->initHooks(array('zonaempleadocompanyinfo'));

/*
 * View
 */

$title = 'Información de la Empresa';
$help_url = '';

// Usar helper estándar de Zona Empleado
zonaempleado_print_header($title);

print '<div class="employee-zone companyinfo-page">';

print '<div class="usercard-container">';

// Back button
print '<div class="employee-back-wrapper">';
print '<a href="'.__DIR__.'/index.php" class="employee-back-link">';
print '← Volver a Zona de Empleados';
print '</a>';
print '</div>';

// Company Header
print '<div class="usercard-header">';
print '<div class="usercard-avatar-section">';
print '<div class="usercard-avatar">';

// Get company logo
if (!empty($mysoc->logo)) {
    $logo_url = DOL_URL_ROOT.'/viewimage.php?modulepart=mycompany&file='.urlencode('logos/'.$mysoc->logo);
    print '<img src="'.$logo_url.'" alt="'.$mysoc->name.'" class="usercard-avatar-img" style="border-radius: 8px;">';
} else {
    // Show company initials
    $company_initials = '';
    $words = explode(' ', $mysoc->name);
    foreach ($words as $word) {
        if (!empty($word)) {
            $company_initials .= strtoupper(substr($word, 0, 1));
            if (strlen($company_initials) >= 2) break;
        }
    }
    if (empty($company_initials)) $company_initials = strtoupper(substr($mysoc->name, 0, 2));
    print '<div class="usercard-avatar-placeholder">'.$company_initials.'</div>';
}

print '</div>';
print '</div>';

print '<div class="usercard-header-info">';
print '<h1 class="usercard-name">'.$mysoc->name.'</h1>';
if (!empty($mysoc->idprof1)) {
    print '<span class="usercard-badge usercard-badge-admin"><i class="fas fa-building"></i> '.($mysoc->idprof1).'</span>';
}
print '</div>';
print '</div>';

// Company Information Cards
print '<div class="usercard-grid">';

// Company Data Card
print '<div class="usercard-card">';
print '<div class="usercard-card-header">';
print '<h3><i class="fas fa-building"></i> Datos de la Empresa</h3>';
print '</div>';
print '<div class="usercard-card-body">';

$company_info = array();

if (!empty($mysoc->name)) {
    $company_info[] = array(
        'label' => 'Nombre',
        'value' => $mysoc->name,
        'icon' => 'fa-building'
    );
}

if (!empty($mysoc->idprof1)) {
    $company_info[] = array(
        'label' => 'CIF/NIF',
        'value' => $mysoc->idprof1,
        'icon' => 'fa-id-card'
    );
}

if (!empty($mysoc->idprof2)) {
    $company_info[] = array(
        'label' => 'Número de Seguridad Social',
        'value' => $mysoc->idprof2,
        'icon' => 'fa-shield-alt'
    );
}

if (!empty($mysoc->country)) {
    $company_info[] = array(
        'label' => 'País',
        'value' => $mysoc->country,
        'icon' => 'fa-globe'
    );
}

if (!empty($mysoc->forme_juridique)) {
    $company_info[] = array(
        'label' => 'Forma Jurídica',
        'value' => $mysoc->forme_juridique,
        'icon' => 'fa-balance-scale'
    );
}

if (!empty($mysoc->capital)) {
    $company_info[] = array(
        'label' => 'Capital Social',
        'value' => price($mysoc->capital, 0, '', 1, -1, -1, $conf->currency),
        'icon' => 'fa-coins'
    );
}

foreach ($company_info as $info) {
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

if (!empty($mysoc->email)) {
    $contact_info[] = array(
        'label' => 'Email',
        'value' => $mysoc->email,
        'icon' => 'fa-envelope',
        'link' => 'mailto:'.$mysoc->email
    );
}

if (!empty($mysoc->phone)) {
    $contact_info[] = array(
        'label' => 'Teléfono',
        'value' => $mysoc->phone,
        'icon' => 'fa-phone',
        'link' => 'tel:'.$mysoc->phone
    );
}

if (!empty($mysoc->fax)) {
    $contact_info[] = array(
        'label' => 'Fax',
        'value' => $mysoc->fax,
        'icon' => 'fa-fax',
    );
}

if (!empty($mysoc->url)) {
    $contact_info[] = array(
        'label' => 'Sitio Web',
        'value' => $mysoc->url,
        'icon' => 'fa-globe',
        'link' => $mysoc->url
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
            print '<a href="'.$info['link'].'" target="_blank">'.$info['value'].'</a>';
        } else {
            print $info['value'];
        }
        print '</div>';
        print '</div>';
    }
}

print '</div>';
print '</div>';

// Address Information Card
print '<div class="usercard-card">';
print '<div class="usercard-card-header">';
print '<h3><i class="fas fa-map-marker-alt"></i> Dirección</h3>';
print '</div>';
print '<div class="usercard-card-body">';

$address_info = array();

if (!empty($mysoc->address)) {
    $address_info[] = array(
        'label' => 'Dirección',
        'value' => $mysoc->address,
        'icon' => 'fa-map-marker-alt'
    );
}

if (!empty($mysoc->zip)) {
    $address_info[] = array(
        'label' => 'Código Postal',
        'value' => $mysoc->zip,
        'icon' => 'fa-mail-bulk'
    );
}

if (!empty($mysoc->town)) {
    $address_info[] = array(
        'label' => 'Ciudad',
        'value' => $mysoc->town,
        'icon' => 'fa-city'
    );
}

if (!empty($mysoc->state)) {
    $address_info[] = array(
        'label' => 'Provincia/Estado',
        'value' => $mysoc->state,
        'icon' => 'fa-map'
    );
}

if (empty($address_info)) {
    print '<p class="usercard-no-data"><i class="fas fa-info-circle"></i> No hay información de dirección disponible</p>';
} else {
    foreach ($address_info as $info) {
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
$parameters = array('mysoc' => $mysoc);
$reshook = $hookmanager->executeHooks('printCompanyInfoContent', $parameters);
if ($reshook > 0) {
    print $hookmanager->resPrint;
}

print '</div>'; // End usercard-container
print '</div>'; // End employee-zone

// Footer estándar Zona Empleado
zonaempleado_print_footer();
