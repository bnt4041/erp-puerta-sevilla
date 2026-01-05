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
 * \file    htdocs/custom/signDol/class/actions_docsig.class.php
 * \ingroup docsig
 * \brief   Clase de acciones/hooks del módulo DocSig
 */

/**
 * Class ActionsDocSig
 * Hooks para insertar funcionalidad de firma en Dolibarr
 */
class ActionsDocSig
{
    /**
     * @var DoliDB Database handler
     */
    public $db;

    /**
     * @var string Error message
     */
    public $error = '';

    /**
     * @var array Errors array
     */
    public $errors = array();

    /**
     * @var array Results from hook
     */
    public $results = array();

    /**
     * @var string Resprints content
     */
    public $resprints = '';

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Hook para añadir iconos de firma en listados de documentos
     *
     * @param array $parameters Parameters
     * @param object $object Object
     * @param string $action Action
     * @param HookManager $hookmanager Hook manager
     * @return int 0 if success, negative if error
     */
    public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs;

        if (!isModEnabled('docsig')) {
            return 0;
        }

        $contexts = array('invoicecard', 'ordercard', 'propalcard', 'contractcard');
        
        if (!in_array($parameters['currentcontext'], $contexts)) {
            return 0;
        }

        // Verificar permisos
        if (!$user->hasRight('docsig', 'envelope', 'read')) {
            return 0;
        }

        $langs->load('docsig@signDol');

        // Determinar el tipo de elemento
        $element = '';
        switch ($parameters['currentcontext']) {
            case 'invoicecard':
                $element = 'facture';
                break;
            case 'ordercard':
                $element = 'commande';
                break;
            case 'propalcard':
                $element = 'propal';
                break;
            case 'contractcard':
                $element = 'contrat';
                break;
        }

        if (empty($element) || empty($object->id)) {
            return 0;
        }

        // Verificar si hay un envelope existente
        $envelopeExists = $this->checkEnvelopeExists($element, $object->id);

        // Añadir botón de firma
        $this->resprints .= '<script>
        jQuery(document).ready(function() {
            // Insertar botón de firma en la barra de acciones
            var signButton = \'<a class="butAction docsig-action" href="javascript:DocSig.openModal(\\\''.dol_escape_js($element).'\\\', \\\''.dol_escape_js($object->id).'\\\', \\\'\\\');"><span class="fa fa-file-signature"></span> '.$langs->trans('RequestSignature').'</a>\';
            jQuery(".tabsAction").append(signButton);
        });
        </script>';

        return 0;
    }

    /**
     * Hook para añadir icono de firma en los listados
     *
     * @param array $parameters Parameters
     * @param object $object Object
     * @param string $action Action
     * @param HookManager $hookmanager Hook manager
     * @return int 0 if success, negative if error
     */
    public function printFieldListValue($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs;

        if (!isModEnabled('docsig')) {
            return 0;
        }

        // Este hook se puede usar para añadir iconos en listados
        // La implementación depende del contexto específico

        return 0;
    }

    /**
     * Hook to add signature icon inside FormFile documents list action column.
     * This is the most reliable way to inject per-file icons because FormFile
     * will replace __FILENAMEURLENCODED__ with the current line filename.
     *
     * @param array $parameters Parameters (contains references)
     * @param object $object Business object (invoice/order/contract...)
     * @param string $action Action
     * @param HookManager $hookmanager Hook manager
     * @return int 0 if success
     */
    public function showDocuments(&$parameters, &$object, &$action, $hookmanager)
    {
        // Icon injection is handled reliably per-line via formBuilddocLineOptions().
        return 0;
    }

    /**
     * Hook para añadir pestaña en ficha de contacto
     *
     * @param array $parameters Parameters
     * @param object $object Object
     * @param string $action Action
     * @param HookManager $hookmanager Hook manager
     * @return int 0 if success, negative if error
     */
    public function addMoreTabs($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs;

        if (!isModEnabled('docsig')) {
            return 0;
        }

        if ($parameters['currentcontext'] === 'contactcard') {
            $langs->load('docsig@signDol');
            
            // La pestaña se añade desde el descriptor del módulo
            // Este hook es para acciones adicionales si es necesario
        }

        return 0;
    }

    /**
     * Verifica si existe un envelope para un objeto
     *
     * @param string $element Tipo de elemento
     * @param int $objectId ID del objeto
     * @return array|false Datos del envelope o false
     */
    private function checkEnvelopeExists($element, $objectId)
    {
        global $conf;

        $sql = "SELECT rowid, ref, status FROM ".MAIN_DB_PREFIX."docsig_envelope";
        $sql .= " WHERE element = '".$this->db->escape($element)."'";
        $sql .= " AND fk_object = ".(int)$objectId;
        $sql .= " AND entity = ".(int)$conf->entity;
        $sql .= " AND status NOT IN (4, 5)"; // No cancelados ni expirados
        $sql .= " ORDER BY rowid DESC LIMIT 1";

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            if ($obj) {
                return array(
                    'id' => $obj->rowid,
                    'ref' => $obj->ref,
                    'status' => $obj->status
                );
            }
        }

        return false;
    }

    /**
     * Hook para el formulario de archivos (formfile)
     * Añade iconos de firma junto a los documentos PDF
     *
     * @param array $parameters Parameters
     * @param object $object Object
     * @param string $action Action
     * @param HookManager $hookmanager Hook manager
     * @return int 0 if success, negative if error
     */
    public function formBuilddocLineOptions($parameters, &$object, &$action, $hookmanager)
    {
        global $user, $langs;

        $this->resprints = '';

        if (!isModEnabled('docsig')) {
            return 0;
        }

        if (!$user->hasRight('docsig', 'envelope', 'read')) {
            $this->resprints = '<td></td>';
            return 0;
        }

        $modulepart = $parameters['modulepart'] ?? '';
        $relativepath = $parameters['relativepath'] ?? '';

        // Always return a cell to keep table columns aligned
        if (empty($modulepart) || empty($relativepath)) {
            $this->resprints = '<td></td>';
            return 0;
        }

        // Only show for PDFs
        if (!preg_match('/\.pdf$/i', $relativepath)) {
            $this->resprints = '<td></td>';
            return 0;
        }

        // Map Dolibarr modulepart to our element types
        $elementMap = array(
            'facture' => 'facture',
            'invoice' => 'facture',
            'commande' => 'commande',
            'order' => 'commande',
            'propal' => 'propal',
            'proposal' => 'propal',
            'contract' => 'contrat',
            'contrat' => 'contrat',
            'fichinter' => 'fichinter',
        );

        if (!isset($elementMap[$modulepart])) {
            $this->resprints = '<td></td>';
            return 0;
        }

        // Object id is usually available as $GLOBALS['id'] on card pages
        $objectId = 0;
        if (!empty($parameters['id'])) {
            $objectId = (int) $parameters['id'];
        } elseif (!empty($GLOBALS['id'])) {
            $objectId = (int) $GLOBALS['id'];
        } elseif (!empty($GLOBALS['object']) && is_object($GLOBALS['object']) && !empty($GLOBALS['object']->id)) {
            $objectId = (int) $GLOBALS['object']->id;
        }

        if (empty($objectId)) {
            $this->resprints = '<td></td>';
            return 0;
        }

        $langs->load('docsig@signDol');

        $mappedElement = $elementMap[$modulepart];
        $icon = '<span class="docsig-icon" title="'.dol_escape_htmltag($langs->trans('RequestSignature')).'"'
            .' data-element="'.dol_escape_htmltag($mappedElement).'"'
            .' data-object-id="'.dol_escape_htmltag($objectId).'"'
            .' data-file-path="'.dol_escape_htmltag($relativepath).'">'
            .'<span class="fa fa-file-signature"></span>'
            .'</span>';

        $this->resprints = '<td class="right nowraponall">'.$icon.'</td>';
        return 0;
    }

    /**
     * Obtiene información del envelope para un archivo específico
     *
     * @param string $element Tipo de elemento
     * @param int $objectId ID del objeto
     * @param string $filePath Ruta del archivo
     * @return array|false Datos del envelope o false
     */
    private function getEnvelopeForFile($element, $objectId, $filePath)
    {
        global $conf;

        $sql = "SELECT rowid, ref, status FROM ".MAIN_DB_PREFIX."docsig_envelope";
        $sql .= " WHERE element = '".$this->db->escape($element)."'";
        $sql .= " AND fk_object = ".(int)$objectId;
        $sql .= " AND file_path LIKE '%".$this->db->escape(basename($filePath))."'";
        $sql .= " AND entity = ".(int)$conf->entity;
        $sql .= " ORDER BY rowid DESC LIMIT 1";

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            if ($obj) {
                return array(
                    'id' => $obj->rowid,
                    'ref' => $obj->ref,
                    'status' => $obj->status
                );
            }
        }

        return false;
    }
}
