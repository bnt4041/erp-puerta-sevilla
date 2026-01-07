<?php
/* Copyright (C) 2025 ZonaJob Dev
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    class/zonajobphoto.class.php
 * \ingroup zonajob
 * \brief   Class for ZonaJob photos
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

/**
 * Class ZonaJobPhoto
 */
class ZonaJobPhoto extends CommonObject
{
    /**
     * @var string ID to identify managed object
     */
    public $element = 'zonajob_photo';

    /**
     * @var string Name of table without prefix
     */
    public $table_element = 'zonajob_photo';

    /**
     * @var int Order ID
     */
    public $fk_commande;

    /**
     * @var string Filename
     */
    public $filename;

    /**
     * @var string Filepath
     */
    public $filepath;

    /**
     * @var string File type (mime)
     */
    public $filetype;

    /**
     * @var int File size
     */
    public $filesize;

    /**
     * @var string Description
     */
    public $description;

    /**
     * @var string Photo type (general, before, after, etc.)
     */
    public $photo_type;

    /**
     * @var string Latitude
     */
    public $latitude;

    /**
     * @var string Longitude
     */
    public $longitude;

    /**
     * @var int Creation date
     */
    public $date_creation;

    /**
     * @var int User who created
     */
    public $fk_user_creat;

    /**
     * @var int Entity
     */
    public $entity;

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
     * Create photo record
     *
     * @param User $user User object
     * @return int <0 if KO, >0 if OK
     */
    public function create($user)
    {
        global $conf;

        $error = 0;
        $now = dol_now();

        $this->entity = $conf->entity;
        $this->date_creation = $now;
        $this->fk_user_creat = $user->id;

        $this->db->begin();

        $sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (";
        $sql .= "fk_commande, filename, filepath, filetype, filesize, description,";
        $sql .= "photo_type, latitude, longitude, date_creation, fk_user_creat, entity";
        $sql .= ") VALUES (";
        $sql .= $this->fk_commande.",";
        $sql .= "'".$this->db->escape($this->filename)."',";
        $sql .= "'".$this->db->escape($this->filepath)."',";
        $sql .= (!empty($this->filetype) ? "'".$this->db->escape($this->filetype)."'" : "NULL").",";
        $sql .= ($this->filesize > 0 ? $this->filesize : "NULL").",";
        $sql .= (!empty($this->description) ? "'".$this->db->escape($this->description)."'" : "NULL").",";
        $sql .= "'".$this->db->escape($this->photo_type ?: 'general')."',";
        $sql .= (!empty($this->latitude) ? "'".$this->db->escape($this->latitude)."'" : "NULL").",";
        $sql .= (!empty($this->longitude) ? "'".$this->db->escape($this->longitude)."'" : "NULL").",";
        $sql .= "'".$this->db->idate($this->date_creation)."',";
        $sql .= $user->id.",";
        $sql .= $conf->entity;
        $sql .= ")";

        $result = $this->db->query($sql);

        if ($result) {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
            $this->db->commit();
            return $this->id;
        } else {
            $error++;
            $this->error = $this->db->lasterror();
            $this->errors[] = $this->error;
            $this->db->rollback();
            return -1;
        }
    }

    /**
     * Fetch photo by ID
     *
     * @param int $id Photo ID
     * @return int <0 if KO, >0 if OK
     */
    public function fetch($id)
    {
        $sql = "SELECT rowid, fk_commande, filename, filepath, filetype, filesize,";
        $sql .= " description, photo_type, latitude, longitude, date_creation, fk_user_creat, entity";
        $sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE rowid = ".((int) $id);

        $result = $this->db->query($sql);

        if ($result) {
            if ($this->db->num_rows($result)) {
                $obj = $this->db->fetch_object($result);

                $this->id = $obj->rowid;
                $this->fk_commande = $obj->fk_commande;
                $this->filename = $obj->filename;
                $this->filepath = $obj->filepath;
                $this->filetype = $obj->filetype;
                $this->filesize = $obj->filesize;
                $this->description = $obj->description;
                $this->photo_type = $obj->photo_type;
                $this->latitude = $obj->latitude;
                $this->longitude = $obj->longitude;
                $this->date_creation = $this->db->jdate($obj->date_creation);
                $this->fk_user_creat = $obj->fk_user_creat;
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
     * Delete photo
     *
     * @param User $user User object
     * @return int <0 if KO, >0 if OK
     */
    public function delete($user)
    {
        global $conf;

        // Delete file from disk
        if (!empty($this->filepath)) {
            $fullpath = $conf->zonajob->dir_output.'/photos/'.$this->filepath;
            if (file_exists($fullpath)) {
                @unlink($fullpath);
            }
        }

        $sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE rowid = ".$this->id;

        $result = $this->db->query($sql);

        if ($result) {
            return 1;
        } else {
            $this->error = $this->db->lasterror();
            return -1;
        }
    }

    /**
     * Get photos for order
     *
     * @param int $fk_commande Order ID
     * @return array Array of photo objects
     */
    public function getPhotosForOrder($fk_commande)
    {
        global $conf;

        $photos = array();

        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE fk_commande = ".((int) $fk_commande);
        $sql .= " AND entity = ".$conf->entity;
        $sql .= " ORDER BY date_creation ASC";

        $result = $this->db->query($sql);

        if ($result) {
            while ($obj = $this->db->fetch_object($result)) {
                $photo = new ZonaJobPhoto($this->db);
                $photo->fetch($obj->rowid);
                $photos[] = $photo;
            }
        }

        return $photos;
    }

    /**
     * Upload photo for order
     *
     * @param int $fk_commande Order ID
     * @param array $fileinfo File info from $_FILES
     * @param User $user User object
     * @param string $photo_type Photo type
     * @param string $description Description
     * @param string $latitude Latitude
     * @param string $longitude Longitude
     * @return int <0 if KO, photo ID if OK
     */
    public function uploadPhoto($fk_commande, $fileinfo, $user, $photo_type = 'general', $description = '', $latitude = '', $longitude = '')
    {
        global $conf;

        if (empty($fileinfo) || empty($fileinfo['tmp_name'])) {
            $this->error = 'No file uploaded';
            return -1;
        }

        // Check file type
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        if (!in_array($fileinfo['type'], $allowed_types)) {
            $this->error = 'File type not allowed';
            return -2;
        }

        // Check file size (max 10MB)
        $max_size = 10 * 1024 * 1024;
        if ($fileinfo['size'] > $max_size) {
            $this->error = 'File too large';
            return -3;
        }

        // Create directory if not exists
        $upload_dir = $conf->zonajob->dir_output.'/photos/'.$fk_commande;
        if (!is_dir($upload_dir)) {
            dol_mkdir($upload_dir);
        }

        // Generate unique filename
        $ext = pathinfo($fileinfo['name'], PATHINFO_EXTENSION);
        $filename = 'photo_'.dol_print_date(dol_now(), 'dayhourlog').'_'.dol_hash(uniqid(), 3).'.'.$ext;
        $filepath = $upload_dir.'/'.$filename;

        // Move uploaded file
        if (!dol_move_uploaded_file($fileinfo['tmp_name'], $filepath, 1, 0, $fileinfo['error'], 0, $varfiles = 'addedfile', $upload_dir)) {
            // Fallback to move_uploaded_file
            if (!move_uploaded_file($fileinfo['tmp_name'], $filepath)) {
                $this->error = 'Failed to save file';
                return -4;
            }
        }

        // Create database record
        $this->fk_commande = $fk_commande;
        $this->filename = $fileinfo['name'];
        $this->filepath = $fk_commande.'/'.$filename;
        $this->filetype = $fileinfo['type'];
        $this->filesize = $fileinfo['size'];
        $this->description = $description;
        $this->photo_type = $photo_type;
        $this->latitude = $latitude;
        $this->longitude = $longitude;

        $result = $this->create($user);
        
        if ($result > 0) {
            // Add to ECM (Dolibarr's document management system)
            require_once DOL_DOCUMENT_ROOT.'/ecm/class/ecmfiles.class.php';
            $ecmfile = new EcmFiles($this->db);
            $ecmfile->filepath = 'zonajob/photos/'.$fk_commande;
            $ecmfile->filename = $filename;
            $ecmfile->label = $description ?: 'Photo '.$photo_type;
            $ecmfile->fullpath_orig = $filepath;
            $ecmfile->gen_or_uploaded = 'uploaded';
            $ecmfile->description = $description;
            $ecmfile->keywords = 'zonajob,photo,'.$photo_type;
            $ecmfile->src_object_type = 'commande';
            $ecmfile->src_object_id = $fk_commande;
            $ecmfile->fk_user_c = $user->id;
            $ecmfile->date_c = dol_now();
            $ecmfile->entity = $conf->entity;
            
            $ecmfile->create($user);
        }

        return $result;
    }

    /**
     * Get photo URL
     *
     * @return string URL to photo
     */
    public function getPhotoUrl()
    {
        global $conf;

        if (empty($this->filepath)) {
            return '';
        }

        return DOL_URL_ROOT.'/custom/zonajob/viewphoto.php?file='.urlencode($this->filepath);
    }

    /**
     * Get thumbnail URL
     *
     * @param int $maxwidth Max width
     * @param int $maxheight Max height
     * @return string URL to thumbnail
     */
    public function getThumbnailUrl($maxwidth = 150, $maxheight = 150)
    {
        global $conf;

        if (empty($this->filepath)) {
            return '';
        }

        return DOL_URL_ROOT.'/custom/zonajob/viewphoto.php?file='.urlencode($this->filepath).'&thumb=1&w='.$maxwidth.'&h='.$maxheight;
    }
}
