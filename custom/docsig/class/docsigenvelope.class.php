<?php
/* Copyright (C) 2026 Document Signature Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       class/docsigenvelope.class.php
 * \ingroup    docsig
 * \brief      Signature envelope class
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class for Docsig Envelope
 */
class DocsigEnvelope extends CommonObject
{
	/**
	 * @var string ID to identify managed object
	 */
	public $element = 'docsigenvelope';

	/**
	 * @var string Name of table without prefix where object is stored
	 */
	public $table_element = 'docsig_envelope';

	/**
	 * @var int ID
	 */
	public $id;

	public $ref;
	public $entity;
	public $element_type;
	public $element_id;
	public $document_path;
	public $document_hash;
	public $document_name;
	public $signed_document_path;
	public $signed_document_hash;
	public $signature_mode = 'parallel';
	public $expiration_date;
	public $custom_message;
	public $status = 0;
	public $cancel_reason;
	public $certificate_path;
	public $certificate_hash;
	public $certificate_date;
	public $system_signature;
	public $date_creation;
	public $tms;
	public $fk_user_creat;
	public $fk_user_modif;
	public $nb_signers = 0;
	public $nb_signed = 0;
	public $last_activity;

	// Status constants
	const STATUS_DRAFT = 0;
	const STATUS_SENT = 1;
	const STATUS_IN_PROGRESS = 2;
	const STATUS_COMPLETED = 3;
	const STATUS_CANCELLED = 4;
	const STATUS_EXPIRED = 5;

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
	 * Create envelope in database
	 *
	 * @param User $user User that creates
	 * @param int $notrigger 0=launch triggers after, 1=disable triggers
	 * @return int <0 if KO, ID of envelope if OK
	 */
	public function create($user, $notrigger = 0)
	{
		global $conf;

		$error = 0;
		$now = dol_now();

		$this->db->begin();

		// Generate reference
		if (empty($this->ref)) {
			$this->ref = $this->getNextNumRef();
		}

		// Sanitize
		$this->element_type = trim($this->element_type);
		$this->document_path = trim($this->document_path);
		$this->document_name = trim($this->document_name);
		$this->signature_mode = in_array($this->signature_mode, array('parallel', 'ordered')) ? $this->signature_mode : 'parallel';

		// Insert
		$sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (";
		$sql .= " ref, entity, element_type, element_id,";
		$sql .= " document_path, document_hash, document_name,";
		$sql .= " signature_mode, expiration_date, custom_message,";
		$sql .= " status, date_creation, fk_user_creat, nb_signers";
		$sql .= ") VALUES (";
		$sql .= " '".$this->db->escape($this->ref)."',";
		$sql .= " ".(int)$conf->entity.",";
		$sql .= " '".$this->db->escape($this->element_type)."',";
		$sql .= " ".(int)$this->element_id.",";
		$sql .= " '".$this->db->escape($this->document_path)."',";
		$sql .= " '".$this->db->escape($this->document_hash)."',";
		$sql .= " '".$this->db->escape($this->document_name)."',";
		$sql .= " '".$this->db->escape($this->signature_mode)."',";
		$sql .= " ".($this->expiration_date ? "'".$this->db->idate($this->expiration_date)."'" : "NULL").",";
		$sql .= " ".($this->custom_message ? "'".$this->db->escape($this->custom_message)."'" : "NULL").",";
		$sql .= " ".(int)$this->status.",";
		$sql .= " '".$this->db->idate($now)."',";
		$sql .= " ".(int)$user->id.",";
		$sql .= " 0";
		$sql .= ")";

		dol_syslog(get_class($this)."::create", LOG_DEBUG);
		$resql = $this->db->query($sql);

		if ($resql) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);

			// Audit trail
			$this->addAuditEvent('envelope_created', array(
				'ref' => $this->ref,
				'element_type' => $this->element_type,
				'element_id' => $this->element_id,
				'document' => $this->document_name,
			), $user);

			if (!$notrigger) {
				// Call trigger
				$result = $this->call_trigger('DOCSIG_ENVELOPE_CREATE', $user);
				if ($result < 0) $error++;
			}

			if (!$error) {
				$this->db->commit();
				return $this->id;
			} else {
				$this->db->rollback();
				return -1;
			}
		} else {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 * Load envelope from database
	 *
	 * @param int $id ID of envelope
	 * @param string $ref Reference of envelope
	 * @return int <0 if KO, >0 if OK
	 */
	public function fetch($id, $ref = null)
	{
		$sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " WHERE entity IN (".getEntity($this->element).")";
		
		if ($id) {
			$sql .= " AND rowid = ".(int)$id;
		} elseif ($ref) {
			$sql .= " AND ref = '".$this->db->escape($ref)."'";
		} else {
			return -1;
		}

		dol_syslog(get_class($this)."::fetch", LOG_DEBUG);
		$resql = $this->db->query($sql);

		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj) {
				$this->id = $obj->rowid;
				$this->ref = $obj->ref;
				$this->entity = $obj->entity;
				$this->element_type = $obj->element_type;
				$this->element_id = $obj->element_id;
				$this->document_path = $obj->document_path;
				$this->document_hash = $obj->document_hash;
				$this->document_name = $obj->document_name;
				$this->signed_document_path = $obj->signed_document_path;
				$this->signed_document_hash = $obj->signed_document_hash;
				$this->signature_mode = $obj->signature_mode;
				$this->expiration_date = $this->db->jdate($obj->expiration_date);
				$this->custom_message = $obj->custom_message;
				$this->status = $obj->status;
				$this->cancel_reason = $obj->cancel_reason;
				$this->certificate_path = $obj->certificate_path;
				$this->certificate_hash = $obj->certificate_hash;
				$this->certificate_date = $this->db->jdate($obj->certificate_date);
				$this->system_signature = $obj->system_signature;
				$this->date_creation = $this->db->jdate($obj->date_creation);
				$this->tms = $this->db->jdate($obj->tms);
				$this->fk_user_creat = $obj->fk_user_creat;
				$this->fk_user_modif = $obj->fk_user_modif;
				$this->nb_signers = $obj->nb_signers;
				$this->nb_signed = $obj->nb_signed;
				$this->last_activity = $this->db->jdate($obj->last_activity);

				return 1;
			}
			return 0;
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}

	/**
	 * Update envelope in database
	 *
	 * @param User $user User that modifies
	 * @param int $notrigger 0=launch triggers after, 1=disable triggers
	 * @return int <0 if KO, >0 if OK
	 */
	public function update($user, $notrigger = 0)
	{
		$error = 0;

		$this->db->begin();

		$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET";
		$sql .= " signed_document_path = ".($this->signed_document_path ? "'".$this->db->escape($this->signed_document_path)."'" : "NULL").",";
		$sql .= " signed_document_hash = ".($this->signed_document_hash ? "'".$this->db->escape($this->signed_document_hash)."'" : "NULL").",";
		$sql .= " status = ".(int)$this->status.",";
		$sql .= " cancel_reason = ".($this->cancel_reason ? "'".$this->db->escape($this->cancel_reason)."'" : "NULL").",";
		$sql .= " certificate_path = ".($this->certificate_path ? "'".$this->db->escape($this->certificate_path)."'" : "NULL").",";
		$sql .= " certificate_hash = ".($this->certificate_hash ? "'".$this->db->escape($this->certificate_hash)."'" : "NULL").",";
		$sql .= " certificate_date = ".($this->certificate_date ? "'".$this->db->idate($this->certificate_date)."'" : "NULL").",";
		$sql .= " system_signature = ".($this->system_signature ? "'".$this->db->escape($this->system_signature)."'" : "NULL").",";
		$sql .= " nb_signed = ".(int)$this->nb_signed.",";
		$sql .= " last_activity = '".$this->db->idate(dol_now())."',";
		$sql .= " fk_user_modif = ".(int)$user->id;
		$sql .= " WHERE rowid = ".(int)$this->id;

		dol_syslog(get_class($this)."::update", LOG_DEBUG);
		$resql = $this->db->query($sql);

		if ($resql) {
			if (!$notrigger) {
				$result = $this->call_trigger('DOCSIG_ENVELOPE_MODIFY', $user);
				if ($result < 0) $error++;
			}

			if (!$error) {
				$this->db->commit();
				return 1;
			} else {
				$this->db->rollback();
				return -1;
			}
		} else {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 * Cancel envelope
	 *
	 * @param User $user User
	 * @param string $reason Cancellation reason
	 * @return int <0 if KO, >0 if OK
	 */
	public function cancel($user, $reason = '')
	{
		$this->status = self::STATUS_CANCELLED;
		$this->cancel_reason = $reason;

		$result = $this->update($user);
		
		if ($result > 0) {
			// Invalidate all signature tokens
			$sql = "UPDATE ".MAIN_DB_PREFIX."docsig_signature";
			$sql .= " SET status = 5"; // cancelled
			$sql .= " WHERE fk_envelope = ".(int)$this->id;
			$this->db->query($sql);

			// Audit trail
			$this->addAuditEvent('envelope_cancelled', array(
				'reason' => $reason,
			), $user);
		}

		return $result;
	}

	/**
	 * Add audit event
	 *
	 * @param string $eventType Event type
	 * @param array $eventData Event data
	 * @param User $user User
	 * @param int $signatureId Signature ID (optional)
	 * @return int <0 if KO, >0 if OK
	 */
	public function addAuditEvent($eventType, $eventData = array(), $user = null, $signatureId = null)
	{
		require_once __DIR__.'/docsigaudittrail.class.php';
		
		$audit = new DocsigAuditTrail($this->db);
		$audit->fk_envelope = $this->id;
		$audit->fk_signature = $signatureId;
		$audit->event_type = $eventType;
		$audit->event_data = json_encode($eventData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$audit->fk_user = $user ? $user->id : null;
		
		if (!empty($_SERVER['REMOTE_ADDR'])) {
			$audit->ip_address = $_SERVER['REMOTE_ADDR'];
		}
		if (!empty($_SERVER['HTTP_USER_AGENT'])) {
			$audit->user_agent = $_SERVER['HTTP_USER_AGENT'];
		}
		
		return $audit->create();
	}

	/**
	 * Get next reference
	 *
	 * @return string
	 */
	public function getNextNumRef()
	{
		global $conf;

		$sql = "SELECT MAX(CAST(SUBSTRING(ref, 4) AS UNSIGNED)) as maxref";
		$sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " WHERE ref LIKE 'ENV%'";
		$sql .= " AND entity = ".$conf->entity;

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			$max = $obj->maxref ? $obj->maxref : 0;
			return 'ENV'.str_pad($max + 1, 6, '0', STR_PAD_LEFT);
		}

		return 'ENV'.str_pad(1, 6, '0', STR_PAD_LEFT);
	}

	/**
	 * Get status label
	 *
	 * @param int $mode 0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto
	 * @return string Label
	 */
	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	/**
	 * Return status label
	 *
	 * @param int $status Status
	 * @param int $mode 0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto
	 * @return string Label
	 */
	public function LibStatut($status, $mode = 0)
	{
		global $langs;

		$statusLabels = array(
			0 => array('Draft', 'Draft', 'status0'),
			1 => array('Sent', 'Sent', 'status4'),
			2 => array('In Progress', 'In Progress', 'status3'),
			3 => array('Completed', 'Completed', 'status6'),
			4 => array('Cancelled', 'Cancelled', 'status9'),
			5 => array('Expired', 'Expired', 'status8'),
		);

		$label = isset($statusLabels[$status]) ? $statusLabels[$status] : array('Unknown', 'Unknown', 'status0');

		if ($mode == 0) return $label[0];
		if ($mode == 1) return $label[1];
		if ($mode == 2) return img_picto($label[0], $label[2]).' '.$label[1];
		if ($mode == 3) return img_picto($label[0], $label[2]);
		if ($mode == 4) return img_picto($label[0], $label[2]).' '.$label[0];
		if ($mode == 5) return $label[1].' '.img_picto($label[0], $label[2]);

		return $label[0];
	}

	/**
	 * Check if envelope is expired
	 *
	 * @return bool
	 */
	public function isExpired()
	{
		if ($this->expiration_date && $this->expiration_date < dol_now()) {
			return true;
		}
		return false;
	}

	/**
	 * Update expiration status
	 *
	 * @return int
	 */
	public function updateExpirationStatus()
	{
		if ($this->isExpired() && $this->status < self::STATUS_COMPLETED && $this->status != self::STATUS_CANCELLED) {
			$this->status = self::STATUS_EXPIRED;
			
			$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
			$sql .= " SET status = ".(int)$this->status;
			$sql .= " WHERE rowid = ".(int)$this->id;
			
			return $this->db->query($sql) ? 1 : -1;
		}
		return 0;
	}
}
