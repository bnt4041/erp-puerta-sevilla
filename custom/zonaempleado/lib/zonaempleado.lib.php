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
 * \file    lib/zonaempleado.lib.php
 * \ingroup zonaempleado
 * \brief   Library files with common functions for ZonaEmpleado
 */

/**
 * Get tabs for employee zone administration
 *
 * @return array Array of tabs
 */
function zonaempleadoAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load("zonaempleado@zonaempleado");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/zonaempleado/admin/setup.php", 1);
    $head[$h][1] = $langs->trans("ZonaEmpleadoSetup");
    $head[$h][2] = 'settings';
    $h++;

    $head[$h][0] = dol_buildpath("/zonaempleado/admin/about.php", 1);
    $head[$h][1] = $langs->trans("ZonaEmpleadoAbout");
    $head[$h][2] = 'about';
    $h++;

    complete_head_from_modules($conf, $langs, null, $head, $h, 'zonaempleado');

    return $head;
}

/**
 * Get available extensions/modules for employee zone
 * 
 * Extensions are sorted by position (lower numbers first)
 *
 * @return array Array of available extensions sorted by position
 */
function zonaempleado_get_extensions()
{
    global $conf, $db, $hookmanager;

    $extensions = array();

    // Hook to allow other modules to register themselves as employee zone extensions
    if (!empty($hookmanager)) {
        $parameters = array('extensions' => &$extensions);
        $hookmanager->executeHooks('registerEmployeeZoneExtension', $parameters);
    }

    // Sort extensions by position
    if (!empty($extensions)) {
        usort($extensions, function($a, $b) {
            $pos_a = isset($a['position']) ? (int)$a['position'] : 999;
            $pos_b = isset($b['position']) ? (int)$b['position'] : 999;
            return $pos_a - $pos_b;
        });
    }

    return $extensions;
}

/**
 * Collect quick access links exposed by modules via hook
 *
 * Links are sorted by position (lower numbers first)
 *
 * @return array
 */
function zonaempleado_get_quick_links()
{
    global $hookmanager, $db, $conf, $user;

    $quickLinks = array();
    
    // TPV link is added directly in index.php, not here to avoid duplication
    
    if (empty($hookmanager)) {
        require_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
        $hookmanager = new HookManager($db);
    }

    // Ensure hooks for zonaempleado are initialized so we can collect links from other modules
    $hookmanager->initHooks(array('zonaempleadoindex'));

    if (!empty($hookmanager)) {
        $parameters = array('quickLinks' => &$quickLinks);
        $hookmanager->executeHooks('addQuickLinks', $parameters);
    }

    if (!empty($quickLinks)) {
        usort($quickLinks, function ($a, $b) {
            $posA = isset($a['position']) ? (int) $a['position'] : 999;
            $posB = isset($b['position']) ? (int) $b['position'] : 999;
            return $posA - $posB;
        });
    }

    return $quickLinks;
}

/**
 * Render icon HTML for ZonaEmpleado helpers
 *
 * Accepts Dolibarr picto names or Font Awesome identifiers (fa-...)
 *
 * @param string $icon Icon identifier provided by hook/module
 * @param string $extraClass Extra CSS classes to apply
 * @return string HTML fragment for the icon
 */
function zonaempleado_render_icon($icon, $extraClass = 'pictofixedwidth')
{
    if (empty($icon)) {
        return '';
    }

    $icon = trim($icon);

    if ($icon === '') {
        return '';
    }

    // Handle Font Awesome identifiers (fa-*)
    if (strpos($icon, 'fa-') !== false) {
        $classes = $icon;

        // Ensure base Font Awesome class is present
        if (strpos($classes, 'fa ') === false && strpos($classes, 'fa-') === 0) {
            $classes = 'fa '.$classes;
        }

        if (!empty($extraClass)) {
            $classes .= ' '.trim($extraClass);
        }

        return '<i class="'.dol_escape_htmltag($classes).'"></i>';
    }

    $morecss = '';
    if (!empty($extraClass)) {
        $morecss = 'class="'.dol_escape_htmltag($extraClass).'"';
    }

    return img_picto('', $icon, $morecss);
}

/**
 * Check if user can access employee zone
 *
 * @param User $user User object
 * @return boolean True if access allowed
 */
function zonaempleado_check_access($user)
{
    if (!$user->id) {
        return false;
    }

    // By default, all authenticated users have access unless explicitly denied
    if (isset($user->rights->zonaempleado->access->read)) {
        return $user->rights->zonaempleado->access->read;
    }

    return true; // Default: allow access to all authenticated users
}

/**
 * Get employee zone configuration
 *
 * @param string $key Configuration key
 * @param mixed $default Default value
 * @return mixed Configuration value
 */
function zonaempleado_get_config($key, $default = '')
{
    global $conf;

    $constant_name = 'ZONAEMPLEADO_' . strtoupper($key);
    
    if (isset($conf->global->$constant_name)) {
        return $conf->global->$constant_name;
    }

    return $default;
}

/**
 * Set employee zone configuration
 *
 * @param string $key Configuration key
 * @param mixed $value Configuration value
 * @return int 1 if OK, -1 if error
 */
function zonaempleado_set_config($key, $value)
{
    global $db, $conf;

    $constant_name = 'ZONAEMPLEADO_' . strtoupper($key);
    
    return dolibarr_set_const($db, $constant_name, $value, 'chaine', 0, '', $conf->entity);
}

/**
 * Generate employee zone sidebar menu
 *
 * @param array $menu_items Array of menu items
 * @param string $current_page Current page identifier
 * @return string HTML sidebar menu
 */
function zonaempleado_sidebar_menu($menu_items, $current_page = '')
{
    global $langs;

    $html = '<nav class="employee-sidebar">';
    $html .= '<ul class="sidebar-nav">';
    
    foreach ($menu_items as $key => $item) {
        $active_class = ($key == $current_page || (isset($item['active']) && $item['active'])) ? ' active' : '';
        
        $html .= '<li class="nav-item' . $active_class . '">';
        $html .= '<a href="' . $item['url'] . '" class="nav-link">';
        
        if (isset($item['icon'])) {
            $html .= '<i class="' . $item['icon'] . '"></i> ';
        }
        
        $html .= $item['label'];
        $html .= '</a>';
        $html .= '</li>';
    }
    
    $html .= '</ul>';
    $html .= '</nav>';
    
    return $html;
}

/**
 * Print ZonaEmpleado header template
 *
 * @param string $title Page title
 * @param array $menu_items Optional menu items for header
 * @param string $current_page Current page key for active state
 * @param array|null $quick_access_links Links to expose in header quick access dropdown
 * @return void
 */
function zonaempleado_print_header($title = '', $menu_items = array(), $current_page = '', $quick_access_links = null)
{
    global $langs;

    if (!empty($current_page) && !empty($menu_items) && isset($menu_items[$current_page])) {
        $menu_items[$current_page]['active'] = true;
    }

    if (empty($title)) {
        $title = $langs->trans('ZonaEmpleadoArea');
    }

    if ($quick_access_links === null) {
        $quick_access_links = zonaempleado_get_quick_links();
    }

    // Check if we are on the main page to decide if we show the "Back to Home" link
    $is_index = (strpos($_SERVER['PHP_SELF'], '/custom/zonaempleado/index.php') !== false);
    $show_home_link = !$is_index;

    // Make vars available to template scope
    $GLOBALS['title'] = $title;
    $GLOBALS['menu_items'] = $menu_items;
    $GLOBALS['zonaempleado_quick_links'] = $quick_access_links;
    $GLOBALS['headerQuickLinks'] = $quick_access_links;
    $GLOBALS['show_home_link'] = $show_home_link;

    include DOL_DOCUMENT_ROOT.'/custom/zonaempleado/tpl/header.tpl.php';
}

/**
 * Print ZonaEmpleado footer template
 *
 * @return void
 */
function zonaempleado_print_footer()
{
    global $langs, $conf, $mysoc, $user;
    include DOL_DOCUMENT_ROOT.'/custom/zonaempleado/tpl/footer.tpl.php';
}
