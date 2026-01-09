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
		$this->dirs = array(
			"/whatsapp/temp",
			"/whatsapp/media"
		);

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
		array('AC_WA_IN', 'whatsapp', 'WhatsApp message received', 'whatsapp', 11),
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
		$this->tabs = array(
  'thirdparty:+whatsapp:WhatsApp:@whatsapp:/custom/whatsapp/whatsapp_chat.php?id=__ID__&objecttype=societe',
  'contact:+whatsapp:WhatsApp:@whatsapp:/custom/whatsapp/whatsapp_chat.php?id=__ID__&objecttype=contact'
);



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

		// Ensure extrafields table exists
		$sql = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."actioncomm_extrafields (\n"
			." rowid INT AUTO_INCREMENT PRIMARY KEY,\n"
			." fk_object INT NOT NULL,\n"
			." import_key VARCHAR(14),\n"
			." UNIQUE KEY uk_actioncomm_extrafields (fk_object)\n"
			.") ENGINE=InnoDB";
		$this->db->query($sql);

		// Add media columns if missing
		$this->db->query("ALTER TABLE ".MAIN_DB_PREFIX."actioncomm_extrafields ADD COLUMN wa_media_type VARCHAR(32) DEFAULT NULL");
		$this->db->query("ALTER TABLE ".MAIN_DB_PREFIX."actioncomm_extrafields ADD COLUMN wa_media_url VARCHAR(512) DEFAULT NULL");
		$this->db->query("ALTER TABLE ".MAIN_DB_PREFIX."actioncomm_extrafields ADD COLUMN wa_media_filename VARCHAR(255) DEFAULT NULL");
		$this->db->query("ALTER TABLE ".MAIN_DB_PREFIX."actioncomm_extrafields ADD COLUMN wa_media_size INT DEFAULT NULL");
		$this->db->query("ALTER TABLE ".MAIN_DB_PREFIX."actioncomm_extrafields ADD COLUMN wa_media_mime VARCHAR(128) DEFAULT NULL");

		$sql = "SELECT id FROM ".MAIN_DB_PREFIX."c_actioncomm WHERE code = 'AC_WA'";
		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql) == 0) {
			// Get next available ID
			$sql_max = "SELECT MAX(id) as maxid FROM ".MAIN_DB_PREFIX."c_actioncomm";
			$resql_max = $this->db->query($sql_max);
			$next_id = 1;
			if ($resql_max) {
				$obj = $this->db->fetch_object($resql_max);
				$next_id = ($obj->maxid ? $obj->maxid : 0) + 1;
			}
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."c_actioncomm (id, code, type, libelle, module, active, position) VALUES (".$next_id.", 'AC_WA', 'whatsapp', 'WhatsApp message sent', 'whatsapp', 1, 10)";
			$this->db->query($sql);
		}
	
	// Ensure AC_WA_IN (incoming) exists
	$sql = "SELECT id FROM ".MAIN_DB_PREFIX."c_actioncomm WHERE code = 'AC_WA_IN'";
	$resql = $this->db->query($sql);
	if ($resql && $this->db->num_rows($resql) == 0) {
		// Get next available ID
		$sql_max = "SELECT MAX(id) as maxid FROM ".MAIN_DB_PREFIX."c_actioncomm";
		$resql_max = $this->db->query($sql_max);
		$next_id = 1;
		if ($resql_max) {
			$obj = $this->db->fetch_object($resql_max);
			$next_id = ($obj->maxid ? $obj->maxid : 0) + 1;
		}
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."c_actioncomm (id, code, type, libelle, module, active, position) VALUES (".$next_id.", 'AC_WA_IN', 'whatsapp', 'WhatsApp message received', 'whatsapp', 1, 11)";
		$this->db->query($sql);
	}

	// Ensure AC_WA_MEDIA (media sent) exists
	$sql = "SELECT id FROM ".MAIN_DB_PREFIX."c_actioncomm WHERE code = 'AC_WA_MEDIA'";
	$resql = $this->db->query($sql);
	if ($resql && $this->db->num_rows($resql) == 0) {
		$sql_max = "SELECT MAX(id) as maxid FROM ".MAIN_DB_PREFIX."c_actioncomm";
		$resql_max = $this->db->query($sql_max);
		$next_id = 1;
		if ($resql_max) {
			$obj = $this->db->fetch_object($resql_max);
			$next_id = ($obj->maxid ? $obj->maxid : 0) + 1;
		}
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."c_actioncomm (id, code, type, libelle, module, active, position) VALUES (".$next_id.", 'AC_WA_MEDIA', 'whatsapp', 'WhatsApp media sent', 'whatsapp', 1, 12)";
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
