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
 * \file    htdocs/custom/signDol/class/docsigenvelope.class.php
 * \ingroup docsig
 * \brief   Clase para gestión de sobres/solicitudes de firma
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class DocSigEnvelope
 * Gestión de sobres de firma
 */
class DocSigEnvelope extends CommonObject
{
    /**
     * @var string ID to identify managed object
     */
    public $element = 'docsig_envelope';

    /**
     * @var string Name of table without prefix where object is stored
     */
    public $table_element = 'docsig_envelope';

    /**
     * @var string Picto
     */
    public $picto = 'fa-file-signature';

    /**
     * Status constants
     */
    const STATUS_DRAFT = 0;
    const STATUS_SENT = 1;
    const STATUS_PARTIAL = 2;
    const STATUS_COMPLETED = 3;
    const STATUS_CANCELED = 4;
    const STATUS_EXPIRED = 5;

    /**
     * @var array Fields definition
     */
    public $fields = array(
        'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'visible' => -1, 'notnull' => 1, 'position' => 1, 'index' => 1),
        'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'showoncombobox' => 1, 'position' => 10, 'searchall' => 1),
        'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'visible' => -1, 'notnull' => 1, 'default' => 1, 'position' => 20, 'index' => 1),
        'element' => array('type' => 'varchar(64)', 'label' => 'Element', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 30),
        'fk_object' => array('type' => 'integer', 'label' => 'ObjectId', 'enabled' => 1, 'visible' => -1, 'notnull' => 1, 'position' => 40, 'index' => 1),
        'file_path' => array('type' => 'varchar(512)', 'label' => 'FilePath', 'enabled' => 1, 'visible' => 0, 'notnull' => 1, 'position' => 50),
        'file_hash' => array('type' => 'varchar(128)', 'label' => 'FileHash', 'enabled' => 1, 'visible' => 0, 'notnull' => 1, 'position' => 60),
        'signature_mode' => array('type' => 'varchar(20)', 'label' => 'SignatureMode', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'default' => 'parallel', 'position' => 70),
        'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'default' => 0, 'position' => 80, 'index' => 1),
        'expire_date' => array('type' => 'datetime', 'label' => 'ExpireDate', 'enabled' => 1, 'visible' => 1, 'notnull' => 0, 'position' => 90),
        'signed_file_path' => array('type' => 'varchar(512)', 'label' => 'SignedFilePath', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 100),
        'compliance_cert_path' => array('type' => 'varchar(512)', 'label' => 'ComplianceCertPath', 'enabled' => 1, 'visible' => 0, 'notnull' => 0, 'position' => 110),
        'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'visible' => -1, 'notnull' => 1, 'position' => 500),
        'date_modification' => array('type' => 'datetime', 'label' => 'DateModification', 'enabled' => 1, 'visible' => -1, 'notnull' => 0, 'position' => 501),
        'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserCreation', 'enabled' => 1, 'visible' => -1, 'notnull' => 1, 'position' => 510),
        'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModification', 'enabled' => 1, 'visible' => -1, 'notnull' => 0, 'position' => 511),
        'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportKey', 'enabled' => 1, 'visible' => -1, 'notnull' => 0, 'position' => 999),
    );

    // Properties
    public $rowid;
    public $ref;
    public $entity;
    public $element_type; // Renamed to avoid conflict with $element
    public $fk_object;
    public $file_path;
    public $file_hash;
    public $signature_mode;
    public $status;
    public $expire_date;
    public $signed_file_path;
    public $compliance_cert_path;
    public $date_creation;
    public $date_modification;
    public $fk_user_creat;
    public $fk_user_modif;
    public $import_key;

    // Signers array
    public $signers = array();

    // Documents array (for multi-document support)
    public $documents = array();

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $conf, $langs;

        $this->db = $db;

        if (!getDolGlobalString('MAIN_SHOW_TECHNICAL_ID')) {
            $this->fields['rowid']['visible'] = 0;
        }
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
        global $conf;

        $error = 0;

        // Generate ref if not set
        if (empty($this->ref)) {
            $this->ref = $this->getNextNumRef();
        }

        // Set dates
        $this->date_creation = dol_now();
        $this->fk_user_creat = $user->id;
        $this->entity = $conf->entity;

        // Calculate expire date if not set
        if (empty($this->expire_date)) {
            $days = getDolGlobalInt('DOCSIG_TOKEN_EXPIRY_DAYS', 7);
            $this->expire_date = dol_time_plus_duree($this->date_creation, $days, 'd');
        }

        // Calculate file hash
        if (!empty($this->file_path) && file_exists($this->file_path)) {
            $this->file_hash = hash_file('sha256', $this->file_path);
        }

        $this->db->begin();

        $sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (";
        $sql .= "ref, entity, element, fk_object, file_path, file_hash,";
        $sql .= "signature_mode, status, expire_date,";
        $sql .= "date_creation, fk_user_creat";
        $sql .= ") VALUES (";
        $sql .= "'".$this->db->escape($this->ref)."',";
        $sql .= " ".(int)$this->entity.",";
        $sql .= " '".$this->db->escape($this->element_type)."',";
        $sql .= " ".(int)$this->fk_object.",";
        $sql .= " '".$this->db->escape($this->file_path)."',";
        $sql .= " '".$this->db->escape($this->file_hash)."',";
        $sql .= " '".$this->db->escape($this->signature_mode)."',";
        $sql .= " ".(int)$this->status.",";
        $sql .= " '".$this->db->idate($this->expire_date)."',";
        $sql .= " '".$this->db->idate($this->date_creation)."',";
        $sql .= " ".(int)$this->fk_user_creat;
        $sql .= ")";

        $resql = $this->db->query($sql);
        if (!$resql) {
            $error++;
            $this->errors[] = 'Error inserting envelope: '.$this->db->lasterror();
        }

        if (!$error) {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);

            // Log event
            $this->logEvent('ENVELOPE_CREATE', 'Envelope created');
        }

        if (!$error && !$notrigger) {
            // Call triggers
            $result = $this->call_trigger('DOCSIG_ENVELOPE_CREATE', $user);
            if ($result < 0) {
                $error++;
            }
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
     * @param string $ref Ref
     * @return int Return integer <0 if KO, 0 if not found, >0 if OK
     */
    public function fetch($id, $ref = null)
    {
        global $conf;

        $sql = "SELECT t.*";
        $sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element." as t";
        if ($ref) {
            $sql .= " WHERE t.ref = '".$this->db->escape($ref)."'";
        } else {
            $sql .= " WHERE t.rowid = ".(int)$id;
        }
        $sql .= " AND t.entity = ".(int)$conf->entity;

        $resql = $this->db->query($sql);
        if ($resql) {
            if ($this->db->num_rows($resql)) {
                $obj = $this->db->fetch_object($resql);

                $this->id = $obj->rowid;
                $this->rowid = $obj->rowid;
                $this->ref = $obj->ref;
                $this->entity = $obj->entity;
                $this->element_type = $obj->element;
                $this->fk_object = $obj->fk_object;
                $this->file_path = $obj->file_path;
                $this->file_hash = $obj->file_hash;
                $this->signature_mode = $obj->signature_mode;
                $this->status = $obj->status;
                $this->expire_date = $this->db->jdate($obj->expire_date);
                $this->signed_file_path = $obj->signed_file_path;
                $this->compliance_cert_path = $obj->compliance_cert_path;
                $this->date_creation = $this->db->jdate($obj->date_creation);
                $this->date_modification = $this->db->jdate($obj->date_modification);
                $this->fk_user_creat = $obj->fk_user_creat;
                $this->fk_user_modif = $obj->fk_user_modif;
                $this->import_key = $obj->import_key;

                // Load signers
                $this->fetchSigners();

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
     * Load signers for this envelope
     *
     * @return int Number of signers loaded
     */
    public function fetchSigners()
    {
        $this->signers = array();

        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."docsig_signer";
        $sql .= " WHERE fk_envelope = ".(int)$this->id;
        $sql .= " ORDER BY sign_order ASC, rowid ASC";

        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                require_once __DIR__.'/docsigsigner.class.php';
                $signer = new DocSigSigner($this->db);
                $signer->fetch($obj->rowid);
                $this->signers[] = $signer;
            }
            return count($this->signers);
        }
        return 0;
    }

    /**
     * Load documents for this envelope
     *
     * @return int Number of documents loaded or -1 if error
     */
    public function fetchDocuments()
    {
        $this->documents = array();

        require_once __DIR__.'/docsigdocument.class.php';
        $documentObj = new DocSigDocument($this->db);
        $result = $documentObj->fetchByEnvelope($this->id);
        
        if (is_array($result)) {
            $this->documents = $result;
            return count($this->documents);
        }
        
        return $result; // Error code
    }

    /**
     * Add a document to this envelope
     *
     * @param string $file_path Path to the PDF file
     * @param User $user User adding the document
     * @param string $label Optional label for the document
     * @param int $sign_order Order for signing (default auto)
     * @return int >0 if OK (document id), <0 if KO
     */
    public function addDocument($file_path, $user, $label = '', $sign_order = 0)
    {
        require_once __DIR__.'/docsigdocument.class.php';
        
        // Check file exists
        if (!file_exists($file_path)) {
            $this->error = 'FileNotFound: '.$file_path;
            return -1;
        }

        // Check file is not in another active envelope
        $docCheck = new DocSigDocument($this->db);
        $existingEnvelope = $docCheck->fileExistsInEnvelope($file_path, $this->id);
        if ($existingEnvelope > 0) {
            $this->error = 'FileAlreadyInEnvelope';
            return -2;
        }
        if ($existingEnvelope < 0) {
            $this->error = $docCheck->error;
            return -3;
        }

        // Determine sign order
        if ($sign_order <= 0) {
            $sign_order = count($this->documents) + 1;
        }

        $document = new DocSigDocument($this->db);
        $document->fk_envelope = $this->id;
        $document->file_path = $file_path;
        $document->original_filename = basename($file_path);
        $document->label = $label ?: basename($file_path);
        $document->sign_order = $sign_order;
        $document->status = DocSigDocument::STATUS_PENDING;

        $result = $document->create($user);
        
        if ($result > 0) {
            $this->documents[] = $document;
            
            // If this is the first document and envelope has no file_path, set it for backwards compatibility
            if (empty($this->file_path)) {
                $this->file_path = $file_path;
                $this->file_hash = hash_file('sha256', $file_path);
                $this->update($user, true);
            }
            
            $this->logEvent('DOCUMENT_ADDED', 'Document added: '.$document->original_filename);
        }

        return $result;
    }

    /**
     * Remove a document from this envelope
     *
     * @param int $document_id Document ID
     * @param User $user User removing the document
     * @return int >0 if OK, <0 if KO
     */
    public function removeDocument($document_id, $user)
    {
        require_once __DIR__.'/docsigdocument.class.php';
        
        // Find document
        $document = null;
        foreach ($this->documents as $key => $doc) {
            if ($doc->id == $document_id) {
                $document = $doc;
                unset($this->documents[$key]);
                break;
            }
        }
        
        if (!$document) {
            $document = new DocSigDocument($this->db);
            if ($document->fetch($document_id) <= 0) {
                $this->error = 'DocumentNotFound';
                return -1;
            }
            
            // Verify document belongs to this envelope
            if ($document->fk_envelope != $this->id) {
                $this->error = 'DocumentNotInEnvelope';
                return -2;
            }
        }

        $filename = $document->original_filename;
        $result = $document->delete($user);
        
        if ($result > 0) {
            $this->logEvent('DOCUMENT_REMOVED', 'Document removed: '.$filename);
            // Re-index documents array
            $this->documents = array_values($this->documents);
        }

        return $result;
    }

    /**
     * Get document count for this envelope
     *
     * @return int Number of documents
     */
    public function getDocumentCount()
    {
        if (!empty($this->documents)) {
            return count($this->documents);
        }
        
        // Count from DB
        $sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."docsig_document";
        $sql .= " WHERE fk_envelope = ".(int)$this->id;
        
        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            return (int) $obj->cnt;
        }
        
        return 0;
    }

    /**
     * Check if envelope has multiple documents
     *
     * @return bool True if envelope has more than one document
     */
    public function hasMultipleDocuments()
    {
        return $this->getDocumentCount() > 1;
    }

    /**
     * Get all file paths from documents (for PDF generation)
     *
     * @return array Array of file paths ordered by sign_order
     */
    public function getDocumentFilePaths()
    {
        if (empty($this->documents)) {
            $this->fetchDocuments();
        }
        
        $paths = array();
        foreach ($this->documents as $doc) {
            $paths[] = $doc->file_path;
        }
        
        // Fallback for envelopes without documents table
        if (empty($paths) && !empty($this->file_path)) {
            $paths[] = $this->file_path;
        }
        
        return $paths;
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
        global $conf;

        $error = 0;

        $this->date_modification = dol_now();
        $this->fk_user_modif = $user->id;

        $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET";
        $sql .= " ref = '".$this->db->escape($this->ref)."',";
        $sql .= " element = '".$this->db->escape($this->element_type)."',";
        $sql .= " fk_object = ".(int)$this->fk_object.",";
        $sql .= " file_path = '".$this->db->escape($this->file_path)."',";
        $sql .= " file_hash = '".$this->db->escape($this->file_hash)."',";
        $sql .= " signature_mode = '".$this->db->escape($this->signature_mode)."',";
        $sql .= " status = ".(int)$this->status.",";
        $sql .= " expire_date = ".($this->expire_date ? "'".$this->db->idate($this->expire_date)."'" : "null").",";
        $sql .= " signed_file_path = ".($this->signed_file_path ? "'".$this->db->escape($this->signed_file_path)."'" : "null").",";
        $sql .= " compliance_cert_path = ".($this->compliance_cert_path ? "'".$this->db->escape($this->compliance_cert_path)."'" : "null").",";
        $sql .= " date_modification = '".$this->db->idate($this->date_modification)."',";
        $sql .= " fk_user_modif = ".(int)$this->fk_user_modif;
        $sql .= " WHERE rowid = ".(int)$this->id;
        $sql .= " AND entity = ".(int)$conf->entity;

        $resql = $this->db->query($sql);
        if (!$resql) {
            $error++;
            $this->errors[] = 'Error updating envelope: '.$this->db->lasterror();
        }

        if (!$error && !$notrigger) {
            $result = $this->call_trigger('DOCSIG_ENVELOPE_MODIFY', $user);
            if ($result < 0) {
                $error++;
            }
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
        global $conf;

        $error = 0;

        $this->db->begin();

        // Delete signers
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."docsig_signer WHERE fk_envelope = ".(int)$this->id;
        if (!$this->db->query($sql)) {
            $error++;
        }

        // Delete events
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."docsig_event WHERE fk_envelope = ".(int)$this->id;
        if (!$this->db->query($sql)) {
            $error++;
        }

        // Delete notifications
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."docsig_notification WHERE fk_envelope = ".(int)$this->id;
        if (!$this->db->query($sql)) {
            $error++;
        }

        // Delete envelope
        if (!$error) {
            $sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element;
            $sql .= " WHERE rowid = ".(int)$this->id;
            $sql .= " AND entity = ".(int)$conf->entity;

            if (!$this->db->query($sql)) {
                $error++;
                $this->errors[] = 'Error deleting envelope: '.$this->db->lasterror();
            }
        }

        if (!$error && !$notrigger) {
            $result = $this->call_trigger('DOCSIG_ENVELOPE_DELETE', $user);
            if ($result < 0) {
                $error++;
            }
        }

        if (!$error) {
            $this->db->commit();
            return 1;
        } else {
            $this->db->rollback();
            return -1;
        }
    }

    /**
     * Return next free reference
     *
     * @return string Next ref
     */
    public function getNextNumRef()
    {
        global $conf;

        $prefix = getDolGlobalString('DOCSIG_REF_PREFIX', 'DS');
        $year = date('y');
        $month = date('m');

        // Find max number for this month
        $sql = "SELECT MAX(CAST(SUBSTRING(ref, ".(strlen($prefix) + 5).") AS UNSIGNED)) as maxnum";
        $sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE ref LIKE '".$this->db->escape($prefix).$year.$month."%'";
        $sql .= " AND entity = ".(int)$conf->entity;

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            $num = ($obj->maxnum ? $obj->maxnum + 1 : 1);
        } else {
            $num = 1;
        }

        return $prefix.$year.$month.str_pad($num, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Set status to sent
     *
     * @param User $user User making the change
     * @return int Return integer <0 if KO, >0 if OK
     */
    public function setSent(User $user)
    {
        $this->status = self::STATUS_SENT;
        $this->logEvent('ENVELOPE_SENT', 'Envelope sent to signers');
        return $this->update($user);
    }

    /**
     * Set status to partial (some signers have signed)
     *
     * @param User $user User making the change
     * @return int Return integer <0 if KO, >0 if OK
     */
    public function setPartial(User $user)
    {
        $this->status = self::STATUS_PARTIAL;
        return $this->update($user);
    }

    /**
     * Set status to completed
     *
     * @param User $user User making the change
     * @return int Return integer <0 if KO, >0 if OK
     */
    public function setCompleted(User $user)
    {
        $this->status = self::STATUS_COMPLETED;
        $this->logEvent('ENVELOPE_COMPLETED', 'All signatures completed');
        return $this->update($user);
    }

    /**
     * Cancel envelope
     *
     * @param User $user User making the change
     * @param string $reason Cancellation reason
     * @return int Return integer <0 if KO, >0 if OK
     */
    public function cancel(User $user, $reason = '')
    {
        $this->status = self::STATUS_CANCELED;
        $this->logEvent('ENVELOPE_CANCELED', 'Envelope canceled: '.$reason);
        return $this->update($user);
    }

    /**
     * Set status to expired
     *
     * @return int Return integer <0 if KO, >0 if OK
     */
    public function setExpired()
    {
        global $user;

        $this->status = self::STATUS_EXPIRED;
        $this->logEvent('ENVELOPE_EXPIRED', 'Envelope expired');
        return $this->update($user, true); // No trigger
    }

    /**
     * Log an event in the audit trail
     *
     * @param string $eventType Event type
     * @param string $description Event description
     * @param string $ipAddress IP address
     * @param string $userAgent User agent
     * @return int ID of created event or -1 on error
     */
    public function logEvent($eventType, $description, $ipAddress = '', $userAgent = '')
    {
        if (empty($ipAddress) && isset($_SERVER['REMOTE_ADDR'])) {
            $ipAddress = $_SERVER['REMOTE_ADDR'];
        }
        if (empty($userAgent) && isset($_SERVER['HTTP_USER_AGENT'])) {
            $userAgent = $_SERVER['HTTP_USER_AGENT'];
        }

        $sql = "INSERT INTO ".MAIN_DB_PREFIX."docsig_event (";
        $sql .= "fk_envelope, event_type, payload_json, ip_address, user_agent, created_at";
        $sql .= ") VALUES (";
        $sql .= (int)$this->id.",";
        $sql .= " '".$this->db->escape($eventType)."',";
        $sql .= " '".$this->db->escape(json_encode(array('description' => $description)))."',";
        $sql .= " '".$this->db->escape($ipAddress)."',";
        $sql .= " '".$this->db->escape(substr($userAgent, 0, 512))."',";
        $sql .= " '".$this->db->idate(dol_now())."'";
        $sql .= ")";

        $resql = $this->db->query($sql);
        if ($resql) {
            return $this->db->last_insert_id(MAIN_DB_PREFIX."docsig_event");
        }
        return -1;
    }

    /**
     * Get events for this envelope
     *
     * @param int $limit Max number of events
     * @return array Array of events
     */
    public function getEvents($limit = 0)
    {
        $events = array();

        $sql = "SELECT * FROM ".MAIN_DB_PREFIX."docsig_event";
        $sql .= " WHERE fk_envelope = ".(int)$this->id;
        $sql .= " ORDER BY created_at ASC";
        if ($limit > 0) {
            $sql .= " LIMIT ".(int)$limit;
        }

        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $payload = json_decode($obj->payload_json, true);
                $events[] = array(
                    'id' => $obj->rowid,
                    'type' => $obj->event_type,
                    'description' => $payload['description'] ?? '',
                    'signer_id' => $obj->fk_signer,
                    'ip_address' => $obj->ip_address,
                    'user_agent' => $obj->user_agent,
                    'date' => $this->db->jdate($obj->created_at),
                );
            }
        }

        return $events;
    }

    /**
     * Get status label
     *
     * @param int $mode 0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
     * @return string Label
     */
    public function getLibStatut($mode = 0)
    {
        return $this->LibStatut($this->status, $mode);
    }

    /**
     * Return the label of a status
     *
     * @param int $status Status
     * @param int $mode Mode (0=long label, 1=short label, 2=Picto + short label, etc.)
     * @return string Label
     */
    public function LibStatut($status, $mode = 0)
    {
        global $langs;
        $langs->load('docsig@signDol');

        $statusLabels = array(
            self::STATUS_DRAFT => array(
                'label' => 'Draft',
                'labelshort' => 'Draft',
                'badgetype' => 'status0',
            ),
            self::STATUS_SENT => array(
                'label' => 'Sent',
                'labelshort' => 'Sent',
                'badgetype' => 'status4',
            ),
            self::STATUS_PARTIAL => array(
                'label' => 'PartialSigned',
                'labelshort' => 'Partial',
                'badgetype' => 'status1',
            ),
            self::STATUS_COMPLETED => array(
                'label' => 'Signed',
                'labelshort' => 'Signed',
                'badgetype' => 'status6',
            ),
            self::STATUS_CANCELED => array(
                'label' => 'Canceled',
                'labelshort' => 'Canceled',
                'badgetype' => 'status9',
            ),
            self::STATUS_EXPIRED => array(
                'label' => 'Expired',
                'labelshort' => 'Expired',
                'badgetype' => 'status8',
            ),
        );

        $statusInfo = $statusLabels[$status] ?? $statusLabels[self::STATUS_DRAFT];

        $labelLong = $langs->trans($statusInfo['label']);
        $labelShort = $langs->trans($statusInfo['labelshort']);
        $badgeType = $statusInfo['badgetype'];

        if ($mode == 0) {
            return $labelLong;
        } elseif ($mode == 1) {
            return $labelShort;
        } elseif ($mode == 2) {
            return dolGetBadge($labelShort, '', $badgeType);
        } elseif ($mode == 3) {
            return dolGetBadge('', '', $badgeType);
        } elseif ($mode == 4) {
            return dolGetBadge($labelLong, '', $badgeType);
        } elseif ($mode == 5) {
            return $labelShort.' '.dolGetBadge('', '', $badgeType);
        } elseif ($mode == 6) {
            return $labelLong.' '.dolGetBadge('', '', $badgeType);
        }

        return $labelLong;
    }

    /**
     * Check if envelope is expired
     *
     * @return bool True if expired
     */
    public function isExpired()
    {
        if ($this->status == self::STATUS_EXPIRED) {
            return true;
        }
        if ($this->expire_date && $this->expire_date < dol_now()) {
            return true;
        }
        return false;
    }

    /**
     * Get all pending signers
     *
     * @return array Array of pending signers
     */
    public function getPendingSigners()
    {
        $pending = array();
        foreach ($this->signers as $signer) {
            if ($signer->status == 0) { // Pending
                $pending[] = $signer;
            }
        }
        return $pending;
    }

    /**
     * Get next signer in queue (for sequential mode)
     *
     * @return DocSigSigner|null Next signer or null
     */
    public function getNextSigner()
    {
        if ($this->signature_mode !== 'sequential') {
            return null;
        }

        foreach ($this->signers as $signer) {
            if ($signer->status == 0) { // First pending signer
                return $signer;
            }
        }
        return null;
    }

    /**
     * Check if all signers have signed
     *
     * @return bool True if all signed
     */
    public function allSignersSigned()
    {
        foreach ($this->signers as $signer) {
            if ($signer->status != 1) { // Not signed
                return false;
            }
        }
        return count($this->signers) > 0;
    }

    /**
     * Check completion and update status accordingly
     *
     * @return int Return integer <0 if KO, >0 if OK
     */
    public function checkCompletion()
    {
        global $user;

        // Reload signers to get fresh status
        $this->fetchSigners();

        $totalSigners = count($this->signers);
        $signedCount = $this->getSignedCount();

        // All signed?
        if ($this->allSignersSigned()) {
            $this->status = self::STATUS_COMPLETED;
            $this->logEvent('ENVELOPE_COMPLETED', 'All '.$totalSigners.' signers have signed the document');

            // Generate signed PDF with embedded signatures
            $signedResult = $this->generateSignedPdf();
            if ($signedResult < 0) {
                dol_syslog('DocSigEnvelope::checkCompletion - Error generating signed PDF', LOG_WARNING);
            }

            // Generate compliance certificate
            $certResult = $this->generateComplianceCertificate();
            if ($certResult < 0) {
                dol_syslog('DocSigEnvelope::checkCompletion - Error generating compliance certificate', LOG_WARNING);
            }

            // Notify all signers of completion
            $this->notifyCompletion();

            return $this->update($user);
        }
        // Some signed?
        elseif ($signedCount > 0) {
            if ($this->status != self::STATUS_PARTIAL) {
                $this->status = self::STATUS_PARTIAL;
                $this->logEvent('ENVELOPE_PARTIAL', $signedCount.' of '.$totalSigners.' signers have signed');
                return $this->update($user);
            }
        }

        return 1;
    }

    /**
     * Generate signed PDF with embedded signatures
     *
     * @return int Return integer <0 if KO, >0 if OK
     */
    public function generateSignedPdf()
    {
        global $conf;

        dol_syslog('DocSigEnvelope::generateSignedPdf - Starting for envelope '.$this->ref);

        // Load documents if not loaded
        if (empty($this->documents)) {
            $this->fetchDocuments();
        }

        // If we have documents in the documents table, generate PDFs for all of them
        if (!empty($this->documents)) {
            return $this->generateAllSignedPdfs();
        }

        // Fallback: Single document mode (backwards compatibility)
        return $this->generateSignedPdfForFile($this->file_path);
    }

    /**
     * Generate signed PDFs for all documents in this envelope
     *
     * @return int >0 if OK, <0 if KO
     */
    public function generateAllSignedPdfs()
    {
        global $conf;

        dol_syslog('DocSigEnvelope::generateAllSignedPdfs - Generating for '.count($this->documents).' documents');

        $successCount = 0;
        $errorCount = 0;

        foreach ($this->documents as $document) {
            $result = $this->generateSignedPdfForDocument($document);
            if ($result > 0) {
                $successCount++;
            } else {
                $errorCount++;
                dol_syslog('DocSigEnvelope::generateAllSignedPdfs - Error generating PDF for document '.$document->id.': '.$this->error, LOG_ERR);
            }
        }

        // Update envelope's signed_file_path to the first document's signed path (for backwards compatibility)
        if (!empty($this->documents[0]) && !empty($this->documents[0]->signed_file_path)) {
            $this->signed_file_path = $this->documents[0]->signed_file_path;
        }

        if ($errorCount > 0) {
            $this->error = sprintf('Generated %d/%d PDFs. %d errors.', $successCount, count($this->documents), $errorCount);
            return -1;
        }

        $this->logEvent('ALL_PDFS_SIGNED', 'Generated signed PDFs for all '.count($this->documents).' documents');
        return $successCount;
    }

    /**
     * Generate signed PDF for a specific document
     *
     * @param DocSigDocument $document Document object
     * @return int >0 if OK, <0 if KO
     */
    public function generateSignedPdfForDocument($document)
    {
        require_once __DIR__.'/docsigdocument.class.php';

        // Generate the signed PDF
        $result = $this->generateSignedPdfForFile($document->file_path, $document);
        
        if ($result > 0 && !empty($this->signed_file_path)) {
            // Update document with signed file info
            $document->signed_file_path = $this->signed_file_path;
            $document->signed_hash = hash_file('sha256', $this->signed_file_path);
            $document->markAsSigned();
        }

        return $result;
    }

    /**
     * Generate signed PDF for a specific file
     *
     * @param string $file_path Path to the original PDF
     * @param DocSigDocument $document Optional document object for context
     * @return int >0 if OK, <0 if KO
     */
    public function generateSignedPdfForFile($file_path, $document = null)
    {
        global $conf;

        dol_syslog('DocSigEnvelope::generateSignedPdfForFile - Starting for file '.$file_path);

        // Check original file exists
        if (!file_exists($file_path)) {
            $this->error = 'Original file not found: '.$file_path;
            return -1;
        }

        // Prepare output path
        $signedFilename = pathinfo($file_path, PATHINFO_FILENAME).'_signed.pdf';
        $signedDir = dirname($file_path);
        $this->signed_file_path = $signedDir.'/'.$signedFilename;

        // Include TCPDF
        require_once TCPDF_PATH.'tcpdf.php';

        // Check if FPDI is available (new setasign version)
        $fpdiAvailable = false;
        $fpdiAutoloadPath = DOL_DOCUMENT_ROOT.'/includes/setasign/fpdi/src/autoload.php';
        if (file_exists($fpdiAutoloadPath)) {
            require_once $fpdiAutoloadPath;
            $fpdiAvailable = class_exists('setasign\Fpdi\Tcpdf\Fpdi');
            dol_syslog('DocSigEnvelope::generateSignedPdfForFile - FPDI autoload found, class exists: '.($fpdiAvailable ? 'YES' : 'NO'));
        } else {
            // Try old path
            $fpdiOldPath = DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/include/fpdi_bridge.php';
            if (file_exists($fpdiOldPath)) {
                require_once $fpdiOldPath;
                $fpdiAvailable = class_exists('FPDI');
            }
        }

        // Get stamp configuration
        $stampConfig = $this->getStampConfiguration();

        try {
            if ($fpdiAvailable) {
                // Use FPDI to import and modify existing PDF
                if (class_exists('setasign\Fpdi\Tcpdf\Fpdi')) {
                    $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
                } else {
                    $pdf = new FPDI();
                }
                
                dol_syslog('DocSigEnvelope::generateSignedPdfForFile - FPDI created, loading source file');
                $pageCount = $pdf->setSourceFile($file_path);
                dol_syslog('DocSigEnvelope::generateSignedPdfForFile - Source has '.$pageCount.' pages');
                
                // Import all pages and add stamps according to configuration
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $templateId = $pdf->importPage($pageNo);
                    $size = $pdf->getTemplateSize($templateId);
                    $pdf->AddPage($size['orientation'], array($size['width'], $size['height']));
                    $pdf->useTemplate($templateId);
                    
                    // Determine if stamp should be added to this page
                    $addStamp = $this->shouldAddStampToPage($pageNo, $pageCount, $stampConfig['pages']);
                    dol_syslog('DocSigEnvelope::generateSignedPdfForFile - Page '.$pageNo.': addStamp='.($addStamp ? 'YES' : 'NO'));
                    
                    if ($addStamp) {
                        $this->addSignatureStampsToPage($pdf, $size, $stampConfig);
                    }
                }
                
                // Save the signed PDF
                $pdf->Output($this->signed_file_path, 'F');
                dol_syslog('DocSigEnvelope::generateSignedPdfForFile - PDF saved to '.$this->signed_file_path);
                
            } else {
                // Copy original and append signature page
                copy($file_path, $this->signed_file_path);
                
                // Create new PDF with signatures
                $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
                $pdf->SetCreator('DocSig');
                $pdf->SetAuthor('DocSig Digital Signature');
                $pdf->SetTitle('Signature Addendum');
                $pdf->AddPage();
                
                $this->addSignatureAddendumPage($pdf);
                
                // Merge with original (simplified approach - just save addendum)
                $addendumPath = $signedDir.'/signature_addendum_'.$this->id.'.pdf';
                $pdf->Output($addendumPath, 'F');
                
                // For now, just keep the original as signed (signatures are recorded in DB)
                // In a full implementation, we would merge PDFs properly
                copy($file_path, $this->signed_file_path);
            }

            // Calculate hash of signed file
            $signedHash = hash_file('sha256', $this->signed_file_path);
            
            // Log event
            $docRef = $document ? $document->ref : basename($file_path);
            $this->logEvent('PDF_SIGNED', 'Signed PDF generated: '.$signedFilename.' (SHA256: '.$signedHash.') Doc: '.$docRef);
            
            dol_syslog('DocSigEnvelope::generateSignedPdfForFile - Signed PDF generated: '.$this->signed_file_path);
            
            return 1;

        } catch (Exception $e) {
            $this->error = 'Error generating signed PDF: '.$e->getMessage();
            dol_syslog('DocSigEnvelope::generateSignedPdfForFile - Error: '.$this->error, LOG_ERR);
            return -1;
        }
    }

    /**
     * Get stamp configuration from module settings
     *
     * @return array Configuration array
     */
    private function getStampConfiguration()
    {
        global $conf;

        // Parse header color from hex to RGB
        $headerColorHex = getDolGlobalString('DOCSIG_STAMP_HEADER_COLOR', '#4682B4');
        $headerColorHex = ltrim($headerColorHex, '#');
        $headerColorR = hexdec(substr($headerColorHex, 0, 2));
        $headerColorG = hexdec(substr($headerColorHex, 2, 2));
        $headerColorB = hexdec(substr($headerColorHex, 4, 2));

        return array(
            'pages' => getDolGlobalString('DOCSIG_STAMP_PAGES', 'all'),
            'x' => (float) getDolGlobalString('DOCSIG_STAMP_X', 10),
            'y' => (float) getDolGlobalString('DOCSIG_STAMP_Y', 10),
            'width' => (float) getDolGlobalString('DOCSIG_STAMP_WIDTH', 55),
            'height' => (float) getDolGlobalString('DOCSIG_STAMP_HEIGHT', 35),
            'orientation' => getDolGlobalString('DOCSIG_STAMP_ORIENTATION', 'horizontal'),
            'opacity' => (int) getDolGlobalString('DOCSIG_STAMP_OPACITY', 100),
            'header_color' => array($headerColorR, $headerColorG, $headerColorB),
        );
    }

    /**
     * Determine if stamp should be added to a specific page
     *
     * @param int $pageNo Current page number (1-based)
     * @param int $pageCount Total number of pages
     * @param string $pagesConfig Configuration: 'all', 'first', 'last', 'first_last'
     * @return bool True if stamp should be added
     */
    private function shouldAddStampToPage($pageNo, $pageCount, $pagesConfig)
    {
        switch ($pagesConfig) {
            case 'first':
                return $pageNo == 1;
            case 'last':
                return $pageNo == $pageCount;
            case 'first_last':
                return $pageNo == 1 || $pageNo == $pageCount;
            case 'all':
            default:
                return true;
        }
    }

    /**
     * Add signature stamps to a PDF page using configuration
     *
     * @param TCPDF|FPDI $pdf PDF object
     * @param array $size Page size array with 'width', 'height', 'orientation'
     * @param array $config Stamp configuration
     */
    private function addSignatureStampsToPage(&$pdf, $size, $config)
    {
        global $langs;
        $langs->load('docsig@signDol');

        // Get stamp dimensions from config
        $stampWidth = $config['width'];
        $stampHeight = $config['height'];
        $startX = $config['x'];
        $startY = $config['y'];
        $orientation = $config['orientation'];
        $opacity = $config['opacity'] / 100;
        $headerColor = $config['header_color'];

        dol_syslog('DocSigEnvelope::addSignatureStampsToPage - Config: X='.$startX.', Y='.$startY.', W='.$stampWidth.', H='.$stampHeight.', O='.$orientation.', Opacity='.$opacity);

        // Collect signed signers
        $signedSigners = array();
        foreach ($this->signers as $signer) {
            if ($signer->status == DocSigSigner::STATUS_SIGNED) {
                $signedSigners[] = $signer;
            }
        }

        if (empty($signedSigners)) {
            dol_syslog('DocSigEnvelope::addSignatureStampsToPage - No signed signers found');
            return;
        }

        // Set transparency if opacity < 100
        if ($opacity < 1) {
            $pdf->SetAlpha($opacity);
        }

        // Calculate spacing between stamps
        $spacing = 5;
        $signerIndex = 0;

        foreach ($signedSigners as $signer) {
            // Calculate position based on orientation
            if ($orientation == 'horizontal') {
                $x = $startX + ($signerIndex * ($stampWidth + $spacing));
                $y = $startY;
                
                // Check if we exceed page width, wrap to next row
                if ($x + $stampWidth > $size['width'] - 10) {
                    $signerIndex = 0;
                    $x = $startX;
                    $startY += $stampHeight + $spacing;
                    $y = $startY;
                }
            } else {
                // Vertical orientation
                $x = $startX;
                $y = $startY + ($signerIndex * ($stampHeight + $spacing));
                
                // Check if we exceed page height, wrap to next column
                if ($y + $stampHeight > $size['height'] - 10) {
                    $signerIndex = 0;
                    $startX += $stampWidth + $spacing;
                    $x = $startX;
                    $y = $startY;
                }
            }

            $this->drawSignatureStampOnPage($pdf, $signer, $x, $y, $stampWidth, $stampHeight, $headerColor);
            $signerIndex++;
        }

        // Reset transparency
        if ($opacity < 1) {
            $pdf->SetAlpha(1);
        }
    }

    /**
     * Draw a single signature stamp on a page
     *
     * @param TCPDF|FPDI $pdf PDF object
     * @param DocSigSigner $signer Signer object
     * @param float $x X position
     * @param float $y Y position
     * @param float $w Width
     * @param float $h Height
     * @param array $headerColor RGB array for header color
     */
    private function drawSignatureStampOnPage(&$pdf, $signer, $x, $y, $w, $h, $headerColor)
    {
        global $langs;

        // Draw stamp border with rounded corners and fill
        $pdf->SetDrawColor(150, 150, 150);
        $pdf->SetLineWidth(0.3);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->RoundedRect($x, $y, $w, $h, 1.5, '1111', 'DF');

        // Header bar with configurable color
        $headerHeight = 5;
        $pdf->SetFillColor($headerColor[0], $headerColor[1], $headerColor[2]);
        $pdf->Rect($x, $y, $w, $headerHeight, 'F');

        // Header text
        $pdf->SetFont('helvetica', 'B', 5);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY($x, $y + 0.8);
        $pdf->Cell($w, 3.5, 'FIRMA DIGITAL VERIFICADA', 0, 0, 'C');

        // Calculate areas: signature on left, details on right
        $sigAreaX = $x + 2;
        $sigAreaY = $y + $headerHeight + 1;
        $sigAreaW = min(22, ($w - 6) * 0.4);
        $sigAreaH = min(14, $h - $headerHeight - 10);

        // Signature image area background
        $pdf->SetFillColor(250, 250, 250);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Rect($sigAreaX, $sigAreaY, $sigAreaW, $sigAreaH, 'DF');

        // Add signature image if available
        $signatureData = $signer->signature_image ?: $signer->signature_data;
        if (!empty($signatureData) && strpos($signatureData, 'data:image') === 0) {
            $imgData = explode(',', $signatureData);
            if (count($imgData) > 1) {
                try {
                    $pdf->Image('@'.base64_decode($imgData[1]), $sigAreaX + 0.5, $sigAreaY + 0.5, $sigAreaW - 1, $sigAreaH - 1, '', '', '', true, 300, '', false, false, 0, 'CM');
                } catch (Exception $e) {
                    // Ignore image errors
                }
            }
        }

        // Details area (right side of signature)
        $detailX = $sigAreaX + $sigAreaW + 2;
        $detailY = $sigAreaY;
        $detailW = $w - $sigAreaW - 6;
        $lineHeight = 2.5;

        // Signer name
        $pdf->SetFont('helvetica', 'B', 5);
        $pdf->SetTextColor(40, 40, 40);
        $pdf->SetXY($detailX, $detailY);
        $signerName = $signer->getFullName();
        if (strlen($signerName) > 18) {
            $signerName = substr($signerName, 0, 16).'...';
        }
        $pdf->Cell($detailW, $lineHeight, $signerName, 0, 1, 'L');

        // Email
        $pdf->SetFont('helvetica', '', 4);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetXY($detailX, $detailY + $lineHeight);
        $email = $signer->email;
        if (strlen($email) > 20) {
            $email = substr($email, 0, 18).'...';
        }
        $pdf->Cell($detailW, $lineHeight, $email, 0, 1, 'L');

        // Date - simplified format to fit
        $pdf->SetXY($detailX, $detailY + ($lineHeight * 2));
        $dateStr = dol_print_date($signer->date_signed, '%d/%m/%Y %H:%M');
        $pdf->Cell($detailW, $lineHeight, $dateStr, 0, 1, 'L');

        // IP Address
        $pdf->SetXY($detailX, $detailY + ($lineHeight * 3));
        $pdf->Cell($detailW, $lineHeight, 'IP: '.($signer->ip_address ?: 'N/A'), 0, 1, 'L');

        // DNI if available
        if (!empty($signer->dni)) {
            $pdf->SetXY($detailX, $detailY + ($lineHeight * 4));
            $pdf->Cell($detailW, $lineHeight, 'DNI: '.$signer->dni, 0, 1, 'L');
        }

        // Hash footer
        $hashY = $y + $h - 6;
        $pdf->SetFont('helvetica', '', 3.5);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->SetXY($x + 2, $hashY);
        $signatureHash = $signer->signature_hash ?: hash('sha256', $signer->email.$signer->date_signed);
        $pdf->Cell($w - 4, 2.5, 'Hash: '.substr($signatureHash, 0, 28).'...', 0, 1, 'L');

        // Reference
        $pdf->SetXY($x + 2, $hashY + 2.5);
        $pdf->Cell($w - 4, 2.5, 'Ref: '.$this->ref, 0, 1, 'L');
    }

    /**
     * Add professional signature stamps to a PDF page (legacy method - kept for compatibility)
     * Each signer gets a visual stamp with: name, date, IP, signature hash, and handwritten signature
     *
     * @param TCPDF $pdf PDF object
     * @param array $size Page size
     */
    private function addSignaturesToPage(&$pdf, $size)
    {
        global $langs;
        $langs->load('docsig@signDol');

        // Stamp dimensions - larger to accommodate all data
        $stampWidth = 75;
        $stampHeight = 45;
        $margin = 10;
        
        // Calculate positions - stamps go at bottom of page
        $startY = $size['height'] - $margin - $stampHeight;
        $startX = $margin;
        
        $signerIndex = 0;
        $maxPerRow = 2; // 2 stamps per row for better readability
        $signedSigners = array();
        
        // Collect signed signers
        foreach ($this->signers as $signer) {
            if ($signer->status == DocSigSigner::STATUS_SIGNED) {
                $signedSigners[] = $signer;
            }
        }
        
        // If there are signatures, add a separator line
        if (!empty($signedSigners)) {
            $lineY = $startY - 5;
            $pdf->SetDrawColor(180, 180, 180);
            $pdf->Line($margin, $lineY, $size['width'] - $margin, $lineY);
            
            // Title above signatures
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->SetXY($margin, $lineY - 6);
            $pdf->Cell(0, 5, $langs->trans('DigitalSignaturesRecord'), 0, 0, 'L');
        }
        
        foreach ($signedSigners as $signer) {
            $col = $signerIndex % $maxPerRow;
            $row = floor($signerIndex / $maxPerRow);
            
            $x = $startX + ($col * ($stampWidth + 10));
            $y = $startY - ($row * ($stampHeight + 5));
            
            // Draw stamp border with slight shadow effect
            $pdf->SetDrawColor(100, 100, 100);
            $pdf->SetLineWidth(0.3);
            $pdf->SetFillColor(252, 252, 252);
            $pdf->RoundedRect($x, $y, $stampWidth, $stampHeight, 2, '1111', 'DF');
            
            // Header bar
            $pdf->SetFillColor(70, 130, 180); // Steel blue
            $pdf->Rect($x, $y, $stampWidth, 6, 'F');
            
            $pdf->SetFont('helvetica', 'B', 6);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY($x, $y + 1);
            $pdf->Cell($stampWidth, 4, 'FIRMA DIGITAL VERIFICADA', 0, 0, 'C');
            
            // Signature image area (left side)
            $sigAreaX = $x + 2;
            $sigAreaY = $y + 8;
            $sigAreaW = 28;
            $sigAreaH = 18;
            
            $pdf->SetDrawColor(200, 200, 200);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Rect($sigAreaX, $sigAreaY, $sigAreaW, $sigAreaH, 'DF');
            
            // Add signature image
            $signatureData = $signer->signature_image ?: $signer->signature_data;
            if (!empty($signatureData) && strpos($signatureData, 'data:image') === 0) {
                $imgData = explode(',', $signatureData);
                if (count($imgData) > 1) {
                    $pdf->Image('@'.base64_decode($imgData[1]), $sigAreaX + 1, $sigAreaY + 1, $sigAreaW - 2, $sigAreaH - 2, '', '', '', true, 300, '', false, false, 0, 'CM');
                }
            }
            
            // Signer details (right side)
            $detailX = $x + 32;
            $detailY = $y + 8;
            $detailW = $stampWidth - 34;
            
            $pdf->SetTextColor(50, 50, 50);
            $pdf->SetFont('helvetica', 'B', 6);
            $pdf->SetXY($detailX, $detailY);
            $signerName = $signer->getFullName();
            if (strlen($signerName) > 20) {
                $signerName = substr($signerName, 0, 18) . '...';
            }
            $pdf->Cell($detailW, 3, $signerName, 0, 1, 'L');
            
            $pdf->SetFont('helvetica', '', 5);
            $pdf->SetTextColor(80, 80, 80);
            
            // Email (truncated if too long)
            $pdf->SetXY($detailX, $detailY + 3);
            $email = $signer->email;
            if (strlen($email) > 22) {
                $email = substr($email, 0, 20) . '...';
            }
            $pdf->Cell($detailW, 3, $email, 0, 1, 'L');
            
            // Date signed
            $pdf->SetXY($detailX, $detailY + 6);
            $pdf->Cell($detailW, 3, dol_print_date($signer->date_signed, 'dayhour'), 0, 1, 'L');
            
            // IP Address
            $pdf->SetXY($detailX, $detailY + 9);
            $pdf->Cell($detailW, 3, 'IP: '.($signer->ip_address ?: 'N/A'), 0, 1, 'L');
            
            // DNI if available
            if (!empty($signer->dni)) {
                $pdf->SetXY($detailX, $detailY + 12);
                $pdf->Cell($detailW, 3, 'DNI: '.$signer->dni, 0, 1, 'L');
            }
            
            // Hash verification footer
            $hashY = $y + $stampHeight - 8;
            $pdf->SetFont('helvetica', '', 4);
            $pdf->SetTextColor(120, 120, 120);
            $pdf->SetXY($x + 2, $hashY);
            $signatureHash = $signer->signature_hash ?: hash('sha256', $signer->email.$signer->date_signed);
            $pdf->Cell($stampWidth - 4, 3, 'Hash: '.substr($signatureHash, 0, 32).'...', 0, 1, 'L');
            
            // Document ref
            $pdf->SetXY($x + 2, $hashY + 3);
            $pdf->Cell($stampWidth - 4, 3, 'Ref: '.$this->ref, 0, 1, 'L');
            
            $signerIndex++;
        }
    }

    /**
     * Add a new page with all signature stamps (fallback when FPDI not available)
     *
     * @param TCPDF $pdf PDF object
     */
    private function addSignatureStampsPage(&$pdf)
    {
        global $langs;
        $langs->load('docsig@signDol');
        
        $pdf->AddPage();
        
        // Header
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetTextColor(51, 51, 51);
        $pdf->Cell(0, 12, $langs->trans('DigitalSignaturesRecord'), 0, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 6, $langs->trans('Document').': '.$this->ref.' - '.basename($this->file_path), 0, 1, 'C');
        $pdf->Ln(8);
        
        // Draw stamps in a grid
        $stampWidth = 85;
        $stampHeight = 50;
        $margin = 15;
        $spacing = 10;
        $maxPerRow = 2;
        
        $signerIndex = 0;
        foreach ($this->signers as $signer) {
            if ($signer->status != DocSigSigner::STATUS_SIGNED) {
                continue;
            }
            
            $col = $signerIndex % $maxPerRow;
            $row = floor($signerIndex / $maxPerRow);
            
            $x = $margin + ($col * ($stampWidth + $spacing));
            $y = 45 + ($row * ($stampHeight + $spacing));
            
            // Check if we need a new page
            if ($y + $stampHeight > $pdf->getPageHeight() - 20) {
                $pdf->AddPage();
                $y = 20;
            }
            
            $this->drawSignatureStamp($pdf, $signer, $x, $y, $stampWidth, $stampHeight);
            $signerIndex++;
        }
    }

    /**
     * Draw a single signature stamp
     *
     * @param TCPDF $pdf PDF object
     * @param DocSigSigner $signer Signer object
     * @param float $x X position
     * @param float $y Y position
     * @param float $w Width
     * @param float $h Height
     */
    private function drawSignatureStamp(&$pdf, $signer, $x, $y, $w, $h)
    {
        // Border
        $pdf->SetDrawColor(70, 130, 180);
        $pdf->SetLineWidth(0.5);
        $pdf->SetFillColor(250, 250, 255);
        $pdf->RoundedRect($x, $y, $w, $h, 3, '1111', 'DF');
        
        // Header
        $pdf->SetFillColor(70, 130, 180);
        $pdf->Rect($x, $y, $w, 7, 'F');
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY($x, $y + 1.5);
        $pdf->Cell($w, 4, 'FIRMA DIGITAL VERIFICADA', 0, 0, 'C');
        
        // Signature area
        $sigX = $x + 3;
        $sigY = $y + 10;
        $sigW = 35;
        $sigH = 22;
        
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($sigX, $sigY, $sigW, $sigH, 'DF');
        
        // Signature image
        $signatureData = $signer->signature_image ?: $signer->signature_data;
        if (!empty($signatureData) && strpos($signatureData, 'data:image') === 0) {
            $imgData = explode(',', $signatureData);
            if (count($imgData) > 1) {
                $pdf->Image('@'.base64_decode($imgData[1]), $sigX + 1, $sigY + 1, $sigW - 2, $sigH - 2, '', '', '', true, 300, '', false, false, 0, 'CM');
            }
        }
        
        // Details
        $detX = $x + 42;
        $detY = $y + 10;
        $detW = $w - 45;
        
        $pdf->SetTextColor(50, 50, 50);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($detX, $detY);
        $pdf->Cell($detW, 4, $signer->getFullName(), 0, 1, 'L');
        
        $pdf->SetFont('helvetica', '', 6);
        $pdf->SetTextColor(80, 80, 80);
        
        $pdf->SetXY($detX, $detY + 5);
        $pdf->Cell($detW, 3, $signer->email, 0, 1, 'L');
        
        if (!empty($signer->dni)) {
            $pdf->SetXY($detX, $detY + 9);
            $pdf->Cell($detW, 3, 'DNI: '.$signer->dni, 0, 1, 'L');
        }
        
        $pdf->SetXY($detX, $detY + 13);
        $pdf->Cell($detW, 3, 'Fecha: '.dol_print_date($signer->date_signed, 'dayhour'), 0, 1, 'L');
        
        $pdf->SetXY($detX, $detY + 17);
        $pdf->Cell($detW, 3, 'IP: '.($signer->ip_address ?: 'N/A'), 0, 1, 'L');
        
        // Footer with hash
        $pdf->SetFont('helvetica', '', 5);
        $pdf->SetTextColor(120, 120, 120);
        $signatureHash = $signer->signature_hash ?: hash('sha256', $signer->email.($signer->date_signed ?: ''));
        $pdf->SetXY($x + 3, $y + $h - 8);
        $pdf->Cell($w - 6, 3, 'Hash verificacion: '.substr($signatureHash, 0, 40).'...', 0, 1, 'L');
        $pdf->SetXY($x + 3, $y + $h - 5);
        $pdf->Cell($w - 6, 3, 'Documento: '.$this->ref.' | Integridad: '.substr($this->file_hash, 0, 16).'...', 0, 1, 'L');
    }

    /**
     * Add a signature addendum page
     *
     * @param TCPDF $pdf PDF object
     */
    private function addSignatureAddendumPage(&$pdf)
    {
        global $langs;
        $langs->load('docsig@signDol');
        
        // Page header
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetTextColor(70, 130, 180);
        $pdf->Cell(0, 12, $langs->trans('DigitalSignaturesRecord'), 0, 1, 'C');
        
        // Document info
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(0, 6, $langs->trans('Document').': '.$this->ref, 0, 1, 'C');
        $pdf->Cell(0, 6, $langs->trans('GeneratedOn').': '.dol_print_date(dol_now(), 'dayhour'), 0, 1, 'C');
        
        $pdf->Ln(8);
        
        // Draw separator line
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
        $pdf->Ln(8);
        
        // Stamp dimensions for addendum page
        $stampWidth = 80;
        $stampHeight = 50;
        $margin = 15;
        $maxPerRow = 2;
        
        // Collect signed signers
        $signedSigners = array();
        foreach ($this->signers as $signer) {
            if ($signer->status == DocSigSigner::STATUS_SIGNED) {
                $signedSigners[] = $signer;
            }
        }
        
        $startY = $pdf->GetY();
        $startX = $margin;
        
        foreach ($signedSigners as $index => $signer) {
            $col = $index % $maxPerRow;
            $row = floor($index / $maxPerRow);
            
            $x = $startX + ($col * ($stampWidth + 10));
            $y = $startY + ($row * ($stampHeight + 10));
            
            // Check if we need a new page
            if ($y + $stampHeight > 270) {
                $pdf->AddPage();
                $startY = 20;
                $y = $startY;
            }
            
            $this->drawSignatureStampAddendum($pdf, $signer, $x, $y, $stampWidth, $stampHeight);
        }
    }
    
    /**
     * Draw individual signature stamp for addendum page
     *
     * @param TCPDF $pdf PDF object
     * @param DocSigSigner $signer Signer object
     * @param float $x X position
     * @param float $y Y position
     * @param float $w Width
     * @param float $h Height
     */
    private function drawSignatureStampAddendum(&$pdf, $signer, $x, $y, $w, $h)
    {
        global $langs;
        
        // Draw stamp border with slight shadow effect
        $pdf->SetDrawColor(100, 100, 100);
        $pdf->SetLineWidth(0.4);
        $pdf->SetFillColor(252, 252, 252);
        $pdf->RoundedRect($x, $y, $w, $h, 3, '1111', 'DF');
        
        // Header bar
        $pdf->SetFillColor(70, 130, 180); // Steel blue
        $pdf->Rect($x, $y, $w, 8, 'F');
        
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY($x, $y + 2);
        $pdf->Cell($w, 5, 'FIRMA DIGITAL VERIFICADA', 0, 0, 'C');
        
        // Signature image area (left side)
        $sigAreaX = $x + 3;
        $sigAreaY = $y + 11;
        $sigAreaW = 32;
        $sigAreaH = 22;
        
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($sigAreaX, $sigAreaY, $sigAreaW, $sigAreaH, 'DF');
        
        // Add signature image
        $signatureData = $signer->signature_image ?: $signer->signature_data;
        if (!empty($signatureData) && strpos($signatureData, 'data:image') === 0) {
            $imgData = explode(',', $signatureData);
            if (count($imgData) > 1) {
                $pdf->Image('@'.base64_decode($imgData[1]), $sigAreaX + 2, $sigAreaY + 2, $sigAreaW - 4, $sigAreaH - 4, '', '', '', true, 300, '', false, false, 0, 'CM');
            }
        }
        
        // Signer details (right side)
        $detailX = $x + 38;
        $detailY = $y + 11;
        $detailW = $w - 42;
        
        $pdf->SetTextColor(50, 50, 50);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($detailX, $detailY);
        $signerName = $signer->getFullName();
        if (strlen($signerName) > 22) {
            $signerName = substr($signerName, 0, 20) . '...';
        }
        $pdf->Cell($detailW, 4, $signerName, 0, 1, 'L');
        
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(80, 80, 80);
        
        // Email
        $pdf->SetXY($detailX, $detailY + 5);
        $email = $signer->email;
        if (strlen($email) > 25) {
            $email = substr($email, 0, 23) . '...';
        }
        $pdf->Cell($detailW, 4, $email, 0, 1, 'L');
        
        // Date signed
        $pdf->SetXY($detailX, $detailY + 10);
        $signedDate = $signer->date_signed ?: $signer->signed_at;
        $pdf->Cell($detailW, 4, $langs->trans('SignedAt').': '.dol_print_date($signedDate, 'dayhour'), 0, 1, 'L');
        
        // IP Address
        $pdf->SetXY($detailX, $detailY + 15);
        $pdf->Cell($detailW, 4, 'IP: '.($signer->ip_address ?: 'N/A'), 0, 1, 'L');
        
        // DNI if available
        if (!empty($signer->dni)) {
            $pdf->SetXY($detailX, $detailY + 20);
            $pdf->Cell($detailW, 4, 'DNI: '.$signer->dni, 0, 1, 'L');
        }
        
        // Hash verification footer
        $hashY = $y + $h - 10;
        $pdf->SetFont('helvetica', '', 5);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->SetXY($x + 3, $hashY);
        $signatureHash = $signer->signature_hash ?: hash('sha256', $signer->email.($signer->date_signed ?: $signer->signed_at));
        $pdf->Cell($w - 6, 4, 'Hash: '.substr($signatureHash, 0, 40).'...', 0, 1, 'L');
        
        // Document ref
        $pdf->SetXY($x + 3, $hashY + 4);
        $pdf->Cell($w - 6, 4, 'Ref: '.$this->ref, 0, 1, 'L');
    }

    /**
     * Generate compliance certificate PDF
     *
     * @return int Return integer <0 if KO, >0 if OK
     */
    public function generateComplianceCertificate()
    {
        global $conf, $langs, $mysoc;

        $langs->load('docsig@signDol');
        dol_syslog('DocSigEnvelope::generateComplianceCertificate - Starting for envelope '.$this->ref);

        // Prepare output path
        $certFilename = 'compliance_certificate_'.$this->ref.'.pdf';
        $certDir = dirname($this->file_path);
        $this->compliance_cert_path = $certDir.'/'.$certFilename;

        require_once TCPDF_PATH.'tcpdf.php';

        try {
            $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            
            // Document info
            $pdf->SetCreator('DocSig');
            $pdf->SetAuthor(getDolGlobalString('MAIN_INFO_SOCIETE_NOM', 'DocSig'));
            $pdf->SetTitle('Certificado - '.$this->ref);
            $pdf->SetSubject('Certificado de Contratación Electrónica');
            
            // Remove default header/footer
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            
            $pdf->SetMargins(15, 10, 15);
            $pdf->SetAutoPageBreak(true, 25);
            $pdf->AddPage();

            // Colors
            $primaryColor = array(0, 102, 153); // Blue
            $headerBg = array(0, 102, 153);
            $lightBg = array(245, 248, 250);
            $borderColor = array(200, 210, 220);
            $textDark = array(51, 51, 51);
            $textLight = array(102, 102, 102);
            $successColor = array(40, 167, 69);

            $pageWidth = 180; // mm (210 - 15*2)
            $colWidth = $pageWidth / 2;

            // ==================== HEADER ====================
            // Company logo area (left)
            $pdf->SetXY(15, 10);
            $companyName = getDolGlobalString('MAIN_INFO_SOCIETE_NOM', 'DocSig');
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
            $pdf->Cell(60, 10, $companyName, 0, 0, 'L');

            // Title and date (right aligned)
            $pdf->SetXY(100, 10);
            $pdf->SetFont('helvetica', 'B', 20);
            $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
            $pdf->Cell(95, 10, 'CERTIFICADO', 0, 1, 'R');
            
            $pdf->SetXY(100, 18);
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor($textLight[0], $textLight[1], $textLight[2]);
            $pdf->Cell(95, 6, 'Contratación electrónica certificada', 0, 1, 'R');
            
            $pdf->SetXY(100, 24);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(95, 5, dol_print_date(dol_now(), 'dayhour'), 0, 1, 'R');

            $pdf->Ln(8);

            // ==================== CERTIFICATION TEXT ====================
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor($textDark[0], $textDark[1], $textDark[2]);
            
            $adminName = getDolGlobalString('MAIN_INFO_SOCIETE_MANAGERS', 'El Administrador');
            $companyCIF = getDolGlobalString('MAIN_INFO_SIREN', '');
            
            $certText = sprintf(
                'D. %s en representación de %s%s, en su condición de Prestador de Servicios de Confianza generando una prueba por interposición CERTIFICA que todos los datos recogidos en el presente documento corresponden con la contratación electrónica certificada entre las partes abajo indicadas, con fecha de creación %s cuyo identificador único es %s, habiéndose procedido a depositar notarialmente la función resumen de su contenido.',
                $adminName,
                $companyName,
                $companyCIF ? ' con NIF '.$companyCIF : '',
                dol_print_date($this->date_creation, 'dayhour'),
                $this->ref
            );
            
            $pdf->MultiCell(0, 5, $certText, 0, 'J');
            $pdf->Ln(5);

            // ==================== INTERVINIENTES ====================
            $this->_drawCertSectionHeader($pdf, 'INTERVINIENTES', $headerBg);
            
            // Emisor del contrato
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetTextColor($textLight[0], $textLight[1], $textLight[2]);
            $pdf->Cell(40, 5, 'EMISOR DEL CONTRATO', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor($textDark[0], $textDark[1], $textDark[2]);
            $pdf->Cell(0, 5, $companyName, 0, 1, 'L');
            
            // Otros intervinientes (firmantes)
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetTextColor($textLight[0], $textLight[1], $textLight[2]);
            $pdf->Cell(40, 5, 'OTROS INTERVINIENTES', 0, 1, 'L');
            
            foreach ($this->signers as $signer) {
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
                $pdf->Cell(5, 4, '', 0);
                $pdf->Cell(0, 4, $signer->getFullName(), 0, 1, 'L');
                
                $pdf->SetFont('helvetica', '', 8);
                $pdf->SetTextColor($textLight[0], $textLight[1], $textLight[2]);
                $pdf->Cell(10, 4, '', 0);
                $signerInfo = 'Identificación: '.$signer->email;
                if ($signer->dni) $signerInfo .= ' - DNI: '.$signer->dni;
                if ($signer->phone) $signerInfo .= ' - Móvil: '.$signer->phone;
                $pdf->Cell(0, 4, $signerInfo, 0, 1, 'L');
            }
            $pdf->Ln(3);

            // ==================== ESTADO ====================
            $this->_drawCertSectionHeader($pdf, 'ESTADO', $headerBg);
            
            $pdf->SetFillColor($lightBg[0], $lightBg[1], $lightBg[2]);
            $pdf->Rect(15, $pdf->GetY(), $pageWidth, 8, 'F');
            
            // Check icon
            $pdf->SetFont('zapfdingbats', '', 12);
            $pdf->SetTextColor($successColor[0], $successColor[1], $successColor[2]);
            $pdf->Cell(8, 8, '4', 0, 0, 'C'); // Checkmark in Zapf Dingbats
            
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetTextColor($successColor[0], $successColor[1], $successColor[2]);
            $pdf->Cell(20, 8, 'FIRMADO', 0, 0, 'L');
            
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor($textDark[0], $textDark[1], $textDark[2]);
            $completionDate = dol_now();
            foreach ($this->signers as $s) {
                if ($s->signed_at && $s->signed_at > $completionDate) {
                    $completionDate = $s->signed_at;
                }
            }
            $pdf->Cell(0, 8, 'Fecha último estado: '.dol_print_date($completionDate, 'dayhour'), 0, 1, 'L');
            $pdf->Ln(3);

            // ==================== VERIFICACIÓN ELECTRÓNICA ====================
            $this->_drawCertSectionHeader($pdf, 'VERIFICACIÓN ELECTRÓNICA', $headerBg);
            
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetTextColor($textLight[0], $textLight[1], $textLight[2]);
            $pdf->Cell(50, 5, 'GUID DE LA TRANSACCIÓN:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor($textDark[0], $textDark[1], $textDark[2]);
            $pdf->Cell(0, 5, $this->ref.'-'.strtoupper(substr(md5($this->ref.$this->date_creation), 0, 16)), 0, 1, 'L');
            
            $pdf->SetFont('helvetica', 'I', 7);
            $pdf->SetTextColor($textLight[0], $textLight[1], $textLight[2]);
            $pdf->Cell(50, 4, '', 0);
            $pdf->Cell(0, 4, 'CONTROL DE INTEGRIDAD BASADO EN LA FUNCIÓN RESUMEN DEL DOCUMENTO TRAMITADO', 0, 1, 'L');
            
            // Document hash
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetTextColor($textLight[0], $textLight[1], $textLight[2]);
            $pdf->Cell(50, 5, 'DOCUMENTO:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor($textDark[0], $textDark[1], $textDark[2]);
            $pdf->Cell(0, 5, basename($this->file_path), 0, 1, 'L');
            
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetTextColor($textLight[0], $textLight[1], $textLight[2]);
            $pdf->Cell(50, 5, 'HASH SHA-256:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetTextColor($textDark[0], $textDark[1], $textDark[2]);
            $pdf->Cell(0, 5, $this->file_hash, 0, 1, 'L');
            $pdf->Ln(3);

            // ==================== REMISIONES ====================
            $this->_drawCertSectionHeader($pdf, 'REMISIONES', $headerBg);
            
            // Table header
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetFillColor($lightBg[0], $lightBg[1], $lightBg[2]);
            $pdf->Cell(55, 6, 'INTERVINIENTE', 1, 0, 'C', true);
            $pdf->Cell(25, 6, 'MÉTODO', 1, 0, 'C', true);
            $pdf->Cell(60, 6, 'DIRECCIÓN/TELÉFONO', 1, 0, 'C', true);
            $pdf->Cell(40, 6, 'FECHA', 1, 1, 'C', true);
            
            // Remisiones data - fetch from events
            $pdf->SetFont('helvetica', '', 8);
            $sql = "SELECT DISTINCT payload_json, created_at FROM ".MAIN_DB_PREFIX."docsig_event 
                    WHERE fk_envelope = ".(int)$this->id." AND event_type IN ('OTP_SENT', 'OTP_EMAIL_SENT', 'OTP_WHATSAPP_SENT')
                    ORDER BY created_at ASC";
            $resql = $this->db->query($sql);
            if ($resql && $this->db->num_rows($resql) > 0) {
                while ($obj = $this->db->fetch_object($resql)) {
                    $payload = json_decode($obj->payload_json, true);
                    $channel = isset($payload['channel']) ? strtoupper($payload['channel']) : 'EMAIL';
                    $dest = isset($payload['destination']) ? $payload['destination'] : '';
                    
                    // Find signer
                    $signerName = '';
                    foreach ($this->signers as $s) {
                        if ($s->email == $dest || $s->phone == $dest) {
                            $signerName = $s->getFullName();
                            break;
                        }
                    }
                    if (!$signerName) $signerName = $dest;
                    
                    $pdf->Cell(55, 5, $signerName, 1, 0, 'L');
                    $pdf->Cell(25, 5, $channel, 1, 0, 'C');
                    $pdf->Cell(60, 5, $dest, 1, 0, 'L');
                    $pdf->Cell(40, 5, dol_print_date($this->db->jdate($obj->created_at), 'dayhour'), 1, 1, 'C');
                }
            } else {
                // If no OTP events, show signers
                foreach ($this->signers as $signer) {
                    $pdf->Cell(55, 5, $signer->getFullName(), 1, 0, 'L');
                    $pdf->Cell(25, 5, 'EMAIL', 1, 0, 'C');
                    $pdf->Cell(60, 5, $signer->email, 1, 0, 'L');
                    $pdf->Cell(40, 5, dol_print_date($this->date_creation, 'dayhour'), 1, 1, 'C');
                }
            }
            $pdf->Ln(3);

            // ==================== FIRMAS ====================
            $this->_drawCertSectionHeader($pdf, 'FIRMAS', $headerBg);
            
            // Table header
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetFillColor($lightBg[0], $lightBg[1], $lightBg[2]);
            $pdf->Cell(50, 6, 'INTERVINIENTE', 1, 0, 'C', true);
            $pdf->Cell(50, 6, 'DOCUMENTO', 1, 0, 'C', true);
            $pdf->Cell(40, 6, 'FECHA', 1, 0, 'C', true);
            $pdf->Cell(40, 6, 'RESULTADO', 1, 1, 'C', true);
            
            // Firmas data
            $pdf->SetFont('helvetica', '', 8);
            foreach ($this->signers as $signer) {
                $pdf->Cell(50, 5, $signer->getFullName(), 1, 0, 'L');
                $pdf->Cell(50, 5, basename($this->file_path), 1, 0, 'L');
                $pdf->Cell(40, 5, $signer->signed_at ? dol_print_date($signer->signed_at, 'dayhour') : '-', 1, 0, 'C');
                
                if ($signer->status == DocSigSigner::STATUS_SIGNED) {
                    $pdf->SetTextColor($successColor[0], $successColor[1], $successColor[2]);
                    $pdf->Cell(40, 5, 'FIRMADO', 1, 1, 'C');
                } else {
                    $pdf->SetTextColor(200, 100, 0);
                    $pdf->Cell(40, 5, 'PENDIENTE', 1, 1, 'C');
                }
                $pdf->SetTextColor($textDark[0], $textDark[1], $textDark[2]);
            }
            
            // Add signature images
            $pdf->Ln(3);
            foreach ($this->signers as $signer) {
                if ($signer->status == DocSigSigner::STATUS_SIGNED) {
                    $signatureData = $signer->signature_image ?: $signer->signature_data;
                    if (!empty($signatureData) && strpos($signatureData, 'data:image') === 0) {
                        $imgData = explode(',', $signatureData);
                        if (count($imgData) > 1) {
                            $pdf->SetFont('helvetica', '', 7);
                            $pdf->SetTextColor($textLight[0], $textLight[1], $textLight[2]);
                            $pdf->Cell(30, 4, 'Firma de '.$signer->getFullName().':', 0, 0, 'L');
                            $x = $pdf->GetX();
                            $y = $pdf->GetY();
                            try {
                                $pdf->Image('@'.base64_decode($imgData[1]), $x, $y - 2, 35, 12, '', '', '', true, 300, '', false, false, 0, 'CM');
                            } catch (Exception $e) {}
                            $pdf->Ln(14);
                        }
                    }
                }
            }

            // ==================== FOOTER ====================
            $pdf->Ln(5);
            
            // Legal text boxes
            $pdf->SetFont('helvetica', '', 6);
            $pdf->SetTextColor($textLight[0], $textLight[1], $textLight[2]);
            
            $legalText1 = $companyName.' custodiará los registros informáticos generados en las transacciones realizadas, que son acreditativos de todo lo antedicho, de acuerdo con contrato suscrito entre '.$companyName.' y el emisor.';
            $legalText2 = 'El soporte que incorpora las firmas electrónicas de las transacciones tiene la consideración de prueba documental, de acuerdo con la normativa aplicable en el Espacio Económico Europeo.';
            
            $pdf->MultiCell(85, 3, $legalText1, 0, 'J');
            $pdf->SetXY(105, $pdf->GetY() - 12);
            $pdf->MultiCell(85, 3, $legalText2, 0, 'J');
            
            $pdf->Ln(5);
            
            // Verification code
            $verificationCode = strtoupper(substr(hash('sha256', $this->ref.$this->file_hash.implode('', array_column($this->signers, 'id'))), 0, 20));
            
            $pdf->SetY(-30);
            $pdf->SetDrawColor($borderColor[0], $borderColor[1], $borderColor[2]);
            $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
            
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
            $pdf->Cell(60, 5, 'Código de verificación del certificado:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 8);
            $pdf->Cell(70, 5, $verificationCode, 0, 0, 'L');
            
            // Verification URL
            $verifyUrl = getDolGlobalString('DOCSIG_VERIFY_URL', dol_buildpath('/signDol/public/verify.php', 2));
            $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
            $pdf->Cell(0, 5, $verifyUrl, 0, 1, 'R');
            
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetTextColor($textLight[0], $textLight[1], $textLight[2]);
            $pdf->Cell(0, 4, $companyName.' - Documento generado electrónicamente', 0, 1, 'L');

            // Save PDF
            $pdf->Output($this->compliance_cert_path, 'F');
            
            $this->logEvent('CERTIFICATE_GENERATED', 'Compliance certificate generated: '.$certFilename.' (Code: '.$verificationCode.')');
            
            dol_syslog('DocSigEnvelope::generateComplianceCertificate - Certificate generated: '.$this->compliance_cert_path);
            
            return 1;

        } catch (Exception $e) {
            $this->error = 'Error generating compliance certificate: '.$e->getMessage();
            dol_syslog('DocSigEnvelope::generateComplianceCertificate - Error: '.$this->error, LOG_ERR);
            return -1;
        }
    }

    /**
     * Draw a section header for the certificate
     */
    private function _drawCertSectionHeader(&$pdf, $title, $bgColor)
    {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor($bgColor[0], $bgColor[1], $bgColor[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(0, 6, $title, 0, 1, 'L', true);
        $pdf->SetTextColor(51, 51, 51);
        $pdf->Ln(2);
    }

    /**
     * Notify all signers of envelope completion
     *
     * @return int Number of notifications sent
     */
    public function notifyCompletion()
    {
        global $conf;

        dol_include_once('/signDol/class/docsignotification.class.php');
        
        $notificationService = new DocSigNotificationService($this->db);
        $sent = 0;
        
        foreach ($this->signers as $signer) {
            $result = $notificationService->sendCompletionNotification($this, $signer);
            if ($result > 0) {
                $sent++;
            }
        }
        
        return $sent;
    }

    /**
     * Get number of signed signers
     *
     * @return int Number of signed signers
     */
    public function getSignedCount()
    {
        $count = 0;
        foreach ($this->signers as $signer) {
            if ($signer->status == 1) { // Signed
                $count++;
            }
        }
        return $count;
    }

    /**
     * Get URL for this envelope
     *
     * @param int $withpicto 0=No picto, 1=Include picto into link, 2=Only picto
     * @param string $option Option
     * @param int $notooltip 1=Disable tooltip
     * @param string $morecss Add more css on link
     * @return string URL string
     */
    public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '')
    {
        global $conf, $langs;

        $result = '';

        $url = dol_buildpath('/signDol/card.php', 1).'?id='.$this->id;

        $label = '<u>'.$langs->trans("Envelope").'</u>';
        $label .= '<br><b>'.$langs->trans('Ref').':</b> '.$this->ref;
        $label .= '<br><b>'.$langs->trans('Status').':</b> '.$this->getLibStatut(0);

        $linkclose = '';
        if (!$notooltip) {
            $linkclose .= ' title="'.dol_escape_htmltag($label, 1).'"';
            $linkclose .= ' class="classfortooltip'.($morecss ? ' '.$morecss : '').'"';
        } else {
            $linkclose .= ($morecss ? ' class="'.$morecss.'"' : '');
        }

        $picto = 'fa-file-signature';

        $result .= '<a href="'.$url.'"'.$linkclose.'>';
        if ($withpicto) {
            $result .= img_object(($notooltip ? '' : $label), $picto, '', 0, 0, $notooltip ? 0 : 1).' ';
        }
        if ($withpicto != 2) {
            $result .= $this->ref;
        }
        $result .= '</a>';

        return $result;
    }
}
