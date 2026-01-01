<?php
/* Copyright (C) 2024 PuertaSevilla
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
 */

/**
 * \defgroup   puertasevilla     Module PuertaSevilla
 * \brief      Módulo para gestión inmobiliaria PuertaSevilla
 * \file       core/modules/modPuertaSevilla.class.php
 * \ingroup    puertasevilla
 * \brief      Descriptor del módulo PuertaSevilla
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module PuertaSevilla
 */
class modPuertaSevilla extends DolibarrModules
{
    const CONTACT_TYPE_CODE_PROPIETARIO_FIRMANTE = 'PSVPROPSIGN';
    /**
     * Constructor. Define names, constants, directories, boxes, permissions
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $langs, $conf;
        $this->db = $db;

        // Id for module (must be unique)
        $this->numero = 500000;
        $this->rights_class = 'puertasevilla';
        $this->family = "other";
        $this->module_position = '90';
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = "Módulo de gestión inmobiliaria PuertaSevilla";
        $this->descriptionlong = "Gestión completa de propiedades, contratos, inquilinos, propietarios y automatización de facturación";
        $this->editor_name = 'PuertaSevilla';
        $this->editor_url = 'https://www.puertasevillainmobiliaria.online';
        $this->version = '1.0.0';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->picto = 'building@puertasevilla';

        // Dependencies
        $this->depends = array('modContrat', 'modFacture', 'modProjet', 'modSociete');
        $this->requiredby = array();
        $this->conflictwith = array();

        // Config pages
        $this->config_page_url = array('setup.php@puertasevilla');

        // Constants
        $this->const = array();

        // Array to add new pages in new tabs
        $this->tabs = array();

        // Dictionaries (se crearán manualmente en init())
        $this->dictionaries = array();

        // Boxes/Widgets
        $this->boxes = array();

        // Module parts
        $this->module_parts = array(
            'triggers' => 1,
            'hooks' => array('contractcard', 'globalcard'),
        );

        // Cronjobs
        $this->cronjobs = array();

        // Permissions
        $this->rights = array();
        $r = 0;

        $r++;
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Leer datos del módulo PuertaSevilla';
        $this->rights[$r][4] = 'read';

        $r++;
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Crear/Modificar datos del módulo PuertaSevilla';
        $this->rights[$r][4] = 'write';

        $r++;
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Eliminar datos del módulo PuertaSevilla';
        $this->rights[$r][4] = 'delete';

        // Main menu entries
        $this->menu = array();
    }

    /**
     * Function called when module is enabled.
     * The init function add constants, boxes, permissions and menus
     * (defined in constructor) into Dolibarr database.
     * It also creates data directories
     *
     * @param string $options Options when enabling module ('', 'noboxes')
     * @return int             1 if OK, 0 if KO
     */
    public function init($options = '')
    {
        global $conf, $langs;

        $sql = array();

        // Crear tablas de diccionarios
        // Tabla: Tipos de Mantenimiento
        $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."c_psv_tipo_mantenimiento (
            rowid integer AUTO_INCREMENT PRIMARY KEY,
            code varchar(16) NOT NULL,
            label varchar(128) NOT NULL,
            active tinyint DEFAULT 1 NOT NULL
        ) ENGINE=innodb";

        $sql[] = "DELETE FROM ".MAIN_DB_PREFIX."c_psv_tipo_mantenimiento";

        $sql[] = "INSERT INTO ".MAIN_DB_PREFIX."c_psv_tipo_mantenimiento (code, label, active) VALUES
            ('urgencia', 'Urgencia', 1),
            ('suministros', 'Suministros', 1),
            ('reparacion', 'Reparación', 1),
            ('limpieza', 'Limpieza', 1),
            ('revision', 'Revisión', 1),
            ('otros', 'Otros', 1)";

        // Tabla: Categorías Contables
        $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."c_psv_categoria_contable (
            rowid integer AUTO_INCREMENT PRIMARY KEY,
            code varchar(16) NOT NULL,
            label varchar(128) NOT NULL,
            active tinyint DEFAULT 1 NOT NULL
        ) ENGINE=innodb";

        $sql[] = "DELETE FROM ".MAIN_DB_PREFIX."c_psv_categoria_contable";

        $sql[] = "INSERT INTO ".MAIN_DB_PREFIX."c_psv_categoria_contable (code, label, active) VALUES
            ('alquiler', 'Alquiler', 1),
            ('comunidad', 'Comunidad', 1),
            ('mantenimiento', 'Mantenimiento', 1),
            ('suministros', 'Suministros', 1),
            ('otros', 'Otros', 1)";

        // Tabla: Estados de Vivienda
        $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."c_psv_estado_vivienda (
            rowid integer AUTO_INCREMENT PRIMARY KEY,
            code varchar(16) NOT NULL,
            label varchar(128) NOT NULL,
            active tinyint DEFAULT 1 NOT NULL
        ) ENGINE=innodb";

        $sql[] = "DELETE FROM ".MAIN_DB_PREFIX."c_psv_estado_vivienda";

        $sql[] = "INSERT INTO ".MAIN_DB_PREFIX."c_psv_estado_vivienda (code, label, active) VALUES
            ('ocupada', 'Ocupada', 1),
            ('vacia', 'Vacía', 1),
            ('reforma', 'En Reforma', 1),
            ('baja', 'Baja', 1)";

        // Tabla: Formas de Pago
        $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."c_psv_forma_pago (
            rowid integer AUTO_INCREMENT PRIMARY KEY,
            code varchar(16) NOT NULL,
            label varchar(128) NOT NULL,
            active tinyint DEFAULT 1 NOT NULL
        ) ENGINE=innodb";

        $sql[] = "DELETE FROM ".MAIN_DB_PREFIX."c_psv_forma_pago";

        $sql[] = "INSERT INTO ".MAIN_DB_PREFIX."c_psv_forma_pago (code, label, active) VALUES
            ('efectivo', 'Efectivo', 1),
            ('transferencia', 'Transferencia', 1),
            ('domiciliacion', 'Domiciliación', 1),
            ('tarjeta', 'Tarjeta', 1),
            ('cheque', 'Cheque', 1)";

        // Ejecutar los SQL
        foreach ($sql as $query) {
            $result = $this->db->query($query);
            if (!$result) {
                dol_syslog("Error ejecutando SQL: ".$this->db->lasterror(), LOG_ERR);
                // Continuar aunque haya errores (las tablas pueden ya existir)
            }
        }

        // Crear tipo de contacto para contratos: "Propietario firmante"
        $contactTypeExists = 0;
        $sqlCheck = "SELECT rowid FROM ".MAIN_DB_PREFIX."c_type_contact";
        $sqlCheck .= " WHERE element='contrat' AND source='external' AND code='".$this->db->escape(self::CONTACT_TYPE_CODE_PROPIETARIO_FIRMANTE)."'";
        $resCheck = $this->db->query($sqlCheck);
        if ($resCheck) {
            $contactTypeExists = $this->db->num_rows($resCheck);
        }
        if (empty($contactTypeExists)) {
            $sqlInsert = "INSERT INTO ".MAIN_DB_PREFIX."c_type_contact";
            $sqlInsert .= " (element, source, code, libelle, module, active, position) VALUES";
            $sqlInsert .= " ('contrat', 'external', '".$this->db->escape(self::CONTACT_TYPE_CODE_PROPIETARIO_FIRMANTE)."', 'Propietario firmante', 'puertasevilla', 1, 100)";
            $this->db->query($sqlInsert);
        }

        // Create extrafields
        include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
        $extrafields = new ExtraFields($this->db);

        // Limpieza por compatibilidad (cambio de definición)
        // - psv_entidad_ccc se elimina
        // - psv_ccc pasa de varchar a selector basado en RIB
        $extrafields->delete('psv_entidad_ccc', 'contratdet');
        $extrafields->delete('psv_ccc', 'contratdet');

        // EXTRAFIELDS para TERCEROS (societe)
        $result = $extrafields->addExtraField(
            'psv_rol',
            'Rol (Propietario/Inquilino/Administrador)',
            'select',
            100,
            '',
            'societe',
            0,
            0,
            '',
            array('options' => array('propietario' => 'Propietario', 'inquilino' => 'Inquilino', 'administrador' => 'Administrador')),
            1,
            '',
            1
        );

        $result = $extrafields->addExtraField(
            'psv_id_origen_tercero',
            'ID Origen del Tercero (migración)',
            'int',
            110,
            10,
            'societe',
            0,
            0,
            '',
            '',
            1,
            '',
            0
        );

        $result = $extrafields->addExtraField(
            'psv_nacionalidad',
            'Nacionalidad',
            'varchar',
            120,
            100,
            'societe',
            0,
            0,
            '',
            '',
            1,
            '',
            1
        );

        $result = $extrafields->addExtraField(
            'psv_forma_pago_origen',
            'Forma de Pago Origen',
            'select',
            130,
            '',
            'societe',
            0,
            0,
            '',
            array('options' => array('efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'domiciliacion' => 'Domiciliación')),
            1,
            '',
            1
        );

        $result = $extrafields->addExtraField(
            'psv_autoinforme',
            '¿Auto-informe?',
            'boolean',
            140,
            '',
            'societe',
            0,
            0,
            '',
            '',
            1,
            '',
            1
        );

        // EXTRAFIELDS para PROYECTOS (projet)
        $result = $extrafields->addExtraField(
            'psv_id_origen_vivienda',
            'ID Origen de Vivienda (migración)',
            'int',
            200,
            10,
            'projet',
            0,
            0,
            '',
            '',
            1,
            '',
            0
        );

        $result = $extrafields->addExtraField(
            'psv_ref_vivienda',
            'Referencia de Vivienda',
            'varchar',
            210,
            50,
            'projet',
            0,
            0,
            '',
            '',
            1,
            '',
            1
        );

        $result = $extrafields->addExtraField(
            'psv_direccion',
            'Dirección Completa',
            'varchar',
            220,
            255,
            'projet',
            0,
            0,
            '',
            '',
            1,
            '',
            1
        );

        $result = $extrafields->addExtraField(
            'psv_localidad',
            'Localidad',
            'varchar',
            230,
            100,
            'projet',
            0,
            0,
            '',
            '',
            1,
            '',
            1
        );

        $result = $extrafields->addExtraField(
            'psv_superficie',
            'Superficie (m²)',
            'double',
            240,
            10,
            'projet',
            0,
            0,
            '',
            '',
            1,
            '',
            1
        );

        $result = $extrafields->addExtraField(
            'psv_bagno',
            'Nº Baños',
            'int',
            250,
            2,
            'projet',
            0,
            0,
            '',
            '',
            1,
            '',
            1
        );

        $result = $extrafields->addExtraField(
            'psv_dormitorio',
            'Nº Dormitorios',
            'int',
            260,
            2,
            'projet',
            0,
            0,
            '',
            '',
            1,
            '',
            1
        );

        $result = $extrafields->addExtraField(
            'psv_catastro',
            'Referencia Catastral',
            'varchar',
            270,
            50,
            'projet',
            0,
            0,
            '',
            '',
            1,
            '',
            1
        );

        $result = $extrafields->addExtraField(
            'psv_estado_vivienda',
            'Estado de la Vivienda',
            'select',
            280,
            '',
            'projet',
            0,
            0,
            '',
            array('options' => array('ocupada' => 'Ocupada', 'vacia' => 'Vacía', 'reforma' => 'En Reforma', 'baja' => 'Baja')),
            1,
            '',
            1
        );

        $result = $extrafields->addExtraField(
            'psv_compania',
            'Compañía Suministros',
            'varchar',
            290,
            100,
            'projet',
            0,
            0,
            '',
            '',
            1,
            '',
            1
        );

        $result = $extrafields->addExtraField(
            'psv_ncontrato',
            'Nº Contrato Suministros',
            'varchar',
            300,
            100,
            'projet',
            0,
            0,
            '',
            '',
            1,
            '',
            1
        );

        $result = $extrafields->addExtraField(
            'psv_nombreCompania',
            'Nombre Compañía',
            'varchar',
            310,
            100,
            'projet',
            0,
            0,
            '',
            '',
            1,
            '',
            1
        );

        // EXTRAFIELDS para CONTRATOS (contrat)
        $result = $extrafields->addExtraField(
            'psv_id_origen_contrato_usuario',
            'ID Origen Contrato Usuario (migración)',
            'int',
            400,
            10,
            'contrat',
            0,
            0,
            '',
            '',
            1,
            '',
            0
        );

        $result = $extrafields->addExtraField(
            'psv_dia_pago',
            'Día de Pago (1-31)',
            'int',
            410,
            2,
            'contrat',
            0,
            0,
            '',
            '',
            1,
            '',
            1
        );

        $result = $extrafields->addExtraField(
            'psv_inventario',
            'Inventario',
            'text',
            420,
            '',
            'contrat',
            0,
            0,
            '',
            '',
            1,
            '',
            1
        );

        $result = $extrafields->addExtraField(
            'psv_autofactura',
            '¿Auto-factura?',
            'boolean',
            430,
            '',
            'contrat',
            0,
            0,
            '',
            '',
            1,
            '',
            1
        );

        // EXTRAFIELDS para LÍNEAS DE CONTRATO (contratdet)
        $result = $extrafields->addExtraField(
            'psv_ccc',
            'Cuenta Bancaria (CCC/IBAN)',
            'varchar',
            440,
            255,
            'contratdet',
            0,
            0,
            '',
            '',
            1,
            '',
            1
        );

        // EXTRAFIELDS para FACTURAS (facture)
        $result = $extrafields->addExtraField(
            'psv_id_origen_factura',
            'ID Origen Factura (migración)',
            'int',
            500,
            10,
            'facture',
            0,
            0,
            '',
            '',
            1,
            '',
            0
        );

        $result = $extrafields->addExtraField(
            'psv_tipo',
            'Tipo de Factura',
            'select',
            510,
            '',
            'facture',
            0,
            0,
            '',
            array('options' => array('alquiler' => 'Alquiler', 'comunidad' => 'Comunidad', 'otros' => 'Otros')),
            1,
            '',
            1
        );

        // EXTRAFIELDS para PEDIDOS (commande)
        $result = $extrafields->addExtraField(
            'psv_id_origen_mantenimiento',
            'ID Origen Mantenimiento (migración)',
            'int',
            600,
            10,
            'commande',
            0,
            0,
            '',
            '',
            1,
            '',
            0
        );

        $result = $extrafields->addExtraField(
            'psv_tipo_mantenimiento',
            'Tipo de Mantenimiento',
            'select',
            610,
            '',
            'commande',
            0,
            0,
            '',
            array('options' => array('urgencia' => 'Urgencia', 'suministros' => 'Suministros', 'reparacion' => 'Reparación', 'limpieza' => 'Limpieza', 'otros' => 'Otros')),
            1,
            '',
            1
        );

        $result = $extrafields->addExtraField(
            'psv_horas_trabajadas',
            'Horas Trabajadas',
            'double',
            620,
            10,
            'commande',
            0,
            0,
            '',
            '',
            1,
            '',
            1
        );

        $result = $extrafields->addExtraField(
            'psv_observaciones',
            'Observaciones',
            'text',
            630,
            '',
            'commande',
            0,
            0,
            '',
            '',
            1,
            '',
            1
        );

        return $this->_init(array(), $options);
    }

    /**
     * Function called when module is disabled.
     * Remove from database constants, boxes and permissions from Dolibarr database.
     * Data directories are not deleted
     *
     * @param string $options Options when disabling module ('', 'noboxes')
     * @return int             1 if OK, 0 if KO
     */
    public function remove($options = '')
    {
        // Borrar extrafields creados por el módulo
        include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
        $extrafields = new ExtraFields($this->db);

        $fieldsByElement = array(
            'societe' => array('psv_rol', 'psv_id_origen_tercero', 'psv_nacionalidad', 'psv_forma_pago_origen', 'psv_autoinforme'),
            'projet' => array(
                'psv_id_origen_vivienda', 'psv_ref_vivienda', 'psv_direccion', 'psv_localidad',
                'psv_superficie', 'psv_bagno', 'psv_dormitorio', 'psv_catastro',
                'psv_estado_vivienda', 'psv_compania', 'psv_ncontrato', 'psv_nombreCompania'
            ),
            'contrat' => array('psv_id_origen_contrato_usuario', 'psv_dia_pago', 'psv_inventario', 'psv_autofactura'),
            'contratdet' => array('psv_ccc'),
            'facture' => array('psv_id_origen_factura', 'psv_tipo'),
            'commande' => array('psv_id_origen_mantenimiento', 'psv_tipo_mantenimiento', 'psv_horas_trabajadas', 'psv_observaciones'),
        );

        foreach ($fieldsByElement as $elementtype => $fields) {
            foreach ($fields as $attrname) {
                $extrafields->delete($attrname, $elementtype);
            }
        }

        // Borrar el tipo de contacto creado
        $sqlDeleteContactType = "DELETE FROM ".MAIN_DB_PREFIX."c_type_contact";
        $sqlDeleteContactType .= " WHERE element='contrat' AND source='external'";
        $sqlDeleteContactType .= " AND code='".$this->db->escape(self::CONTACT_TYPE_CODE_PROPIETARIO_FIRMANTE)."'";
        $sqlDeleteContactType .= " AND module='puertasevilla'";
        $this->db->query($sqlDeleteContactType);

        return $this->_remove(array(), $options);
    }
}
