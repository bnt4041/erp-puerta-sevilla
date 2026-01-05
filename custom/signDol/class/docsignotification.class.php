<?php
/* Copyright (C) 2026 DocSig Module
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    htdocs/custom/signDol/class/docsignotification.class.php
 * \ingroup docsig
 * \brief   Sistema de notificaciones para DocSig (arquitectura hexagonal)
 */

/**
 * Interface para proveedores de notificación
 */
interface DocSigNotificationProviderInterface
{
    /**
     * Envía una notificación
     *
     * @param string $destination Destino (email, teléfono, etc.)
     * @param string $subject Asunto
     * @param string $bodyText Cuerpo en texto plano
     * @param string $bodyHtml Cuerpo en HTML
     * @param array $options Opciones adicionales
     * @return array Array con success, messageId, error
     */
    public function send($destination, $subject, $bodyText, $bodyHtml = '', $options = array());

    /**
     * Obtiene el nombre del canal
     *
     * @return string
     */
    public function getChannel();
}

/**
 * Proveedor de notificaciones por Email usando Dolibarr
 */
class DocSigEmailProvider implements DocSigNotificationProviderInterface
{
    /**
     * @var DoliDB Base de datos
     */
    private $db;

    /**
     * Constructor
     *
     * @param DoliDB $db Base de datos
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function send($destination, $subject, $bodyText, $bodyHtml = '', $options = array())
    {
        global $conf, $langs;

        require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';

        $result = array(
            'success' => false,
            'messageId' => null,
            'error' => null
        );

        // Configurar remitente
        $from = getDolGlobalString('MAIN_MAIL_EMAIL_FROM');
        if (empty($from)) {
            $from = $conf->global->MAIN_INFO_SOCIETE_MAIL ?? 'noreply@example.com';
        }

        // Nombre del remitente
        $fromName = getDolGlobalString('MAIN_INFO_SOCIETE_NOM', 'DocSig');

        // Crear email
        $mail = new CMailFile(
            $subject,
            $destination,
            $from,
            $bodyHtml ?: $bodyText,
            array(), // files
            array(), // mimefilename
            array(), // mimetype
            '', // cc
            '', // bcc
            0, // deliveryreceipt
            !empty($bodyHtml) ? 1 : 0, // ishtml
            '', // errors_to
            '', // css
            '', // trackid
            '', // moreinheader
            'standard', // sendcontext
            '', // replyto
            '' // upload_dir_tmp
        );

        // Enviar
        $sendResult = $mail->sendfile();

        if ($sendResult) {
            $result['success'] = true;
            $result['messageId'] = uniqid('docsig_');
            dol_syslog('DocSigEmailProvider::send - Email sent to ' . $destination, LOG_INFO);
        } else {
            $result['error'] = $mail->error;
            dol_syslog('DocSigEmailProvider::send - Error: ' . $mail->error, LOG_ERR);
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getChannel()
    {
        return 'email';
    }
}

/**
 * Proveedor de notificaciones por SMS (preparado para futuro)
 */
class DocSigSMSProvider implements DocSigNotificationProviderInterface
{
    /**
     * @var DoliDB Base de datos
     */
    private $db;

    /**
     * @var string URL del API de SMS
     */
    private $apiUrl;

    /**
     * @var string API Key
     */
    private $apiKey;

    /**
     * Constructor
     *
     * @param DoliDB $db Base de datos
     */
    public function __construct($db)
    {
        $this->db = $db;
        $this->apiUrl = getDolGlobalString('DOCSIG_SMS_API_URL', '');
        $this->apiKey = getDolGlobalString('DOCSIG_SMS_API_KEY', '');
    }

    /**
     * @inheritDoc
     */
    public function send($destination, $subject, $bodyText, $bodyHtml = '', $options = array())
    {
        $result = array(
            'success' => false,
            'messageId' => null,
            'error' => 'SMS provider not configured'
        );

        // TODO: Implementar integración con API de SMS
        // Ejemplo: Twilio, Nexmo, etc.

        if (empty($this->apiUrl) || empty($this->apiKey)) {
            return $result;
        }

        // Implementación placeholder
        dol_syslog('DocSigSMSProvider::send - SMS not implemented', LOG_WARNING);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getChannel()
    {
        return 'sms';
    }
}

/**
 * Servicio principal de notificaciones (Facade)
 */
class DocSigNotificationService
{
    /**
     * @var DoliDB Base de datos
     */
    private $db;

    /**
     * @var array Proveedores disponibles
     */
    private $providers = array();

    /**
     * @var string Último error
     */
    public $error;

    /**
     * Constructor
     *
     * @param DoliDB $db Base de datos
     */
    public function __construct($db)
    {
        $this->db = $db;

        // Registrar proveedores disponibles
        $this->providers['email'] = new DocSigEmailProvider($db);
        $this->providers['sms'] = new DocSigSMSProvider($db);
    }

    /**
     * Envía una notificación y la registra en la base de datos
     *
     * @param string $channel Canal de notificación (email, sms)
     * @param string $destination Destino
     * @param string $subject Asunto
     * @param string $bodyText Cuerpo texto
     * @param string $bodyHtml Cuerpo HTML
     * @param array $context Contexto (envelope_id, signer_id, contact_id, type)
     * @return int ID de la notificación creada o -1 si falla
     */
    public function send($channel, $destination, $subject, $bodyText, $bodyHtml = '', $context = array())
    {
        global $user;

        // Verificar que el proveedor existe
        if (!isset($this->providers[$channel])) {
            $this->error = 'Unknown notification channel: ' . $channel;
            return -1;
        }

        $provider = $this->providers[$channel];

        // Crear registro de notificación
        $notificationId = $this->createNotificationRecord($channel, $destination, $subject, $bodyText, $bodyHtml, $context);
        if ($notificationId < 0) {
            return -1;
        }

        // Enviar notificación
        $result = $provider->send($destination, $subject, $bodyText, $bodyHtml);

        // Actualizar estado
        if ($result['success']) {
            $this->updateNotificationStatus($notificationId, 1, null, dol_now());
            
            // Crear registro en actioncomm si hay contacto
            if (!empty($context['contact_id'])) {
                $this->createActionComm($context['contact_id'], $subject, $bodyHtml ?: $bodyText, $context);
            }
        } else {
            $this->updateNotificationStatus($notificationId, 2, $result['error']);
            $this->error = $result['error'];
        }

        return $result['success'] ? $notificationId : -1;
    }

    /**
     * Crea el registro de notificación en la base de datos
     *
     * @param string $channel Canal
     * @param string $destination Destino
     * @param string $subject Asunto
     * @param string $bodyText Cuerpo texto
     * @param string $bodyHtml Cuerpo HTML
     * @param array $context Contexto
     * @return int ID creado o -1 si falla
     */
    private function createNotificationRecord($channel, $destination, $subject, $bodyText, $bodyHtml, $context)
    {
        global $user, $conf;

        $sql = "INSERT INTO ".MAIN_DB_PREFIX."docsig_notification (";
        $sql .= "fk_envelope, fk_signer, fk_socpeople, envelope_ref,";
        $sql .= "notification_type, channel, destination,";
        $sql .= "subject, body_text, body_html,";
        $sql .= "status, date_creation, fk_user_create";
        $sql .= ") VALUES (";
        $sql .= (isset($context['envelope_id']) ? (int)$context['envelope_id'] : "NULL").",";
        $sql .= (isset($context['signer_id']) ? (int)$context['signer_id'] : "NULL").",";
        $sql .= (isset($context['contact_id']) ? (int)$context['contact_id'] : "NULL").",";
        $sql .= (isset($context['envelope_ref']) ? "'".$this->db->escape($context['envelope_ref'])."'" : "NULL").",";
        $sql .= "'".$this->db->escape($context['type'] ?? 'general')."',";
        $sql .= "'".$this->db->escape($channel)."',";
        $sql .= "'".$this->db->escape($destination)."',";
        $sql .= "'".$this->db->escape($subject)."',";
        $sql .= "'".$this->db->escape($bodyText)."',";
        $sql .= "'".$this->db->escape($bodyHtml)."',";
        $sql .= "0,"; // status = pending
        $sql .= "'".$this->db->idate(dol_now())."',";
        $sql .= (int)($user->id ?? 0);
        $sql .= ")";

        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            dol_syslog('DocSigNotificationService::createNotificationRecord error: ' . $this->error, LOG_ERR);
            return -1;
        }

        return $this->db->last_insert_id(MAIN_DB_PREFIX."docsig_notification");
    }

    /**
     * Actualiza el estado de una notificación
     *
     * @param int $id ID de la notificación
     * @param int $status Nuevo estado
     * @param string|null $error Mensaje de error
     * @param int|null $sentAt Timestamp de envío
     * @return bool True si se actualizó
     */
    private function updateNotificationStatus($id, $status, $error = null, $sentAt = null)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."docsig_notification SET";
        $sql .= " status = ".(int)$status;
        if ($error !== null) {
            $sql .= ", error_message = '".$this->db->escape($error)."'";
        }
        if ($sentAt !== null) {
            $sql .= ", sent_at = '".$this->db->idate($sentAt)."'";
        }
        $sql .= " WHERE rowid = ".(int)$id;

        return $this->db->query($sql) ? true : false;
    }

    /**
     * Crea un registro en actioncomm vinculado al contacto
     *
     * @param int $contactId ID del contacto
     * @param string $subject Asunto
     * @param string $body Cuerpo
     * @param array $context Contexto adicional
     * @return int ID creado o -1 si falla
     */
    private function createActionComm($contactId, $subject, $body, $context = array())
    {
        global $user, $conf;

        require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
        require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';

        // Cargar contacto para obtener el tercero
        $contact = new Contact($this->db);
        if ($contact->fetch($contactId) <= 0) {
            return -1;
        }

        $actioncomm = new ActionComm($this->db);
        $actioncomm->type_code = 'AC_EMAIL';
        $actioncomm->code = 'AC_DOCSIG';
        $actioncomm->label = $subject;
        $actioncomm->note_private = $body;
        $actioncomm->datep = dol_now();
        $actioncomm->datef = dol_now();
        $actioncomm->percentage = -1; // Not applicable
        $actioncomm->socid = $contact->socid;
        $actioncomm->contact_id = $contactId;
        $actioncomm->authorid = $user->id ?? 0;
        $actioncomm->userownerid = $user->id ?? 0;
        $actioncomm->fk_element = $context['envelope_id'] ?? 0;
        $actioncomm->elementtype = 'docsig_envelope';

        $result = $actioncomm->create($user);

        if ($result > 0) {
            // Actualizar notificación con el ID de actioncomm
            if (!empty($context['notification_id'])) {
                $sql = "UPDATE ".MAIN_DB_PREFIX."docsig_notification SET";
                $sql .= " fk_actioncomm = ".(int)$result;
                $sql .= " WHERE rowid = ".(int)$context['notification_id'];
                $this->db->query($sql);
            }
        }

        return $result;
    }

    /**
     * Obtiene el historial de notificaciones de un contacto
     *
     * @param int $contactId ID del contacto
     * @param int $limit Límite de resultados
     * @return array Array de notificaciones
     */
    public function getContactNotifications($contactId, $limit = 50)
    {
        $notifications = array();

        $sql = "SELECT n.*, e.ref as envelope_ref_loaded";
        $sql .= " FROM ".MAIN_DB_PREFIX."docsig_notification as n";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."docsig_envelope as e ON e.rowid = n.fk_envelope";
        $sql .= " WHERE n.fk_socpeople = ".(int)$contactId;
        $sql .= " ORDER BY n.date_creation DESC";
        $sql .= " LIMIT ".(int)$limit;

        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $notifications[] = array(
                    'id' => $obj->rowid,
                    'envelope_id' => $obj->fk_envelope,
                    'envelope_ref' => $obj->envelope_ref ?: $obj->envelope_ref_loaded,
                    'type' => $obj->notification_type,
                    'channel' => $obj->channel,
                    'destination' => $obj->destination,
                    'subject' => $obj->subject,
                    'body_text' => $obj->body_text,
                    'status' => $obj->status,
                    'sent_at' => $obj->sent_at,
                    'date_creation' => $obj->date_creation,
                );
            }
            $this->db->free($resql);
        }

        return $notifications;
    }
}
