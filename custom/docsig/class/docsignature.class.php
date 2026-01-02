<?php
/* Copyright (C) 2026 Document Signature Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       class/docsignature.class.php
 * \ingroup    docsig
 * \brief      Signature class
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class for Docsig Signature
 */
class DocsigSignature extends CommonObject
{
	public $element = 'docsignature';
	public $table_element = 'docsig_signature';

	public $id;
	public $entity;
	public $fk_envelope;
	public $fk_socpeople;
	public $signer_name;
	public $signer_email;
	public $signer_dni;
	public $signer_order = 0;
	public $token;
	public $token_plain;
	public $token_expiry;
	public $status = 0;
	public $otp_code;
	public $otp_expiry;
	public $otp_attempts = 0;
	public $otp_sent_count = 0;
	public $last_otp_sent;
	public $signature_image;
	public $signature_date;
	public $signature_ip;
	public $signature_user_agent;
	public $signature_position_x;
	public $signature_position_y;
	public $signature_page;
	public $date_creation;
	public $tms;
	public $fk_user_creat;
	public $link_opened_count = 0;
	public $first_opened_date;
	public $last_activity;

	// Status constants
	const STATUS_PENDING = 0;
	const STATUS_OPENED = 1;
	const STATUS_AUTHENTICATED = 2;
	const STATUS_SIGNED = 3;
	const STATUS_FAILED = 4;
	const STATUS_CANCELLED = 5;
	const STATUS_EXPIRED = 6;

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
	 * Create signature in database
	 *
	 * @param User $user User that creates
	 * @return int <0 if KO, ID if OK
	 */
	public function create($user = null)
	{
		global $conf;

		$error = 0;
		$now = dol_now();

		$this->db->begin();

		// Generate secure token
		if (empty($this->token_plain)) {
			$tokenLength = !empty($conf->global->DOCSIG_TOKEN_LENGTH) ? $conf->global->DOCSIG_TOKEN_LENGTH : 64;
			$this->token_plain = bin2hex(random_bytes($tokenLength / 2));
		}

		// Hash token for storage
		$this->token = hash('sha256', $this->token_plain);

		// Set expiration from envelope if not set
		if (empty($this->token_expiry)) {
			require_once __DIR__.'/docsigenvelope.class.php';
			$envelope = new DocsigEnvelope($this->db);
			if ($envelope->fetch($this->fk_envelope) > 0 && $envelope->expiration_date) {
				$this->token_expiry = $envelope->expiration_date;
			} else {
				$days = !empty($conf->global->DOCSIG_EXPIRATION_DAYS) ? $conf->global->DOCSIG_EXPIRATION_DAYS : 30;
				$this->token_expiry = $now + ($days * 24 * 3600);
			}
		}

		// Sanitize
		$this->signer_name = trim($this->signer_name);
		$this->signer_email = strtolower(trim($this->signer_email));
		$this->signer_dni = trim($this->signer_dni);

		// Insert
		$sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (";
		$sql .= " entity, fk_envelope, fk_socpeople,";
		$sql .= " signer_name, signer_email, signer_dni, signer_order,";
		$sql .= " token, token_plain, token_expiry,";
		$sql .= " status, date_creation";
		if ($user) $sql .= ", fk_user_creat";
		$sql .= ") VALUES (";
		$sql .= " ".(int)$conf->entity.",";
		$sql .= " ".(int)$this->fk_envelope.",";
		$sql .= " ".(int)$this->fk_socpeople.",";
		$sql .= " '".$this->db->escape($this->signer_name)."',";
		$sql .= " '".$this->db->escape($this->signer_email)."',";
		$sql .= " ".($this->signer_dni ? "'".$this->db->escape($this->signer_dni)."'" : "NULL").",";
		$sql .= " ".(int)$this->signer_order.",";
		$sql .= " '".$this->db->escape($this->token)."',";
		$sql .= " '".$this->db->escape($this->token_plain)."',";
		$sql .= " '".$this->db->idate($this->token_expiry)."',";
		$sql .= " ".(int)$this->status.",";
		$sql .= " '".$this->db->idate($now)."'";
		if ($user) $sql .= ", ".(int)$user->id;
		$sql .= ")";

		dol_syslog(get_class($this)."::create", LOG_DEBUG);
		$resql = $this->db->query($sql);

		if ($resql) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);

			// Update envelope signer count
			$sql = "UPDATE ".MAIN_DB_PREFIX."docsig_envelope";
			$sql .= " SET nb_signers = nb_signers + 1";
			$sql .= " WHERE rowid = ".(int)$this->fk_envelope;
			$this->db->query($sql);

			$this->db->commit();
			return $this->id;
		} else {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 * Load signature from database
	 *
	 * @param int $id ID
	 * @param string $token Token (plain)
	 * @return int <0 if KO, >0 if OK
	 */
	public function fetch($id = null, $token = null)
	{
		$sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " WHERE entity IN (".getEntity($this->element).")";
		
		if ($id) {
			$sql .= " AND rowid = ".(int)$id;
		} elseif ($token) {
			$tokenHash = hash('sha256', $token);
			$sql .= " AND token = '".$this->db->escape($tokenHash)."'";
		} else {
			return -1;
		}

		dol_syslog(get_class($this)."::fetch", LOG_DEBUG);
		$resql = $this->db->query($sql);

		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj) {
				$this->id = $obj->rowid;
				$this->entity = $obj->entity;
				$this->fk_envelope = $obj->fk_envelope;
				$this->fk_socpeople = $obj->fk_socpeople;
				$this->signer_name = $obj->signer_name;
				$this->signer_email = $obj->signer_email;
				$this->signer_dni = $obj->signer_dni;
				$this->signer_order = $obj->signer_order;
				$this->token = $obj->token;
				$this->token_plain = $obj->token_plain;
				$this->token_expiry = $this->db->jdate($obj->token_expiry);
				$this->status = $obj->status;
				$this->otp_code = $obj->otp_code;
				$this->otp_expiry = $this->db->jdate($obj->otp_expiry);
				$this->otp_attempts = $obj->otp_attempts;
				$this->otp_sent_count = $obj->otp_sent_count;
				$this->last_otp_sent = $this->db->jdate($obj->last_otp_sent);
				$this->signature_image = $obj->signature_image;
				$this->signature_date = $this->db->jdate($obj->signature_date);
				$this->signature_ip = $obj->signature_ip;
				$this->signature_user_agent = $obj->signature_user_agent;
				$this->signature_position_x = $obj->signature_position_x;
				$this->signature_position_y = $obj->signature_position_y;
				$this->signature_page = $obj->signature_page;
				$this->date_creation = $this->db->jdate($obj->date_creation);
				$this->tms = $this->db->jdate($obj->tms);
				$this->fk_user_creat = $obj->fk_user_creat;
				$this->link_opened_count = $obj->link_opened_count;
				$this->first_opened_date = $this->db->jdate($obj->first_opened_date);
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
	 * Update signature
	 *
	 * @return int <0 if KO, >0 if OK
	 */
	public function update()
	{
		$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET";
		$sql .= " status = ".(int)$this->status.",";
		$sql .= " otp_code = ".($this->otp_code ? "'".$this->db->escape($this->otp_code)."'" : "NULL").",";
		$sql .= " otp_expiry = ".($this->otp_expiry ? "'".$this->db->idate($this->otp_expiry)."'" : "NULL").",";
		$sql .= " otp_attempts = ".(int)$this->otp_attempts.",";
		$sql .= " otp_sent_count = ".(int)$this->otp_sent_count.",";
		$sql .= " last_otp_sent = ".($this->last_otp_sent ? "'".$this->db->idate($this->last_otp_sent)."'" : "NULL").",";
		$sql .= " signature_image = ".($this->signature_image ? "'".$this->db->escape($this->signature_image)."'" : "NULL").",";
		$sql .= " signature_date = ".($this->signature_date ? "'".$this->db->idate($this->signature_date)."'" : "NULL").",";
		$sql .= " signature_ip = ".($this->signature_ip ? "'".$this->db->escape($this->signature_ip)."'" : "NULL").",";
		$sql .= " signature_user_agent = ".($this->signature_user_agent ? "'".$this->db->escape($this->signature_user_agent)."'" : "NULL").",";
		$sql .= " signature_position_x = ".($this->signature_position_x ? (float)$this->signature_position_x : "NULL").",";
		$sql .= " signature_position_y = ".($this->signature_position_y ? (float)$this->signature_position_y : "NULL").",";
		$sql .= " signature_page = ".($this->signature_page ? (int)$this->signature_page : "NULL").",";
		$sql .= " link_opened_count = ".(int)$this->link_opened_count.",";
		$sql .= " first_opened_date = ".($this->first_opened_date ? "'".$this->db->idate($this->first_opened_date)."'" : "NULL").",";
		$sql .= " last_activity = '".$this->db->idate(dol_now())."'";
		$sql .= " WHERE rowid = ".(int)$this->id;

		dol_syslog(get_class($this)."::update", LOG_DEBUG);
		$resql = $this->db->query($sql);

		return $resql ? 1 : -1;
	}

	/**
	 * Generate and send OTP
	 *
	 * @return int <0 if KO, >0 if OK
	 */
	public function generateOTP()
	{
		global $conf;

		// Check rate limit
		if (!$this->checkRateLimit('otp_email', $this->signer_email)) {
			$this->error = 'Rate limit exceeded for email';
			return -1;
		}

		if (!empty($_SERVER['REMOTE_ADDR']) && !$this->checkRateLimit('otp_ip', $_SERVER['REMOTE_ADDR'])) {
			$this->error = 'Rate limit exceeded for IP';
			return -2;
		}

		// Generate 6-digit OTP
		$this->otp_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
		
		// Set expiry
		$minutes = !empty($conf->global->DOCSIG_OTP_EXPIRY_MINUTES) ? $conf->global->DOCSIG_OTP_EXPIRY_MINUTES : 10;
		$this->otp_expiry = dol_now() + ($minutes * 60);
		
		// Reset attempts
		$this->otp_attempts = 0;
		$this->otp_sent_count++;
		$this->last_otp_sent = dol_now();

		// Update database
		$result = $this->update();
		
		if ($result > 0) {
			// Send OTP email
			$result = $this->sendOTPEmail();
			
			if ($result > 0) {
				// Record rate limit
				$this->recordRateLimit('otp_email', $this->signer_email);
				if (!empty($_SERVER['REMOTE_ADDR'])) {
					$this->recordRateLimit('otp_ip', $_SERVER['REMOTE_ADDR']);
				}
			}
		}

		return $result;
	}

	/**
	 * Validate OTP
	 *
	 * @param string $code OTP code
	 * @return int <0 if KO, >0 if OK
	 */
	public function validateOTP($code)
	{
		global $conf;

		$code = trim($code);
		$now = dol_now();

		// Check if OTP expired
		if (!$this->otp_expiry || $this->otp_expiry < $now) {
			$this->error = 'OTP expired';
			return -1;
		}

		// Check attempts
		$maxAttempts = !empty($conf->global->DOCSIG_OTP_MAX_ATTEMPTS) ? $conf->global->DOCSIG_OTP_MAX_ATTEMPTS : 5;
		if ($this->otp_attempts >= $maxAttempts) {
			$this->error = 'Maximum attempts exceeded';
			$this->status = self::STATUS_FAILED;
			$this->update();
			return -2;
		}

		// Increment attempts
		$this->otp_attempts++;
		$this->update();

		// Validate
		if ($code === $this->otp_code) {
			$this->status = self::STATUS_AUTHENTICATED;
			$this->update();
			return 1;
		}

		$this->error = 'Invalid OTP';
		return -3;
	}

	/**
	 * Send OTP email
	 *
	 * @return int <0 if KO, >0 if OK
	 */
	private function sendOTPEmail()
	{
		global $conf, $langs;

		require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
		require_once __DIR__.'/docsignotification.class.php';

		$subject = 'Your signature verification code';
		$message = "Your verification code is: <strong>".$this->otp_code."</strong><br><br>";
		$message .= "This code will expire in ".(!empty($conf->global->DOCSIG_OTP_EXPIRY_MINUTES) ? $conf->global->DOCSIG_OTP_EXPIRY_MINUTES : 10)." minutes.<br>";
		$message .= "Do not share this code with anyone.";

		$from = $conf->global->MAIN_MAIL_EMAIL_FROM;
		
		$mail = new CMailFile(
			$subject,
			$this->signer_email,
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
		$notification = new DocsigNotification($this->db);
		$notification->fk_envelope = $this->fk_envelope;
		$notification->fk_signature = $this->id;
		$notification->fk_socpeople = $this->fk_socpeople;
		$notification->notification_type = 'otp';
		$notification->email_to = $this->signer_email;
		$notification->email_subject = $subject;
		$notification->email_body = $message;
		$notification->status = $result ? 1 : 2;
		$notification->error_message = $result ? '' : $mail->error;
		$notification->create();

		return $result ? 1 : -1;
	}

	/**
	 * Check rate limit
	 *
	 * @param string $type Type (otp_email, otp_ip, etc)
	 * @param string $key Key (email, IP, etc)
	 * @return bool
	 */
	private function checkRateLimit($type, $key)
	{
		global $conf;

		$window = !empty($conf->global->DOCSIG_RATE_LIMIT_WINDOW) ? $conf->global->DOCSIG_RATE_LIMIT_WINDOW : 3600;
		$maxAttempts = !empty($conf->global->DOCSIG_RATE_LIMIT_MAX) ? $conf->global->DOCSIG_RATE_LIMIT_MAX : 10;
		$now = dol_now();
		$windowStart = $now - $window;

		$sql = "SELECT attempt_count, is_blocked, blocked_until";
		$sql .= " FROM ".MAIN_DB_PREFIX."docsig_rate_limit";
		$sql .= " WHERE limiter_type = '".$this->db->escape($type)."'";
		$sql .= " AND limiter_key = '".$this->db->escape($key)."'";
		$sql .= " AND expires_at > '".$this->db->idate($now)."'";
		$sql .= " ORDER BY rowid DESC LIMIT 1";

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj) {
				// Check if blocked
				if ($obj->is_blocked && $obj->blocked_until && $this->db->jdate($obj->blocked_until) > $now) {
					return false;
				}

				// Check attempts
				if ($obj->attempt_count >= $maxAttempts) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Record rate limit attempt
	 *
	 * @param string $type Type
	 * @param string $key Key
	 * @return int
	 */
	private function recordRateLimit($type, $key)
	{
		global $conf;

		$window = !empty($conf->global->DOCSIG_RATE_LIMIT_WINDOW) ? $conf->global->DOCSIG_RATE_LIMIT_WINDOW : 3600;
		$now = dol_now();
		$expiresAt = $now + $window;

		// Check if record exists in current window
		$sql = "SELECT rowid, attempt_count FROM ".MAIN_DB_PREFIX."docsig_rate_limit";
		$sql .= " WHERE limiter_type = '".$this->db->escape($type)."'";
		$sql .= " AND limiter_key = '".$this->db->escape($key)."'";
		$sql .= " AND expires_at > '".$this->db->idate($now)."'";
		$sql .= " ORDER BY rowid DESC LIMIT 1";

		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql) > 0) {
			$obj = $this->db->fetch_object($resql);
			
			// Update existing
			$sql = "UPDATE ".MAIN_DB_PREFIX."docsig_rate_limit";
			$sql .= " SET attempt_count = attempt_count + 1,";
			$sql .= " last_attempt = '".$this->db->idate($now)."'";
			$sql .= " WHERE rowid = ".(int)$obj->rowid;
			
			return $this->db->query($sql) ? 1 : -1;
		} else {
			// Insert new
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."docsig_rate_limit (";
			$sql .= " limiter_type, limiter_key, fk_signature,";
			$sql .= " attempt_count, first_attempt, last_attempt, expires_at";
			$sql .= ") VALUES (";
			$sql .= " '".$this->db->escape($type)."',";
			$sql .= " '".$this->db->escape($key)."',";
			$sql .= " ".(int)$this->id.",";
			$sql .= " 1,";
			$sql .= " '".$this->db->idate($now)."',";
			$sql .= " '".$this->db->idate($now)."',";
			$sql .= " '".$this->db->idate($expiresAt)."'";
			$sql .= ")";
			
			return $this->db->query($sql) ? 1 : -1;
		}
	}

	/**
	 * Check if token is valid
	 *
	 * @return bool
	 */
	public function isTokenValid()
	{
		$now = dol_now();
		
		if ($this->status >= self::STATUS_SIGNED || $this->status == self::STATUS_CANCELLED) {
			return false;
		}
		
		if ($this->token_expiry && $this->token_expiry < $now) {
			return false;
		}
		
		return true;
	}

	/**
	 * Get status label
	 *
	 * @param int $mode Mode
	 * @return string
	 */
	public function getLibStatut($mode = 0)
	{
		$statusLabels = array(
			0 => array('Pending', 'Pending', 'status0'),
			1 => array('Opened', 'Opened', 'status3'),
			2 => array('Authenticated', 'Authenticated', 'status4'),
			3 => array('Signed', 'Signed', 'status6'),
			4 => array('Failed', 'Failed', 'status8'),
			5 => array('Cancelled', 'Cancelled', 'status9'),
			6 => array('Expired', 'Expired', 'status8'),
		);

		$label = isset($statusLabels[$this->status]) ? $statusLabels[$this->status] : array('Unknown', 'Unknown', 'status0');

		if ($mode == 0) return $label[0];
		if ($mode == 1) return $label[1];
		if ($mode == 2) return img_picto($label[0], $label[2]).' '.$label[1];
		if ($mode == 3) return img_picto($label[0], $label[2]);
		if ($mode == 4) return img_picto($label[0], $label[2]).' '.$label[0];
		if ($mode == 5) return $label[1].' '.img_picto($label[0], $label[2]);

		return $label[0];
	}
}
