<?php
/* Copyright (C) 2026 Document Signature Module */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class for Audit Trail
 */
class DocsigAuditTrail extends CommonObject
{
	public $element = 'docsigaudittrail';
	public $table_element = 'docsig_audit_trail';

	public $id;
	public $entity;
	public $fk_envelope;
	public $fk_signature;
	public $event_type;
	public $event_date;
	public $event_data;
	public $ip_address;
	public $user_agent;
	public $session_id;
	public $fk_user;
	public $event_hash;
	public $previous_hash;

	/**
	 * Constructor
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Create audit trail entry (append-only)
	 *
	 * @return int <0 if KO, ID if OK
	 */
	public function create()
	{
		global $conf;

		$now = dol_now();
		$this->event_date = $now;

		// Get previous hash for blockchain-like integrity
		$sql = "SELECT event_hash FROM ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " WHERE fk_envelope = ".(int)$this->fk_envelope;
		$sql .= " ORDER BY rowid DESC LIMIT 1";
		
		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql) > 0) {
			$obj = $this->db->fetch_object($resql);
			$this->previous_hash = $obj->event_hash;
		} else {
			$this->previous_hash = hash('sha256', 'docsig-genesis-'.$this->fk_envelope);
		}

		// Calculate event hash
		$hashData = json_encode(array(
			'envelope' => $this->fk_envelope,
			'signature' => $this->fk_signature,
			'type' => $this->event_type,
			'date' => $now,
			'data' => $this->event_data,
			'ip' => $this->ip_address,
			'previous' => $this->previous_hash,
		), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		
		$this->event_hash = hash('sha256', $hashData);

		// Insert (no update/delete allowed - append-only)
		$sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (";
		$sql .= " entity, fk_envelope, fk_signature, event_type, event_date,";
		$sql .= " event_data, ip_address, user_agent, session_id, fk_user,";
		$sql .= " event_hash, previous_hash";
		$sql .= ") VALUES (";
		$sql .= " ".(int)$conf->entity.",";
		$sql .= " ".(int)$this->fk_envelope.",";
		$sql .= " ".($this->fk_signature ? (int)$this->fk_signature : "NULL").",";
		$sql .= " '".$this->db->escape($this->event_type)."',";
		$sql .= " '".$this->db->idate($now)."',";
		$sql .= " ".($this->event_data ? "'".$this->db->escape($this->event_data)."'" : "NULL").",";
		$sql .= " ".($this->ip_address ? "'".$this->db->escape($this->ip_address)."'" : "NULL").",";
		$sql .= " ".($this->user_agent ? "'".$this->db->escape(substr($this->user_agent, 0, 512))."'" : "NULL").",";
		$sql .= " ".($this->session_id ? "'".$this->db->escape($this->session_id)."'" : "NULL").",";
		$sql .= " ".($this->fk_user ? (int)$this->fk_user : "NULL").",";
		$sql .= " '".$this->db->escape($this->event_hash)."',";
		$sql .= " ".($this->previous_hash ? "'".$this->db->escape($this->previous_hash)."'" : "NULL");
		$sql .= ")";

		dol_syslog(get_class($this)."::create", LOG_DEBUG);
		$resql = $this->db->query($sql);

		if ($resql) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
			return $this->id;
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}

	/**
	 * Fetch audit trail
	 *
	 * @param int $id ID
	 * @return int
	 */
	public function fetch($id)
	{
		$sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " WHERE rowid = ".(int)$id;

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj) {
				$this->id = $obj->rowid;
				$this->entity = $obj->entity;
				$this->fk_envelope = $obj->fk_envelope;
				$this->fk_signature = $obj->fk_signature;
				$this->event_type = $obj->event_type;
				$this->event_date = $this->db->jdate($obj->event_date);
				$this->event_data = $obj->event_data;
				$this->ip_address = $obj->ip_address;
				$this->user_agent = $obj->user_agent;
				$this->session_id = $obj->session_id;
				$this->fk_user = $obj->fk_user;
				$this->event_hash = $obj->event_hash;
				$this->previous_hash = $obj->previous_hash;
				return 1;
			}
			return 0;
		}
		return -1;
	}

	/**
	 * Get audit trail for envelope
	 *
	 * @param int $envelopeId Envelope ID
	 * @return array
	 */
	public function fetchByEnvelope($envelopeId)
	{
		$results = array();

		$sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " WHERE fk_envelope = ".(int)$envelopeId;
		$sql .= " ORDER BY rowid ASC";

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$record = new DocsigAuditTrail($this->db);
				$record->id = $obj->rowid;
				$record->entity = $obj->entity;
				$record->fk_envelope = $obj->fk_envelope;
				$record->fk_signature = $obj->fk_signature;
				$record->event_type = $obj->event_type;
				$record->event_date = $this->db->jdate($obj->event_date);
				$record->event_data = $obj->event_data;
				$record->ip_address = $obj->ip_address;
				$record->user_agent = $obj->user_agent;
				$record->session_id = $obj->session_id;
				$record->fk_user = $obj->fk_user;
				$record->event_hash = $obj->event_hash;
				$record->previous_hash = $obj->previous_hash;
				
				$results[] = $record;
			}
		}

		return $results;
	}

	/**
	 * Verify audit trail integrity
	 *
	 * @param int $envelopeId Envelope ID
	 * @return bool
	 */
	public function verifyIntegrity($envelopeId)
	{
		$trail = $this->fetchByEnvelope($envelopeId);
		
		if (empty($trail)) {
			return true;
		}

		$expectedPreviousHash = hash('sha256', 'docsig-genesis-'.$envelopeId);

		foreach ($trail as $entry) {
			// Verify previous hash matches
			if ($entry->previous_hash !== $expectedPreviousHash) {
				return false;
			}

			// Recalculate hash
			$hashData = json_encode(array(
				'envelope' => $entry->fk_envelope,
				'signature' => $entry->fk_signature,
				'type' => $entry->event_type,
				'date' => $entry->event_date,
				'data' => $entry->event_data,
				'ip' => $entry->ip_address,
				'previous' => $entry->previous_hash,
			), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			
			$calculatedHash = hash('sha256', $hashData);

			if ($calculatedHash !== $entry->event_hash) {
				return false;
			}

			$expectedPreviousHash = $entry->event_hash;
		}

		return true;
	}
}
