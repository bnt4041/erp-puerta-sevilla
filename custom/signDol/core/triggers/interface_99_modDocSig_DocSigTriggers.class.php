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
 * \file    htdocs/custom/signDol/core/triggers/interface_99_modDocSig_DocSigTriggers.class.php
 * \ingroup docsig
 * \brief   Triggers del módulo DocSig
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolobarrtriggers.class.php';

/**
 * Class InterfaceDocSigTriggers
 * Triggers para eventos de firma
 */
class InterfaceDocSigTriggers extends DolibarrTriggers
{
    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = "demo";
        $this->description = "DocSig triggers for signature events";
        $this->version = '1.0.0';
        $this->picto = 'fa-file-signature';
    }

    /**
     * Function called when a Dolibarr business event is done.
     *
     * @param string $action Event action code
     * @param Object $object Object
     * @param User $user User object
     * @param Translate $langs Langs object
     * @param conf $conf Conf object
     * @return int 0 if OK, -1 if KO
     */
    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        if (!isModEnabled('docsig')) {
            return 0;
        }

        // Log trigger execution
        dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".(isset($object->id) ? $object->id : ''));

        switch ($action) {
            // Cuando se genera un documento PDF
            case 'BILL_BUILDDOC':
            case 'ORDER_BUILDDOC':
            case 'PROPAL_BUILDDOC':
            case 'CONTRACT_BUILDDOC':
            case 'FICHINTER_BUILDDOC':
                // Aquí se podría auto-crear un envelope si está configurado
                // Por ahora, solo registramos el evento
                dol_syslog("DocSig: Document built for ".$action);
                break;

            // Cuando se valida un documento
            case 'BILL_VALIDATE':
            case 'ORDER_VALIDATE':
            case 'PROPAL_VALIDATE':
            case 'CONTRACT_VALIDATE':
                // Opcionalmente, enviar solicitud de firma automática
                if (getDolGlobalInt('DOCSIG_AUTO_REQUEST_ON_VALIDATE')) {
                    // TODO: Implementar auto-request
                    dol_syslog("DocSig: Auto request on validate for ".$action);
                }
                break;

            // Eventos propios del módulo DocSig
            case 'DOCSIG_ENVELOPE_CREATE':
                dol_syslog("DocSig: Envelope created - ID: ".$object->id);
                break;

            case 'DOCSIG_ENVELOPE_SENT':
                dol_syslog("DocSig: Envelope sent - ID: ".$object->id);
                break;

            case 'DOCSIG_SIGNATURE_COMPLETED':
                dol_syslog("DocSig: Signature completed - Envelope ID: ".$object->id);
                break;

            case 'DOCSIG_ENVELOPE_CANCELED':
                dol_syslog("DocSig: Envelope canceled - ID: ".$object->id);
                break;
        }

        return 0;
    }
}
