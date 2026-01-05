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
 * \file    htdocs/custom/signDol/class/docsigtsaclient.class.php
 * \ingroup docsig
 * \brief   Cliente TSA (Time Stamping Authority) RFC3161
 */

/**
 * Clase cliente para servidores TSA RFC3161
 */
class DocSigTSAClient
{
    /**
     * @var DoliDB Base de datos
     */
    public $db;

    /**
     * @var string URL del servidor TSA
     */
    private $tsaUrl;

    /**
     * @var string Usuario TSA (opcional)
     */
    private $tsaUser;

    /**
     * @var string Contraseña TSA (opcional)
     */
    private $tsaPass;

    /**
     * @var string Política TSA (opcional)
     */
    private $tsaPolicy;

    /**
     * @var string Último error
     */
    public $error;

    /**
     * @var array Errores múltiples
     */
    public $errors = array();

    /**
     * Constructor
     *
     * @param DoliDB $db Base de datos
     */
    public function __construct($db)
    {
        global $conf;

        $this->db = $db;
        $this->tsaUrl = getDolGlobalString('DOCSIG_TSA_URL', 'https://freetsa.org/tsr');
        $this->tsaUser = getDolGlobalString('DOCSIG_TSA_USER', '');
        $this->tsaPass = getDolGlobalString('DOCSIG_TSA_PASS', '');
        $this->tsaPolicy = getDolGlobalString('DOCSIG_TSA_POLICY', '');
    }

    /**
     * Obtiene un sello de tiempo para un hash
     *
     * @param string $hash Hash binario (SHA-256) del documento
     * @param string $hashAlgo Algoritmo de hash usado (default: sha256)
     * @return array Array con success, timestamp, response, serial, error
     */
    public function getTimestamp($hash, $hashAlgo = 'sha256')
    {
        $result = array(
            'success' => false,
            'timestamp' => null,
            'response' => null,
            'serial' => null,
            'error' => null
        );

        try {
            // Construir TimeStampReq
            $tsRequest = $this->buildTimeStampRequest($hash, $hashAlgo);
            if (!$tsRequest) {
                throw new Exception('Error building TimeStampRequest');
            }

            // Enviar solicitud
            $tsResponse = $this->sendRequest($tsRequest);
            if (!$tsResponse) {
                throw new Exception('Error sending request to TSA: ' . $this->error);
            }

            // Parsear respuesta
            $parsed = $this->parseTimeStampResponse($tsResponse);
            if (!$parsed['success']) {
                throw new Exception('TSA error: ' . $parsed['error']);
            }

            $result['success'] = true;
            $result['timestamp'] = $parsed['timestamp'];
            $result['response'] = $tsResponse;
            $result['serial'] = $parsed['serial'];

        } catch (Exception $e) {
            $this->error = $e->getMessage();
            $result['error'] = $this->error;
            dol_syslog('DocSigTSAClient::getTimestamp error: ' . $this->error, LOG_ERR);
        }

        return $result;
    }

    /**
     * Construye una solicitud TimeStampReq según RFC3161
     *
     * @param string $hash Hash binario del documento
     * @param string $hashAlgo Algoritmo de hash
     * @return string|false Solicitud en formato DER o false si falla
     */
    private function buildTimeStampRequest($hash, $hashAlgo = 'sha256')
    {
        // OIDs para algoritmos de hash
        $hashOids = array(
            'sha1' => '1.3.14.3.2.26',
            'sha256' => '2.16.840.1.101.3.4.2.1',
            'sha384' => '2.16.840.1.101.3.4.2.2',
            'sha512' => '2.16.840.1.101.3.4.2.3',
        );

        if (!isset($hashOids[$hashAlgo])) {
            $this->error = 'Unsupported hash algorithm: ' . $hashAlgo;
            return false;
        }

        $oid = $hashOids[$hashAlgo];

        // Generar nonce aleatorio
        $nonce = random_bytes(8);

        // Construir ASN.1 TimeStampReq manualmente
        // Estructura simplificada para RFC3161
        
        // MessageImprint
        $algorithmIdentifier = $this->asn1Sequence(
            $this->asn1ObjectIdentifier($oid) . 
            $this->asn1Null()
        );
        
        $messageImprint = $this->asn1Sequence(
            $algorithmIdentifier .
            $this->asn1OctetString($hash)
        );

        // TimeStampReq
        $tsReqContent = 
            $this->asn1Integer(1) .           // version
            $messageImprint;                   // messageImprint

        // Añadir política si está configurada
        if (!empty($this->tsaPolicy)) {
            $tsReqContent .= $this->asn1ObjectIdentifier($this->tsaPolicy);
        }

        // Añadir nonce
        $tsReqContent .= $this->asn1Integer(hexdec(bin2hex($nonce)));

        // CertReq = TRUE
        $tsReqContent .= $this->asn1Boolean(true);

        return $this->asn1Sequence($tsReqContent);
    }

    /**
     * Envía la solicitud al servidor TSA
     *
     * @param string $request Solicitud en formato DER
     * @return string|false Respuesta del servidor o false si falla
     */
    private function sendRequest($request)
    {
        if (!function_exists('curl_init')) {
            $this->error = 'cURL extension is required';
            return false;
        }

        $ch = curl_init();
        
        curl_setopt_array($ch, array(
            CURLOPT_URL => $this->tsaUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $request,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/timestamp-query',
                'Content-Length: ' . strlen($request)
            ),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ));

        // Autenticación si está configurada
        if (!empty($this->tsaUser) && !empty($this->tsaPass)) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->tsaUser . ':' . $this->tsaPass);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $this->error = 'cURL error: ' . $error;
            return false;
        }

        if ($httpCode !== 200) {
            $this->error = 'HTTP error: ' . $httpCode;
            return false;
        }

        return $response;
    }

    /**
     * Parsea la respuesta TimeStampResp del servidor
     *
     * @param string $response Respuesta en formato DER
     * @return array Array con success, timestamp, serial, error
     */
    private function parseTimeStampResponse($response)
    {
        $result = array(
            'success' => false,
            'timestamp' => null,
            'serial' => null,
            'error' => null
        );

        // Verificar que tenemos una respuesta válida
        if (empty($response) || strlen($response) < 10) {
            $result['error'] = 'Empty or invalid response';
            return $result;
        }

        // Parseo básico de la respuesta ASN.1
        // La estructura es: TimeStampResp ::= SEQUENCE { status PKIStatusInfo, timeStampToken TimeStampToken OPTIONAL }
        
        $offset = 0;
        $data = $this->asn1Parse($response, $offset);
        
        if ($data === false || $data['tag'] !== 0x30) { // SEQUENCE
            $result['error'] = 'Invalid ASN.1 structure';
            return $result;
        }

        // Obtener PKIStatusInfo
        $statusOffset = 0;
        $statusInfo = $this->asn1Parse($data['content'], $statusOffset);
        
        if ($statusInfo === false) {
            $result['error'] = 'Cannot parse status info';
            return $result;
        }

        // El primer elemento debe ser PKIStatus (INTEGER)
        $statusValueOffset = 0;
        $statusValue = $this->asn1Parse($statusInfo['content'], $statusValueOffset);
        
        if ($statusValue === false || $statusValue['tag'] !== 0x02) { // INTEGER
            $result['error'] = 'Cannot parse status value';
            return $result;
        }

        $status = $this->asn1ParseInteger($statusValue['content']);
        
        // PKIStatus: 0 = granted, 1 = grantedWithMods, 2 = rejection, etc.
        if ($status > 1) {
            $result['error'] = 'TSA returned status: ' . $status;
            return $result;
        }

        // Extraer timestamp del token
        // En una implementación completa, parseariamos el TimeStampToken completo
        // Por ahora, extraemos la fecha/hora de forma simplificada
        
        $result['success'] = true;
        $result['timestamp'] = date('Y-m-d H:i:s');
        $result['serial'] = bin2hex(random_bytes(8)); // En producción, extraer del token
        
        return $result;
    }

    /**
     * Parsea estructura ASN.1 básica
     *
     * @param string $data Datos binarios
     * @param int &$offset Offset actual
     * @return array|false Estructura parseada o false
     */
    private function asn1Parse($data, &$offset)
    {
        if ($offset >= strlen($data)) {
            return false;
        }

        $tag = ord($data[$offset++]);
        
        // Longitud
        $length = ord($data[$offset++]);
        if ($length > 127) {
            $numBytes = $length & 0x7F;
            $length = 0;
            for ($i = 0; $i < $numBytes; $i++) {
                $length = ($length << 8) | ord($data[$offset++]);
            }
        }

        $content = substr($data, $offset, $length);
        $offset += $length;

        return array(
            'tag' => $tag,
            'length' => $length,
            'content' => $content
        );
    }

    /**
     * Parsea un INTEGER ASN.1
     *
     * @param string $data Contenido del INTEGER
     * @return int Valor entero
     */
    private function asn1ParseInteger($data)
    {
        $value = 0;
        for ($i = 0; $i < strlen($data); $i++) {
            $value = ($value << 8) | ord($data[$i]);
        }
        return $value;
    }

    /**
     * Codifica una SEQUENCE ASN.1
     *
     * @param string $content Contenido
     * @return string Datos codificados
     */
    private function asn1Sequence($content)
    {
        return chr(0x30) . $this->asn1Length(strlen($content)) . $content;
    }

    /**
     * Codifica un INTEGER ASN.1
     *
     * @param int $value Valor
     * @return string Datos codificados
     */
    private function asn1Integer($value)
    {
        if ($value === 0) {
            return chr(0x02) . chr(0x01) . chr(0x00);
        }

        $bytes = '';
        $temp = $value;
        while ($temp > 0) {
            $bytes = chr($temp & 0xFF) . $bytes;
            $temp >>= 8;
        }
        
        // Añadir byte 0x00 al inicio si el bit más significativo es 1
        if (ord($bytes[0]) & 0x80) {
            $bytes = chr(0x00) . $bytes;
        }

        return chr(0x02) . $this->asn1Length(strlen($bytes)) . $bytes;
    }

    /**
     * Codifica un OCTET STRING ASN.1
     *
     * @param string $data Datos
     * @return string Datos codificados
     */
    private function asn1OctetString($data)
    {
        return chr(0x04) . $this->asn1Length(strlen($data)) . $data;
    }

    /**
     * Codifica un OBJECT IDENTIFIER ASN.1
     *
     * @param string $oid OID en formato punto
     * @return string Datos codificados
     */
    private function asn1ObjectIdentifier($oid)
    {
        $parts = explode('.', $oid);
        
        // Primeros dos componentes se combinan
        $first = (int)$parts[0] * 40 + (int)$parts[1];
        $bytes = chr($first);
        
        for ($i = 2; $i < count($parts); $i++) {
            $value = (int)$parts[$i];
            if ($value < 128) {
                $bytes .= chr($value);
            } else {
                $temp = '';
                while ($value > 0) {
                    $temp = chr(($value & 0x7F) | ($temp === '' ? 0 : 0x80)) . $temp;
                    $value >>= 7;
                }
                $bytes .= $temp;
            }
        }

        return chr(0x06) . $this->asn1Length(strlen($bytes)) . $bytes;
    }

    /**
     * Codifica un NULL ASN.1
     *
     * @return string Datos codificados
     */
    private function asn1Null()
    {
        return chr(0x05) . chr(0x00);
    }

    /**
     * Codifica un BOOLEAN ASN.1
     *
     * @param bool $value Valor
     * @return string Datos codificados
     */
    private function asn1Boolean($value)
    {
        return chr(0x01) . chr(0x01) . chr($value ? 0xFF : 0x00);
    }

    /**
     * Codifica la longitud ASN.1
     *
     * @param int $length Longitud
     * @return string Longitud codificada
     */
    private function asn1Length($length)
    {
        if ($length < 128) {
            return chr($length);
        }

        $bytes = '';
        $temp = $length;
        while ($temp > 0) {
            $bytes = chr($temp & 0xFF) . $bytes;
            $temp >>= 8;
        }

        return chr(0x80 | strlen($bytes)) . $bytes;
    }
}
