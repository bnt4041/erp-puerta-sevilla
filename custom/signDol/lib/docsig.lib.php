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
 * \file    htdocs/custom/signDol/lib/docsig.lib.php
 * \ingroup docsig
 * \brief   Funciones de librería comunes para DocSig
 */

/**
 * Prepara las pestañas del header de administración
 *
 * @return array Array de pestañas
 */
function docsigAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load("docsig@signDol");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/signDol/admin/setup.php", 1);
    $head[$h][1] = $langs->trans("Settings");
    $head[$h][2] = 'settings';
    $h++;

    $head[$h][0] = dol_buildpath("/signDol/admin/about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    complete_head_from_modules($conf, $langs, null, $head, $h, 'docsig@signDol');
    complete_head_from_modules($conf, $langs, null, $head, $h, 'docsig@signDol', 'remove');

    return $head;
}

/**
 * Genera un token seguro aleatorio
 *
 * @param int $length Longitud en bytes (por defecto 32)
 * @return string Token en hexadecimal
 */
function docsig_generate_secure_token($length = 32)
{
    return bin2hex(random_bytes($length));
}

/**
 * Genera el hash de un token para almacenar en DB
 *
 * @param string $token Token plano
 * @return string Hash SHA-256
 */
function docsig_hash_token($token)
{
    return hash('sha256', $token);
}

/**
 * Verifica un token contra su hash almacenado
 *
 * @param string $token Token plano
 * @param string $hash Hash almacenado
 * @return bool True si coincide
 */
function docsig_verify_token($token, $hash)
{
    return hash_equals($hash, docsig_hash_token($token));
}

/**
 * Genera un código OTP numérico
 *
 * @param int $length Longitud del código (por defecto 6)
 * @return string Código OTP
 */
function docsig_generate_otp($length = 6)
{
    $otp = '';
    for ($i = 0; $i < $length; $i++) {
        $otp .= random_int(0, 9);
    }
    return $otp;
}

/**
 * Genera hash del OTP con salt
 *
 * @param string $otp Código OTP
 * @param string $salt Salt aleatorio
 * @return string Hash del OTP
 */
function docsig_hash_otp($otp, $salt)
{
    return hash('sha256', $salt . $otp);
}

/**
 * Verifica un OTP contra su hash almacenado
 *
 * @param string $otp Código OTP introducido
 * @param string $hash Hash almacenado
 * @param string $salt Salt usado
 * @return bool True si coincide
 */
function docsig_verify_otp($otp, $hash, $salt)
{
    return hash_equals($hash, docsig_hash_otp($otp, $salt));
}

/**
 * Calcula el hash SHA-256 de un archivo
 *
 * @param string $filepath Ruta del archivo
 * @return string|false Hash en hexadecimal o false si falla
 */
function docsig_file_hash($filepath)
{
    if (!file_exists($filepath)) {
        return false;
    }
    return hash_file('sha256', $filepath);
}

/**
 * Obtiene la IP del cliente de forma segura
 *
 * @return string Dirección IP
 */
function docsig_get_client_ip()
{
    $ip = '';
    
    // Prioridad: headers de proxy confiables, luego REMOTE_ADDR
    $headers = array(
        'HTTP_CF_CONNECTING_IP',     // Cloudflare
        'HTTP_X_REAL_IP',            // Nginx proxy
        'HTTP_X_FORWARDED_FOR',      // Proxy general
        'REMOTE_ADDR'
    );
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            // X-Forwarded-For puede contener múltiples IPs, tomar la primera
            if (strpos($ip, ',') !== false) {
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
            }
            break;
        }
    }
    
    // Validar IP
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }
    
    return '0.0.0.0';
}

/**
 * Obtiene el User-Agent del cliente
 *
 * @return string User-Agent
 */
function docsig_get_user_agent()
{
    return isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 512) : '';
}

/**
 * Valida formato de DNI/NIE español
 *
 * @param string $dni DNI o NIE a validar
 * @return bool True si es válido
 */
function docsig_validate_dni($dni)
{
    $dni = strtoupper(trim($dni));
    
    // DNI: 8 dígitos + letra
    if (preg_match('/^[0-9]{8}[A-Z]$/', $dni)) {
        $letters = 'TRWAGMYFPDXBNJZSQVHLCKE';
        $number = (int) substr($dni, 0, 8);
        $expectedLetter = $letters[$number % 23];
        return $dni[8] === $expectedLetter;
    }
    
    // NIE: X/Y/Z + 7 dígitos + letra
    if (preg_match('/^[XYZ][0-9]{7}[A-Z]$/', $dni)) {
        $letters = 'TRWAGMYFPDXBNJZSQVHLCKE';
        $prefix = array('X' => '0', 'Y' => '1', 'Z' => '2');
        $number = (int) ($prefix[$dni[0]] . substr($dni, 1, 7));
        $expectedLetter = $letters[$number % 23];
        return $dni[8] === $expectedLetter;
    }
    
    return false;
}

/**
 * Convierte un elemento de Dolibarr a su nombre legible
 *
 * @param string $element Tipo de elemento (facture, commande, etc.)
 * @return string Nombre legible
 */
function docsig_element_to_label($element)
{
    global $langs;
    
    $labels = array(
        'facture' => $langs->trans('Invoice'),
        'commande' => $langs->trans('Order'),
        'propal' => $langs->trans('Proposal'),
        'contrat' => $langs->trans('Contract'),
        'fichinter' => $langs->trans('Intervention'),
        'supplier_proposal' => $langs->trans('SupplierProposal'),
        'order_supplier' => $langs->trans('SupplierOrder'),
        'invoice_supplier' => $langs->trans('SupplierInvoice'),
    );
    
    return $labels[$element] ?? $element;
}

/**
 * Obtiene el objeto Dolibarr correspondiente a un elemento
 *
 * @param DoliDB $db Base de datos
 * @param string $element Tipo de elemento
 * @param int $fk_object ID del objeto
 * @return CommonObject|null Objeto cargado o null
 */
function docsig_get_object($db, $element, $fk_object)
{
    $object = null;
    
    switch ($element) {
        case 'facture':
            require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
            $object = new Facture($db);
            break;
        case 'commande':
            require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
            $object = new Commande($db);
            break;
        case 'propal':
            require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
            $object = new Propal($db);
            break;
        case 'contrat':
            require_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
            $object = new Contrat($db);
            break;
        case 'fichinter':
            require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
            $object = new Fichinter($db);
            break;
    }
    
    if ($object && $object->fetch($fk_object) > 0) {
        return $object;
    }
    
    return null;
}

/**
 * Array de estados del envelope con etiquetas y colores
 *
 * @return array Estados con info
 */
function docsig_get_envelope_statuses()
{
    global $langs;
    
    return array(
        0 => array('label' => $langs->trans('Draft'), 'code' => 'draft', 'color' => 'status0'),
        1 => array('label' => $langs->trans('Sent'), 'code' => 'sent', 'color' => 'status1'),
        2 => array('label' => $langs->trans('PartiallySigned'), 'code' => 'partial', 'color' => 'status3'),
        3 => array('label' => $langs->trans('Completed'), 'code' => 'completed', 'color' => 'status4'),
        4 => array('label' => $langs->trans('Canceled'), 'code' => 'canceled', 'color' => 'status5'),
        5 => array('label' => $langs->trans('Expired'), 'code' => 'expired', 'color' => 'status8'),
    );
}

/**
 * Array de estados del firmante con etiquetas y colores
 *
 * @return array Estados con info
 */
function docsig_get_signer_statuses()
{
    global $langs;
    
    return array(
        0 => array('label' => $langs->trans('Pending'), 'code' => 'pending', 'color' => 'status0'),
        1 => array('label' => $langs->trans('Viewed'), 'code' => 'viewed', 'color' => 'status1'),
        2 => array('label' => $langs->trans('OTPSent'), 'code' => 'otp_sent', 'color' => 'status1'),
        3 => array('label' => $langs->trans('OTPVerified'), 'code' => 'otp_verified', 'color' => 'status3'),
        4 => array('label' => $langs->trans('Signed'), 'code' => 'signed', 'color' => 'status4'),
        5 => array('label' => $langs->trans('Rejected'), 'code' => 'rejected', 'color' => 'status5'),
        6 => array('label' => $langs->trans('Expired'), 'code' => 'expired', 'color' => 'status8'),
        7 => array('label' => $langs->trans('Blocked'), 'code' => 'blocked', 'color' => 'status8'),
    );
}

/**
 * Genera badge HTML para un estado
 *
 * @param int $status Código de estado
 * @param string $type 'envelope' o 'signer'
 * @return string HTML del badge
 */
function docsig_get_status_badge($status, $type = 'envelope')
{
    if ($type === 'envelope') {
        $statuses = docsig_get_envelope_statuses();
    } else {
        $statuses = docsig_get_signer_statuses();
    }
    
    $statusInfo = $statuses[$status] ?? array('label' => 'Unknown', 'color' => 'status0');
    
    return '<span class="badge badge-'.$statusInfo['color'].'">'.$statusInfo['label'].'</span>';
}

/**
 * Genera la URL pública de firma
 *
 * @param string $token Token del firmante
 * @return string URL completa
 */
function docsig_get_public_sign_url($token)
{
    global $conf;
    
    $baseUrl = getDolGlobalString('DOCSIG_PUBLIC_URL');
    if (empty($baseUrl)) {
        $baseUrl = DOL_MAIN_URL_ROOT;
    }
    
    return $baseUrl.'/custom/signDol/public/sign.php?token='.$token;
}

/**
 * Genera la referencia única para un envelope
 *
 * @param DoliDB $db Base de datos
 * @return string Referencia única
 */
function docsig_get_next_ref($db)
{
    global $conf;
    
    $prefix = 'SIG';
    $year = date('Y');
    
    // Obtener el último número
    $sql = "SELECT MAX(CAST(SUBSTRING(ref, 8) AS UNSIGNED)) as maxnum";
    $sql .= " FROM ".MAIN_DB_PREFIX."docsig_envelope";
    $sql .= " WHERE ref LIKE '".$db->escape($prefix.$year)."%'";
    $sql .= " AND entity = ".(int) $conf->entity;
    
    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        $num = ($obj->maxnum ? $obj->maxnum + 1 : 1);
    } else {
        $num = 1;
    }
    
    return $prefix.$year.str_pad($num, 5, '0', STR_PAD_LEFT);
}

/**
 * Reemplaza variables en plantillas de email
 *
 * @param string $template Plantilla con variables __VAR__
 * @param array $vars Array asociativo de variables
 * @return string Texto con variables reemplazadas
 */
function docsig_replace_template_vars($template, $vars)
{
    foreach ($vars as $key => $value) {
        $template = str_replace('__'.$key.'__', $value, $template);
    }
    return $template;
}

/**
 * Verifica si un objeto Dolibarr tiene notificaciones habilitadas
 *
 * @param string $element Tipo de elemento
 * @return bool True si está habilitado
 */
function docsig_is_notification_enabled($element)
{
    $mapping = array(
        'facture' => 'DOCSIG_NOTIFY_FACTURE',
        'commande' => 'DOCSIG_NOTIFY_COMMANDE',
        'propal' => 'DOCSIG_NOTIFY_PROPAL',
        'contrat' => 'DOCSIG_NOTIFY_CONTRAT',
        'fichinter' => 'DOCSIG_NOTIFY_FICHINTER',
    );
    
    $constName = $mapping[$element] ?? '';
    if (empty($constName)) {
        return true; // Por defecto habilitado si no está en el mapping
    }
    
    return getDolGlobalInt($constName, 1) == 1;
}
