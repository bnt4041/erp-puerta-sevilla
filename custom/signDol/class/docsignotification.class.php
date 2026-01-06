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
 * Proveedor de notificaciones por WhatsApp usando GoWAClient
 */
class DocSigWhatsAppProvider implements DocSigNotificationProviderInterface
{
    /**
     * @var DoliDB Base de datos
     */
    private $db;

    /**
     * @var object GoWAClient instance
     */
    private $client;

    /**
     * @var bool Indica si el módulo WhatsApp está disponible
     */
    private $available = false;

    /**
     * Constructor
     *
     * @param DoliDB $db Base de datos
     */
    public function __construct($db)
    {
        global $conf;

        $this->db = $db;

        // Verificar si el módulo WhatsApp está habilitado y configurado
        if (isModEnabled('whatsapp') && !empty($conf->global->WHATSAPP_GOWA_URL)) {
            $clientPath = DOL_DOCUMENT_ROOT.'/custom/whatsapp/class/gowaclient.class.php';
            if (file_exists($clientPath)) {
                require_once $clientPath;
                $this->client = new GoWAClient($db);
                $this->available = true;
            }
        }
    }

    /**
     * Verifica si el proveedor está disponible
     *
     * @return bool True si WhatsApp está configurado
     */
    public function isAvailable()
    {
        return $this->available;
    }

    /**
     * @inheritDoc
     */
    public function send($destination, $subject, $bodyText, $bodyHtml = '', $options = array())
    {
        $result = array(
            'success' => false,
            'messageId' => null,
            'error' => null
        );

        // Verificar disponibilidad
        if (!$this->available) {
            $result['error'] = 'WhatsApp provider not available. Check module configuration.';
            dol_syslog('DocSigWhatsAppProvider::send - WhatsApp not available', LOG_WARNING);
            return $result;
        }

        // Limpiar número de teléfono
        $phone = $this->normalizePhoneNumber($destination);
        if (empty($phone)) {
            $result['error'] = 'Invalid phone number: ' . $destination;
            return $result;
        }

        // Construir mensaje - WhatsApp no usa HTML, usar texto plano
        // Incluir asunto como título si existe
        $message = '';
        if (!empty($subject)) {
            $message = "*" . $subject . "*\n\n";
        }

        // Usar texto plano o convertir HTML a texto si es necesario
        if (!empty($bodyText)) {
            $message .= $bodyText;
        } elseif (!empty($bodyHtml)) {
            // Convertir HTML a texto plano básico
            $message .= $this->htmlToWhatsApp($bodyHtml);
        }

        // Añadir enlace si está en las opciones
        if (!empty($options['signing_url'])) {
            $message .= "\n\n*Enlace de firma:*\n" . $options['signing_url'];
        }

        // Enviar usando GoWAClient
        try {
            $apiResult = $this->client->sendMessage($phone, $message);

            if ($apiResult['error'] == 0) {
                $result['success'] = true;
                $result['messageId'] = 'wa_' . uniqid();
                dol_syslog('DocSigWhatsAppProvider::send - Message sent to ' . $phone, LOG_INFO);
            } else {
                $result['error'] = $apiResult['message'] ?? 'Unknown WhatsApp error';
                dol_syslog('DocSigWhatsAppProvider::send - Error: ' . $result['error'], LOG_ERR);
            }
        } catch (Exception $e) {
            $result['error'] = 'WhatsApp Exception: ' . $e->getMessage();
            dol_syslog('DocSigWhatsAppProvider::send - Exception: ' . $e->getMessage(), LOG_ERR);
        }

        return $result;
    }

    /**
     * Normaliza un número de teléfono para WhatsApp
     *
     * @param string $phone Número original
     * @return string Número normalizado o vacío si inválido
     */
    private function normalizePhoneNumber($phone)
    {
        // Eliminar todo excepto números y el símbolo +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Si empieza con 00, reemplazar por +
        if (strpos($phone, '00') === 0) {
            $phone = '+' . substr($phone, 2);
        }

        // Si no tiene código de país, asumir España (+34)
        if (strpos($phone, '+') !== 0 && strlen($phone) == 9) {
            $phone = '+34' . $phone;
        }

        // Verificar longitud mínima (código país + número)
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($cleanPhone) < 10) {
            return '';
        }

        return $phone;
    }

    /**
     * Convierte HTML básico a formato WhatsApp
     *
     * @param string $html Contenido HTML
     * @return string Texto formateado para WhatsApp
     */
    private function htmlToWhatsApp($html)
    {
        // Reemplazar etiquetas comunes
        $text = $html;

        // Negritas
        $text = preg_replace('/<(strong|b)[^>]*>(.*?)<\/(strong|b)>/i', '*$2*', $text);

        // Itálicas
        $text = preg_replace('/<(em|i)[^>]*>(.*?)<\/(em|i)>/i', '_$2_', $text);

        // Saltos de línea
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\/(p|div|h[1-6]|tr)>/i', "\n\n", $text);

        // Enlaces - mantener URL
        $text = preg_replace('/<a[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/i', '$2: $1', $text);

        // Listas
        $text = preg_replace('/<li[^>]*>/i', "\n• ", $text);

        // Eliminar todas las demás etiquetas
        $text = strip_tags($text);

        // Limpiar espacios múltiples y líneas vacías
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        // Decodificar entidades HTML
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        return $text;
    }

    /**
     * @inheritDoc
     */
    public function getChannel()
    {
        return 'whatsapp';
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
     * Constantes para tipos de notificación
     */
    const TYPE_REQUEST = 'request';       // Solicitud de firma
    const TYPE_OTP = 'otp';               // Envío de código OTP
    const TYPE_REMINDER = 'reminder';     // Recordatorio
    const TYPE_COMPLETED = 'completed';   // Firma completada
    const TYPE_CANCELLED = 'cancelled';   // Firma cancelada
    const TYPE_REJECTED = 'rejected';     // Firma rechazada

    /**
     * Mapeo de tipos a códigos de actioncomm
     */
    private static $actionCommTypes = array(
        'request' => 'AC_DOCSIG_REQUEST',
        'otp' => 'AC_DOCSIG_OTP',
        'reminder' => 'AC_DOCSIG_REMINDER',
        'completed' => 'AC_DOCSIG_COMPLETED',
        'cancelled' => 'AC_DOCSIG_CANCELLED',
        'rejected' => 'AC_DOCSIG_CANCELLED',
        'general' => 'AC_DOCSIG',
    );

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

        // Registrar WhatsApp si está disponible
        $whatsappProvider = new DocSigWhatsAppProvider($db);
        if ($whatsappProvider->isAvailable()) {
            $this->providers['whatsapp'] = $whatsappProvider;
        }
    }

    /**
     * Obtiene el canal de notificación configurado
     *
     * @return string Canal configurado (email, whatsapp, both)
     */
    public function getConfiguredChannel()
    {
        return getDolGlobalString('DOCSIG_NOTIFICATION_CHANNEL', 'email');
    }

    /**
     * Verifica si un canal está disponible
     *
     * @param string $channel Canal a verificar
     * @return bool True si está disponible
     */
    public function isChannelAvailable($channel)
    {
        return isset($this->providers[$channel]);
    }

    /**
     * Obtiene los canales disponibles
     *
     * @return array Array de canales disponibles
     */
    public function getAvailableChannels()
    {
        return array_keys($this->providers);
    }

    /**
     * Envía una notificación usando el canal configurado
     *
     * @param string $destination Destino (email o teléfono según canal)
     * @param string $subject Asunto
     * @param string $bodyText Cuerpo texto
     * @param string $bodyHtml Cuerpo HTML
     * @param array $context Contexto (envelope_id, signer_id, contact_id, type, phone, email)
     * @return array Array con resultados por canal
     */
    public function sendNotification($destination, $subject, $bodyText, $bodyHtml = '', $context = array())
    {
        $configuredChannel = $this->getConfiguredChannel();
        $results = array();

        // Determinar qué canales usar
        $channelsToUse = array();

        if ($configuredChannel === 'both') {
            // Enviar por ambos canales si están disponibles
            if ($this->isChannelAvailable('email') && !empty($context['email'])) {
                $channelsToUse['email'] = $context['email'];
            }
            if ($this->isChannelAvailable('whatsapp') && !empty($context['phone'])) {
                $channelsToUse['whatsapp'] = $context['phone'];
            }
        } elseif ($configuredChannel === 'whatsapp') {
            // Solo WhatsApp
            if ($this->isChannelAvailable('whatsapp')) {
                $phone = $context['phone'] ?? $destination;
                if (!empty($phone)) {
                    $channelsToUse['whatsapp'] = $phone;
                }
            }
        } else {
            // Por defecto: email
            if ($this->isChannelAvailable('email')) {
                $email = $context['email'] ?? $destination;
                if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $channelsToUse['email'] = $email;
                }
            }
        }

        // Enviar por cada canal seleccionado
        foreach ($channelsToUse as $channel => $dest) {
            // Preparar cuerpo específico por canal
            $channelBodyText = $bodyText;
            $channelBodyHtml = $bodyHtml;
            
            // Si es WhatsApp, usar template específico si existe
            if ($channel === 'whatsapp' && !empty($context['type'])) {
                global $langs;
                $langs->load('docsig@signDol');
                
                $typeToTemplate = array(
                    'signature_request' => 'WhatsAppRequestBody',
                    'reminder' => 'WhatsAppReminderBody',
                    'completed' => 'WhatsAppCompletedBody',
                    'otp' => 'WhatsAppOTPBody',
                );
                
                $templateKey = $typeToTemplate[$context['type']] ?? null;
                if ($templateKey) {
                    $waTemplate = $langs->trans($templateKey);
                    if ($waTemplate !== $templateKey) {
                        // Sustituir variables en el template de WhatsApp
                        $vars = array(
                            '__REF__' => $context['envelope_ref'] ?? '',
                            '__SIGNER_NAME__' => $context['signer_name'] ?? '',
                            '__DOCUMENT_NAME__' => $context['document_name'] ?? '',
                            '__SIGN_URL__' => $context['signing_url'] ?? '',
                            '__EXPIRATION_DATE__' => $context['expiration_date'] ?? '',
                            '__DAYS_LEFT__' => $context['days_left'] ?? '',
                            '__DOWNLOAD_URL__' => $context['download_url'] ?? '',
                            '__CODE__' => $context['otp_code'] ?? '',
                            '__EXPIRATION_MINUTES__' => $context['expiration_minutes'] ?? '10',
                            '__COMPANY_NAME__' => getDolGlobalString('MAIN_INFO_SOCIETE_NOM', 'DocSig'),
                        );
                        $channelBodyText = strtr($waTemplate, $vars);
                        $channelBodyHtml = '';
                    }
                }
            }
            
            $result = $this->send($channel, $dest, $subject, $channelBodyText, $channelBodyHtml, $context);
            $results[$channel] = array(
                'destination' => $dest,
                'success' => $result > 0,
                'notification_id' => $result,
                'error' => $result < 0 ? $this->error : null
            );
        }

        return $results;
    }

    /**
     * Envía una notificación y la registra en la base de datos
     *
     * @param string $channel Canal de notificación (email, sms, whatsapp)
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
            
            // Crear registro en actioncomm (siempre, aunque no haya contacto vinculado)
            $actionContactId = isset($context['contact_id']) ? (int)$context['contact_id'] : 0;
            // Añadir canal y notification_id al contexto para actioncomm
            $actionContext = array_merge($context, array(
                'channel' => $channel,
                'notification_id' => $notificationId,
                'destination' => $destination
            ));
            $this->createActionComm($actionContactId, $subject, $bodyHtml ?: $bodyText, $actionContext);
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
        $contactId = isset($context['contact_id']) ? (int)$context['contact_id'] : 0;
        $sql .= ($contactId > 0 ? $contactId : "NULL").",";
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
     * Crea un registro en actioncomm vinculado al contacto (o sin contacto si no existe)
     *
     * @param int $contactId ID del contacto (puede ser 0 si no hay contacto vinculado)
     * @param string $subject Asunto
     * @param string $body Cuerpo
     * @param array $context Contexto adicional (type, channel, envelope_id, notification_id, destination)
     * @return int ID creado o -1 si falla
     */
    private function createActionComm($contactId, $subject, $body, $context = array())
    {
        global $user, $conf;

        require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
        require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';

        // Intentar cargar contacto si existe
        $contact = null;
        $socid = 0;
        if ($contactId > 0) {
            $contact = new Contact($this->db);
            if ($contact->fetch($contactId) > 0) {
                $socid = $contact->socid;
            } else {
                $contact = null;
                $contactId = 0;
            }
        }

        // Determinar el tipo de actioncomm según el tipo de notificación
        $notificationType = $context['type'] ?? 'general';
        $typeCode = self::$actionCommTypes[$notificationType] ?? 'AC_DOCSIG';

        // Si es WhatsApp, usar tipo específico
        $channel = $context['channel'] ?? 'email';
        if ($channel === 'whatsapp') {
            $typeCode = 'AC_DOCSIG_WHATSAPP';
        }

        // Verificar que el tipo existe en la BD, si no usar AC_DOCSIG genérico
        $sql = "SELECT id FROM ".MAIN_DB_PREFIX."c_actioncomm WHERE code = '".$this->db->escape($typeCode)."'";
        $resql = $this->db->query($sql);
        if (!$resql || $this->db->num_rows($resql) == 0) {
            // Tipo no existe, usar genérico o AC_OTH
            $typeCode = 'AC_OTH';
        }

        $actioncomm = new ActionComm($this->db);
        $actioncomm->type_code = $typeCode;
        $actioncomm->code = $typeCode;
        $actioncomm->label = '[DocSig] ' . $subject;
        
        // Añadir información del canal y destino al cuerpo
        $channelInfo = '';
        $destination = $context['destination'] ?? '';
        switch ($channel) {
            case 'whatsapp':
                $channelInfo = "[WhatsApp]" . ($destination ? " Enviado a $destination" : "") . "\n";
                break;
            case 'email':
                $channelInfo = "[Email]" . ($destination ? " Enviado a $destination" : "") . "\n";
                break;
            case 'sms':
                $channelInfo = "[SMS]" . ($destination ? " Enviado a $destination" : "") . "\n";
                break;
        }
        
        $actioncomm->note_private = $channelInfo . "\n" . $body;
        $actioncomm->datep = dol_now();
        $actioncomm->datef = dol_now();
        $actioncomm->percentage = -1; // Not applicable
        $actioncomm->socid = $socid;
        $actioncomm->contact_id = $contactId > 0 ? $contactId : null;
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
            
            dol_syslog('DocSigNotificationService::createActionComm - Created actioncomm #' . $result . ' type ' . $typeCode, LOG_INFO);
        } else {
            dol_syslog('DocSigNotificationService::createActionComm - Error: ' . $actioncomm->error, LOG_ERR);
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

    /**
     * Envía una solicitud de firma a un firmante
     *
     * @param DocSigEnvelope $envelope Envelope
     * @param DocSigSigner $signer Firmante
     * @param string $signUrl URL de firma
     * @param string $customMessage Mensaje personalizado (opcional)
     * @return int ID de notificación o -1 si falla
     */
    public function sendSignatureRequest($envelope, $signer, $signUrl, $customMessage = '')
    {
        global $conf, $langs;

        $langs->load('docsig@signDol');

        // Preparar variables de sustitución
        $vars = array(
            '__REF__' => $envelope->ref,
            '__SIGNER_NAME__' => $signer->getFullName(),
            '__DOCUMENT_NAME__' => basename($envelope->file_path),
            '__SIGN_URL__' => $signUrl,
            '__EXPIRATION_DATE__' => dol_print_date($envelope->expire_date, 'dayhour'),
            '__COMPANY_NAME__' => getDolGlobalString('MAIN_INFO_SOCIETE_NOM', 'DocSig'),
        );

        // Asunto
        $subject = getDolGlobalString('DOCSIG_EMAIL_SUBJECT_REQUEST', $langs->trans('SignatureRequest').': __REF__');
        $subject = strtr($subject, $vars);

        // Cuerpo
        $bodyHtml = $langs->trans('EmailRequestBody');
        $bodyHtml = strtr($bodyHtml, $vars);

        if (!empty($customMessage)) {
            $bodyHtml .= '<hr><p>'.$customMessage.'</p>';
        }

        $bodyText = strip_tags(str_replace(array('<br>', '<p>', '</p>'), array("\n", "\n", "\n"), $bodyHtml));

        // Contexto (incluir toda la info para templates WhatsApp)
        $context = array(
            'envelope_id' => $envelope->id,
            'envelope_ref' => $envelope->ref,
            'signer_id' => $signer->id,
            'contact_id' => $signer->fk_socpeople,
            'type' => 'signature_request',
            'email' => $signer->email,
            'phone' => $signer->phone,
            // Variables para templates WhatsApp
            'signer_name' => $signer->getFullName(),
            'document_name' => basename($envelope->file_path),
            'signing_url' => $signUrl,
            'expiration_date' => dol_print_date($envelope->expire_date, 'dayhour'),
        );

        // Enviar notificación
        $results = $this->sendNotification($signer->email, $subject, $bodyText, $bodyHtml, $context);

        // Retornar el primer ID de notificación exitoso
        foreach ($results as $channel => $result) {
            if ($result['success'] && !empty($result['notification_id'])) {
                return $result['notification_id'];
            }
        }

        return -1;
    }

    /**
     * Envía un recordatorio a un firmante
     *
     * @param DocSigEnvelope $envelope Envelope
     * @param DocSigSigner $signer Firmante
     * @param string $signUrl URL de firma
     * @return int ID de notificación o -1 si falla
     */
    public function sendReminder($envelope, $signer, $signUrl)
    {
        global $conf, $langs;

        $langs->load('docsig@signDol');

        // Calcular días restantes
        $daysLeft = ceil(($envelope->expire_date - dol_now()) / (24 * 3600));
        if ($daysLeft < 0) {
            $daysLeft = 0;
        }

        // Preparar variables de sustitución
        $vars = array(
            '__REF__' => $envelope->ref,
            '__SIGNER_NAME__' => $signer->getFullName(),
            '__DOCUMENT_NAME__' => basename($envelope->file_path),
            '__SIGN_URL__' => $signUrl,
            '__DAYS_LEFT__' => $daysLeft,
            '__COMPANY_NAME__' => getDolGlobalString('MAIN_INFO_SOCIETE_NOM', 'DocSig'),
        );

        // Asunto
        $subject = getDolGlobalString('DOCSIG_EMAIL_SUBJECT_REMINDER', $langs->trans('ReminderNotification').': __REF__');
        $subject = strtr($subject, $vars);

        // Cuerpo
        $bodyHtml = $langs->trans('EmailReminderBody');
        $bodyHtml = strtr($bodyHtml, $vars);

        $bodyText = strip_tags(str_replace(array('<br>', '<p>', '</p>'), array("\n", "\n", "\n"), $bodyHtml));

        // Contexto (incluir toda la info para templates WhatsApp)
        $context = array(
            'envelope_id' => $envelope->id,
            'envelope_ref' => $envelope->ref,
            'signer_id' => $signer->id,
            'contact_id' => $signer->fk_socpeople,
            'type' => 'reminder',
            'email' => $signer->email,
            'phone' => $signer->phone,
            // Variables para templates WhatsApp
            'signer_name' => $signer->getFullName(),
            'document_name' => basename($envelope->file_path),
            'signing_url' => $signUrl,
            'days_left' => $daysLeft,
        );

        // Enviar notificación
        $results = $this->sendNotification($signer->email, $subject, $bodyText, $bodyHtml, $context);

        // Retornar el primer ID de notificación exitoso
        foreach ($results as $channel => $result) {
            if ($result['success'] && !empty($result['notification_id'])) {
                return $result['notification_id'];
            }
        }

        return -1;
    }

    /**
     * Envía notificación de firma completada
     *
     * @param DocSigEnvelope $envelope Envelope
     * @param DocSigSigner $signer Firmante
     * @return int ID de notificación o -1 si falla
     */
    public function sendCompletionNotification($envelope, $signer)
    {
        global $conf, $langs;

        $langs->load('docsig@signDol');

        // URL de descarga
        // - En backend no tenemos el token en claro (solo token_hash). Permitimos que download.php acepte ambos formatos.
        $downloadToken = (!empty($signer->plain_token) ? $signer->plain_token : $signer->token_hash);
        $downloadUrl = dol_buildpath('/signDol/public/download.php', 3).'?type=signed&token='.$downloadToken;

        // Preparar variables de sustitución
        $vars = array(
            '__REF__' => $envelope->ref,
            '__SIGNER_NAME__' => $signer->getFullName(),
            '__DOCUMENT_NAME__' => basename($envelope->file_path),
            '__DOWNLOAD_URL__' => $downloadUrl,
            '__COMPANY_NAME__' => getDolGlobalString('MAIN_INFO_SOCIETE_NOM', 'DocSig'),
        );

        // Asunto
        $subject = getDolGlobalString('DOCSIG_EMAIL_SUBJECT_COMPLETED', $langs->trans('CompletedNotification').': __REF__');
        $subject = strtr($subject, $vars);

        // Cuerpo
        $bodyHtml = $langs->trans('EmailCompletedBody');
        $bodyHtml = strtr($bodyHtml, $vars);

        $bodyText = strip_tags(str_replace(array('<br>', '<p>', '</p>'), array("\n", "\n", "\n"), $bodyHtml));

        // Contexto
        $context = array(
            'envelope_id' => $envelope->id,
            'envelope_ref' => $envelope->ref,
            'signer_id' => $signer->id,
            'contact_id' => $signer->fk_socpeople,
            'type' => 'completed',
            'email' => $signer->email,
            'phone' => $signer->phone,
            // Variables para templates WhatsApp
            'signer_name' => $signer->getFullName(),
            'document_name' => basename($envelope->file_path),
            'download_url' => $downloadUrl,
        );

        // Enviar notificación
        $results = $this->sendNotification($signer->email, $subject, $bodyText, $bodyHtml, $context);

        // Retornar el primer ID de notificación exitoso
        foreach ($results as $channel => $result) {
            if ($result['success'] && !empty($result['notification_id'])) {
                return $result['notification_id'];
            }
        }

        return -1;
    }

    /**
     * Envía código OTP a un firmante
     *
     * @param DocSigSigner $signer Firmante
     * @param string $code Código OTP
     * @param string $channel Canal (email, whatsapp)
     * @return int ID de notificación o -1 si falla
     */
    public function sendOTPNotification($signer, $code, $channel = 'email')
    {
        global $conf, $langs;

        $langs->load('docsig@signDol');

        $expirationMinutes = getDolGlobalInt('DOCSIG_OTP_EXPIRATION_MINUTES', 10);

        // Preparar variables de sustitución
        $vars = array(
            '__CODE__' => $code,
            '__SIGNER_NAME__' => $signer->getFullName(),
            '__EXPIRATION_MINUTES__' => $expirationMinutes,
        );

        // Asunto
        $subject = getDolGlobalString('DOCSIG_EMAIL_SUBJECT_OTP', $langs->trans('OTPNotification'));
        $subject = strtr($subject, $vars);

        // Cuerpo según canal
        if ($channel === 'whatsapp') {
            $bodyText = $langs->trans('WhatsAppOTPBody');
        } else {
            $bodyText = $langs->trans('EmailOTPBody');
        }
        $bodyText = strtr($bodyText, $vars);

        $bodyHtml = '<p>'.nl2br($bodyText).'</p>';

        // Destino según canal
        $destination = ($channel === 'whatsapp') ? $signer->phone : $signer->email;

        // Contexto
        $context = array(
            'signer_id' => $signer->id,
            'contact_id' => $signer->fk_socpeople,
            'type' => 'otp',
            'email' => $signer->email,
            'phone' => $signer->phone,
        );

        // Enviar por canal específico
        return $this->send($channel, $destination, $subject, $bodyText, $bodyHtml, $context);
    }
}
