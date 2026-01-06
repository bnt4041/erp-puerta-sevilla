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
 * \file    htdocs/custom/signDol/class/docsigotpmanager.class.php
 * \ingroup docsig
 * \brief   Gestión de códigos OTP para autenticación de firmantes
 */

/**
 * Class DocSigOTPManager
 * Gestiona la generación, envío y verificación de códigos OTP
 */
class DocSigOTPManager
{
    /**
     * @var DoliDB Base de datos
     */
    private $db;

    /**
     * @var string Último error
     */
    public $error;

    /**
     * @var array Errores
     */
    public $errors = array();

    /**
     * Constantes de estado OTP
     */
    const STATUS_PENDING = 0;
    const STATUS_VERIFIED = 1;
    const STATUS_EXPIRED = 2;
    const STATUS_BLOCKED = 3;

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
     * Genera un nuevo código OTP para un firmante
     *
     * @param int $signerId ID del firmante
     * @param string $channel Canal de envío ('email' o 'phone')
     * @param string $destination Email o teléfono destino
     * @param string $ipAddress IP del solicitante
     * @return array Array con 'success', 'code' (plano), 'otp_id', 'error'
     */
    public function generateOTP($signerId, $channel, $destination, $ipAddress = '')
    {
        $result = array(
            'success' => false,
            'code' => null,
            'otp_id' => null,
            'error' => null
        );

        // Verificar rate limit
        if (!$this->checkRateLimit($signerId, $ipAddress)) {
            $result['error'] = 'rate_limit_exceeded';
            return $result;
        }

        // Invalidar OTPs anteriores pendientes
        $this->invalidatePreviousOTPs($signerId);

        // Generar código OTP
        $length = getDolGlobalInt('DOCSIG_OTP_LENGTH', 6);
        $code = $this->generateSecureCode($length);

        // Generar salt y hash
        $salt = bin2hex(random_bytes(32));
        $codeHash = $this->hashCode($code, $salt);

        // Calcular expiración
        $expirationMinutes = getDolGlobalInt('DOCSIG_OTP_EXPIRATION_MINUTES', 10);
        $expiresAt = dol_time_plus_duree(dol_now(), $expirationMinutes, 'i');

        // Máximo de intentos
        $maxAttempts = getDolGlobalInt('DOCSIG_OTP_MAX_ATTEMPTS', 5);

        // Insertar en base de datos
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."docsig_otp (";
        $sql .= "fk_signer, code_hash, code_salt, expires_at, max_attempts,";
        $sql .= "status, channel, destination, sent_at, ip_address";
        $sql .= ") VALUES (";
        $sql .= (int)$signerId.",";
        $sql .= "'".$this->db->escape($codeHash)."',";
        $sql .= "'".$this->db->escape($salt)."',";
        $sql .= "'".$this->db->idate($expiresAt)."',";
        $sql .= (int)$maxAttempts.",";
        $sql .= self::STATUS_PENDING.",";
        $sql .= "'".$this->db->escape($channel)."',";
        $sql .= "'".$this->db->escape($destination)."',";
        $sql .= "'".$this->db->idate(dol_now())."',";
        $sql .= "'".$this->db->escape($ipAddress)."'";
        $sql .= ")";

        $resql = $this->db->query($sql);
        if (!$resql) {
            $result['error'] = 'db_error';
            $this->error = $this->db->lasterror();
            return $result;
        }

        $otpId = $this->db->last_insert_id(MAIN_DB_PREFIX."docsig_otp");

        $result['success'] = true;
        $result['code'] = $code;
        $result['otp_id'] = $otpId;
        $result['expires_at'] = $expiresAt;
        $result['expiration_minutes'] = $expirationMinutes;

        dol_syslog('DocSigOTPManager::generateOTP - OTP generated for signer ' . $signerId . ', channel ' . $channel, LOG_INFO);

        return $result;
    }

    /**
     * Envía el OTP usando el sistema de notificaciones
     *
     * @param int $signerId ID del firmante
     * @param string $code Código OTP en plano
     * @param string $channel Canal de envío ('email' o 'whatsapp')
     * @param array $signerData Datos del firmante (firstname, lastname, email, phone)
     * @return array Array con 'success', 'error'
     */
    public function sendOTP($signerId, $code, $channel, $signerData)
    {
        global $conf, $langs;

        $langs->load('docsig@signDol');

        $result = array(
            'success' => false,
            'error' => null
        );

        // Determinar destino
        $destination = ($channel === 'whatsapp' || $channel === 'phone') ? $signerData['phone'] : $signerData['email'];

        // Usar el servicio de notificaciones
        dol_include_once('/signDol/class/docsignotification.class.php');
        $notificationService = new DocSigNotificationService($this->db);

        // Contexto para el registro
        $contactId = 0;
        if (!empty($signerData['fk_socpeople'])) {
            $contactId = (int) $signerData['fk_socpeople'];
        } elseif (!empty($signerData['fk_contact'])) {
            // Backward-compat: algunos flujos antiguos metían el id de socpeople en fk_contact
            $contactId = (int) $signerData['fk_contact'];
        }

        $context = array(
            'signer_id' => $signerId,
            // Importante: si no hay contacto válido, debe ser NULL (no 0) por la FK
            'contact_id' => ($contactId > 0 ? $contactId : null),
            'type' => 'otp',
            'email' => $signerData['email'],
            'phone' => $signerData['phone']
        );

        // Determinar canal real
        $actualChannel = 'email';
        if ($channel === 'whatsapp' && $notificationService->isChannelAvailable('whatsapp')) {
            $actualChannel = 'whatsapp';
        } elseif ($channel === 'phone' && $notificationService->isChannelAvailable('sms')) {
            $actualChannel = 'sms';
        }

        // Construir firmante (preferimos el objeto real para usar getFullName y plantillas)
        dol_include_once('/signDol/class/docsigsigner.class.php');
        $signerObj = new DocSigSigner($this->db);
        if ($signerObj->fetch((int) $signerId) <= 0) {
            // Fallback con los datos aportados por el flujo público
            $signerObj->id = (int) $signerId;
            $signerObj->rowid = (int) $signerId;
            $signerObj->firstname = $signerData['firstname'] ?? '';
            $signerObj->lastname = $signerData['lastname'] ?? '';
            $signerObj->email = $signerData['email'] ?? '';
            $signerObj->phone = $signerData['phone'] ?? '';
            $signerObj->fk_socpeople = (!empty($contactId) ? (int) $contactId : null);
        }

        // Enviar (usa textos/plantillas correctas, p.ej. WhatsAppOTPBody)
        $sendResult = $notificationService->sendOTPNotification($signerObj, $code, $actualChannel);

        if ($sendResult > 0) {
            $result['success'] = true;
            $result['channel'] = $actualChannel;
            dol_syslog('DocSigOTPManager::sendOTP - OTP sent to ' . $destination . ' via ' . $actualChannel, LOG_INFO);
        } else {
            $result['error'] = $notificationService->error;
            dol_syslog('DocSigOTPManager::sendOTP - Error: ' . $notificationService->error, LOG_ERR);
        }

        return $result;
    }

    /**
     * Verifica un código OTP
     *
     * @param int $signerId ID del firmante
     * @param string $code Código introducido por el usuario
     * @param string $ipAddress IP del verificador
     * @return array Array con 'success', 'error', 'attempts_remaining', 'blocked'
     */
    public function verifyOTP($signerId, $code, $ipAddress = '')
    {
        $result = array(
            'success' => false,
            'error' => null,
            'attempts_remaining' => 0,
            'blocked' => false
        );

        // Obtener OTP activo
        $sql = "SELECT * FROM ".MAIN_DB_PREFIX."docsig_otp";
        $sql .= " WHERE fk_signer = ".(int)$signerId;
        $sql .= " AND status = ".self::STATUS_PENDING;
        $sql .= " ORDER BY sent_at DESC LIMIT 1";

        $resql = $this->db->query($sql);
        if (!$resql) {
            $result['error'] = 'db_error';
            return $result;
        }

        if ($this->db->num_rows($resql) == 0) {
            $result['error'] = 'no_active_otp';
            return $result;
        }

        $otp = $this->db->fetch_object($resql);

        // Verificar expiración
        $expiresAt = $this->db->jdate($otp->expires_at);
        if ($expiresAt < dol_now()) {
            $this->updateOTPStatus($otp->rowid, self::STATUS_EXPIRED);
            $result['error'] = 'otp_expired';
            return $result;
        }

        // Verificar intentos
        if ($otp->attempts >= $otp->max_attempts) {
            $this->updateOTPStatus($otp->rowid, self::STATUS_BLOCKED);
            $result['error'] = 'otp_blocked';
            $result['blocked'] = true;
            return $result;
        }

        // Verificar código
        $codeHash = $this->hashCode($code, $otp->code_salt);
        
        if ($codeHash === $otp->code_hash) {
            // Código correcto
            $this->updateOTPStatus($otp->rowid, self::STATUS_VERIFIED, dol_now());
            
            // Registrar evento en el firmante
            $this->logSignerEvent($signerId, 'OTP_VERIFIED', $ipAddress);
            
            $result['success'] = true;
            dol_syslog('DocSigOTPManager::verifyOTP - OTP verified for signer ' . $signerId, LOG_INFO);
        } else {
            // Código incorrecto - incrementar intentos
            $newAttempts = $otp->attempts + 1;
            $this->incrementAttempts($otp->rowid, $newAttempts);
            
            $result['attempts_remaining'] = $otp->max_attempts - $newAttempts;
            
            if ($newAttempts >= $otp->max_attempts) {
                $this->updateOTPStatus($otp->rowid, self::STATUS_BLOCKED);
                $result['error'] = 'otp_blocked';
                $result['blocked'] = true;
                
                // Bloquear el firmante también
                $this->blockSigner($signerId);
                
                dol_syslog('DocSigOTPManager::verifyOTP - OTP blocked for signer ' . $signerId, LOG_WARNING);
            } else {
                $result['error'] = 'otp_invalid';
                dol_syslog('DocSigOTPManager::verifyOTP - Invalid OTP attempt for signer ' . $signerId . ', remaining: ' . $result['attempts_remaining'], LOG_INFO);
            }
        }

        return $result;
    }

    /**
     * Verifica el rate limit de solicitudes OTP
     *
     * @param int $signerId ID del firmante
     * @param string $ipAddress IP del solicitante
     * @return bool True si se permite, false si excede límite
     */
    public function checkRateLimit($signerId, $ipAddress)
    {
        $limit = getDolGlobalInt('DOCSIG_RATE_LIMIT_OTP', 3);
        $windowMinutes = 5; // Ventana de tiempo en minutos

        // Contar OTPs enviados en la ventana de tiempo
        $windowStart = dol_time_plus_duree(dol_now(), -$windowMinutes, 'i');

        $sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."docsig_otp";
        $sql .= " WHERE (fk_signer = ".(int)$signerId;
        $sql .= " OR ip_address = '".$this->db->escape($ipAddress)."')";
        $sql .= " AND sent_at >= '".$this->db->idate($windowStart)."'";

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            if ($obj->cnt >= $limit) {
                dol_syslog('DocSigOTPManager::checkRateLimit - Rate limit exceeded for signer ' . $signerId . ' or IP ' . $ipAddress, LOG_WARNING);
                return false;
            }
        }

        return true;
    }

    /**
     * Obtiene información del OTP activo de un firmante
     *
     * @param int $signerId ID del firmante
     * @return array|null Array con info del OTP o null
     */
    public function getActiveOTP($signerId)
    {
        $sql = "SELECT * FROM ".MAIN_DB_PREFIX."docsig_otp";
        $sql .= " WHERE fk_signer = ".(int)$signerId;
        $sql .= " AND status = ".self::STATUS_PENDING;
        $sql .= " AND expires_at > '".$this->db->idate(dol_now())."'";
        $sql .= " ORDER BY sent_at DESC LIMIT 1";

        $resql = $this->db->query($sql);
        if ($resql && $this->db->num_rows($resql) > 0) {
            $obj = $this->db->fetch_object($resql);
            return array(
                'id' => $obj->rowid,
                'channel' => $obj->channel,
                'destination' => $obj->destination,
                'expires_at' => $this->db->jdate($obj->expires_at),
                'attempts' => $obj->attempts,
                'max_attempts' => $obj->max_attempts,
                'sent_at' => $this->db->jdate($obj->sent_at)
            );
        }

        return null;
    }

    /**
     * Verifica si un firmante está bloqueado por OTP
     *
     * @param int $signerId ID del firmante
     * @return bool True si está bloqueado
     */
    public function isSignerBlocked($signerId)
    {
        // En contexto público (NOLOGIN), algunas libs de Dolibarr pueden no estar cargadas.
        if (!function_exists('dol_time_plus_duree')) {
            require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
        }

        $sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."docsig_otp";
        $sql .= " WHERE fk_signer = ".(int)$signerId;
        $sql .= " AND status = ".self::STATUS_BLOCKED;
        $sql .= " AND sent_at >= '".$this->db->idate(dol_time_plus_duree(dol_now(), -24, 'h'))."'"; // Últimas 24h

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            return $obj->cnt > 0;
        }

        return false;
    }

    // ==================== MÉTODOS PRIVADOS ====================

    /**
     * Genera un código numérico seguro
     *
     * @param int $length Longitud del código
     * @return string Código generado
     */
    private function generateSecureCode($length = 6)
    {
        $min = pow(10, $length - 1);
        $max = pow(10, $length) - 1;
        return (string)random_int($min, $max);
    }

    /**
     * Genera hash del código con salt
     *
     * @param string $code Código
     * @param string $salt Salt
     * @return string Hash SHA-256
     */
    private function hashCode($code, $salt)
    {
        return hash('sha256', $code . $salt);
    }

    /**
     * Invalida OTPs anteriores pendientes de un firmante
     *
     * @param int $signerId ID del firmante
     */
    private function invalidatePreviousOTPs($signerId)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."docsig_otp SET";
        $sql .= " status = ".self::STATUS_EXPIRED;
        $sql .= " WHERE fk_signer = ".(int)$signerId;
        $sql .= " AND status = ".self::STATUS_PENDING;

        $this->db->query($sql);
    }

    /**
     * Actualiza estado de un OTP
     *
     * @param int $otpId ID del OTP
     * @param int $status Nuevo estado
     * @param int|null $verifiedAt Timestamp de verificación
     */
    private function updateOTPStatus($otpId, $status, $verifiedAt = null)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."docsig_otp SET";
        $sql .= " status = ".(int)$status;
        if ($verifiedAt) {
            $sql .= ", verified_at = '".$this->db->idate($verifiedAt)."'";
        }
        $sql .= " WHERE rowid = ".(int)$otpId;

        $this->db->query($sql);
    }

    /**
     * Incrementa contador de intentos
     *
     * @param int $otpId ID del OTP
     * @param int $attempts Nuevo número de intentos
     */
    private function incrementAttempts($otpId, $attempts)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."docsig_otp SET";
        $sql .= " attempts = ".(int)$attempts;
        $sql .= " WHERE rowid = ".(int)$otpId;

        $this->db->query($sql);
    }

    /**
     * Bloquea un firmante por exceso de intentos OTP
     *
     * @param int $signerId ID del firmante
     */
    private function blockSigner($signerId)
    {
        // Actualizar estado del firmante a bloqueado
        $sql = "UPDATE ".MAIN_DB_PREFIX."docsig_signer SET";
        $sql .= " status = 3"; // STATUS_BLOCKED
        $sql .= " WHERE rowid = ".(int)$signerId;

        $this->db->query($sql);

        // Registrar evento
        $this->logSignerEvent($signerId, 'SIGNER_BLOCKED', '', 'Blocked due to too many OTP attempts');
    }

    /**
     * Registra un evento del firmante
     *
     * @param int $signerId ID del firmante
     * @param string $eventType Tipo de evento
     * @param string $ipAddress IP
     * @param string $description Descripción
     */
    private function logSignerEvent($signerId, $eventType, $ipAddress = '', $description = '')
    {
        // Obtener fk_envelope del firmante
        $sql = "SELECT fk_envelope FROM ".MAIN_DB_PREFIX."docsig_signer WHERE rowid = ".(int)$signerId;
        $resql = $this->db->query($sql);
        if ($resql && $this->db->num_rows($resql) > 0) {
            $obj = $this->db->fetch_object($resql);
            
            dol_include_once('/signDol/class/docsigenvelope.class.php');
            $envelope = new DocSigEnvelope($this->db);
            if ($envelope->fetch($obj->fk_envelope) > 0) {
                $envelope->logEvent($eventType, $description, $ipAddress, '', $signerId);
            }
        }
    }
}
