<?php
/* Copyright (C) 2025 ZonaJob Dev
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
 * \defgroup   zonajob     Module ZonaJob
 * \brief      ZonaJob module descriptor - Gestión de pedidos para zona empleado
 *
 * \file       htdocs/custom/zonajob/core/modules/modZonaJob.class.php
 * \ingroup    zonajob
 * \brief      Description and activation file for module ZonaJob
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module ZonaJob
 */
class modZonaJob extends DolibarrModules
{
    /**
     * Constructor. Define names, constants, directories, boxes, permissions
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $langs, $conf;
        $this->db = $db;

        // Id for module (must be unique).
        $this->numero = 6000020;

        // Key text used to identify module (for permissions, menus, etc...)
        $this->rights_class = 'zonajob';

        // Family can be 'base','crm','financial','hr','projects','products','ecm','technic','interface','other'
        $this->family = "other";

        // Module position in the family on 2 digits ('01', '10', '20', ...)
        $this->module_position = '91';

        // Module label (no space allowed)
        $this->name = preg_replace('/^mod/i', '', get_class($this));

        // Module description
        $this->description = "ZonaJob - Gestión de pedidos y firma de cliente para Zona Empleado";
        $this->descriptionlong = "Módulo para gestionar pedidos de Dolibarr desde la Zona de Empleado de forma responsive. Permite visualizar, editar, añadir fotos, firmar y enviar por WhatsApp/Email.";

        // Author
        $this->editor_name = 'ZonaJob Dev';
        $this->editor_url = '';

        // Version
        $this->version = '1.0.0';

        // Key used in llx_const table to save module status
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

        // Name of image file
        $this->picto = 'order';

        // Define some features supported by module
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
                '/zonajob/css/zonajob.css.php',
            ),
            'js' => array(
                '/zonajob/js/zonajob.js.php',
            ),
            'hooks' => array(
                'data' => array(
                    'zonaempleadoindex',
                    'ordercard',
                    'ordersuppliercard',
                ),
                'entity' => '0',
            ),
            'moduleforexternal' => 0,
        );

        // Data directories to create when module is enabled
        $this->dirs = array(
            "/zonajob/temp",
            "/zonajob/signatures",
            "/zonajob/photos"
        );

        // Config pages
        $this->config_page_url = array("setup.php@zonajob");

        // Dependencies
        $this->hidden = false;
        $this->depends = array('modCommande', 'modZonaEmpleado');
        $this->requiredby = array();
        $this->conflictwith = array();

        // The language file dedicated to your module
        $this->langfiles = array("zonajob@zonajob");

        // Prerequisites
        $this->phpmin = array(7, 4);
        $this->need_dolibarr_version = array(14, 0);

        // Messages at activation
        $this->warnings_activation = array();
        $this->warnings_activation_ext = array();

        // Constants
        $this->const = array(
            1 => array('ZONAJOB_SHOW_DRAFT_ORDERS', 'chaine', '1', 'Mostrar pedidos borrador', 0, 'current', 1),
            2 => array('ZONAJOB_SHOW_VALIDATED_ORDERS', 'chaine', '1', 'Mostrar pedidos validados', 0, 'current', 1),
            3 => array('ZONAJOB_SHOW_ALL_ORDERS', 'chaine', '0', 'Mostrar todos los pedidos', 0, 'current', 1),
            4 => array('ZONAJOB_SIGNATURE_REQUIRED', 'chaine', '1', 'Firma del cliente requerida', 0, 'current', 1),
            5 => array('ZONAJOB_CHANGE_STATUS_AFTER_SIGN', 'chaine', '1', 'Solicitar cambio de estado después de firmar', 0, 'current', 1),
            6 => array('ZONAJOB_SEND_WHATSAPP', 'chaine', '1', 'Permitir envío por WhatsApp', 0, 'current', 1),
            7 => array('ZONAJOB_SEND_EMAIL', 'chaine', '1', 'Permitir envío por Email', 0, 'current', 1),
            8 => array('ZONAJOB_AUTO_CREATE_CONTACT', 'chaine', '1', 'Permitir crear contactos desde zona pedidos', 0, 'current', 1),
        );

        if (!isset($conf->zonajob) || !isset($conf->zonajob->enabled)) {
            $conf->zonajob = new stdClass();
            $conf->zonajob->enabled = 0;
        }

        // Array to add new pages in new tabs
        $this->tabs = array();

        // Dictionaries
        $this->dictionaries = array();

        // Boxes/Widgets
        $this->boxes = array();

        // Cronjobs
        $this->cronjobs = array();

        // Permissions
        $this->rights = array();
        $r = 0;

        // Permission id 600002001
        $this->rights[$r][0] = 600002001;
        $this->rights[$r][1] = 'Ver pedidos en zona empleado';
        $this->rights[$r][4] = 'order';
        $this->rights[$r][5] = 'read';
        $r++;

        // Permission id 600002002
        $this->rights[$r][0] = 600002002;
        $this->rights[$r][1] = 'Crear/Modificar pedidos en zona empleado';
        $this->rights[$r][4] = 'order';
        $this->rights[$r][5] = 'write';
        $r++;

        // Permission id 600002003
        $this->rights[$r][0] = 600002003;
        $this->rights[$r][1] = 'Solicitar firma de cliente';
        $this->rights[$r][4] = 'signature';
        $this->rights[$r][5] = 'request';
        $r++;

        // Permission id 600002004
        $this->rights[$r][0] = 600002004;
        $this->rights[$r][1] = 'Enviar por WhatsApp/Email';
        $this->rights[$r][4] = 'send';
        $this->rights[$r][5] = 'execute';
        $r++;

        // Permission id 600002005
        $this->rights[$r][0] = 600002005;
        $this->rights[$r][1] = 'Añadir fotos a pedidos';
        $this->rights[$r][4] = 'photo';
        $this->rights[$r][5] = 'upload';
        $r++;

        // Permission id 600002006
        $this->rights[$r][0] = 600002006;
        $this->rights[$r][1] = 'Crear contactos desde zona pedidos';
        $this->rights[$r][4] = 'contact';
        $this->rights[$r][5] = 'create';
        $r++;

        // Main menu entries
        $this->menu = array();
        $r = 0;

        // Menu entry for admin setup
        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=home,fk_leftmenu=admintools',
            'type' => 'left',
            'titre' => 'ZonaJob Setup',
            'prefix' => img_picto('', 'order', 'class="paddingright pictofixedwidth"'),
            'mainmenu' => 'home',
            'leftmenu' => 'zonajob_setup',
            'url' => '/zonajob/admin/setup.php',
            'langs' => 'zonajob@zonajob',
            'position' => 100,
            'enabled' => '$conf->zonajob->enabled',
            'perms' => '$user->admin',
            'target' => '',
            'user' => 2,
        );
    }

    /**
     * Function called when module is enabled.
     * The init function add constants, boxes, permissions and menus
     *
     * @param string $options Options when enabling module ('', 'noboxes')
     * @return int 1 if OK, 0 if KO
     */
    public function init($options = '')
    {
        global $conf, $langs;

        $result = $this->_load_tables('/zonajob/sql/');
        if ($result < 0) {
            return -1;
        }

        // Create directories
        $this->_createDirectories();

        return $this->_init(array(), $options);
    }

    /**
     * Create required directories
     *
     * @return void
     */
    private function _createDirectories()
    {
        global $conf;

        $upload = $conf->zonajob->dir_output;
        if (!is_dir($upload)) {
            dol_mkdir($upload);
        }

        $subdirs = array('signatures', 'photos', 'temp');
        foreach ($subdirs as $sub) {
            $dir = $upload . '/' . $sub;
            if (!is_dir($dir)) {
                dol_mkdir($dir);
            }
        }
    }

    /**
     * Function called when module is disabled.
     *
     * @param string $options Options when disabling module ('', 'noboxes')
     * @return int 1 if OK, 0 if KO
     */
    public function remove($options = '')
    {
        return $this->_remove(array(), $options);
    }
}
