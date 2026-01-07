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
 * \file    class/actions_zonaempleado.class.php
 * \ingroup zonaempleado
 * \brief   Hook actions class for ZonaEmpleado module
 */

/**
 * Hook actions class for ZonaEmpleado module
 */
class ActionsZonaEmpleado
{
    /**
     * @var DoliDB Database handler
     */
    public $db;

    /**
     * @var string Error message
     */
    public $error = '';

    /**
     * @var array Error messages
     */
    public $errors = array();

    /**
     * @var array Hook results
     */
    public $results = array();

    /**
     * @var string String displayed by executeHooks() method
     */
    public $resprints;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Hook to register extension in employee zone
     * 
     * Other modules should implement this hook to register themselves in the employee zone.
     * They should add their extension info to $parameters['extensions'] array.
     *
     * Example usage in another module's actions class:
     * <code>
     * public function registerEmployeeZoneExtension($parameters, &$object, &$action, $hookmanager) {
     *     global $conf, $langs;
     *     if (!empty($conf->mymodule->enabled)) {
     *         $parameters['extensions'][] = array(
     *             'id' => 'mymodule',
     *             'name' => $langs->trans('MyModule'),
     *             'description' => $langs->trans('MyModuleDesc'),
     *             'icon' => 'fa-cog',
     *             'url' => '/custom/mymodule/employee.php',
     *             'enabled' => true,
     *             'position' => 10
     *         );
     *     }
     *     return 0;
     * }
     * </code>
     *
     * @param array  $parameters Hook parameters (contains 'extensions' array to fill)
     * @param object $object     Object
     * @param string $action     Action
     * @param object $hookmanager Hook manager
     * @return int 0 if OK
     */
    public function registerEmployeeZoneExtension($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs;

        if (empty($conf->zonaempleado->enabled)) return 0;

        // This is the base hook - other modules will implement this
        // No action needed here, this is just for documentation
        
        return 0;
    }

    /**
     * Hook to add quick links to employee zone dashboard
     * 
     * Other modules can use this hook to add quick access buttons to the dashboard.
     *
     * Example usage:
     * <code>
     * public function addQuickLinks($parameters, &$object, &$action, $hookmanager) {
     *     global $conf, $langs, $user;
     *     if (!empty($conf->mymodule->enabled)) {
     *         $parameters['quickLinks'][] = array(
     *             'label' => $langs->trans('MyAction'),
     *             'url' => DOL_URL_ROOT.'/custom/mymodule/action.php',
     *             'icon' => 'fa-plus',
     *             'position' => 5
     *         );
     *     }
     *     return 0;
     * }
     * </code>
     *
     * @param array  $parameters Hook parameters (contains 'quickLinks' array to fill)
     * @param object $object     Object
     * @param string $action     Action
     * @param object $hookmanager Hook manager
     * @return int 0 if OK
     */
    public function addQuickLinks($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs, $user;

        if (empty($conf->zonaempleado->enabled)) return 0;

        // Base hook for other modules to implement
        // $parameters['quickLinks'] is an array that modules can append to
        
        return 0;
    }

    /**
     * Hook to add menu items to employee zone navigation
     * 
     * Example usage:
     * <code>
     * public function getEmployeeZoneMenu($parameters, &$object, &$action, $hookmanager) {
     *     global $conf, $langs;
     *     if (!empty($conf->mymodule->enabled)) {
     *         $parameters['menu'][] = array(
     *             'id' => 'mymodule_menu',
     *             'label' => $langs->trans('MyModule'),
     *             'url' => '/custom/mymodule/employee.php',
     *             'icon' => 'fas fa-cog',
     *             'position' => 20
     *         );
     *     }
     *     return 0;
     * }
     * </code>
     *
     * @param array  $parameters Hook parameters (contains menu array and user)
     * @param object $object     Object
     * @param string $action     Action
     * @param object $hookmanager Hook manager
     * @return int 0 if OK
     */
    public function getEmployeeZoneMenu($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs, $user;

        if (empty($conf->zonaempleado->enabled)) return 0;

        // Base hook - modules should add items to $parameters['menu']
        
        return 0;
    }

    /**
     * Hook to get user recent activity
     * 
     * Example usage:
     * <code>
     * public function getRecentActivity($parameters, &$object, &$action, $hookmanager) {
     *     global $conf, $db;
     *     if (!empty($conf->mymodule->enabled) && !empty($parameters['user'])) {
     *         // Get recent activities from your module
     *         $parameters['activities'][] = array(
     *             'date' => time(),
     *             'text' => 'User performed an action',
     *             'icon' => 'fa-check',
     *             'module' => 'mymodule'
     *         );
     *     }
     *     return 0;
     * }
     * </code>
     *
     * @param array  $parameters Hook parameters (contains activities array and user)
     * @param object $object     Object
     * @param string $action     Action
     * @param object $hookmanager Hook manager
     * @return int 0 if OK
     */
    public function getRecentActivity($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs, $db;

        if (empty($conf->zonaempleado->enabled)) return 0;

        // Base hook - modules should add items to $parameters['activities']
        
        return 0;
    }

    /**
     * Hook to get user statistics for profile page
     * 
     * Example usage:
     * <code>
     * public function getUserProfileStats($parameters, &$object, &$action, $hookmanager) {
     *     global $conf, $db;
     *     if (!empty($conf->mymodule->enabled) && !empty($parameters['user'])) {
     *         // Calculate your stats
     *         $count = 42; // Your calculation here
     *         $parameters['stats'][] = array(
     *             'label' => 'My Statistics',
     *             'value' => $count,
     *             'icon' => 'fa-chart-bar'
     *         );
     *     }
     *     return 0;
     * }
     * </code>
     *
     * @param array  $parameters Hook parameters (contains stats array and user)
     * @param object $object     Object
     * @param string $action     Action
     * @param object $hookmanager Hook manager
     * @return int 0 if OK
     */
    public function getUserProfileStats($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs, $db;

        if (empty($conf->zonaempleado->enabled)) return 0;

        // Base hook - modules should add items to $parameters['stats']
        
        return 0;
    }

    /**
     * Hook to add quick actions to user profile page
     * 
     * Example usage:
     * <code>
     * public function getUserProfileActions($parameters, &$object, &$action, $hookmanager) {
     *     global $conf, $langs, $user;
     *     if (!empty($conf->mymodule->enabled)) {
     *         $parameters['actions'][] = array(
     *             'label' => $langs->trans('MyAction'),
     *             'url' => DOL_URL_ROOT.'/custom/mymodule/action.php',
     *             'icon' => 'fa-plus',
     *             'target' => '_blank' // optional
     *         );
     *     }
     *     return 0;
     * }
     * </code>
     *
     * @param array  $parameters Hook parameters (contains actions array and user)
     * @param object $object     Object
     * @param string $action     Action
     * @param object $hookmanager Hook manager
     * @return int 0 if OK
     */
    public function getUserProfileActions($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs, $user;

        if (empty($conf->zonaempleado->enabled)) return 0;

        // Base hook - modules should add items to $parameters['actions']
        
        return 0;
    }

    /**
     * Hook to add content to employee zone dashboard
     *
     * @param array  $parameters Hook parameters
     * @param object $object     Object
     * @param string $action     Action
     * @param object $hookmanager Hook manager
     * @return int 0 if OK
     */
    public function addEmployeeZoneContent($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs, $user;

        if (empty($conf->zonaempleado->enabled)) return 0;

        // This hook allows modules to add additional content to the dashboard
        // Use $this->resprints to return HTML content
        
        return 0;
    }

    /**
     * Hook to add content to employee profile page
     *
     * @param array  $parameters Hook parameters
     * @param object $object     Object
     * @param string $action     Action
     * @param object $hookmanager Hook manager
     * @return int 0 if OK
     */
    public function addEmployeeProfileContent($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs, $user;

        if (empty($conf->zonaempleado->enabled)) return 0;

        // This hook allows modules to add additional content to the profile page
        // Use $this->resprints to return HTML content
        
        return 0;
    }
}
