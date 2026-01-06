<?php
/* Copyright (C) 2026 DocSig Module
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
 * \file    htdocs/custom/signDol/class/docsigsigner.class.php
 * \ingroup docsig
 * \brief   Clase para gestión de firmantes
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class DocSigSigner
 * Gestión de firmantes de un sobre
 */
class DocSigSigner extends CommonObject
{
    /**
     * @var string ID to identify managed object
     */
    public $element = 'docsig_signer';

    /**
     * @var string Name of table without prefix where object is stored
     */
    public $table_element = 'docsig_signer';

    /**
     * Status constants
     */
    const STATUS_PENDING = 0;
    const STATUS_SIGNED = 1;
    const STATUS_REJECTED = 2;

    // Properties
    public $rowid;
    public $fk_envelope;
    public $fk_contact;
    public $fk_socpeople;
    public $email;
    public $phone;
    public $firstname;
    public $lastname;
    public $dni;
    public $sign_order;
    public $token_hash;
    public $token_expire;
    public $status;
    public $date_signed;
    public $signature_image;
    public $signature_hash;
    public $ip_address;
    public $user_agent;
    public $date_creation;
    public $date_modification;

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
     * Create object into database
     *
     * @param User $user User that creates
     * @param bool $notrigger false=launch triggers after, true=disable triggers
     * @return int Return integer <0 if KO, Id of created object if OK
     */
    public function create(User $user, $notrigger = false)
    {
        $error = 0;

        // Generate secure token
        $token = docsig_generate_secure_token();
        $this->token_hash = docsig_hash_token($token);

        // Set dates
        $this->date_creation = dol_now();

        // Calculate token expiry
        $days = getDolGlobalInt('DOCSIG_TOKEN_EXPIRY_DAYS', 7);
        $this->token_expire = dol_time_plus_duree($this->date_creation, $days, 'd');

        $this->db->begin();

        $sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (";
        $sql .= "fk_envelope, fk_contact, fk_socpeople, email, phone, firstname, lastname, dni,";
        $sql .= "sign_order, token_hash, token_expire, status, date_creation";
        $sql .= ") VALUES (";
        $sql .= (int)$this->fk_envelope.",";
        $sql .= " ".($this->fk_contact > 0 ? (int)$this->fk_contact : "null").",";
        $sql .= " ".($this->fk_socpeople > 0 ? (int)$this->fk_socpeople : "null").",";
        $sql .= " '".$this->db->escape($this->email)."',";
        $sql .= " ".($this->phone ? "'".$this->db->escape($this->phone)."'" : "null").",";
        $sql .= " '".$this->db->escape($this->firstname)."',";
        $sql .= " '".$this->db->escape($this->lastname)."',";
        $sql .= " ".($this->dni ? "'".$this->db->escape($this->dni)."'" : "null").",";
        $sql .= " ".(int)$this->sign_order.",";
        $sql .= " '".$this->db->escape($this->token_hash)."',";
        $sql .= " '".$this->db->idate($this->token_expire)."',";
        $sql .= " ".(int)$this->status.",";
        $sql .= " '".$this->db->idate($this->date_creation)."'";
        $sql .= ")";

        $resql = $this->db->query($sql);
        if (!$resql) {
            $error++;
            $this->errors[] = 'Error inserting signer: '.$this->db->lasterror();
        }

        if (!$error) {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
            // Store plain token to return to caller (for sending in email)
            $this->plain_token = $token;
        }

        if (!$error) {
            $this->db->commit();
            return $this->id;
        } else {
            $this->db->rollback();
            return -1;
        }
    }

    /**
     * Load object in memory from the database
     *
     * @param int $id Id object
     * @param string $tokenHash Token hash
     * @return int Return integer <0 if KO, 0 if not found, >0 if OK
     */
    public function fetch($id, $tokenHash = null)
    {
        $sql = "SELECT t.*";
        $sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element." as t";
        if ($tokenHash) {
            $sql .= " WHERE t.token_hash = '".$this->db->escape($tokenHash)."'";
        } else {
            $sql .= " WHERE t.rowid = ".(int)$id;
        }

        $resql = $this->db->query($sql);
        if ($resql) {
            if ($this->db->num_rows($resql)) {
                $obj = $this->db->fetch_object($resql);

                $this->id = $obj->rowid;
                $this->rowid = $obj->rowid;
                $this->fk_envelope = $obj->fk_envelope;
                $this->fk_contact = $obj->fk_contact;
                $this->fk_socpeople = $obj->fk_socpeople;
                $this->email = $obj->email;
                $this->phone = $obj->phone;
                $this->firstname = $obj->firstname;
                $this->lastname = $obj->lastname;
                $this->dni = $obj->dni;
                $this->sign_order = $obj->sign_order;
                $this->token_hash = $obj->token_hash;
                $this->token_expire = $this->db->jdate($obj->token_expire);
                $this->status = $obj->status;
                $this->date_signed = $this->db->jdate($obj->date_signed);
                $this->signature_image = $obj->signature_image;
                $this->signature_hash = $obj->signature_hash;
                $this->ip_address = $obj->ip_address;
                $this->user_agent = $obj->user_agent;
                $this->date_creation = $this->db->jdate($obj->date_creation);
                $this->date_modification = $this->db->jdate($obj->date_modification);

                return 1;
            } else {
                return 0;
            }
        } else {
            $this->error = $this->db->lasterror();
            return -1;
        }
    }

    /**
     * Fetch signer by plain token (verifying hash)
     *
     * @param string $plainToken The plain token from URL
     * @return int Return integer <0 if KO, 0 if not found, >0 if OK
     */
    public function fetchByToken($plainToken)
    {
        $hash = docsig_hash_token($plainToken);
        return $this->fetch(0, $hash);
    }

    /**
     * Update object into database
     *
     * @param User $user User that modifies
     * @param bool $notrigger false=launch triggers after, true=disable triggers
     * @return int Return integer <0 if KO, >0 if OK
     */
    public function update(User $user, $notrigger = false)
    {
        $error = 0;

        $this->date_modification = dol_now();

        $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET";
        $sql .= " fk_contact = ".($this->fk_contact > 0 ? (int)$this->fk_contact : "null").",";
        $sql .= " fk_socpeople = ".($this->fk_socpeople > 0 ? (int)$this->fk_socpeople : "null").",";
        $sql .= " email = '".$this->db->escape($this->email)."',";
        $sql .= " phone = ".($this->phone ? "'".$this->db->escape($this->phone)."'" : "null").",";
        $sql .= " firstname = '".$this->db->escape($this->firstname)."',";
        $sql .= " lastname = '".$this->db->escape($this->lastname)."',";
        $sql .= " dni = ".($this->dni ? "'".$this->db->escape($this->dni)."'" : "null").",";
        $sql .= " sign_order = ".(int)$this->sign_order.",";
        $sql .= " status = ".(int)$this->status.",";
        $sql .= " date_signed = ".($this->date_signed ? "'".$this->db->idate($this->date_signed)."'" : "null").",";
        $sql .= " signature_image = ".($this->signature_image ? "'".$this->db->escape($this->signature_image)."'" : "null").",";
        $sql .= " signature_hash = ".($this->signature_hash ? "'".$this->db->escape($this->signature_hash)."'" : "null").",";
        $sql .= " ip_address = ".($this->ip_address ? "'".$this->db->escape($this->ip_address)."'" : "null").",";
        $sql .= " user_agent = ".($this->user_agent ? "'".$this->db->escape($this->user_agent)."'" : "null").",";
        $sql .= " date_modification = '".$this->db->idate($this->date_modification)."'";
        $sql .= " WHERE rowid = ".(int)$this->id;

        $resql = $this->db->query($sql);
        if (!$resql) {
            $error++;
            $this->errors[] = 'Error updating signer: '.$this->db->lasterror();
        }

        return $error ? -1 : 1;
    }

    /**
     * Delete object in database
     *
     * @param User $user User that deletes
     * @param bool $notrigger false=launch triggers after, true=disable triggers
     * @return int Return integer <0 if KO, >0 if OK
     */
    public function delete(User $user, $notrigger = false)
    {
        $sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE rowid = ".(int)$this->id;

        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->errors[] = 'Error deleting signer: '.$this->db->lasterror();
            return -1;
        }

        return 1;
    }

    /**
     * Record signature
     *
     * @param string $signatureImage Base64 encoded signature image
     * @param string $ipAddress IP address
     * @param string $userAgent User agent
     * @return int Return integer <0 if KO, >0 if OK
     */
    public function recordSignature($signatureImage, $ipAddress = '', $userAgent = '')
    {
        global $user;

        $this->status = self::STATUS_SIGNED;
        $this->date_signed = dol_now();
        $this->signature_image = $signatureImage;
        $this->signature_hash = hash('sha256', $signatureImage);
        $this->ip_address = $ipAddress ?: ($_SERVER['REMOTE_ADDR'] ?? '');
        $this->user_agent = $userAgent ?: (substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512));

        // Log event on envelope
        require_once __DIR__.'/docsigenvelope.class.php';
        $envelope = new DocSigEnvelope($this->db);
        if ($envelope->fetch($this->fk_envelope) > 0) {
            $envelope->logEvent(
                'SIGNER_SIGNED',
                'Signer '.$this->firstname.' '.$this->lastname.' signed the document',
                $this->ip_address,
                $this->user_agent
            );
        }

        return $this->update($user);
    }

    /**
     * Reject signature
     *
     * @param string $reason Rejection reason
     * @return int Return integer <0 if KO, >0 if OK
     */
    public function reject($reason = '')
    {
        global $user;

        $this->status = self::STATUS_REJECTED;
        $this->ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $this->user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);

        // Log event on envelope
        require_once __DIR__.'/docsigenvelope.class.php';
        $envelope = new DocSigEnvelope($this->db);
        if ($envelope->fetch($this->fk_envelope) > 0) {
            $envelope->logEvent(
                'SIGNER_REJECTED',
                'Signer '.$this->firstname.' '.$this->lastname.' rejected: '.$reason,
                $this->ip_address,
                $this->user_agent
            );
        }

        return $this->update($user);
    }

    /**
     * Regenerate token (for resend)
     *
     * @return string New plain token
     */
    public function regenerateToken()
    {
        global $user;

        $token = docsig_generate_secure_token();
        $this->token_hash = docsig_hash_token($token);

        $days = getDolGlobalInt('DOCSIG_TOKEN_EXPIRY_DAYS', 7);
        $this->token_expire = dol_time_plus_duree(dol_now(), $days, 'd');

        $this->update($user);

        return $token;
    }

    /**
     * Check if token is valid
     *
     * @param string $plainToken Plain token to verify
     * @return bool True if valid
     */
    public function verifyToken($plainToken)
    {
        // Verify hash matches
        $hash = docsig_hash_token($plainToken);
        if ($hash !== $this->token_hash) {
            return false;
        }

        // Verify not expired
        if ($this->token_expire < dol_now()) {
            return false;
        }

        // Verify not already signed/rejected
        if ($this->status != self::STATUS_PENDING) {
            return false;
        }

        return true;
    }

    /**
     * Check if signer's turn (for sequential signing)
     *
     * @return bool True if it's this signer's turn
     */
    public function isMyTurn()
    {
        // Check envelope mode
        require_once __DIR__.'/docsigenvelope.class.php';
        $envelope = new DocSigEnvelope($this->db);
        if ($envelope->fetch($this->fk_envelope) <= 0) {
            return false;
        }

        // Parallel mode: always my turn
        if ($envelope->signature_mode === 'parallel') {
            return true;
        }

        // Sequential mode: check if previous signers have signed
        $sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE fk_envelope = ".(int)$this->fk_envelope;
        $sql .= " AND sign_order < ".(int)$this->sign_order;
        $sql .= " AND status != ".(int)self::STATUS_SIGNED;

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            return ($obj->cnt == 0);
        }

        return false;
    }

    /**
     * Get full name
     *
     * @return string Full name
     */
    public function getFullName()
    {
        return trim($this->firstname.' '.$this->lastname);
    }

    /**
     * Get status label
     *
     * @param int $mode 0=long label, 1=short label, 2=Picto + short label
     * @return string Label
     */
    public function getLibStatut($mode = 0)
    {
        global $langs;
        $langs->load('docsig@signDol');

        $statusLabels = array(
            self::STATUS_PENDING => array(
                'label' => 'PendingSignature',
                'labelshort' => 'Pending',
                'badgetype' => 'status4',
            ),
            self::STATUS_SIGNED => array(
                'label' => 'Signed',
                'labelshort' => 'Signed',
                'badgetype' => 'status6',
            ),
            self::STATUS_REJECTED => array(
                'label' => 'Rejected',
                'labelshort' => 'Rejected',
                'badgetype' => 'status9',
            ),
        );

        $statusInfo = $statusLabels[$this->status] ?? $statusLabels[self::STATUS_PENDING];

        $labelLong = $langs->trans($statusInfo['label']);
        $labelShort = $langs->trans($statusInfo['labelshort']);
        $badgeType = $statusInfo['badgetype'];

        if ($mode == 0) {
            return $labelLong;
        } elseif ($mode == 1) {
            return $labelShort;
        } elseif ($mode == 2) {
            return dolGetBadge($labelShort, '', $badgeType);
        }

        return $labelLong;
    }

    /**
     * Get signing URL for this signer
     *
     * @return string Public URL for signing
     */
    public function getSignUrl()
    {
        // Plain token must be available (only right after creation)
        if (empty($this->plain_token)) {
            return '';
        }

        return docsig_get_public_sign_url($this->plain_token);
    }
}
