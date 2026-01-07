<?php
/* Copyright (C) 2025 ZonaJob Dev
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    class/actions_zonajob.class.php
 * \ingroup zonajob
 * \brief   Hook actions class for ZonaJob module
 */

/**
 * Hook actions class for ZonaJob module
 */
class ActionsZonaJob
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
     * DISABLED: Este módulo se accede solo por quick links, no como extensión visible
     *
     * @param array  $parameters Hook parameters
     * @param object $object     Object
     * @param string $action     Action
     * @param object $hookmanager Hook manager
     * @return int 0 if OK
     */
    public function registerEmployeeZoneExtension($parameters, &$object, &$action, $hookmanager)
    {
        // Extensión deshabilitada intencionalmente
        // El módulo se accede únicamente a través de quick links
        return 0;
    }

    /**
     * Hook to add quick links to employee zone dashboard
     *
     * @param array  $parameters Hook parameters
     * @param object $object     Object
     * @param string $action     Action
     * @param object $hookmanager Hook manager
     * @return int 0 if OK
     */
    public function addQuickLinks($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs, $user;

        if (empty($conf->zonajob->enabled)) {
            return 0;
        }

        // Check permission
        if (empty($user->rights->zonajob->order->read)) {
            return 0;
        }

        $langs->load("zonajob@zonajob");

        // Link to orders list
        $parameters['quickLinks'][] = array(
            'label' => $langs->trans('ZonaJobOrders'),
            'url' => DOL_URL_ROOT.'/custom/zonajob/orders.php',
            'icon' => 'fa-shopping-cart',
            'position' => 10
        );

        return 0;
    }

    /**
     * Hook to add menu items to employee zone navigation
     *
     * @param array  $parameters Hook parameters
     * @param object $object     Object
     * @param string $action     Action
     * @param object $hookmanager Hook manager
     * @return int 0 if OK
     */
    public function getEmployeeZoneMenu($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs, $user;

        if (empty($conf->zonajob->enabled)) {
            return 0;
        }

        // Check permission
        if (empty($user->rights->zonajob->order->read)) {
            return 0;
        }

        $langs->load("zonajob@zonajob");

        $parameters['menu'][] = array(
            'id' => 'zonajob_orders',
            'label' => $langs->trans('ZonaJobOrders'),
            'url' => '/custom/zonajob/orders.php',
            'icon' => 'fas fa-shopping-cart',
            'position' => 10
        );

        return 0;
    }

    /**
     * Hook to get user recent activity
     *
     * @param array  $parameters Hook parameters
     * @param object $object     Object
     * @param string $action     Action
     * @param object $hookmanager Hook manager
     * @return int 0 if OK
     */
    public function getRecentActivity($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs, $db;

        if (empty($conf->zonajob->enabled)) {
            return 0;
        }

        if (empty($parameters['user'])) {
            return 0;
        }

        $langs->load("zonajob@zonajob");
        $user = $parameters['user'];

        // Get recent signatures
        $sql = "SELECT s.rowid, s.ref, s.date_signature, c.ref as order_ref";
        $sql .= " FROM ".MAIN_DB_PREFIX."zonajob_signature as s";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."commande as c ON c.rowid = s.fk_commande";
        $sql .= " WHERE s.fk_user_creat = ".((int) $user->id);
        $sql .= " AND s.status = 1";
        $sql .= " ORDER BY s.date_signature DESC";
        $sql .= " LIMIT 5";

        $result = $db->query($sql);

        if ($result) {
            while ($obj = $db->fetch_object($result)) {
                $parameters['activities'][] = array(
                    'date' => $db->jdate($obj->date_signature),
                    'text' => sprintf($langs->trans('ZonaJobSignatureActivity'), $obj->order_ref),
                    'icon' => 'fa-signature',
                    'module' => 'zonajob'
                );
            }
        }

        // Get recent photo uploads
        $sql = "SELECT p.rowid, p.date_creation, c.ref as order_ref";
        $sql .= " FROM ".MAIN_DB_PREFIX."zonajob_photo as p";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."commande as c ON c.rowid = p.fk_commande";
        $sql .= " WHERE p.fk_user_creat = ".((int) $user->id);
        $sql .= " ORDER BY p.date_creation DESC";
        $sql .= " LIMIT 5";

        $result = $db->query($sql);

        if ($result) {
            while ($obj = $db->fetch_object($result)) {
                $parameters['activities'][] = array(
                    'date' => $db->jdate($obj->date_creation),
                    'text' => sprintf($langs->trans('ZonaJobPhotoActivity'), $obj->order_ref),
                    'icon' => 'fa-camera',
                    'module' => 'zonajob'
                );
            }
        }

        return 0;
    }
}
