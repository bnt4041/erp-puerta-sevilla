<?php

require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 *  Description and activation class for module WhatsApp
 */
class modWhatsApp extends DolibarrModules
{
	/**
	 *   Constructor. Define the module
	 *
	 *   @param      DoliDB      $db      Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		// Id for module (must be unique).
		// Chosen a random high number if not registered.
		$this->numero = 500000;
		// Key for module (must be unique)
		$this->name = "WhatsApp";
		// Family of module
		$this->family = "crm";
		// Module description
		$this->description = "WhatsApp integration using goWA";
		// Possible values for version: 'development', 'experimental', 'stable'
		$this->version = '1.0.0';
		// Key used in settings or local vars for this module
		$this->const_name = 'MAIN_MODULE_WHATSAPP';
		// Module picture (placed in /whatsapp/img/logo.png)
		$this->picto = 'whatsapp@whatsapp';

		// Data directories to create when module is enabled
		$this->dirs = array("/whatsapp/temp");

		// Config pages
		$this->config_page_url = array("setup.php@whatsapp");

		// Dependencies
		$this->depends = array();
		$this->requiredby = array();
		$this->phpmin = array(7, 0);
		$this->need_dolibarr_version = array(14, 0);

		// Constants
		$this->const = array();

		// Dictionaries
		$this->dictionaries = array(
			'table' => array(MAIN_DB_PREFIX.'c_actioncomm'),
			'field' => array('code,type,libelle,module,position'),
			'data' => array(
				array('AC_WA', 'whatsapp', 'WhatsApp message sent', 'whatsapp', 10),
			),
		);

		// Hooks
		$this->module_parts = array(
			'hooks' => array(
				'invoicecard',
				'propalcard',
				'ordercard',
				'projectcard',
				'thirdpartycard',
				'contactcard',
				'actioncard',
				'comm/action/card',
				'formobjectoptions',
				'addmoreactionsbuttons'
			),
			'triggers' => 1,
			'api' => 1,
            'js' => array('/whatsapp/js/whatsapp_widget.js')
		);

		// Tabs
		$this->tabs = array();

		// Exports
		$this->export_code = array();
		$this->export_label = array();
		$this->export_permission = array();
		$this->export_fields_array = array();
		$this->export_entities_array = array();
		$this->export_sql_start = array();
		$this->export_sql_end = array();
	}

	/**
	 * Function called when module is enabled.
	 * The init function add constants, tables, permissions...
	 *
	 * @param string $options Options for activation
	 * @return int 1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		$result = $this->_load_tables('/whatsapp/sql/');
		if ($result < 0) return 0;

		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."c_actioncomm WHERE code = 'AC_WA'";
		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql) == 0) {
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."c_actioncomm (code, type, libelle, module, active, position) VALUES ('AC_WA', 'whatsapp', 'WhatsApp message sent', 'whatsapp', 1, 10)";
			$this->db->query($sql);
		}

		return $this->_init(array(), $options);
	}

	/**
	 * Function called when module is disabled.
	 *
	 * @param string $options Options for deactivation
	 * @return int 1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		return $this->_remove(array(), $options);
	}
}
