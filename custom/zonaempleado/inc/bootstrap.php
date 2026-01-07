<?php
/* Bootstrap para páginas integradas en Zona Empleado */

// Evitar doble carga del entorno
if (!defined('DOL_DOCUMENT_ROOT')) {
    $res = 0;
    if (!$res && file_exists(__DIR__.'/../../main.inc.php')) $res = @include __DIR__.'/../../main.inc.php';
    if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) $res = @include __DIR__.'/../../../main.inc.php';
    if (!$res && file_exists(__DIR__.'/../../../../main.inc.php')) $res = @include __DIR__.'/../../../../main.inc.php';
    if (!$res) die('Include of main fails');
}

// Seguridad: usuario logueado
if (empty($user) || empty($user->id)) {
    header('Location: '.DOL_URL_ROOT.'/');
    exit;
}

// Cargar librerías de Zona Empleado
require_once DOL_DOCUMENT_ROOT.'/custom/zonaempleado/lib/zonaempleado.lib.php';

// Idiomas base para Zona Empleado
$langs->loadLangs(array('main', 'users', 'zonaempleado@zonaempleado'));

// Permisos por defecto (lectura si está configurado)
$hasAccess = true;
if (isset($user->rights->zonaempleado->access->read)) {
    $hasAccess = (bool) $user->rights->zonaempleado->access->read;
}
if (!$hasAccess) accessforbidden($langs->trans('ZonaEmpleadoAccessDenied'));

// Hook manager mínimo
if (empty($hookmanager)) {
    require_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
    $hookmanager = new HookManager($db);
}

// Título por defecto (puede sobreescribirse previamente)
if (!isset($title) || !$title) {
    $title = $langs->trans('ZonaEmpleadoArea');
}

// Menú opcional (puede ser ajustado por la página llamante)
if (!isset($menu_items)) $menu_items = array();
if (!isset($current_page)) $current_page = '';

// Imprimir header estandarizado
zonaempleado_print_header($title, $menu_items, $current_page);
