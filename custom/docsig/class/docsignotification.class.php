<?php
/* Copyright (C) 2026 Document Signature Module */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class for Notifications
 */
class DocsigNotification extends CommonObject
{
	public $element = 'docsignotification';
	public $table_element = 'docsig_notification';

	public $id;
	public $entity;
	public $fk_envelope;
	public $fk_signature;
	public $fk_socpeople;
	public $notification_type;
	public $email_to;
	public $email_subject;
	public $email_body;
	public $email_format = 'html';
	public $sent_date;
	public $status = 0;
	public $error_message;
	public $opened_date;
	public $clicked_date;

	// Status constants
	const STATUS_PENDING = 0;
	const STATUS_SENT = 1;
	const STATUS_FAILED = 2;
	const STATUS_BOUNCED = 3;

	/**
	 * Constructor
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Create notification
	 *
	 * @return int <0 if KO, ID if OK
	 */
	public function create()
	{
		global $conf;

		$now = dol_now();
		$this->sent_date = $now;

		$sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (";
		$sql .= " entity, fk_envelope, fk_signature, fk_socpeople,";
		$sql .= " notification_type, email_to, email_subject, email_body, email_format,";
		$sql .= " sent_date, status, error_message";
		$sql .= ") VALUES (";
		$sql .= " ".(int)$conf->entity.",";
		$sql .= " ".(int)$this->fk_envelope.",";
		$sql .= " ".($this->fk_signature ? (int)$this->fk_signature : "NULL").",";
		$sql .= " ".(int)$this->fk_socpeople.",";
		$sql .= " '".$this->db->escape($this->notification_type)."',";
		$sql .= " '".$this->db->escape($this->email_to)."',";
		$sql .= " '".$this->db->escape($this->email_subject)."',";
		$sql .= " '".$this->db->escape($this->email_body)."',";
		$sql .= " '".$this->db->escape($this->email_format)."',";
		$sql .= " '".$this->db->idate($now)."',";
		$sql .= " ".(int)$this->status.",";
		$sql .= " ".($this->error_message ? "'".$this->db->escape($this->error_message)."'" : "NULL");
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
	 * Fetch notification
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
				$this->fk_socpeople = $obj->fk_socpeople;
				$this->notification_type = $obj->notification_type;
				$this->email_to = $obj->email_to;
				$this->email_subject = $obj->email_subject;
				$this->email_body = $obj->email_body;
				$this->email_format = $obj->email_format;
				$this->sent_date = $this->db->jdate($obj->sent_date);
				$this->status = $obj->status;
				$this->error_message = $obj->error_message;
				$this->opened_date = $this->db->jdate($obj->opened_date);
				$this->clicked_date = $this->db->jdate($obj->clicked_date);
				return 1;
			}
			return 0;
		}
		return -1;
	}

	/**
	 * Get notifications for contact
	 *
	 * @param int $socpeopleId Contact ID
	 * @param int $limit Limit
	 * @return array
	 */
	public function fetchByContact($socpeopleId, $limit = 100)
	{
		$results = array();

		$sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " WHERE fk_socpeople = ".(int)$socpeopleId;
		$sql .= " ORDER BY sent_date DESC";
		$sql .= " LIMIT ".(int)$limit;

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$record = new DocsigNotification($this->db);
				$record->id = $obj->rowid;
				$record->entity = $obj->entity;
				$record->fk_envelope = $obj->fk_envelope;
				$record->fk_signature = $obj->fk_signature;
				$record->fk_socpeople = $obj->fk_socpeople;
				$record->notification_type = $obj->notification_type;
				$record->email_to = $obj->email_to;
				$record->email_subject = $obj->email_subject;
				$record->email_body = $obj->email_body;
				$record->email_format = $obj->email_format;
				$record->sent_date = $this->db->jdate($obj->sent_date);
				$record->status = $obj->status;
				$record->error_message = $obj->error_message;
				$record->opened_date = $this->db->jdate($obj->opened_date);
				$record->clicked_date = $this->db->jdate($obj->clicked_date);
				
				$results[] = $record;
			}
		}

		return $results;
	}

	/**
	 * Get notifications for envelope
	 *
	 * @param int $envelopeId Envelope ID
	 * @return array
	 */
	public function fetchByEnvelope($envelopeId)
	{
		$results = array();

		$sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " WHERE fk_envelope = ".(int)$envelopeId;
		$sql .= " ORDER BY sent_date DESC";

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$record = new DocsigNotification($this->db);
				$record->id = $obj->rowid;
				$record->entity = $obj->entity;
				$record->fk_envelope = $obj->fk_envelope;
				$record->fk_signature = $obj->fk_signature;
				$record->fk_socpeople = $obj->fk_socpeople;
				$record->notification_type = $obj->notification_type;
				$record->email_to = $obj->email_to;
				$record->email_subject = $obj->email_subject;
				$record->email_body = $obj->email_body;
				$record->email_format = $obj->email_format;
				$record->sent_date = $this->db->jdate($obj->sent_date);
				$record->status = $obj->status;
				$record->error_message = $obj->error_message;
				$record->opened_date = $this->db->jdate($obj->opened_date);
				$record->clicked_date = $this->db->jdate($obj->clicked_date);
				
				$results[] = $record;
			}
		}

		return $results;
	}

	/**
	 * Send signature request notification
	 *
	 * @param DocsigSignature $signature Signature object
	 * @param string $signUrl Signature URL
	 * @param string $customMessage Custom message
	 * @return int
	 */
	public static function sendSignatureRequest($signature, $signUrl, $customMessage = '')
	{
		global $conf, $db;

		require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
		require_once __DIR__.'/docsigenvelope.class.php';

		$envelope = new DocsigEnvelope($db);
		$envelope->fetch($signature->fk_envelope);

		$subject = 'Signature request for '.$envelope->document_name;
		
		$message = "<h2>Document Signature Request</h2>";
		$message .= "<p>Hello ".$signature->signer_name.",</p>";
		
		if ($customMessage) {
			$message .= "<p>".$customMessage."</p>";
		} else {
			$message .= "<p>You have been requested to sign the following document:</p>";
		}
		
		$message .= "<p><strong>Document:</strong> ".$envelope->document_name."</p>";
		$message .= "<p><strong>Reference:</strong> ".$envelope->ref."</p>";
		
		if ($envelope->expiration_date) {
			$message .= "<p><strong>Valid until:</strong> ".dol_print_date($envelope->expiration_date, 'dayhour')."</p>";
		}
		
		$message .= "<br><p><a href='".$signUrl."' style='display:inline-block;padding:10px 20px;background:#0066cc;color:#fff;text-decoration:none;border-radius:4px;'>Sign Document</a></p>";
		$message .= "<br><p>Or copy this link: <a href='".$signUrl."'>".$signUrl."</a></p>";
		$message .= "<br><p style='color:#666;font-size:12px;'>This is an automated message. Please do not reply.</p>";

		$from = $conf->global->MAIN_MAIL_EMAIL_FROM;
		
		$mail = new CMailFile(
			$subject,
			$signature->signer_email,
			$from,
			$message,
			array(),
			array(),
			array(),
			'',
			'',
			0,
			-1,
			'',
			'',
			'',
			'docsig',
			''
		);

		$result = $mail->sendfile();

		// Log notification
		$notification = new DocsigNotification($db);
		$notification->fk_envelope = $signature->fk_envelope;
		$notification->fk_signature = $signature->id;
		$notification->fk_socpeople = $signature->fk_socpeople;
		$notification->notification_type = 'request';
		$notification->email_to = $signature->signer_email;
		$notification->email_subject = $subject;
		$notification->email_body = $message;
		$notification->status = $result ? self::STATUS_SENT : self::STATUS_FAILED;
		$notification->error_message = $result ? '' : $mail->error;
		$notification->create();

		return $result ? 1 : -1;
	}
}
