<?php
/* Copyright (C) 2026 Document Signature Module
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

/**
 * \defgroup   docsig     Module Document Signature
 * \brief      Docsig module descriptor
 * \file       core/modules/modDocsig.class.php
 * \ingroup    docsig
 * \brief      Description and activation file for module Docsig
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module Docsig
 */
class modDocsig extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;
		$this->db = $db;

		$this->numero = 6000004;
		$this->rights_class = 'docsig';
		$this->family = "technic";
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Document Signature Module - PAdES compliant digital signatures with TSA RFC3161";
		$this->descriptionlong = "Complete document signature solution with:\n";
		$this->descriptionlong .= "- Multi-signer envelopes (parallel or ordered)\n";
		$this->descriptionlong .= "- Double authentication (TVA intra/DNI + Email OTP)\n";
		$this->descriptionlong .= "- Handwritten signature capture\n";
		$this->descriptionlong .= "- PAdES-BES/LT PDF signing\n";
		$this->descriptionlong .= "- TSA RFC3161 timestamp\n";
		$this->descriptionlong .= "- Immutable audit trail\n";
		$this->descriptionlong .= "- Compliance certificate generation\n";
		$this->descriptionlong .= "- Notification tracking";

		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-file-signature';

		// Dependencies
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();

		// Config pages
		$this->config_page_url = array("setup.php@docsig");

		// Constants
		$this->const = array(
			1 => array('DOCSIG_SIGNATURE_MODE', 'chaine', 'parallel', 'Default signature mode (parallel/ordered)', 1, 'current', 0),
			2 => array('DOCSIG_EXPIRATION_DAYS', 'chaine', '30', 'Default expiration days for signature requests', 1, 'current', 0),
			3 => array('DOCSIG_OTP_EXPIRY_MINUTES', 'chaine', '10', 'OTP expiration time in minutes', 1, 'current', 0),
			4 => array('DOCSIG_OTP_MAX_ATTEMPTS', 'chaine', '5', 'Maximum OTP attempts before blocking', 1, 'current', 0),
			5 => array('DOCSIG_TOKEN_LENGTH', 'chaine', '64', 'Token length in characters', 1, 'current', 0),
			6 => array('DOCSIG_ENABLE_TSA', 'chaine', '1', 'Enable TSA timestamp', 1, 'current', 0),
			7 => array('DOCSIG_TSA_URL', 'chaine', '', 'TSA server URL (RFC3161)', 1, 'current', 0),
			8 => array('DOCSIG_TSA_USER', 'chaine', '', 'TSA username (if required)', 1, 'current', 0),
			9 => array('DOCSIG_TSA_PASSWORD', 'chaine', '', 'TSA password (if required)', 1, 'current', 1),
			10 => array('DOCSIG_TSA_POLICY', 'chaine', '', 'TSA policy OID', 1, 'current', 0),
			11 => array('DOCSIG_VISIBLE_SIGNATURE', 'chaine', '1', 'Enable visible signature on PDF', 1, 'current', 0),
			12 => array('DOCSIG_SIGNATURE_POSITION', 'chaine', 'bottom-left', 'Default signature position (bottom-left, bottom-right, etc)', 1, 'current', 0),
			13 => array('DOCSIG_AUTO_CERTIFICATE', 'chaine', '1', 'Auto-generate compliance certificate on completion', 1, 'current', 0),
			14 => array('DOCSIG_CERTIFICATE_MODE', 'chaine', 'all', 'Certificate mode: each (per signer) or all (when complete)', 1, 'current', 0),
			15 => array('DOCSIG_RATE_LIMIT_WINDOW', 'chaine', '3600', 'Rate limit window in seconds', 1, 'current', 0),
			16 => array('DOCSIG_RATE_LIMIT_MAX', 'chaine', '10', 'Max attempts per rate limit window', 1, 'current', 0),
		);

		// Arrays of triggers
		$this->module_parts = array(
			'triggers' => 0,
			'hooks' => array(
				'formfile',
				'invoicelist',
				'invoicecard',
				'orderlist',
				'ordercard',
				'contractlist',
				'contractcard',
				'propallist',
				'propalcard',
				'supplierinvoicelist',
				'supplierinvoicecard',
				'supplierproposallist',
				'supplierproposalcard',
				'contactlist',
				'contactcard',
				'thirdpartycard',
			),
		);

		// Data directories to create when module is enabled
		$this->dirs = array(
			"/docsig/temp",
			"/docsig/envelopes",
			"/docsig/certificates",
			"/docsig/signatures",
		);

		// Permissions
		$this->rights = array();
		$r = 0;

		$r++;
		$this->rights[$r][0] = $this->numero + $r;
		$this->rights[$r][1] = 'Read signature envelopes';
		$this->rights[$r][4] = 'envelope';
		$this->rights[$r][5] = 'read';

		$r++;
		$this->rights[$r][0] = $this->numero + $r;
		$this->rights[$r][1] = 'Create/modify signature requests';
		$this->rights[$r][4] = 'envelope';
		$this->rights[$r][5] = 'write';

		$r++;
		$this->rights[$r][0] = $this->numero + $r;
		$this->rights[$r][1] = 'Delete/cancel signature requests';
		$this->rights[$r][4] = 'envelope';
		$this->rights[$r][5] = 'delete';

		$r++;
		$this->rights[$r][0] = $this->numero + $r;
		$this->rights[$r][1] = 'View audit trail';
		$this->rights[$r][4] = 'audit';
		$this->rights[$r][5] = 'read';

		$r++;
		$this->rights[$r][0] = $this->numero + $r;
		$this->rights[$r][1] = 'Download signed documents and certificates';
		$this->rights[$r][4] = 'document';
		$this->rights[$r][5] = 'download';

		$r++;
		$this->rights[$r][0] = $this->numero + $r;
		$this->rights[$r][1] = 'Manage module configuration';
		$this->rights[$r][4] = 'config';
		$this->rights[$r][5] = 'write';

		// Admin menu
		$this->menu = array();
		$r = 0;

		$r++;
		$this->menu[$r] = array(
			'fk_menu' => '',
			'type' => 'top',
			'titre' => 'Docsig',
			'mainmenu' => 'docsig',
			'leftmenu' => '',
			'url' => '/docsig/envelope_list.php',
			'langs' => 'docsig@docsig',
			'position' => 1000 + $r,
			'enabled' => '$conf->docsig->enabled',
			'perms' => '$user->rights->docsig->envelope->read',
			'target' => '',
			'user' => 2,
		);

		$r++;
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=docsig',
			'type' => 'left',
			'titre' => 'Envelopes',
			'mainmenu' => 'docsig',
			'leftmenu' => 'docsig_envelopes',
			'url' => '/docsig/envelope_list.php',
			'langs' => 'docsig@docsig',
			'position' => 1000 + $r,
			'enabled' => '$conf->docsig->enabled',
			'perms' => '$user->rights->docsig->envelope->read',
			'target' => '',
			'user' => 2,
		);

		$r++;
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=docsig,fk_leftmenu=docsig_envelopes',
			'type' => 'left',
			'titre' => 'New Envelope',
			'mainmenu' => 'docsig',
			'leftmenu' => '',
			'url' => '/docsig/envelope_card.php?action=create',
			'langs' => 'docsig@docsig',
			'position' => 1000 + $r,
			'enabled' => '$conf->docsig->enabled',
			'perms' => '$user->rights->docsig->envelope->write',
			'target' => '',
			'user' => 2,
		);

		$r++;
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=docsig,fk_leftmenu=docsig_envelopes',
			'type' => 'left',
			'titre' => 'List',
			'mainmenu' => 'docsig',
			'leftmenu' => '',
			'url' => '/docsig/envelope_list.php',
			'langs' => 'docsig@docsig',
			'position' => 1000 + $r,
			'enabled' => '$conf->docsig->enabled',
			'perms' => '$user->rights->docsig->envelope->read',
			'target' => '',
			'user' => 2,
		);

		$r++;
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=docsig',
			'type' => 'left',
			'titre' => 'Audit Trail',
			'mainmenu' => 'docsig',
			'leftmenu' => 'docsig_audit',
			'url' => '/docsig/audit_list.php',
			'langs' => 'docsig@docsig',
			'position' => 1000 + $r,
			'enabled' => '$conf->docsig->enabled',
			'perms' => '$user->rights->docsig->audit->read',
			'target' => '',
			'user' => 2,
		);

		$r++;
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=docsig',
			'type' => 'left',
			'titre' => 'Setup',
			'mainmenu' => 'docsig',
			'leftmenu' => '',
			'url' => '/docsig/admin/setup.php',
			'langs' => 'docsig@docsig',
			'position' => 1000 + $r,
			'enabled' => '$conf->docsig->enabled',
			'perms' => '$user->rights->docsig->config->write',
			'target' => '',
			'user' => 2,
		);
	}

	/**
	 * Function called when module is enabled
	 * The init function add constants, boxes, permissions and menus
	 * (defined in constructor) into Dolibarr database
	 * It also creates data directories
	 *
	 * @param string $options Options when enabling module ('', 'noboxes')
	 * @return int 1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		global $conf, $langs;

		// Load sql sub-directories
		$result = $this->_load_tables('/docsig/sql/');
		if ($result < 0) {
			return -1;
		}

		// No need to create extrafields, we use native tva_intra field from socpeople

		// Generate system certificate on first install
		$this->_generateSystemCertificate();

		// Ensure actioncomm type exists for signature events
		$sql = "SELECT code FROM ".MAIN_DB_PREFIX."c_actioncomm WHERE code = 'AC_DOCSIG_SIGNATURE'";
		$resql = $this->db->query($sql);
		$exists = ($resql && $this->db->num_rows($resql) > 0);
		if (!$exists) {
			$sqlins = "INSERT INTO ".MAIN_DB_PREFIX."c_actioncomm (code, type, libelle, module, active, position) VALUES ("
				."'AC_DOCSIG_SIGNATURE', 'system', 'Firma', 'docsig', 1, 1001)";
			$this->db->query($sqlins);
		}

		return $this->_init(array(), $options);
	}

	/**
	 * Function called when module is disabled
	 * Remove from database constants, boxes and permissions from Dolibarr database
	 * Data directories are not deleted
	 *
	 * @param string $options Options when disabling module ('', 'noboxes')
	 * @return int 1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		global $conf;
		return $this->_remove($conf->global->MAIN_VERSION_LAST_INSTALL, $options);
	}

	/**
	 * Generate system certificate for signing
	 *
	 * @return int 1 if OK, <0 if KO
	 */
	private function _generateSystemCertificate()
	{
		global $conf;

		// Check if already exists
		$sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."docsig_key";
		$sql .= " WHERE key_type = 'signing' AND is_active = 1";
		$sql .= " AND entity = ".$conf->entity;
		
		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj->nb > 0) {
				return 1; // Already exists
			}
		}

		// Generate RSA keypair
		$config = array(
			"digest_alg" => "sha256",
			"private_key_bits" => 2048,
			"private_key_type" => OPENSSL_KEYTYPE_RSA,
		);

		$res = openssl_pkey_new($config);
		if (!$res) {
			return -1;
		}

		// Export private key
		openssl_pkey_export($res, $privateKey);

		// Export public key
		$publicKey = openssl_pkey_get_details($res);
		$publicKeyPEM = $publicKey['key'];

		// Generate self-signed certificate
		$dn = array(
			"countryName" => "ES",
			"stateOrProvinceName" => "Spain",
			"localityName" => "Madrid",
			"organizationName" => "Docsig",
			"commonName" => "Docsig System Certificate"
		);

		$csr = openssl_csr_new($dn, $res, $config);
		$x509 = openssl_csr_sign($csr, null, $res, 3650, $config);
		openssl_x509_export($x509, $certPEM);

		$certData = openssl_x509_parse($certPEM);

		// Encrypt private key with AES-256-GCM
		$encryptionKey = $this->_getEncryptionKey();
		$iv = openssl_random_pseudo_bytes(16);
		$tag = '';
		$encryptedPrivateKey = openssl_encrypt($privateKey, 'aes-256-gcm', $encryptionKey, 0, $iv, $tag);

		// Store in database
		$now = $this->db->idate(dol_now());
		$validFrom = $this->db->idate($certData['validFrom_time_t']);
		$validTo = $this->db->idate($certData['validTo_time_t']);

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."docsig_key (";
		$sql .= " entity, key_type, key_algorithm,";
		$sql .= " public_key, private_key_encrypted, private_key_iv, private_key_tag,";
		$sql .= " certificate, certificate_serial, certificate_subject, certificate_issuer,";
		$sql .= " certificate_valid_from, certificate_valid_to, is_active, date_creation";
		$sql .= ") VALUES (";
		$sql .= " ".$conf->entity.", 'signing', 'RSA-2048',";
		$sql .= " '".$this->db->escape($publicKeyPEM)."',";
		$sql .= " '".$this->db->escape($encryptedPrivateKey)."',";
		$sql .= " '".$this->db->escape(base64_encode($iv))."',";
		$sql .= " '".$this->db->escape(base64_encode($tag))."',";
		$sql .= " '".$this->db->escape($certPEM)."',";
		$sql .= " '".$this->db->escape($certData['serialNumber'])."',";
		$sql .= " '".$this->db->escape($certData['name'])."',";
		$sql .= " '".$this->db->escape($certData['issuer']['CN'])."',";
		$sql .= " '".$validFrom."', '".$validTo."', 1, '".$now."'";
		$sql .= ")";

		$result = $this->db->query($sql);
		return $result ? 1 : -1;
	}

	/**
	 * Get encryption key for private key storage
	 *
	 * @return string
	 */
	private function _getEncryptionKey()
	{
		global $conf;

		// Use Dolibarr's secret key or generate one
		if (!empty($conf->file->instance_unique_id)) {
			return hash('sha256', $conf->file->instance_unique_id, true);
		}

		// Fallback
		return hash('sha256', 'docsig-secret-key-' . $conf->entity, true);
	}
}
