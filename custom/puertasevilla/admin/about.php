<?php
/* Copyright (C) 2024 PuertaSevilla
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       puertasevilla/admin/about.php
 * \ingroup    puertasevilla
 * \brief      About page for PuertaSevilla module.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
    $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
    $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

global $langs, $user;

// Libraries
require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once '../lib/puertasevilla.lib.php';

// Translations
$langs->loadLangs(array("admin", "puertasevilla@puertasevilla"));

// Access control
if (!$user->admin) {
    accessforbidden();
}

/*
 * View
 */

$page_name = "PuertaSevillaAbout";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration header
$head = puertasevillaAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $langs->trans("ModulePuertaSevillaName"), -1, 'puertasevilla@puertasevilla');

print '<div class="info">';
print '<p><strong>Módulo PuertaSevilla v1.0.0</strong></p>';
print '<p>Módulo de gestión inmobiliaria completa para Dolibarr.</p>';
print '<p><strong>Funcionalidades principales:</strong></p>';
print '<ul>';
print '<li>Gestión de propietarios e inquilinos como terceros</li>';
print '<li>Gestión de viviendas como proyectos</li>';
print '<li>Contratos de alquiler con generación automática de facturas plantilla</li>';
print '<li>Mantenimientos como pedidos</li>';
print '<li>Campos personalizados (extrafields) para todos los objetos</li>';
print '<li>Diccionarios personalizados para datos específicos del sector inmobiliario</li>';
print '</ul>';
print '<p><strong>Desarrollado por:</strong> PuertaSevilla Inmobiliaria</p>';
print '<p><strong>URL:</strong> <a href="https://www.puertasevillainmobiliaria.online" target="_blank">www.puertasevillainmobiliaria.online</a></p>';
print '<p><strong>Licencia:</strong> GPL v3</p>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
