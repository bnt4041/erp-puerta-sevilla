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
 * \file    core/triggers/interface_99_modZonaEmpleado_ZonaEmpleadoTriggers.class.php
 * \ingroup zonaempleado
 * \brief   Trigger file for ZonaEmpleado module
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/notify.class.php';

/**
 * Class of triggers for ZonaEmpleado module
 */
class InterfaceZonaEmpleadoTriggers extends DolibarrTriggers
{
    /**
     * Events supported by ZonaEmpleado module notifications
     */
    public static $arrayofnotifsupported = array(
        'ZONAEMPLEADO_USER_LOGIN',
        'ZONAEMPLEADO_USER_LOGOUT',
        'ZONAEMPLEADO_USER_REGISTRATION',
        'ZONAEMPLEADO_PROFILE_UPDATED',
        'ZONAEMPLEADO_DOCUMENT_SHARED',
        'ZONAEMPLEADO_ANNOUNCEMENT_CREATED',
        'ZONAEMPLEADO_ANNOUNCEMENT_UPDATED',
        'ZONAEMPLEADO_HOLIDAY_REQUEST_SUBMITTED',
        'ZONAEMPLEADO_HOLIDAY_REQUEST_APPROVED',
        'ZONAEMPLEADO_HOLIDAY_REQUEST_REJECTED',
        'ZONAEMPLEADO_PAYSLIP_PUBLISHED',
        'ZONAEMPLEADO_MESSAGE_RECEIVED',
        'ZONAEMPLEADO_SCHEDULE_MODIFIED',
    );

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = "zonaempleado";
        $this->description = "ZonaEmpleado triggers with notification support.";
        $this->version = '1.0';
        $this->picto = 'users';
    }

    /**
     * Trigger name
     *
     * @return string Name of trigger file
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Trigger description
     *
     * @return string Description of trigger file
     */
    public function getDesc()
    {
        return $this->description;
    }


    /**
     * Function called when a Dolibarr business event occurs.
     * All functions "runTrigger" are triggered if file
     * is inside directory core/triggers
     *
     * @param string 		$action 	Event action code
     * @param CommonObject 	$object 	Object
     * @param User 			$user 		Object user
     * @param Translate 	$langs 		Object langs
     * @param Conf 			$conf 		Object conf
     * @return int                		<0 if KO, 0 if no triggered ran, >0 if OK
     */
    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        if (empty($conf->zonaempleado->enabled)) return 0; // If module is disabled, we do nothing

        // Put here code you want to execute when a Dolibarr business events occurs.
        // Data and type of action are stored into $object and $action

        dol_syslog(get_class($this)."::runTrigger ".$action." for object ".get_class($object)." with id ".$object->id, LOG_DEBUG);

        switch ($action) {
            case 'USER_LOGIN':
                // Log user login for employee zone statistics
                $this->logEmployeeActivity($user, 'login');
                // Send notification
                $this->sendNotification('ZONAEMPLEADO_USER_LOGIN', $object, $user, $langs, $conf);
                break;

            case 'USER_LOGOUT':
                // Log user logout
                $this->logEmployeeActivity($user, 'logout');
                // Send notification
                $this->sendNotification('ZONAEMPLEADO_USER_LOGOUT', $object, $user, $langs, $conf);
                break;

            case 'USER_CREATE':
                // When a new user is created, initialize employee zone settings
                $this->initializeEmployeeSettings($object);
                // Send notification
                $this->sendNotification('ZONAEMPLEADO_USER_REGISTRATION', $object, $user, $langs, $conf);
                break;

            case 'USER_MODIFY':
                // When user is modified, update employee zone data if needed
                $this->updateEmployeeData($object);
                // Send notification
                $this->sendNotification('ZONAEMPLEADO_PROFILE_UPDATED', $object, $user, $langs, $conf);
                break;

            case 'USER_DELETE':
                // Clean up employee zone data when user is deleted
                $this->cleanupEmployeeData($object);
                break;

            default:
                // Other actions can be handled by extension modules
                break;
        }

        return 0;
    }

    /**
     * Log employee activity for statistics
     *
     * @param User $user User object
     * @param string $activity Activity type
     * @return int 1 if OK, -1 if error
     */
    private function logEmployeeActivity($user, $activity)
    {
        // This could be extended to log activities in a custom table
        // For now, we just use Dolibarr's built-in logging
        
        dol_syslog("ZonaEmpleado: User ".$user->login." ".$activity, LOG_INFO);
        
        // TODO: Implement custom activity logging table
        // This would allow for better statistics in the employee zone
        
        return 1;
    }

    /**
     * Initialize employee zone settings for new user
     *
     * @param User $user User object
     * @return int 1 if OK, -1 if error
     */
    private function initializeEmployeeSettings($user)
    {
        // Set default employee zone preferences
        // This could include theme, language preferences, etc.
        
        dol_syslog("ZonaEmpleado: Initializing settings for user ".$user->login, LOG_DEBUG);
        
        // TODO: Add default settings initialization
        
        return 1;
    }

    /**
     * Update employee data when user is modified
     *
     * @param User $user User object
     * @return int 1 if OK, -1 if error
     */
    private function updateEmployeeData($user)
    {
        // Update any employee zone specific data when user profile changes
        
        dol_syslog("ZonaEmpleado: Updating employee data for user ".$user->login, LOG_DEBUG);
        
        // TODO: Implement employee data updates
        
        return 1;
    }

    /**
     * Clean up employee data when user is deleted
     *
     * @param User $user User object
     * @return int 1 if OK, -1 if error
     */
    private function cleanupEmployeeData($user)
    {
        // Clean up any employee zone specific data when user is deleted
        
        dol_syslog("ZonaEmpleado: Cleaning up employee data for user ".$user->login, LOG_DEBUG);
        
        // TODO: Implement data cleanup
        
        return 1;
    }

    /**
     * Send notification for ZonaEmpleado events
     *
     * @param string 		$action 	Event action code (ZONAEMPLEADO_*)
     * @param CommonObject 	$object 	Object triggering the event
     * @param User 			$user 		User object
     * @param Translate 	$langs 		Language object
     * @param Conf 			$conf 		Config object
     * @return int 1 if OK, 0 if notifications disabled
     */
    private function sendNotification($action, $object, User $user, Translate $langs, Conf $conf)
    {
        // Check if notification module is enabled
        if (empty($conf->notification) || !isModEnabled('notification')) {
            return 0; // Notification module not enabled
        }

        try {
            $notify = new Notify($this->db);
            
            // Send the notification using Dolibarr's notification system
            $result = $notify->send($action, $object);
            
            if ($result < 0) {
                dol_syslog("ZonaEmpleado: Error sending notification for action ".$action, LOG_ERR);
                return -1;
            }
            
            dol_syslog("ZonaEmpleado: Notification sent for action ".$action." on object id ".$object->id, LOG_DEBUG);
            return 1;
        } catch (Exception $e) {
            dol_syslog("ZonaEmpleado: Exception in sendNotification: ".$e->getMessage(), LOG_ERR);
            return -1;
        }
    }
}
