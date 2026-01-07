<?php
/* Copyright (C) 2026 DocSig Module
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * DocSig Document Class
 * Representa un documento individual dentro de un envelope de firma
 */

/**
 * Class DocSigDocument
 * 
 * Gestiona documentos individuales dentro de un envelope de firma.
 * Un envelope puede contener múltiples documentos, pero cada documento
 * solo puede pertenecer a un envelope activo.
 */
class DocSigDocument extends CommonObject
{
    /**
     * @var string ID of module
     */
    public $module = 'signDol';

    /**
     * @var string Element type
     */
    public $element = 'docsig_document';

    /**
     * @var string Table name
     */
    public $table_element = 'docsig_document';

    /**
     * @var int Document ID
     */
    public $id;

    /**
     * @var string Reference (DOC-001, etc.)
     */
    public $ref;

    /**
     * @var string Descriptive label
     */
    public $label;

    /**
     * @var int Parent envelope ID
     */
    public $fk_envelope;

    /**
     * @var string Original filename
     */
    public $original_filename;

    /**
     * @var string Path to original PDF
     */
    public $file_path;

    /**
     * @var string SHA-256 hash of original file
     */
    public $file_hash;

    /**
     * @var int File size in bytes
     */
    public $file_size;

    /**
     * @var string Path to signed PDF
     */
    public $signed_file_path;

    /**
     * @var string SHA-256 hash of signed file
     */
    public $signed_hash;

    /**
     * @var int Signing order (1, 2, 3...)
     */
    public $sign_order = 1;

    /**
     * @var int Status (0=pending, 1=partial, 2=completed)
     */
    public $status = 0;

    /**
     * @var int Require all signers (1=yes, 0=no)
     */
    public $require_all_signers = 1;

    /**
     * @var string JSON array of specific signer IDs for this document
     */
    public $specific_signers_json;

    /**
     * @var int Number of pages
     */
    public $page_count;

    /**
     * @var string MIME type
     */
    public $mime_type = 'application/pdf';

    /**
     * @var string Download token for public access
     */
    public $download_token;

    /**
     * @var int|string Token expiration datetime
     */
    public $token_expires;

    /**
     * @var int User who created
     */
    public $fk_user_creat;

    /**
     * @var int|string Creation date
     */
    public $date_creation;

    /**
     * @var int|string Modification date
     */
    public $date_modification;

    /**
     * @var int|string Signed completion date
     */
    public $signed_at;

    // Status constants
    const STATUS_PENDING = 0;
    const STATUS_PARTIAL = 1;
    const STATUS_COMPLETED = 2;

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
     * Create document in database
     *
     * @param User $user User creating
     * @param int $notrigger Disable triggers
     * @return int <0 if KO, Id of created object if OK
     */
    public function create($user, $notrigger = 0)
    {
        global $conf;

        dol_syslog('DocSigDocument::create', LOG_DEBUG);

        $error = 0;
        $now = dol_now();

        // Check required fields
        if (empty($this->fk_envelope)) {
            $this->error = 'ErrorEnvelopeRequired';
            return -1;
        }
        if (empty($this->file_path)) {
            $this->error = 'ErrorFilePathRequired';
            return -2;
        }

        // Generate reference if not set
        if (empty($this->ref)) {
            $this->ref = $this->getNextNumRef();
        }

        // Calculate file hash if file exists
        if (file_exists($this->file_path)) {
            $this->file_hash = hash_file('sha256', $this->file_path);
            $this->file_size = filesize($this->file_path);
        }

        // Get original filename from path if not set
        if (empty($this->original_filename)) {
            $this->original_filename = basename($this->file_path);
        }

        // Generate download token
        $this->download_token = bin2hex(random_bytes(32));

        $this->db->begin();

        $sql = "INSERT INTO ".MAIN_DB_PREFIX."docsig_document (";
        $sql .= "fk_envelope, ref, label, original_filename, file_path, file_hash, file_size,";
        $sql .= "sign_order, status, require_all_signers, specific_signers_json,";
        $sql .= "page_count, mime_type, download_token,";
        $sql .= "fk_user_creat, date_creation";
        $sql .= ") VALUES (";
        $sql .= (int) $this->fk_envelope.",";
        $sql .= "'".$this->db->escape($this->ref)."',";
        $sql .= ($this->label ? "'".$this->db->escape($this->label)."'" : "NULL").",";
        $sql .= "'".$this->db->escape($this->original_filename)."',";
        $sql .= "'".$this->db->escape($this->file_path)."',";
        $sql .= ($this->file_hash ? "'".$this->db->escape($this->file_hash)."'" : "NULL").",";
        $sql .= ($this->file_size ? (int) $this->file_size : "NULL").",";
        $sql .= (int) $this->sign_order.",";
        $sql .= (int) $this->status.",";
        $sql .= (int) $this->require_all_signers.",";
        $sql .= ($this->specific_signers_json ? "'".$this->db->escape($this->specific_signers_json)."'" : "NULL").",";
        $sql .= ($this->page_count ? (int) $this->page_count : "NULL").",";
        $sql .= "'".$this->db->escape($this->mime_type)."',";
        $sql .= "'".$this->db->escape($this->download_token)."',";
        $sql .= (int) $user->id.",";
        $sql .= "'".$this->db->idate($now)."'";
        $sql .= ")";

        dol_syslog(get_class($this)."::create", LOG_DEBUG);
        $resql = $this->db->query($sql);

        if ($resql) {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."docsig_document");
            $this->date_creation = $now;
            $this->fk_user_creat = $user->id;

            $this->db->commit();
            return $this->id;
        } else {
            $this->error = $this->db->lasterror();
            $this->db->rollback();
            return -1;
        }
    }

    /**
     * Load document from database
     *
     * @param int $id Document ID
     * @param string $ref Document ref (optional)
     * @param string $download_token Download token (optional)
     * @return int <0 if KO, >0 if OK
     */
    public function fetch($id, $ref = '', $download_token = '')
    {
        dol_syslog('DocSigDocument::fetch id='.$id.' ref='.$ref.' token='.$download_token, LOG_DEBUG);

        $sql = "SELECT d.rowid, d.fk_envelope, d.ref, d.label, d.original_filename,";
        $sql .= " d.file_path, d.file_hash, d.file_size,";
        $sql .= " d.signed_file_path, d.signed_hash,";
        $sql .= " d.sign_order, d.status, d.require_all_signers, d.specific_signers_json,";
        $sql .= " d.page_count, d.mime_type,";
        $sql .= " d.download_token, d.token_expires,";
        $sql .= " d.fk_user_creat, d.date_creation, d.date_modification, d.signed_at";
        $sql .= " FROM ".MAIN_DB_PREFIX."docsig_document as d";
        
        if ($id > 0) {
            $sql .= " WHERE d.rowid = ".(int) $id;
        } elseif (!empty($ref)) {
            $sql .= " WHERE d.ref = '".$this->db->escape($ref)."'";
        } elseif (!empty($download_token)) {
            $sql .= " WHERE d.download_token = '".$this->db->escape($download_token)."'";
        } else {
            return -1;
        }

        $resql = $this->db->query($sql);

        if ($resql) {
            if ($this->db->num_rows($resql)) {
                $obj = $this->db->fetch_object($resql);

                $this->id = $obj->rowid;
                $this->fk_envelope = $obj->fk_envelope;
                $this->ref = $obj->ref;
                $this->label = $obj->label;
                $this->original_filename = $obj->original_filename;
                $this->file_path = $obj->file_path;
                $this->file_hash = $obj->file_hash;
                $this->file_size = $obj->file_size;
                $this->signed_file_path = $obj->signed_file_path;
                $this->signed_hash = $obj->signed_hash;
                $this->sign_order = $obj->sign_order;
                $this->status = $obj->status;
                $this->require_all_signers = $obj->require_all_signers;
                $this->specific_signers_json = $obj->specific_signers_json;
                $this->page_count = $obj->page_count;
                $this->mime_type = $obj->mime_type;
                $this->download_token = $obj->download_token;
                $this->token_expires = $this->db->jdate($obj->token_expires);
                $this->fk_user_creat = $obj->fk_user_creat;
                $this->date_creation = $this->db->jdate($obj->date_creation);
                $this->date_modification = $this->db->jdate($obj->date_modification);
                $this->signed_at = $this->db->jdate($obj->signed_at);

                $this->db->free($resql);
                return 1;
            } else {
                $this->db->free($resql);
                return 0;
            }
        } else {
            $this->error = $this->db->lasterror();
            return -1;
        }
    }

    /**
     * Update document in database
     *
     * @param User $user User updating
     * @param int $notrigger Disable triggers
     * @return int <0 if KO, >0 if OK
     */
    public function update($user, $notrigger = 0)
    {
        global $conf;

        $error = 0;
        $now = dol_now();

        $this->db->begin();

        $sql = "UPDATE ".MAIN_DB_PREFIX."docsig_document SET";
        $sql .= " ref = '".$this->db->escape($this->ref)."',";
        $sql .= " label = ".($this->label ? "'".$this->db->escape($this->label)."'" : "NULL").",";
        $sql .= " file_path = '".$this->db->escape($this->file_path)."',";
        $sql .= " file_hash = ".($this->file_hash ? "'".$this->db->escape($this->file_hash)."'" : "NULL").",";
        $sql .= " file_size = ".($this->file_size ? (int) $this->file_size : "NULL").",";
        $sql .= " signed_file_path = ".($this->signed_file_path ? "'".$this->db->escape($this->signed_file_path)."'" : "NULL").",";
        $sql .= " signed_hash = ".($this->signed_hash ? "'".$this->db->escape($this->signed_hash)."'" : "NULL").",";
        $sql .= " sign_order = ".(int) $this->sign_order.",";
        $sql .= " status = ".(int) $this->status.",";
        $sql .= " require_all_signers = ".(int) $this->require_all_signers.",";
        $sql .= " specific_signers_json = ".($this->specific_signers_json ? "'".$this->db->escape($this->specific_signers_json)."'" : "NULL").",";
        $sql .= " page_count = ".($this->page_count ? (int) $this->page_count : "NULL").",";
        $sql .= " download_token = ".($this->download_token ? "'".$this->db->escape($this->download_token)."'" : "NULL").",";
        $sql .= " token_expires = ".($this->token_expires ? "'".$this->db->idate($this->token_expires)."'" : "NULL").",";
        $sql .= " signed_at = ".($this->signed_at ? "'".$this->db->idate($this->signed_at)."'" : "NULL").",";
        $sql .= " date_modification = '".$this->db->idate($now)."'";
        $sql .= " WHERE rowid = ".(int) $this->id;

        dol_syslog(get_class($this)."::update", LOG_DEBUG);
        $resql = $this->db->query($sql);

        if ($resql) {
            $this->date_modification = $now;
            $this->db->commit();
            return 1;
        } else {
            $this->error = $this->db->lasterror();
            $this->db->rollback();
            return -1;
        }
    }

    /**
     * Delete document from database
     *
     * @param User $user User deleting
     * @param int $notrigger Disable triggers
     * @return int <0 if KO, >0 if OK
     */
    public function delete($user, $notrigger = 0)
    {
        dol_syslog('DocSigDocument::delete id='.$this->id, LOG_DEBUG);

        $error = 0;

        $this->db->begin();

        // Delete signed file if exists
        if (!empty($this->signed_file_path) && file_exists($this->signed_file_path)) {
            @unlink($this->signed_file_path);
        }

        $sql = "DELETE FROM ".MAIN_DB_PREFIX."docsig_document";
        $sql .= " WHERE rowid = ".(int) $this->id;

        $resql = $this->db->query($sql);

        if ($resql) {
            $this->db->commit();
            return 1;
        } else {
            $this->error = $this->db->lasterror();
            $this->db->rollback();
            return -1;
        }
    }

    /**
     * Get documents by envelope ID
     *
     * @param int $fk_envelope Envelope ID
     * @param string $sortfield Sort field
     * @param string $sortorder Sort order (ASC/DESC)
     * @return DocSigDocument[]|int Array of documents or <0 if error
     */
    public function fetchByEnvelope($fk_envelope, $sortfield = 'sign_order', $sortorder = 'ASC')
    {
        $documents = array();

        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."docsig_document";
        $sql .= " WHERE fk_envelope = ".(int) $fk_envelope;
        $sql .= " ORDER BY ".$this->db->escape($sortfield)." ".$this->db->escape($sortorder);

        $resql = $this->db->query($sql);

        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $document = new DocSigDocument($this->db);
                $document->fetch($obj->rowid);
                $documents[] = $document;
            }
            $this->db->free($resql);
            return $documents;
        } else {
            $this->error = $this->db->lasterror();
            return -1;
        }
    }

    /**
     * Check if a file already belongs to an active envelope
     *
     * @param string $file_path File path to check
     * @param int $exclude_envelope_id Envelope ID to exclude from check
     * @return int 0=not in envelope, >0=envelope ID, <0=error
     */
    public function fileExistsInEnvelope($file_path, $exclude_envelope_id = 0)
    {
        $sql = "SELECT d.fk_envelope FROM ".MAIN_DB_PREFIX."docsig_document as d";
        $sql .= " JOIN ".MAIN_DB_PREFIX."docsig_envelope as e ON e.rowid = d.fk_envelope";
        $sql .= " WHERE d.file_path = '".$this->db->escape($file_path)."'";
        $sql .= " AND e.status NOT IN (4, 5)"; // Not cancelled or expired
        if ($exclude_envelope_id > 0) {
            $sql .= " AND d.fk_envelope != ".(int) $exclude_envelope_id;
        }

        $resql = $this->db->query($sql);

        if ($resql) {
            if ($obj = $this->db->fetch_object($resql)) {
                return $obj->fk_envelope;
            }
            return 0;
        }
        
        $this->error = $this->db->lasterror();
        return -1;
    }

    /**
     * Generate next reference number
     *
     * @return string Next reference
     */
    public function getNextNumRef()
    {
        global $conf;

        // Format: DOC-YYYYMMDD-XXXX
        $prefix = 'DOC-'.date('Ymd').'-';
        
        $sql = "SELECT MAX(CAST(SUBSTRING(ref, ".(strlen($prefix)+1).") AS UNSIGNED)) as maxnum";
        $sql .= " FROM ".MAIN_DB_PREFIX."docsig_document";
        $sql .= " WHERE ref LIKE '".$this->db->escape($prefix)."%'";

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            $num = ($obj->maxnum ? $obj->maxnum + 1 : 1);
            return $prefix.str_pad($num, 4, '0', STR_PAD_LEFT);
        }

        return $prefix.'0001';
    }

    /**
     * Generate download URL for this document
     *
     * @param bool $signed Get signed document URL
     * @return string Download URL
     */
    public function getDownloadUrl($signed = false)
    {
        global $conf;

        if (empty($this->download_token)) {
            return '';
        }

        $baseUrl = DOL_URL_ROOT.'/custom/signDol/public/download.php';
        $params = array(
            'token' => $this->download_token,
            'doc' => $this->id
        );
        
        if ($signed) {
            $params['signed'] = 1;
        }

        return $baseUrl.'?'.http_build_query($params);
    }

    /**
     * Regenerate download token
     *
     * @param int $expiration_hours Hours until token expires (0 = no expiration)
     * @return int <0 if KO, >0 if OK
     */
    public function regenerateToken($expiration_hours = 168)
    {
        $this->download_token = bin2hex(random_bytes(32));
        
        if ($expiration_hours > 0) {
            $this->token_expires = dol_now() + ($expiration_hours * 3600);
        } else {
            $this->token_expires = null;
        }

        return $this->update(new User($this->db));
    }

    /**
     * Check if download token is valid
     *
     * @return bool True if valid
     */
    public function isTokenValid()
    {
        if (empty($this->download_token)) {
            return false;
        }

        if (!empty($this->token_expires) && $this->token_expires < dol_now()) {
            return false;
        }

        return true;
    }

    /**
     * Mark document as signed (completed)
     *
     * @return int <0 if KO, >0 if OK
     */
    public function markAsSigned()
    {
        $this->status = self::STATUS_COMPLETED;
        $this->signed_at = dol_now();

        // Calculate signed file hash if exists
        if (!empty($this->signed_file_path) && file_exists($this->signed_file_path)) {
            $this->signed_hash = hash_file('sha256', $this->signed_file_path);
        }

        return $this->update(new User($this->db));
    }

    /**
     * Get specific signers for this document
     *
     * @return array Array of signer IDs or empty array for all signers
     */
    public function getSpecificSigners()
    {
        if (empty($this->specific_signers_json)) {
            return array();
        }

        $signers = json_decode($this->specific_signers_json, true);
        return is_array($signers) ? $signers : array();
    }

    /**
     * Set specific signers for this document
     *
     * @param array $signer_ids Array of signer IDs
     * @return void
     */
    public function setSpecificSigners($signer_ids)
    {
        if (empty($signer_ids)) {
            $this->specific_signers_json = null;
            $this->require_all_signers = 1;
        } else {
            $this->specific_signers_json = json_encode(array_values($signer_ids));
            $this->require_all_signers = 0;
        }
    }

    /**
     * Get label status
     *
     * @param int $mode 0=long label, 1=short label, 2=Picto + short label
     * @return string Status label
     */
    public function getLibStatut($mode = 0)
    {
        return $this->LibStatut($this->status, $mode);
    }

    /**
     * Return label of status
     *
     * @param int $status Status value
     * @param int $mode Mode
     * @return string Label
     */
    public function LibStatut($status, $mode = 0)
    {
        global $langs;
        $langs->load('docsig@signDol');

        $statusLabel = array(
            self::STATUS_PENDING => $langs->trans('DocSigDocumentStatusPending'),
            self::STATUS_PARTIAL => $langs->trans('DocSigDocumentStatusPartial'),
            self::STATUS_COMPLETED => $langs->trans('DocSigDocumentStatusCompleted'),
        );

        $statusClass = array(
            self::STATUS_PENDING => 'status0',
            self::STATUS_PARTIAL => 'status1',
            self::STATUS_COMPLETED => 'status4',
        );

        $statusLabel = isset($statusLabel[$status]) ? $statusLabel[$status] : 'Unknown';
        $statusClass = isset($statusClass[$status]) ? $statusClass[$status] : 'status0';

        if ($mode == 0) {
            return $statusLabel;
        } elseif ($mode == 1) {
            return $statusLabel;
        } else {
            return dolGetStatus($statusLabel, '', '', $statusClass, $mode);
        }
    }
}
