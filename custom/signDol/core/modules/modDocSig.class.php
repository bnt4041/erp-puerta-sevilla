<?php
/* Copyright (C) 2026 DocSig Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \defgroup   docsig     Module DocSig
 * \brief      Document Signature Dolibarr - Módulo para firma digital de documentos
 *
 * \file       htdocs/custom/signDol/core/modules/modDocSig.class.php
 * \ingroup    docsig
 * \brief      Descriptor del módulo DocSig
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Clase de descripción y activación del módulo DocSig
 */
class modDocSig extends DolibarrModules
{
    /**
     * Constructor. Define nombres, constantes, directorios, cajas, permisos
     *
     * @param DoliDB $db Manejador de base de datos
     */
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;

        // ID único del módulo (reservado: 60000010)
        $this->numero = 60000010;

        // Clave de texto para permisos, menús, etc.
        $this->rights_class = 'docsig';

        // Familia del módulo
        $this->family = "technic";

        // Posición del módulo en la familia
        $this->module_position = '50';

        // Nombre del módulo
        $this->name = preg_replace('/^mod/i', '', get_class($this));

        // Descripción del módulo
        $this->description = "Firma digital de documentos PDF con PAdES y sello de tiempo TSA";
        $this->descriptionlong = "Módulo para solicitar y recoger firmas digitales sobre PDFs generados en Dolibarr. Incluye firma PAdES, sello de tiempo TSA RFC3161, doble autenticación (DNI + email/teléfono + OTP), certificado de cumplimiento y registro de auditoría.";

        // Autor
        $this->editor_name = 'DocSig Module';
        $this->editor_url = '';

        // Versión
        $this->version = '1.0.0';

        // Clave para guardar estado del módulo
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

        // Icono del módulo
        $this->picto = 'fa-file-signature';

        // Características del módulo
        $this->module_parts = array(
            'triggers' => 1,
            'login' => 0,
            'substitutions' => 0,
            'menus' => 0,
            'tpl' => 0,
            'barcode' => 0,
            'models' => 0,
            'printing' => 0,
            'theme' => 0,
            'css' => array(
                '/signDol/css/docsig.css.php',
            ),
            'js' => array(
                '/signDol/js/docsig.js.php',
            ),
            'hooks' => array(
                'data' => array(
                    'invoicecard',
                    'invoicelist',
                    'ordercard',
                    'orderlist',
                    'propalcard',
                    'propallist',
                    'contractcard',
                    'contractlist',
                    'contactcard',
                    'thirdpartycard',
                    'globalcard',
                    'formfile',
                    'fileslib',
                ),
                'entity' => '0',
            ),
            'moduleforexternal' => 1,
            'websitetemplates' => 0
        );

        // Directorios a crear
        $this->dirs = array(
            "/docsig/temp",
            "/docsig/certificates",
            "/docsig/signatures"
        );

        // Página de configuración
        $this->config_page_url = array("setup.php@signDol");

        // Dependencias
        $this->hidden = false;
        $this->depends = array();
        $this->requiredby = array();
        $this->conflictwith = array();

        // Archivo de idioma
        $this->langfiles = array("docsig@signDol");

        // Requisitos
        $this->phpmin = array(8, 1);
        $this->need_dolibarr_version = array(19, 0);
        $this->need_javascript_ajax = 1;

        // Mensajes de activación
        $this->warnings_activation = array();
        $this->warnings_activation_ext = array();

        // Constantes del módulo
        $this->const = array(
            // TSA Configuration
            1 => array('DOCSIG_TSA_URL', 'chaine', 'https://freetsa.org/tsr', 'URL del servidor TSA RFC3161', 1),
            2 => array('DOCSIG_TSA_USER', 'chaine', '', 'Usuario TSA (opcional)', 1),
            3 => array('DOCSIG_TSA_PASS', 'chaine', '', 'Contraseña TSA (opcional)', 0),
            4 => array('DOCSIG_TSA_POLICY', 'chaine', '', 'Política TSA OID (opcional)', 1),
            
            // Token & OTP Configuration
            5 => array('DOCSIG_TOKEN_EXPIRATION_DAYS', 'chaine', '7', 'Días de expiración del token de firma', 1),
            6 => array('DOCSIG_OTP_EXPIRATION_MINUTES', 'chaine', '10', 'Minutos de expiración del OTP', 1),
            7 => array('DOCSIG_OTP_MAX_ATTEMPTS', 'chaine', '5', 'Intentos máximos de OTP', 1),
            8 => array('DOCSIG_OTP_LENGTH', 'chaine', '6', 'Longitud del código OTP', 1),
            
            // Signature Configuration
            9 => array('DOCSIG_SIGNATURE_MODE', 'chaine', 'parallel', 'Modo de firma: parallel o sequential', 1),
            10 => array('DOCSIG_SIGNATURE_VISIBLE', 'chaine', '1', 'Firma visible en PDF', 1),
            11 => array('DOCSIG_SIGNATURE_POSITION', 'chaine', 'bottom-right', 'Posición de firma: bottom-right, bottom-left, top-right, top-left', 1),
            
            // Notification Configuration  
            12 => array('DOCSIG_NOTIFY_FACTURE', 'chaine', '1', 'Habilitar notificaciones para facturas', 1),
            13 => array('DOCSIG_NOTIFY_COMMANDE', 'chaine', '1', 'Habilitar notificaciones para pedidos', 1),
            14 => array('DOCSIG_NOTIFY_PROPAL', 'chaine', '1', 'Habilitar notificaciones para presupuestos', 1),
            15 => array('DOCSIG_NOTIFY_CONTRAT', 'chaine', '1', 'Habilitar notificaciones para contratos', 1),
            16 => array('DOCSIG_NOTIFY_FICHINTER', 'chaine', '1', 'Habilitar notificaciones para intervenciones', 1),
            
            // Email Templates
            17 => array('DOCSIG_EMAIL_SUBJECT_REQUEST', 'chaine', 'Solicitud de firma: __REF__', 'Asunto email solicitud', 1),
            18 => array('DOCSIG_EMAIL_SUBJECT_OTP', 'chaine', 'Código de verificación: __CODE__', 'Asunto email OTP', 1),
            19 => array('DOCSIG_EMAIL_SUBJECT_COMPLETED', 'chaine', 'Documento firmado: __REF__', 'Asunto email completado', 1),
            20 => array('DOCSIG_EMAIL_SUBJECT_REMINDER', 'chaine', 'Recordatorio de firma: __REF__', 'Asunto email recordatorio', 1),
            
            // Security
            21 => array('DOCSIG_PUBLIC_DOWNLOAD_ENABLED', 'chaine', '0', 'Permitir descarga pública del PDF firmado', 1),
            22 => array('DOCSIG_RATE_LIMIT_OTP', 'chaine', '3', 'OTPs máximos por minuto por IP', 1),
            
            // Certificate Configuration
            23 => array('DOCSIG_CERT_KEY_SIZE', 'chaine', '2048', 'Tamaño de clave RSA para certificado interno', 1),
            24 => array('DOCSIG_CERT_VALIDITY_DAYS', 'chaine', '3650', 'Días de validez del certificado interno', 1),
            25 => array('DOCSIG_CERT_CN', 'chaine', 'DocSig Internal CA', 'Common Name del certificado', 1),
            26 => array('DOCSIG_CERT_ORG', 'chaine', '', 'Organización del certificado', 1),
        );

        // Inicializar si no está habilitado
        if (!isModEnabled('docsig')) {
            $conf->docsig = new stdClass();
            $conf->docsig->enabled = 0;
        }

        // Tabs adicionales
        $this->tabs = array(
            'contact:+docsig_notifications:DocSigNotifications:docsig@signDol:$user->hasRight("docsig", "read"):/signDol/contact_notifications.php?id=__ID__',
        );

        // Diccionarios - Tipo de actioncomm para rastro de notificaciones
        $this->dictionaries = array(
            'langs' => 'docsig@signDol',
            'tabname' => array(MAIN_DB_PREFIX.'c_actioncomm'),
            'tablib' => array('DocSigActionCommTypes'),
            'tabsql' => array('SELECT id as rowid, code, type, libelle, module, active, color, picto, position FROM '.MAIN_DB_PREFIX.'c_actioncomm WHERE module = \'docsig\''),
            'tabsqlsort' => array('position ASC'),
            'tabfield' => array('code,type,libelle,module,active,color,picto,position'),
            'tabfieldvalue' => array('code,type,libelle,module,active,color,picto,position'),
            'tabfieldinsert' => array('code,type,libelle,module,active,color,picto,position'),
            'tabrowid' => array('rowid'),
            'tabcond' => array(isModEnabled('docsig')),
            'tabhelp' => array(array('code' => 'Código único del tipo de acción', 'libelle' => 'Etiqueta visible')),
        );

        // Widgets/Boxes
        $this->boxes = array(
            0 => array(
                'file' => 'docsigwidget.php@signDol',
                'note' => 'Widget de firmas pendientes',
                'enabledbydefaulton' => 'Home',
            ),
        );

        // Cron jobs
        $this->cronjobs = array(
            0 => array(
                'label' => 'Enviar recordatorios de firma pendiente',
                'jobtype' => 'method',
                'class' => '/signDol/class/docsigenvelope.class.php',
                'objectname' => 'DocSigEnvelope',
                'method' => 'sendReminders',
                'parameters' => '',
                'comment' => 'Envía recordatorios a firmantes pendientes',
                'frequency' => 1,
                'unitfrequency' => 86400,
                'status' => 0,
                'test' => 'isModEnabled("docsig")',
                'priority' => 50,
            ),
            1 => array(
                'label' => 'Expirar tokens vencidos',
                'jobtype' => 'method',
                'class' => '/signDol/class/docsigenvelope.class.php',
                'objectname' => 'DocSigEnvelope',
                'method' => 'expireTokens',
                'parameters' => '',
                'comment' => 'Marca como expirados los tokens vencidos',
                'frequency' => 1,
                'unitfrequency' => 3600,
                'status' => 0,
                'test' => 'isModEnabled("docsig")',
                'priority' => 50,
            ),
        );

        // Permisos
        $this->rights = array();
        $r = 0;

        // Permiso de lectura
        $this->rights[$r][0] = $this->numero + 1;
        $this->rights[$r][1] = 'Leer solicitudes de firma';
        $this->rights[$r][4] = 'envelope';
        $this->rights[$r][5] = 'read';
        $r++;

        // Permiso de creación
        $this->rights[$r][0] = $this->numero + 2;
        $this->rights[$r][1] = 'Crear solicitudes de firma';
        $this->rights[$r][4] = 'envelope';
        $this->rights[$r][5] = 'write';
        $r++;

        // Permiso de eliminación/cancelación
        $this->rights[$r][0] = $this->numero + 3;
        $this->rights[$r][1] = 'Cancelar solicitudes de firma';
        $this->rights[$r][4] = 'envelope';
        $this->rights[$r][5] = 'delete';
        $r++;

        // Permiso de descarga
        $this->rights[$r][0] = $this->numero + 4;
        $this->rights[$r][1] = 'Descargar documentos firmados';
        $this->rights[$r][4] = 'document';
        $this->rights[$r][5] = 'download';
        $r++;

        // Permiso de administración
        $this->rights[$r][0] = $this->numero + 5;
        $this->rights[$r][1] = 'Administrar configuración de firmas';
        $this->rights[$r][4] = 'admin';
        $this->rights[$r][5] = 'setup';
        $r++;

        // Menús
        $this->menu = array();
        $r = 0;

        // Menú principal
        $this->menu[$r++] = array(
            'fk_menu' => '',
            'type' => 'top',
            'titre' => 'DocSig',
            'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
            'mainmenu' => 'docsig',
            'leftmenu' => '',
            'url' => '/signDol/list.php',
            'langs' => 'docsig@signDol',
            'position' => 1000 + $r,
            'enabled' => 'isModEnabled("docsig")',
            'perms' => '$user->hasRight("docsig", "envelope", "read")',
            'target' => '',
            'user' => 2,
        );

        // Submenú - Lista de solicitudes
        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=docsig',
            'type' => 'left',
            'titre' => 'Lista de firmas',
            'prefix' => img_picto('', 'list', 'class="pictofixedwidth valignmiddle paddingright"'),
            'mainmenu' => 'docsig',
            'leftmenu' => 'docsig_list',
            'url' => '/signDol/list.php',
            'langs' => 'docsig@signDol',
            'position' => 1000 + $r,
            'enabled' => 'isModEnabled("docsig")',
            'perms' => '$user->hasRight("docsig", "envelope", "read")',
            'target' => '',
            'user' => 2,
        );

        // Submenú - Nueva solicitud
        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=docsig,fk_leftmenu=docsig_list',
            'type' => 'left',
            'titre' => 'Nueva solicitud',
            'mainmenu' => 'docsig',
            'leftmenu' => 'docsig_new',
            'url' => '/signDol/card.php?action=create',
            'langs' => 'docsig@signDol',
            'position' => 1000 + $r,
            'enabled' => 'isModEnabled("docsig")',
            'perms' => '$user->hasRight("docsig", "envelope", "write")',
            'target' => '',
            'user' => 2,
        );

        // Submenú - Configuración
        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=docsig',
            'type' => 'left',
            'titre' => 'Configuración',
            'prefix' => img_picto('', 'setup', 'class="pictofixedwidth valignmiddle paddingright"'),
            'mainmenu' => 'docsig',
            'leftmenu' => 'docsig_setup',
            'url' => '/signDol/admin/setup.php',
            'langs' => 'docsig@signDol',
            'position' => 1000 + $r,
            'enabled' => 'isModEnabled("docsig")',
            'perms' => '$user->admin',
            'target' => '',
            'user' => 0,
        );
    }

    /**
     * Función llamada cuando se habilita el módulo.
     * Añade constantes, cajas, permisos y menús a la base de datos.
     * También crea directorios de datos y genera el certificado interno.
     *
     * @param string $options Opciones al habilitar ('', 'noboxes')
     * @return int 1 si OK, 0 si KO
     */
    public function init($options = '')
    {
        global $conf, $langs;

        // Cargar tablas SQL
        $result = $this->_load_tables('/signDol/sql/');
        if ($result < 0) {
            return -1;
        }

        // Crear extrafield tva_intra para contactos si no existe
        include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
        $extrafields = new ExtraFields($this->db);
        
        // Verificar si ya existe el extrafield
        $extrafields->fetch_name_optionals_label('socpeople');
        if (!isset($extrafields->attributes['socpeople']['type']['docsig_dni'])) {
            $result = $extrafields->addExtraField(
                'docsig_dni',
                'DNI/NIE',
                'varchar',
                100,
                20,
                'socpeople',
                0,
                0,
                '',
                '',
                1,
                '',
                0,
                0,
                '',
                '',
                'docsig@signDol',
                'isModEnabled("docsig")'
            );
        }

        // Eliminar módulo anterior para reinstalar
        $this->remove($options);

        $sql = array();

        // Generar certificado interno si no existe
        $this->generateInternalCertificate();

        return $this->_init($sql, $options);
    }

    /**
     * Función llamada cuando se deshabilita el módulo.
     *
     * @param string $options Opciones al deshabilitar
     * @return int 1 si OK, 0 si KO
     */
    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }

    /**
     * Genera el par de claves RSA y certificado autofirmado para firmar documentos
     *
     * @return bool true si se generó correctamente
     */
    private function generateInternalCertificate()
    {
        global $conf;

        // Verificar si ya existe el certificado
        $certPath = $conf->docsig->dir_output.'/certificates/docsig_internal.crt';
        $keyPath = $conf->docsig->dir_output.'/certificates/docsig_internal.key';

        if (file_exists($certPath) && file_exists($keyPath)) {
            return true;
        }

        // Crear directorio si no existe
        if (!is_dir($conf->docsig->dir_output.'/certificates')) {
            dol_mkdir($conf->docsig->dir_output.'/certificates');
        }

        // Configuración del certificado
        $keySize = getDolGlobalInt('DOCSIG_CERT_KEY_SIZE', 2048);
        $validityDays = getDolGlobalInt('DOCSIG_CERT_VALIDITY_DAYS', 3650);
        $cn = getDolGlobalString('DOCSIG_CERT_CN', 'DocSig Internal CA');
        $org = getDolGlobalString('DOCSIG_CERT_ORG', $conf->global->MAIN_INFO_SOCIETE_NOM ?? 'DocSig');

        // Generar par de claves RSA
        $configargs = array(
            'private_key_bits' => $keySize,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg' => 'sha256',
        );

        $privateKey = openssl_pkey_new($configargs);
        if (!$privateKey) {
            dol_syslog('DocSig: Error generando clave privada: ' . openssl_error_string(), LOG_ERR);
            return false;
        }

        // Información del certificado
        $dn = array(
            'commonName' => $cn,
            'organizationName' => $org,
            'countryName' => 'ES',
        );

        // Generar certificado autofirmado
        $csr = openssl_csr_new($dn, $privateKey, $configargs);
        $cert = openssl_csr_sign($csr, null, $privateKey, $validityDays, $configargs);

        // Exportar certificado
        openssl_x509_export($cert, $certPem);
        
        // Exportar clave privada (cifrada con el unique_id de Dolibarr)
        $passphrase = $conf->global->DOLIBARR_MAIN_INSTANCE_UNIQUE_ID ?? $conf->file->instance_unique_id;
        openssl_pkey_export($privateKey, $keyPem, $passphrase);

        // Guardar archivos
        $resultCert = file_put_contents($certPath, $certPem);
        $resultKey = file_put_contents($keyPath, $keyPem);

        // Establecer permisos restrictivos
        if ($resultCert && $resultKey) {
            chmod($keyPath, 0600);
            chmod($certPath, 0644);
            dol_syslog('DocSig: Certificado interno generado correctamente', LOG_INFO);
            return true;
        }

        dol_syslog('DocSig: Error guardando certificado interno', LOG_ERR);
        return false;
    }
}
