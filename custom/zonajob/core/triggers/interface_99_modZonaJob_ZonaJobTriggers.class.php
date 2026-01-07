<?php
/* Copyright (C) 2025 ZonaJob Dev
 *
 * Triggers for ZonaJob module
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 * Class InterfaceZonaJobTriggers
 * Triggers for ZonaJob events
 */
class InterfaceZonaJobTriggers extends DolibarrTriggers
{
    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = "zonajob";
        $this->description = "ZonaJob triggers";
        $this->version = '1.0';
        $this->picto = 'zonajob@zonajob';
    }

    /**
     * Return name of trigger file
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Return description of trigger file
     *
     * @return string
     */
    public function getDesc()
    {
        return $this->description;
    }

    /**
     * Function called when a Dolibar business event is done.
     *
     * @param string        $action     Event action code
     * @param Object        $object     Object
     * @param User          $user       Object user
     * @param Translate     $langs      Object langs
     * @param conf          $conf       Object conf
     * @return int                      <0 if KO, 0 if no action, >0 if OK
     */
    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        if (empty($conf->zonajob) || empty($conf->zonajob->enabled)) {
            return 0;
        }

        // Put here code you want to execute when a Dolibarr business event occurs
        switch ($action) {
            // Orders
            case 'ORDER_VALIDATE':
                dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__);
                // Could auto-create signature record when order is validated
                break;

            case 'ORDER_CLOSE':
                dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__);
                break;

            case 'ORDER_CANCEL':
                dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__);
                // Cancel any pending signatures
                $this->cancelPendingSignatures($object->id);
                break;

            case 'ORDER_DELETE':
                dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__);
                // Clean up zonajob data
                $this->cleanupOrderData($object->id);
                break;

            // ZonaJob specific events
            case 'ZONAJOB_SIGNATURE_CREATE':
                dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__);
                break;

            case 'ZONAJOB_SIGNATURE_SIGN':
                dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__);
                // Auto-send if configured
                if (getDolGlobalInt('ZONAJOB_AUTO_SEND_ON_SIGN')) {
                    $this->autoSendAfterSign($object);
                }
                break;

            case 'ZONAJOB_PHOTO_UPLOAD':
                dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__);
                break;

            case 'ZONAJOB_SEND_WHATSAPP':
                dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__);
                break;

            case 'ZONAJOB_SEND_EMAIL':
                dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__);
                break;

            default:
                break;
        }

        return 0;
    }

    /**
     * Cancel pending signatures when order is cancelled
     *
     * @param int $orderId Order ID
     * @return int
     */
    private function cancelPendingSignatures($orderId)
    {
        dol_include_once('/zonajob/class/zonajobsignature.class.php');
        
        $sql = "UPDATE ".MAIN_DB_PREFIX."zonajob_signature";
        $sql .= " SET status = -1";
        $sql .= " WHERE fk_commande = ".((int) $orderId);
        $sql .= " AND status = 0";
        
        return $this->db->query($sql);
    }

    /**
     * Clean up all ZonaJob data when order is deleted
     *
     * @param int $orderId Order ID
     * @return int
     */
    private function cleanupOrderData($orderId)
    {
        global $conf;
        
        // Delete signatures
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."zonajob_signature";
        $sql .= " WHERE fk_commande = ".((int) $orderId);
        $this->db->query($sql);
        
        // Delete photos (and files)
        dol_include_once('/zonajob/class/zonajobphoto.class.php');
        $photo = new ZonaJobPhoto($this->db);
        $photos = $photo->getPhotosForOrder($orderId);
        foreach ($photos as $p) {
            $photoObj = new ZonaJobPhoto($this->db);
            $photoObj->fetch($p['rowid']);
            $photoObj->delete($user);
        }
        
        // Delete send history
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."zonajob_send_history";
        $sql .= " WHERE fk_commande = ".((int) $orderId);
        $this->db->query($sql);
        
        return 1;
    }

    /**
     * Auto-send order after signature if configured
     *
     * @param Object $signature Signature object
     * @return int
     */
    private function autoSendAfterSign($signature)
    {
        global $conf, $user;
        
        dol_include_once('/zonajob/class/zonajobsender.class.php');
        
        $sender = new ZonaJobSender($this->db);
        
        // Get default send method
        $method = getDolGlobalString('ZONAJOB_DEFAULT_SEND_METHOD', 'email');
        
        // Get recipients
        $recipients = $sender->getRecipientsForOrder($signature->fk_commande);
        
        if (empty($recipients)) {
            return 0;
        }
        
        // Get first recipient
        $recipient = reset($recipients);
        
        // Send based on method
        if ($method == 'whatsapp' && !empty($recipient['phone'])) {
            return $sender->sendWhatsApp(
                $signature->fk_commande,
                $recipient['phone'],
                $user->id,
                '',
                true,
                true
            );
        } elseif (!empty($recipient['email'])) {
            return $sender->sendEmail(
                $signature->fk_commande,
                $recipient['email'],
                $user->id,
                '',
                '',
                true,
                true
            );
        }
        
        return 0;
    }
}
