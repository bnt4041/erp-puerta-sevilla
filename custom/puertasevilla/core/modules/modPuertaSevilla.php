<?php
/* Copyright (C) 2024 PuertaSevilla
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    core/modules/modPuertaSevilla.php
 * \ingroup puertasevilla
 * \brief   Descripción del módulo PuertaSevilla
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Class to describe and enable module PuertaSevilla
 */
class modPuertaSevilla extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, icons, versions
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		// Id for new mainmenu (0 if this module does not add new mainmenu)
		$this->numero = 500000;

		// Family can be 'crm', 'financial', 'hr', 'projects', 'products', 'ecm', 'technic', 'other', 'interface'
		$this->family = "interface";

		// Module label (no space allowed), used if translation string 'ModulePuertaSevilla' not found (Tabs -> System information -> Modules)
		$this->name = preg_replace('/^mod/i', '', get_class($this));

		// Module description, used if translation string 'PuertaSevilla' not found (Tabs -> System information -> Modules)
		$this->description = "PuertaSevilla Module";

		// Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated' or a version number like 'x.y.z'
		$this->version = '1.0.0';

		// Key used in llx_const table to save module status and other parameters
		$this->const_name = 'MAIN_MODULE_PUERTASEVILLA';

		// Where to find resources this modules
		$this->module_position = '';

		// List of editor names as string to tell about who build/have build this module.
		// To add a new player to this list, this playbook has to be accepted by community.
		$this->editor_name = 'PuertaSevilla';

		// Relative path to module style sheet if exists. Stored in css folder.
		// Leave empty if no css to add.
		$this->style_name = '';

		// List of php file uris tests to ensure module will work
		$this->php_uris = array();

		// Set to >0 if order of default menu must be changed. Useless otherwise.
		$this->menu_order = '';

		// Hooks
		$this->hooks = array(
			'printActionButtons',      // Para agregar botón en fichas de contrato
			'printFieldListAction',    // Para agregar acciones masivas en listados
			'printActionButtons2'      // Para procesar acciones masivas
		);

		// Additional permissions provided by this module
		$this->rights = array();
		$r = 0;

		// In the following four arrays, type is used to separate: Standard (sample=0), Permissive (sample=1). Negative values are used for sharing your innovations in chain (integrated in source, not in module).
		// Example to complete to declare new permissions:
		// $this->rights[$r][0] = $numero_permission;
		// $this->rights[$r][1] = 'label of lets say data object';
		// $this->rights[$r][4] = 'level of permission (1, 2, 3, 4 ou 5)';

		// Exports
		$this->export_code[$r] = 'puertasevilla_sample'.$r;
		$this->export_permission[$r] = 'puertasevilla:read';
		$this->export_fields_array[$r] = array(
			't.rowid' => "TechnicalID", 't.ref' => "Ref", 't.libelle' => 'Label', 't.date_creation' => 'DateCreation', 't.tms' => 'DateModification'
		);

		// Permissions
		$this->rights[$r][0] = $numero_permission + $r; // Permission id
		$this->rights[$r][1] = 'Read'; // Permission label
		$this->rights[$r][3] = 0; // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'level1'; // In php code, permission will be checked by test if ($user->rights->puertasevilla->level1)
		$this->rights[$r][5] = 'read'; // In php code, permission will be checked by test if ($user->rights->puertasevilla->read)
		$r++;
		$this->rights[$r][0] = $numero_permission + $r;
		$this->rights[$r][1] = 'Create/Update';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'level2';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $numero_permission + $r;
		$this->rights[$r][1] = 'Delete';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'level3';
		$this->rights[$r][5] = 'delete';
		$r++;

		// Main menu entries to add
		$this->menu = array();
		// $r=0;
		// $this->menu[$r]['fk_menu'] = '';	// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx'
		// $this->menu[$r]['type'] = 'top';			// This is a Top menu entry
		// $this->menu[$r]['titre'] = 'PuertaSevilla';
		// $this->menu[$r]['mainmenu'] = 'puertasevilla';
		// $this->menu[$r]['leftmenu'] = '';			// Left menu entry to add
		// $this->menu[$r]['url'] = '/puertasevilla/index.php';
		// $this->menu[$r]['langs'] = 'puertasevilla@puertasevilla';	// Lang file to use
		// $this->menu[$r]['position'] = 100;
		// $this->menu[$r]['enabled'] = '';			// Define condition to show or hide menu entry. Use '$conf->puertasevilla->enabled' if entry must be visible only if module is enabled. Use 'isModEnabled(...)' or $user->rights->... for permissions
		// $this->menu[$r]['target'] = '';
		// $this->menu[$r]['user'] = 2;				// 0=Menu for internal users, 1=external users, 2=both
		// $r++;
	}

	/**
	 * Activation function used to add/update data required to install module
	 *
	 * @param  Translate $langs Lang object
	 * @return bool/string installation message or true if ok
	 */
	public function init()
	{
		global $conf, $langs;

		$sql = array();

		return $this->_init($sql, $langs);
	}

	/**
	 * Function called when module is enabled to add permissions to default roles
	 *
	 * @param  Translate $langs Lang object
	 * @return bool always true
	 */
	public function addPermissions($langs)
	{
		return true;
	}

	/**
	 * Function called when module is disabled
	 */
	public function remove()
	{
		return true;
	}
}
