<?php
/* Copyright (C) 2025 ZonaJob Dev
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    class/zonajobsignature.class.php
 * \ingroup zonajob
 * \brief   Class for ZonaJob signatures
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class ZonaJobSignature
 */
class ZonaJobSignature extends CommonObject
{
    /**
     * @var string ID to identify managed object
     */
    public $element = 'zonajob_signature';

    /**
     * @var string Name of table without prefix
     */
    public $table_element = 'zonajob_signature';

    /**
     * @var string Ref
     */
    public $ref;

    /**
     * @var int Order ID
     */
    public $fk_commande;

    /**
     * @var int Third party ID
     */
    public $fk_soc;

    /**
     * @var int Contact ID
     */
    public $fk_socpeople;

    /**
     * @var string Signer name
     */
    public $signer_name;

    /**
     * @var string Signer email
     */
    public $signer_email;

    /**
     * @var string Signer phone
     */
    public $signer_phone;

    /**
     * @var string Base64 signature data
     */
    public $signature_data;

    /**
     * @var string Signature file path
     */
    public $signature_file;

    /**
     * @var string IP address
     */
    public $ip_address;

    /**
     * @var string User agent
     */
    public $user_agent;

    /**
     * @var string Latitude
     */
    public $latitude;

    /**
     * @var string Longitude
     */
    public $longitude;

    /**
     * @var int Status (0=pending, 1=signed, -1=cancelled)
     */
    public $status;

    /**
     * @var int Creation date
     */
    public $date_creation;

    /**
     * @var int Signature date
     */
    public $date_signature;

    /**
     * @var int User who created
     */
    public $fk_user_creat;

    /**
     * @var int User who modified
     */
    public $fk_user_modif;

    /**
     * @var int Entity
     */
    public $entity;

    /**
     * Status constants
     */
    const STATUS_PENDING = 0;
    const STATUS_SIGNED = 1;
    const STATUS_CANCELLED = -1;

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
     * Create signature record
     *
     * @param User $user User object
     * @param bool $notrigger Disable triggers
     * @return int <0 if KO, >0 if OK
     */
    public function create($user, $notrigger = false)
    {
        global $conf;

        $error = 0;
        $now = dol_now();

        // Generate ref
        if (empty($this->ref)) {
            $this->ref = $this->getNextRef();
        }

        $this->entity = $conf->entity;
        $this->date_creation = $now;
        $this->fk_user_creat = $user->id;
        $this->status = self::STATUS_PENDING;

        $this->db->begin();

        $sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (";
        $sql .= "ref, fk_commande, fk_soc, fk_socpeople, signer_name, signer_email, signer_phone,";
        $sql .= "signature_data, signature_file, ip_address, user_agent, latitude, longitude,";
        $sql .= "status, date_creation, date_signature, fk_user_creat, entity";
        $sql .= ") VALUES (";
        $sql .= "'".$this->db->escape($this->ref)."',";
        $sql .= ($this->fk_commande > 0 ? $this->fk_commande : "NULL").",";
        $sql .= ($this->fk_soc > 0 ? $this->fk_soc : "NULL").",";
        $sql .= ($this->fk_socpeople > 0 ? $this->fk_socpeople : "NULL").",";
        $sql .= (!empty($this->signer_name) ? "'".$this->db->escape($this->signer_name)."'" : "NULL").",";
        $sql .= (!empty($this->signer_email) ? "'".$this->db->escape($this->signer_email)."'" : "NULL").",";
        $sql .= (!empty($this->signer_phone) ? "'".$this->db->escape($this->signer_phone)."'" : "NULL").",";
        $sql .= (!empty($this->signature_data) ? "'".$this->db->escape($this->signature_data)."'" : "NULL").",";
        $sql .= (!empty($this->signature_file) ? "'".$this->db->escape($this->signature_file)."'" : "NULL").",";
        $sql .= (!empty($this->ip_address) ? "'".$this->db->escape($this->ip_address)."'" : "NULL").",";
        $sql .= (!empty($this->user_agent) ? "'".$this->db->escape($this->user_agent)."'" : "NULL").",";
        $sql .= (!empty($this->latitude) ? "'".$this->db->escape($this->latitude)."'" : "NULL").",";
        $sql .= (!empty($this->longitude) ? "'".$this->db->escape($this->longitude)."'" : "NULL").",";
        $sql .= $this->status.",";
        $sql .= "'".$this->db->idate($this->date_creation)."',";
        $sql .= ($this->date_signature ? "'".$this->db->idate($this->date_signature)."'" : "NULL").",";
        $sql .= $user->id.",";
        $sql .= $conf->entity;
        $sql .= ")";

        $result = $this->db->query($sql);

        if ($result) {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);

            if (!$notrigger) {
                // Uncomment to enable triggers
                // $result = $this->call_trigger('ZONAJOB_SIGNATURE_CREATE', $user);
                // if ($result < 0) $error++;
            }
        } else {
            $error++;
            $this->error = $this->db->lasterror();
            $this->errors[] = $this->error;
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
     * Update signature (when signed)
     *
     * @param User $user User object
     * @param bool $notrigger Disable triggers
     * @return int <0 if KO, >0 if OK
     */
    public function update($user, $notrigger = false)
    {
        $error = 0;

        $this->fk_user_modif = $user->id;

        $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET";
        $sql .= " signer_name = ".(!empty($this->signer_name) ? "'".$this->db->escape($this->signer_name)."'" : "NULL").",";
        $sql .= " signer_email = ".(!empty($this->signer_email) ? "'".$this->db->escape($this->signer_email)."'" : "NULL").",";
        $sql .= " signer_phone = ".(!empty($this->signer_phone) ? "'".$this->db->escape($this->signer_phone)."'" : "NULL").",";
        $sql .= " signature_data = ".(!empty($this->signature_data) ? "'".$this->db->escape($this->signature_data)."'" : "NULL").",";
        $sql .= " signature_file = ".(!empty($this->signature_file) ? "'".$this->db->escape($this->signature_file)."'" : "NULL").",";
        $sql .= " ip_address = ".(!empty($this->ip_address) ? "'".$this->db->escape($this->ip_address)."'" : "NULL").",";
        $sql .= " user_agent = ".(!empty($this->user_agent) ? "'".$this->db->escape($this->user_agent)."'" : "NULL").",";
        $sql .= " latitude = ".(!empty($this->latitude) ? "'".$this->db->escape($this->latitude)."'" : "NULL").",";
        $sql .= " longitude = ".(!empty($this->longitude) ? "'".$this->db->escape($this->longitude)."'" : "NULL").",";
        $sql .= " status = ".$this->status.",";
        $sql .= " date_signature = ".($this->date_signature ? "'".$this->db->idate($this->date_signature)."'" : "NULL").",";
        $sql .= " fk_user_modif = ".$user->id;
        $sql .= " WHERE rowid = ".$this->id;

        $result = $this->db->query($sql);

        if ($result) {
            return 1;
        } else {
            $this->error = $this->db->lasterror();
            $this->errors[] = $this->error;
            return -1;
        }
    }

    /**
     * Fetch signature by ID
     *
     * @param int $id Signature ID
     * @return int <0 if KO, >0 if OK
     */
    public function fetch($id)
    {
        $sql = "SELECT rowid, ref, fk_commande, fk_soc, fk_socpeople, signer_name, signer_email, signer_phone,";
        $sql .= " signature_data, signature_file, ip_address, user_agent, latitude, longitude,";
        $sql .= " status, date_creation, date_signature, fk_user_creat, fk_user_modif, entity";
        $sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE rowid = ".((int) $id);

        $result = $this->db->query($sql);

        if ($result) {
            if ($this->db->num_rows($result)) {
                $obj = $this->db->fetch_object($result);

                $this->id = $obj->rowid;
                $this->ref = $obj->ref;
                $this->fk_commande = $obj->fk_commande;
                $this->fk_soc = $obj->fk_soc;
                $this->fk_socpeople = $obj->fk_socpeople;
                $this->signer_name = $obj->signer_name;
                $this->signer_email = $obj->signer_email;
                $this->signer_phone = $obj->signer_phone;
                $this->signature_data = $obj->signature_data;
                $this->signature_file = $obj->signature_file;
                $this->ip_address = $obj->ip_address;
                $this->user_agent = $obj->user_agent;
                $this->latitude = $obj->latitude;
                $this->longitude = $obj->longitude;
                $this->status = $obj->status;
                $this->date_creation = $this->db->jdate($obj->date_creation);
                $this->date_signature = $this->db->jdate($obj->date_signature);
                $this->fk_user_creat = $obj->fk_user_creat;
                $this->fk_user_modif = $obj->fk_user_modif;
                $this->entity = $obj->entity;

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
     * Fetch signature by order ID
     *
     * @param int $fk_commande Order ID
     * @return int <0 if KO, 0 if not found, >0 if OK
     */
    public function fetchByOrder($fk_commande)
    {
        global $conf;

        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE fk_commande = ".((int) $fk_commande);
        $sql .= " AND entity = ".$conf->entity;
        $sql .= " ORDER BY date_creation DESC LIMIT 1";

        $result = $this->db->query($sql);

        if ($result) {
            if ($this->db->num_rows($result)) {
                $obj = $this->db->fetch_object($result);
                return $this->fetch($obj->rowid);
            }
            return 0;
        } else {
            $this->error = $this->db->lasterror();
            return -1;
        }
    }

    /**
     * Get signatures for order
     *
     * @param int $fk_commande Order ID
     * @return array Array of signature objects
     */
    public function getSignaturesForOrder($fk_commande)
    {
        global $conf;

        $signatures = array();

        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE fk_commande = ".((int) $fk_commande);
        $sql .= " AND entity = ".$conf->entity;
        $sql .= " ORDER BY date_creation DESC";

        $result = $this->db->query($sql);

        if ($result) {
            while ($obj = $this->db->fetch_object($result)) {
                $sig = new ZonaJobSignature($this->db);
                $sig->fetch($obj->rowid);
                $signatures[] = $sig;
            }
        }

        return $signatures;
    }

    /**
     * Sign the document
     *
     * @param string $signature_data Base64 signature data
     * @param string $signer_name Signer name
     * @param User $user User object
     * @return int <0 if KO, >0 if OK
     */
    public function sign($signature_data, $signer_name, $user)
    {
        global $conf;

        $this->signature_data = $signature_data;
        $this->signer_name = $signer_name;
        $this->date_signature = dol_now();
        $this->status = self::STATUS_SIGNED;
        $this->ip_address = getUserRemoteIP();
        $this->user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

        // Save signature image to file
        if (!empty($signature_data)) {
            $this->saveSignatureToFile($signature_data);
        }

        return $this->update($user);
    }

    /**
     * Save signature data to file with geolocation and timestamp stamp
     * Saves to ECM (Dolibarr's standard document folder for the order)
     *
     * @param string $signature_data Base64 signature data
     * @return string|bool File path or false on error
     */
    public function saveSignatureToFile($signature_data)
    {
        global $conf, $db;

        // Remove data URL prefix if present
        if (strpos($signature_data, 'data:image') === 0) {
            $signature_data = preg_replace('#^data:image/\w+;base64,#i', '', $signature_data);
        }

        $decoded = base64_decode($signature_data);
        if ($decoded === false) {
            return false;
        }

        // Get order reference to save in ECM folder
        $orderRef = '';
        if (!empty($this->fk_commande)) {
            require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
            $order = new Commande($db);
            if ($order->fetch($this->fk_commande) > 0) {
                $orderRef = $order->ref;
            }
        }

        // Create image from signature data
        $image = @imagecreatefromstring($decoded);
        if ($image === false) {
            // If imagecreatefromstring fails, save raw data without stamp
            return $this->saveRawSignatureFile($decoded, $orderRef);
        }

        // Add geolocation and timestamp stamp to the image
        $stampedImage = $this->addStampToSignature($image);

        // Save to ECM standard folder (documents/commande/REF/)
        if (!empty($orderRef)) {
            $dir = $conf->commande->dir_output.'/'.$orderRef;
        } else {
            $dir = $conf->zonajob->dir_output.'/signatures';
        }

        if (!is_dir($dir)) {
            dol_mkdir($dir);
        }

        $filename = 'signature_'.$this->ref.'_'.dol_print_date(dol_now(), 'dayhourlog').'.png';
        $filepath = $dir.'/'.$filename;

        // Save the stamped image
        $result = imagepng($stampedImage, $filepath);
        imagedestroy($image);
        imagedestroy($stampedImage);

        if ($result) {
            $this->signature_file = $filename;
            // Store relative path from commande folder for PDF use
            if (!empty($orderRef)) {
                $this->signature_file = $orderRef.'/'.$filename;
            }
            return $filepath;
        }

        return false;
    }

    /**
     * Save raw signature file without stamp (fallback)
     *
     * @param string $decoded Decoded image data
     * @param string $orderRef Order reference
     * @return string|bool File path or false on error
     */
    private function saveRawSignatureFile($decoded, $orderRef)
    {
        global $conf;

        if (!empty($orderRef)) {
            $dir = $conf->commande->dir_output.'/'.$orderRef;
        } else {
            $dir = $conf->zonajob->dir_output.'/signatures';
        }

        if (!is_dir($dir)) {
            dol_mkdir($dir);
        }

        $filename = 'signature_'.$this->ref.'_'.dol_print_date(dol_now(), 'dayhourlog').'.png';
        $filepath = $dir.'/'.$filename;

        if (file_put_contents($filepath, $decoded)) {
            $this->signature_file = $filename;
            if (!empty($orderRef)) {
                $this->signature_file = $orderRef.'/'.$filename;
            }
            return $filepath;
        }

        return false;
    }

    /**
     * Add geolocation and timestamp stamp to signature image
     *
     * @param resource $image GD image resource
     * @return resource Stamped image resource
     */
    private function addStampToSignature($image)
    {
        $width = imagesx($image);
        $height = imagesy($image);

        // Create new canvas with extra space for stamp (40px at bottom)
        $stampHeight = 40;
        $newHeight = $height + $stampHeight;
        $newImage = imagecreatetruecolor($width, $newHeight);

        // Make background white
        $white = imagecolorallocate($newImage, 255, 255, 255);
        imagefill($newImage, 0, 0, $white);

        // Copy original signature to new canvas
        imagecopy($newImage, $image, 0, 0, 0, 0, $width, $height);

        // Prepare stamp text
        $stampColor = imagecolorallocate($newImage, 80, 80, 80); // Dark gray
        $lineColor = imagecolorallocate($newImage, 200, 200, 200); // Light gray for separator

        // Draw separator line
        imageline($newImage, 5, $height + 2, $width - 5, $height + 2, $lineColor);

        // Build stamp text
        $dateText = 'Fecha: '.dol_print_date($this->date_signature ?: dol_now(), 'dayhour');
        $geoText = '';
        if (!empty($this->latitude) && !empty($this->longitude)) {
            $geoText = 'GPS: '.$this->latitude.', '.$this->longitude;
        }
        $ipText = 'IP: '.($this->ip_address ?: getUserRemoteIP());

        // Use built-in font (size 2 = small)
        $font = 2;
        $lineHeight = 12;
        $yStart = $height + 8;

        // Draw stamp texts
        imagestring($newImage, $font, 5, $yStart, $dateText, $stampColor);
        if (!empty($geoText)) {
            imagestring($newImage, $font, 5, $yStart + $lineHeight, $geoText, $stampColor);
            imagestring($newImage, $font, 5, $yStart + ($lineHeight * 2), $ipText, $stampColor);
        } else {
            imagestring($newImage, $font, 5, $yStart + $lineHeight, $ipText, $stampColor);
        }

        // Add signer name on the right
        if (!empty($this->signer_name)) {
            $signerText = 'Firmante: '.$this->signer_name;
            $textWidth = imagefontwidth($font) * strlen($signerText);
            imagestring($newImage, $font, $width - $textWidth - 5, $yStart, $signerText, $stampColor);
        }

        return $newImage;
    }

    /**
     * Get next ref
     *
     * @return string
     */
    public function getNextRef()
    {
        global $conf;

        $sql = "SELECT MAX(CAST(SUBSTRING(ref, 5) AS UNSIGNED)) as max_num";
        $sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE ref LIKE 'SIG-%'";
        $sql .= " AND entity = ".$conf->entity;

        $result = $this->db->query($sql);
        if ($result) {
            $obj = $this->db->fetch_object($result);
            $num = ($obj->max_num ? $obj->max_num + 1 : 1);
            return 'SIG-'.sprintf('%06d', $num);
        }

        return 'SIG-'.sprintf('%06d', 1);
    }

    /**
     * Get status label
     *
     * @param int $mode Mode (0=long, 1=short, 2=picto)
     * @return string Status label
     */
    public function getLibStatut($mode = 0)
    {
        return self::LibStatut($this->status, $mode);
    }

    /**
     * Get status label (static)
     *
     * @param int $status Status
     * @param int $mode Mode
     * @return string
     */
    public static function LibStatut($status, $mode = 0)
    {
        global $langs;

        $labelStatus = array(
            self::STATUS_CANCELLED => 'Cancelado',
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_SIGNED => 'Firmado'
        );

        $labelStatusShort = array(
            self::STATUS_CANCELLED => 'Cancelado',
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_SIGNED => 'Firmado'
        );

        $statusType = array(
            self::STATUS_CANCELLED => 'status5',
            self::STATUS_PENDING => 'status1',
            self::STATUS_SIGNED => 'status4'
        );

        if ($mode == 0) {
            return $labelStatus[$status];
        } elseif ($mode == 1) {
            return $labelStatusShort[$status];
        } elseif ($mode == 2) {
            return dolGetStatus($labelStatus[$status], $labelStatusShort[$status], '', $statusType[$status], 2);
        }

        return $labelStatus[$status];
    }
}
