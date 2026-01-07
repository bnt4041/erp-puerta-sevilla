<?php
/* Copyright (C) 2025 ZonaJob Dev
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    class/zonajobsender.class.php
 * \ingroup zonajob
 * \brief   Class for sending orders via WhatsApp/Email
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class ZonaJobSender
 */
class ZonaJobSender extends CommonObject
{
    /**
     * @var string ID to identify managed object
     */
    public $element = 'zonajob_send_history';

    /**
     * @var string Name of table without prefix
     */
    public $table_element = 'zonajob_send_history';

    /**
     * @var int Order ID
     */
    public $fk_commande;

    /**
     * @var int Signature ID
     */
    public $fk_signature;

    /**
     * @var int Third party ID
     */
    public $fk_soc;

    /**
     * @var int Contact ID
     */
    public $fk_socpeople;

    /**
     * @var string Send type (whatsapp, email)
     */
    public $send_type;

    /**
     * @var string Recipient
     */
    public $recipient;

    /**
     * @var string Subject
     */
    public $subject;

    /**
     * @var string Message
     */
    public $message;

    /**
     * @var int Status (0=pending, 1=sent, -1=failed)
     */
    public $status;

    /**
     * @var string Error message
     */
    public $error_message;

    /**
     * @var int Creation date
     */
    public $date_creation;

    /**
     * @var int Send date
     */
    public $date_send;

    /**
     * @var int User who created
     */
    public $fk_user_creat;

    /**
     * @var int Entity
     */
    public $entity;

    /**
     * Status constants
     */
    const STATUS_PENDING = 0;
    const STATUS_SENT = 1;
    const STATUS_FAILED = -1;

    const TYPE_WHATSAPP = 'whatsapp';
    const TYPE_EMAIL = 'email';

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
     * Create send history record
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
        $this->status = self::STATUS_PENDING;

        $sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (";
        $sql .= "fk_commande, fk_signature, fk_soc, fk_socpeople, send_type, recipient,";
        $sql .= "subject, message, status, error_message, date_creation, date_send, fk_user_creat, entity";
        $sql .= ") VALUES (";
        $sql .= ($this->fk_commande > 0 ? $this->fk_commande : "NULL").",";
        $sql .= ($this->fk_signature > 0 ? $this->fk_signature : "NULL").",";
        $sql .= ($this->fk_soc > 0 ? $this->fk_soc : "NULL").",";
        $sql .= ($this->fk_socpeople > 0 ? $this->fk_socpeople : "NULL").",";
        $sql .= "'".$this->db->escape($this->send_type)."',";
        $sql .= "'".$this->db->escape($this->recipient)."',";
        $sql .= (!empty($this->subject) ? "'".$this->db->escape($this->subject)."'" : "NULL").",";
        $sql .= (!empty($this->message) ? "'".$this->db->escape($this->message)."'" : "NULL").",";
        $sql .= $this->status.",";
        $sql .= "NULL,";
        $sql .= "'".$this->db->idate($this->date_creation)."',";
        $sql .= "NULL,";
        $sql .= $user->id.",";
        $sql .= $conf->entity;
        $sql .= ")";

        $result = $this->db->query($sql);

        if ($result) {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
            return $this->id;
        } else {
            $this->error = $this->db->lasterror();
            return -1;
        }
    }

    /**
     * Update send history record
     *
     * @return int <0 if KO, >0 if OK
     */
    public function update()
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET";
        $sql .= " status = ".$this->status.",";
        $sql .= " error_message = ".(!empty($this->error_message) ? "'".$this->db->escape($this->error_message)."'" : "NULL").",";
        $sql .= " date_send = ".($this->date_send ? "'".$this->db->idate($this->date_send)."'" : "NULL");
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
     * Send order via WhatsApp
     *
     * @param Commande $order Order object
     * @param string $phone Phone number
     * @param string $message Message
     * @param User $user User object
     * @param int $fk_socpeople Contact ID (optional)
     * @param string $pdfFile PDF file path (optional)
     * @return int <0 if KO, >0 if OK
     */
    public function sendWhatsApp($order, $phone, $message, $user, $fk_socpeople = 0, $pdfFile = '')
    {
        global $conf;

        // Check if WhatsApp module is enabled
        if (empty($conf->whatsapp->enabled)) {
            $this->error = 'WhatsApp module not enabled';
            return -1;
        }

        // Initialize record
        $this->fk_commande = $order->id;
        $this->fk_soc = $order->socid;
        $this->fk_socpeople = $fk_socpeople;
        $this->send_type = self::TYPE_WHATSAPP;
        $this->recipient = $phone;
        $this->message = $message;

        $record_id = $this->create($user);
        if ($record_id < 0) {
            return -1;
        }

        // Load WhatsApp client
        require_once DOL_DOCUMENT_ROOT.'/custom/whatsapp/class/gowaclient.class.php';
        $client = new GoWAClient($this->db);

        // Send message
        $result = $client->sendMessage($phone, $message);

        if ($result['error'] == 0) {
            $this->status = self::STATUS_SENT;
            $this->date_send = dol_now();
            $this->update();
            return 1;
        } else {
            $this->status = self::STATUS_FAILED;
            $this->error_message = $result['message'];
            $this->update();
            $this->error = $result['message'];
            return -2;
        }
    }

    /**
     * Send order via Email
     *
     * @param Commande $order Order object
     * @param string $email Email address
     * @param string $subject Subject
     * @param string $message Message
     * @param User $user User object
     * @param int $fk_socpeople Contact ID (optional)
     * @param string $pdfFile PDF file path (optional)
     * @return int <0 if KO, >0 if OK
     */
    public function sendEmail($order, $email, $subject, $message, $user, $fk_socpeople = 0, $pdfFile = '')
    {
        global $conf, $langs, $mysoc;

        require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';

        // Initialize record
        $this->fk_commande = $order->id;
        $this->fk_soc = $order->socid;
        $this->fk_socpeople = $fk_socpeople;
        $this->send_type = self::TYPE_EMAIL;
        $this->recipient = $email;
        $this->subject = $subject;
        $this->message = $message;

        $record_id = $this->create($user);
        if ($record_id < 0) {
            return -1;
        }

        // From
        $from = $conf->global->MAIN_MAIL_EMAIL_FROM;
        if (empty($from)) {
            $from = $mysoc->email;
        }

        // Attachments
        $attachments = array();
        if (!empty($pdfFile) && file_exists($pdfFile)) {
            $attachments[] = $pdfFile;
        }

        // Send email
        $mail = new CMailFile(
            $subject,
            $email,
            $from,
            $message,
            $attachments,
            array(),
            array(),
            '',
            '',
            0,
            1 // HTML
        );

        $result = $mail->sendfile();

        if ($result) {
            $this->status = self::STATUS_SENT;
            $this->date_send = dol_now();
            $this->update();
            return 1;
        } else {
            $this->status = self::STATUS_FAILED;
            $this->error_message = $mail->error;
            $this->update();
            $this->error = $mail->error;
            return -2;
        }
    }

    /**
     * Get send history for order
     *
     * @param int $fk_commande Order ID
     * @return array Array of send history objects
     */
    public function getHistoryForOrder($fk_commande)
    {
        global $conf;

        $history = array();

        $sql = "SELECT rowid, fk_commande, fk_signature, fk_soc, fk_socpeople, send_type,";
        $sql .= " recipient, subject, message, status, error_message, date_creation, date_send, fk_user_creat";
        $sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE fk_commande = ".((int) $fk_commande);
        $sql .= " AND entity = ".$conf->entity;
        $sql .= " ORDER BY date_creation DESC";

        $result = $this->db->query($sql);

        if ($result) {
            while ($obj = $this->db->fetch_object($result)) {
                $history[] = array(
                    'id' => $obj->rowid,
                    'send_type' => $obj->send_type,
                    'recipient' => $obj->recipient,
                    'subject' => $obj->subject,
                    'message' => $obj->message,
                    'status' => $obj->status,
                    'error_message' => $obj->error_message,
                    'date_creation' => $this->db->jdate($obj->date_creation),
                    'date_send' => $this->db->jdate($obj->date_send)
                );
            }
        }

        return $history;
    }

    /**
     * Get available recipients for order
     *
     * @param Commande $order Order object
     * @return array Array of recipients
     */
    public function getRecipientsForOrder($order)
    {
        global $langs;

        $recipients = array();

        // Get third party
        if ($order->socid > 0) {
            require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
            $soc = new Societe($this->db);
            $soc->fetch($order->socid);

            if (!empty($soc->email) || !empty($soc->phone)) {
                $recipients[] = array(
                    'type' => 'thirdparty',
                    'id' => $soc->id,
                    'name' => $soc->name,
                    'email' => $soc->email,
                    'phone' => $soc->phone,
                    'label' => $soc->name.' ('.$langs->trans('ThirdParty').')'
                );
            }
        }

        // Get contacts
        if ($order->socid > 0) {
            require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';

            $sql = "SELECT rowid, lastname, firstname, email, phone, phone_mobile";
            $sql .= " FROM ".MAIN_DB_PREFIX."socpeople";
            $sql .= " WHERE fk_soc = ".((int) $order->socid);
            $sql .= " AND statut = 1";

            $result = $this->db->query($sql);

            if ($result) {
                while ($obj = $this->db->fetch_object($result)) {
                    $phone = !empty($obj->phone_mobile) ? $obj->phone_mobile : $obj->phone;
                    if (!empty($obj->email) || !empty($phone)) {
                        $name = trim($obj->firstname.' '.$obj->lastname);
                        $recipients[] = array(
                            'type' => 'contact',
                            'id' => $obj->rowid,
                            'name' => $name,
                            'email' => $obj->email,
                            'phone' => $phone,
                            'label' => $name.' ('.$langs->trans('Contact').')'
                        );
                    }
                }
            }
        }

        // Get order contacts
        $contacts = $order->liste_contact(-1, 'external');
        if (is_array($contacts)) {
            foreach ($contacts as $contact) {
                // Check if already in list
                $found = false;
                foreach ($recipients as $r) {
                    if ($r['type'] == 'contact' && $r['id'] == $contact['id']) {
                        $found = true;
                        break;
                    }
                }

                if (!$found && (!empty($contact['email']) || !empty($contact['phone']))) {
                    $recipients[] = array(
                        'type' => 'contact',
                        'id' => $contact['id'],
                        'name' => $contact['firstname'].' '.$contact['lastname'],
                        'email' => $contact['email'],
                        'phone' => !empty($contact['phone_mobile']) ? $contact['phone_mobile'] : $contact['phone'],
                        'label' => $contact['firstname'].' '.$contact['lastname'].' ('.$contact['libelle'].')'
                    );
                }
            }
        }

        return $recipients;
    }
}
