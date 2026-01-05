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
 * \file    htdocs/custom/signDol/class/docsigcertificate.class.php
 * \ingroup docsig
 * \brief   Gestión del certificado interno para firma de documentos
 */

/**
 * Clase para gestionar el certificado interno del módulo
 */
class DocSigCertificate
{
    /**
     * @var DoliDB Base de datos
     */
    public $db;

    /**
     * @var string Ruta al certificado
     */
    private $certPath;

    /**
     * @var string Ruta a la clave privada
     */
    private $keyPath;

    /**
     * @var string Passphrase para la clave privada
     */
    private $passphrase;

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
        
        $basePath = $conf->docsig->dir_output.'/certificates';
        $this->certPath = $basePath.'/docsig_internal.crt';
        $this->keyPath = $basePath.'/docsig_internal.key';
        $this->passphrase = $conf->global->DOLIBARR_MAIN_INSTANCE_UNIQUE_ID ?? $conf->file->instance_unique_id;
    }

    /**
     * Verifica si el certificado existe
     *
     * @return bool True si existe
     */
    public function exists()
    {
        return file_exists($this->certPath) && file_exists($this->keyPath);
    }

    /**
     * Obtiene la información del certificado
     *
     * @return array|false Array con información del certificado o false si no existe
     */
    public function getInfo()
    {
        if (!$this->exists()) {
            return false;
        }

        $certContent = file_get_contents($this->certPath);
        $certData = openssl_x509_parse($certContent);

        if (!$certData) {
            $this->error = 'Error parsing certificate';
            return false;
        }

        return array(
            'subject' => $certData['subject'],
            'issuer' => $certData['issuer'],
            'validFrom' => $certData['validFrom_time_t'],
            'validTo' => $certData['validTo_time_t'],
            'serialNumber' => $certData['serialNumber'],
            'serialNumberHex' => $certData['serialNumberHex'] ?? dechex($certData['serialNumber']),
            'version' => $certData['version'],
            'signatureTypeSN' => $certData['signatureTypeSN'] ?? 'SHA256-RSA',
        );
    }

    /**
     * Verifica si el certificado es válido (no expirado)
     *
     * @return bool True si es válido
     */
    public function isValid()
    {
        $info = $this->getInfo();
        if (!$info) {
            return false;
        }

        $now = time();
        return ($now >= $info['validFrom'] && $now <= $info['validTo']);
    }

    /**
     * Obtiene el contenido del certificado en formato PEM
     *
     * @return string|false Certificado PEM o false si falla
     */
    public function getCertificatePEM()
    {
        if (!$this->exists()) {
            $this->error = 'Certificate does not exist';
            return false;
        }

        return file_get_contents($this->certPath);
    }

    /**
     * Obtiene la clave privada (desencriptada)
     *
     * @return resource|OpenSSLAsymmetricKey|false Clave privada o false si falla
     */
    public function getPrivateKey()
    {
        if (!$this->exists()) {
            $this->error = 'Private key does not exist';
            return false;
        }

        $keyContent = file_get_contents($this->keyPath);
        $privateKey = openssl_pkey_get_private($keyContent, $this->passphrase);

        if (!$privateKey) {
            $this->error = 'Error decrypting private key: ' . openssl_error_string();
            return false;
        }

        return $privateKey;
    }

    /**
     * Firma datos con la clave privada
     *
     * @param string $data Datos a firmar
     * @param int $algorithm Algoritmo de firma (default: OPENSSL_ALGO_SHA256)
     * @return string|false Firma binaria o false si falla
     */
    public function sign($data, $algorithm = OPENSSL_ALGO_SHA256)
    {
        $privateKey = $this->getPrivateKey();
        if (!$privateKey) {
            return false;
        }

        $signature = '';
        $result = openssl_sign($data, $signature, $privateKey, $algorithm);

        if (!$result) {
            $this->error = 'Error signing data: ' . openssl_error_string();
            return false;
        }

        return $signature;
    }

    /**
     * Verifica una firma
     *
     * @param string $data Datos originales
     * @param string $signature Firma a verificar
     * @param int $algorithm Algoritmo de firma
     * @return bool True si la firma es válida
     */
    public function verify($data, $signature, $algorithm = OPENSSL_ALGO_SHA256)
    {
        $certPEM = $this->getCertificatePEM();
        if (!$certPEM) {
            return false;
        }

        $publicKey = openssl_pkey_get_public($certPEM);
        if (!$publicKey) {
            $this->error = 'Error getting public key: ' . openssl_error_string();
            return false;
        }

        $result = openssl_verify($data, $signature, $publicKey, $algorithm);

        return $result === 1;
    }

    /**
     * Genera un nuevo par de claves y certificado autofirmado
     *
     * @param array $options Opciones de generación
     * @return bool True si se generó correctamente
     */
    public function generate($options = array())
    {
        global $conf;

        // Opciones por defecto
        $keySize = $options['keySize'] ?? getDolGlobalInt('DOCSIG_CERT_KEY_SIZE', 2048);
        $validityDays = $options['validityDays'] ?? getDolGlobalInt('DOCSIG_CERT_VALIDITY_DAYS', 3650);
        $cn = $options['cn'] ?? getDolGlobalString('DOCSIG_CERT_CN', 'DocSig Internal CA');
        $org = $options['org'] ?? getDolGlobalString('DOCSIG_CERT_ORG', $conf->global->MAIN_INFO_SOCIETE_NOM ?? 'DocSig');
        $country = $options['country'] ?? 'ES';

        // Crear directorio si no existe
        $dir = dirname($this->certPath);
        if (!is_dir($dir)) {
            if (!dol_mkdir($dir)) {
                $this->error = 'Cannot create certificate directory';
                return false;
            }
        }

        // Configuración de OpenSSL
        $configargs = array(
            'private_key_bits' => $keySize,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg' => 'sha256',
        );

        // Generar par de claves
        $privateKey = openssl_pkey_new($configargs);
        if (!$privateKey) {
            $this->error = 'Error generating private key: ' . openssl_error_string();
            return false;
        }

        // Información del certificado
        $dn = array(
            'commonName' => $cn,
            'organizationName' => $org,
            'countryName' => $country,
        );

        // Generar CSR
        $csr = openssl_csr_new($dn, $privateKey, $configargs);
        if (!$csr) {
            $this->error = 'Error generating CSR: ' . openssl_error_string();
            return false;
        }

        // Autofirmar
        $cert = openssl_csr_sign($csr, null, $privateKey, $validityDays, $configargs);
        if (!$cert) {
            $this->error = 'Error signing certificate: ' . openssl_error_string();
            return false;
        }

        // Exportar certificado
        $certPem = '';
        if (!openssl_x509_export($cert, $certPem)) {
            $this->error = 'Error exporting certificate: ' . openssl_error_string();
            return false;
        }

        // Exportar clave privada (cifrada)
        $keyPem = '';
        if (!openssl_pkey_export($privateKey, $keyPem, $this->passphrase)) {
            $this->error = 'Error exporting private key: ' . openssl_error_string();
            return false;
        }

        // Guardar archivos
        if (file_put_contents($this->certPath, $certPem) === false) {
            $this->error = 'Error saving certificate file';
            return false;
        }

        if (file_put_contents($this->keyPath, $keyPem) === false) {
            $this->error = 'Error saving private key file';
            // Limpiar certificado si falla la clave
            unlink($this->certPath);
            return false;
        }

        // Establecer permisos restrictivos
        chmod($this->keyPath, 0600);
        chmod($this->certPath, 0644);

        dol_syslog('DocSigCertificate::generate - Certificate generated successfully', LOG_INFO);

        return true;
    }

    /**
     * Elimina el certificado y la clave privada
     *
     * @return bool True si se eliminó correctamente
     */
    public function delete()
    {
        $success = true;

        if (file_exists($this->certPath)) {
            $success = $success && unlink($this->certPath);
        }

        if (file_exists($this->keyPath)) {
            $success = $success && unlink($this->keyPath);
        }

        return $success;
    }

    /**
     * Genera un hash PKCS#7 firmado de los datos
     *
     * @param string $data Datos a firmar
     * @param bool $detached Si es true, genera firma separada
     * @return string|false Firma PKCS#7 o false si falla
     */
    public function signPKCS7($data, $detached = true)
    {
        // Crear archivo temporal con los datos
        $dataFile = tempnam(sys_get_temp_dir(), 'docsig_data_');
        $signFile = tempnam(sys_get_temp_dir(), 'docsig_sign_');

        file_put_contents($dataFile, $data);

        $flags = PKCS7_BINARY | PKCS7_NOATTR;
        if ($detached) {
            $flags |= PKCS7_DETACHED;
        }

        $privateKey = $this->getPrivateKey();
        if (!$privateKey) {
            unlink($dataFile);
            unlink($signFile);
            return false;
        }

        $cert = file_get_contents($this->certPath);

        $result = openssl_pkcs7_sign(
            $dataFile,
            $signFile,
            $cert,
            $privateKey,
            array(),
            $flags
        );

        if (!$result) {
            $this->error = 'Error creating PKCS#7 signature: ' . openssl_error_string();
            unlink($dataFile);
            unlink($signFile);
            return false;
        }

        $signature = file_get_contents($signFile);

        // Limpiar archivos temporales
        unlink($dataFile);
        unlink($signFile);

        return $signature;
    }

    /**
     * Obtiene la huella digital (fingerprint) del certificado
     *
     * @param string $hashAlgo Algoritmo de hash (default: sha256)
     * @return string|false Fingerprint o false si falla
     */
    public function getFingerprint($hashAlgo = 'sha256')
    {
        $certPEM = $this->getCertificatePEM();
        if (!$certPEM) {
            return false;
        }

        $fingerprint = openssl_x509_fingerprint($certPEM, $hashAlgo);
        
        return $fingerprint ? strtoupper(chunk_split($fingerprint, 2, ':')) : false;
    }
}
