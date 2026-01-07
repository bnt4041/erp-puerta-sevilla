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
 * \file    class/zonaempleado.class.php
 * \ingroup zonaempleado
 * \brief   Employee Zone main class
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class for ZonaEmpleado
 */
class ZonaEmpleado extends CommonObject
{
    /**
     * @var string Module name
     */
    public $module = 'zonaempleado';

    /**
     * @var string Element name
     */
    public $element = 'zonaempleado';

    /**
     * @var string Name of table without prefix where object is stored
     */
    public $table_element = 'zonaempleado_config';

    /**
     * @var int Does this object support multicompany module ?
     * 0=No test on entity, 1=Test with field entity, 'field@table'=Test with link by field@table
     */
    public $ismultientitymanaged = 1;

    /**
     * @var int Does object support extrafields ? 0=No, 1=Yes
     */
    public $isextrafieldmanaged = 0;

    /**
     * @var string String with name of icon for zonaempleado. Must be a 'fa-xxx' fontawesome code (or 'fa-xxx_fa_color_size') or 'zonaempleado@zonaempleado' if picto is file 'img/object_zonaempleado.png'
     */
    public $picto = 'fa-users';

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    /**
     * Check if user has access to employee zone
     *
     * @param User $user User object to check
     * @return boolean True if user has access, False otherwise
     */
    public function checkUserAccess($user)
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
     * Check if user can use employee zone features
     *
     * @param User $user User object to check
     * @return boolean True if user can use features, False otherwise
     */
    public function checkUserCanUse($user)
    {
        if (!$this->checkUserAccess($user)) {
            return false;
        }

        return isset($user->rights->zonaempleado->use->write) && $user->rights->zonaempleado->use->write;
    }

    /**
     * Check if user can configure employee zone
     *
     * @param User $user User object to check
     * @return boolean True if user can configure, False otherwise
     */
    public function checkUserCanConfig($user)
    {
        if (!$user->id) {
            return false;
        }

        return isset($user->rights->zonaempleado->config->write) && $user->rights->zonaempleado->config->write;
    }

    /**
     * Get configuration value
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value
     */
    public function getConfig($key, $default = '')
    {
        global $conf;

        $constant_name = 'ZONAEMPLEADO_' . strtoupper($key);
        
        if (isset($conf->global->$constant_name)) {
            return $conf->global->$constant_name;
        }

        return $default;
    }

    /**
     * Set configuration value
     *
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     * @return int 1 if OK, -1 if error
     */
    public function setConfig($key, $value)
    {
        $constant_name = 'ZONAEMPLEADO_' . strtoupper($key);
        
        return dolibarr_set_const($this->db, $constant_name, $value, 'chaine', 0, '', $conf->entity);
    }

    /**
     * Get user recent activity
     *
     * @param User $user User object
     * @param int $limit Limit of activities to return
     * @return array Array of activities
     */
    public function getUserRecentActivity($user, $limit = 10)
    {
        $activities = array();

        // This can be extended by hooks or subclasses
        // Basic implementation - can be enhanced with actual activity tracking

        global $hookmanager;
        $parameters = array('user' => $user, 'limit' => $limit, 'activities' => &$activities);
        $hookmanager->executeHooks('getUserRecentActivity', $parameters, $this);

        return $activities;
    }

    /**
     * Get user statistics
     *
     * @param User $user User object
     * @return array Array of statistics
     */
    public function getUserStats($user)
    {
        $stats = array();

        // Basic stats
        $stats['login_count'] = 0; // This would need proper tracking implementation
        $stats['last_login'] = $user->datelastlogin;
        $stats['member_since'] = $user->datec;

        // Hook to allow other modules to add statistics
        global $hookmanager;
        $parameters = array('user' => $user, 'stats' => &$stats);
        $hookmanager->executeHooks('getUserStats', $parameters, $this);

        return $stats;
    }

    /**
     * Get available widgets for dashboard
     *
     * @param User $user User object
     * @return array Array of widgets
     */
    public function getDashboardWidgets($user)
    {
        $widgets = array();

        // Default widgets
        $widgets['profile'] = array(
            'name' => 'Profile',
            'enabled' => true,
            'order' => 10
        );

        $widgets['quickaccess'] = array(
            'name' => 'QuickAccess',
            'enabled' => true,
            'order' => 20
        );

        $widgets['activity'] = array(
            'name' => 'RecentActivity',
            'enabled' => true,
            'order' => 30
        );

        // Hook to allow other modules to add widgets
        global $hookmanager;
        $parameters = array('user' => $user, 'widgets' => &$widgets);
        $hookmanager->executeHooks('getDashboardWidgets', $parameters, $this);

        // Sort widgets by order
        uasort($widgets, function($a, $b) {
            return $a['order'] - $b['order'];
        });

        return $widgets;
    }

    /**
     * Generate employee zone navigation menu
     *
     * @param User $user User object
     * @param string $current_page Current page identifier
     * @return array Array of navigation items
     */
    public function getNavigationMenu($user, $current_page = 'home')
    {
        global $langs;

        $menu = array();

        // Default menu items
        $menu['home'] = array(
            'label' => $langs->trans('ZonaEmpleadoHome'),
            'url' => '/custom/zonaempleado/index.php',
            'icon' => 'fa-home',
            'active' => ($current_page == 'home'),
            'order' => 10
        );

        $menu['profile'] = array(
            'label' => $langs->trans('ZonaEmpleadoProfile'),
            'url' => '/custom/zonaempleado/profile.php',
            'icon' => 'fa-user',
            'active' => ($current_page == 'profile'),
            'order' => 20
        );

        // Hook to allow other modules to add menu items
        global $hookmanager;
        $parameters = array('user' => $user, 'menu' => &$menu, 'current_page' => $current_page);
        $hookmanager->executeHooks('getEmployeeZoneMenu', $parameters, $this);

        // Sort menu by order
        uasort($menu, function($a, $b) {
            return $a['order'] - $b['order'];
        });

        return $menu;
    }
}