<?php
/* Copyright (C) 2024 PuertaSevilla
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    core/triggers/interface_99_modPuertaSevilla_PuertaSevillaTriggers.class.php
 * \ingroup puertasevilla
 * \brief   Trigger file for PuertaSevilla module
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class of triggers for PuertaSevilla module
 */
class InterfacePuertaSevillaTriggers extends DolibarrTriggers
{
    /**
     * @var DoliDB Database handler
     */
    protected $db;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = "puertasevilla";
        $this->description = "PuertaSevilla triggers";
        $this->version = '1.0.0';
        $this->picto = 'puertasevilla@puertasevilla';
    }

    /**
     * Trigger name
     *
     * @return string Name of trigger file
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Trigger description
     *
     * @return string Description of trigger file
     */
    public function getDesc()
    {
        return $this->description;
    }

    /**
     * Function called when a Dolibarr business event is done.
     * All functions "runTrigger" are triggered if file
     * is inside directory core/triggers
     *
     * @param string        $action Event action code
     * @param CommonObject  $object Object
     * @param User          $user   Object user
     * @param Translate     $langs  Object langs
     * @param Conf          $conf   Object conf
     * @return int                  <0 if KO, 0 if no triggered ran, >0 if OK
     */
    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        if (!isModEnabled('puertasevilla')) {
            return 0;
        }

        // Put here code you want to execute when a Dolibarr business events occurs.
        // Data and type of action are stored into $object and $action

        dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);

        switch ($action) {
            // LINECONTRACT_ACTIVATE: cuando se activa una línea de contrato
            case 'LINECONTRACT_ACTIVATE':
                return $this->generateInvoiceTemplateFromContractLine($object, $user, $langs, $conf);

            // LINECONTRACT_CREATE: cuando se crea y activa directamente
            case 'LINECONTRACT_CREATE':
                // Solo generar si la línea está activa
                if ($object->statut == 4) { // ContratLigne::STATUS_OPEN
                    return $this->generateInvoiceTemplateFromContractLine($object, $user, $langs, $conf);
                }
                break;

            // LINECONTRACT_MODIFY: cuando se modifica una línea de contrato ya existente
            case 'LINECONTRACT_MODIFY':
                // Si la línea está activa, recalcular/actualizar la plantilla recurrente (nb_gen_max, fecha, etc.)
                if ($object->statut == 4) { // ContratLigne::STATUS_OPEN
                    return $this->generateInvoiceTemplateFromContractLine($object, $user, $langs, $conf);
                }
                break;

            // LINECONTRACT_CLOSE: cuando se desactiva/cierra una línea de contrato
            case 'LINECONTRACT_CLOSE':
                return $this->suspendInvoiceTemplateFromContractLine($object, $user, $langs, $conf);

            default:
                dol_syslog("Trigger '".$this->name."' for action '$action': no action defined");
                break;
        }

        return 0;
    }

    /**
     * Sincroniza importes/cantidad/IVA de la(s) línea(s) de una factura recurrente con una línea de contrato.
     *
     * Nota: el trigger ya actualiza la cabecera (date_when, nb_gen_max, etc.) pero si la recurrente ya existía,
     * Dolibarr no actualiza automáticamente las líneas de llx_facturedet_rec.
     *
     * @param int $invoiceRecId
     * @param mixed $contractLine
     * @param Societe $thirdparty
     * @param User $user
     * @return void
     */
    private function syncInvoiceTemplateLinesFromContractLine($invoiceRecId, $contractLine, $thirdparty, User $user)
    {
        global $mysoc;

        $invoiceRecId = (int) $invoiceRecId;
        if ($invoiceRecId <= 0 || empty($contractLine) || empty($contractLine->id)) {
            return;
        }

        require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture-rec.class.php';
        include_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';

        $invoiceTemplate = new FactureRec($this->db);
        if ($invoiceTemplate->fetch($invoiceRecId) <= 0) {
            return;
        }

        $contractLineId = (int) $contractLine->id;
        $targetLines = array();

        if (!empty($invoiceTemplate->lines) && is_array($invoiceTemplate->lines)) {
            foreach ($invoiceTemplate->lines as $line) {
                if ((int) $line->fk_contract_line === $contractLineId) {
                    $targetLines[] = $line;
                }
            }
        }

        // Fallback: si la recurrente solo tiene 1 línea, asumimos que corresponde a este contrato
        if (empty($targetLines) && !empty($invoiceTemplate->lines) && count($invoiceTemplate->lines) === 1) {
            $targetLines[] = $invoiceTemplate->lines[0];
        }

        // Fallback: match por producto si hay exactamente 1 coincidencia
        if (empty($targetLines) && !empty($contractLine->fk_product) && !empty($invoiceTemplate->lines)) {
            $matches = array();
            foreach ($invoiceTemplate->lines as $line) {
                if ((int) $line->fk_product === (int) $contractLine->fk_product) {
                    $matches[] = $line;
                }
            }
            if (count($matches) === 1) {
                $targetLines[] = $matches[0];
            }
        }

        if (empty($targetLines)) {
            dol_syslog(get_class($this).'::syncInvoiceTemplateLinesFromContractLine No se pudo identificar línea de facture_rec '.$invoiceRecId.' para contratdet '.$contractLineId, LOG_WARNING);
            return;
        }

        foreach ($targetLines as $line) {
            // Enlazar para futuras sincronizaciones
            $line->fk_contract_line = $contractLineId;

            // Propagar importe/cantidad/IVA
            if (!empty($contractLine->qty)) {
                $line->qty = $contractLine->qty;
            }
            if (isset($contractLine->tva_tx)) {
                $line->tva_tx = $contractLine->tva_tx;
            }
            if (isset($contractLine->subprice)) {
                $line->subprice = price2num($contractLine->subprice);
                $line->price = $line->subprice;
            }

            // Recalcular totales
            $localtaxes_type = getLocalTaxesFromRate($line->tva_tx, 0, $thirdparty, $mysoc);
            $tabprice = calcul_price_total(
                price2num($line->qty),
                price2num($line->subprice),
                price2num($line->remise_percent),
                $line->tva_tx,
                price2num($line->localtax1_tx),
                price2num($line->localtax2_tx),
                0,
                'HT',
                (int) $line->info_bits,
                (int) $line->product_type,
                $mysoc,
                $localtaxes_type,
                100,
                (!empty($invoiceTemplate->multicurrency_tx) ? $invoiceTemplate->multicurrency_tx : 1),
                0
            );

            $line->total_ht = $tabprice[0];
            $line->total_tva = $tabprice[1];
            $line->total_ttc = $tabprice[2];
            $line->total_localtax1 = $tabprice[9];
            $line->total_localtax2 = $tabprice[10];

            $upd = $line->update($user, 1);
            if ($upd <= 0) {
                dol_syslog(get_class($this).'::syncInvoiceTemplateLinesFromContractLine Error al actualizar línea '.$line->id.' de facture_rec '.$invoiceRecId.': '.$line->error, LOG_WARNING);
            }
        }

        // Recalcular cabecera por consistencia
        if (method_exists($invoiceTemplate, 'update_price')) {
            $invoiceTemplate->update_price(1);
        }
    }

    /**
     * Normaliza un valor de fecha (timestamp o string) a timestamp.
     *
     * @param mixed $value
     * @return int
     */
    public function normalizeDateToTimestamp($value)
    {
        if (empty($value) || $value === -1) {
            return 0;
        }
        if (is_numeric($value)) {
            $iv = (int) $value;
            return ($iv > 0 ? $iv : 0);
        }
        $ts = dol_stringtotime((string) $value, 1);
        return ($ts > 0 ? $ts : 0);
    }

    /**
     * Calcula meses inclusivos entre dos timestamps (por mes/año).
     * Si no hay fecha fin, devuelve 0 (ilimitado).
     *
     * @param int $startTs
     * @param int $endTs
     * @return int
     */
    public function monthsBetweenInclusive($startTs, $endTs)
    {
        if (empty($startTs) || $startTs <= 0) {
            return 0;
        }
        if (empty($endTs) || $endTs <= 0) {
            return 0; // ilimitado si no hay fin
        }
        if ($endTs < $startTs) {
            return 1;
        }

        $y1 = (int) dol_print_date($startTs, '%Y');
        $m1 = (int) dol_print_date($startTs, '%m');
        $y2 = (int) dol_print_date($endTs, '%Y');
        $m2 = (int) dol_print_date($endTs, '%m');

        $diff = ($y2 * 12 + $m2) - ($y1 * 12 + $m1) + 1;
        if ($diff < 1) {
            $diff = 1;
        }
        return $diff;
    }

    /**
     * Extrae el ID de FactureRec desde el comentario de la línea.
     *
     * @param string $comment
     * @return int
     */
    public function extractInvoiceRecIdFromComment($comment)
    {
        if (empty($comment)) {
            return 0;
        }
        if (preg_match('/PSV_FACTUREREC_ID\s*=\s*(\d+)/', $comment, $m)) {
            return (int) $m[1];
        }
        // Compatibilidad con texto anterior
        if (preg_match('/Factura\s+plantilla\s+generada\s*:\s*(\d+)/i', $comment, $m2)) {
            return (int) $m2[1];
        }
        return 0;
    }

    /**
     * Busca una factura recurrente asociada a la línea del contrato en la nota privada
     *
     * @param int $lineId
     * @return int
     */
    public function findInvoiceRecIdFromLine(int $lineId)
    {
        global $db;

        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."facture_rec";
        $sql .= " WHERE note_private LIKE '%Línea de contrato: " . ((int) $lineId) . "%'";
        $sql .= " ORDER BY rowid DESC";
        $sql .= " LIMIT 1";
        $resql = $db->query($sql);
        if ($resql) {
            if ($obj = $db->fetch_object($resql)) {
                return (int) $obj->rowid;
            }
        }
        return 0;
    }

    /**
     * Busca una factura recurrente vinculada a una línea de contrato usando la tabla element_element
     *
     * @param int $lineId ID de la línea de contrato (contratdet.rowid)
     * @return int ID de la factura recurrente, o 0 si no existe
     */
    public function findInvoiceRecIdFromElementElement(int $lineId)
    {
        global $db;

        // Buscar en la tabla element_element donde fk_source_type = 'contratdet' 
        // y fk_target_type = 'facturerec'
        $sql = "SELECT fk_target_id FROM ".MAIN_DB_PREFIX."element_element";
        $sql .= " WHERE fk_source_type = 'contratdet'";
        $sql .= " AND fk_source_id = ".((int) $lineId);
        $sql .= " AND fk_target_type = 'facturerec'";
        $sql .= " LIMIT 1";
        $resql = $db->query($sql);
        if ($resql) {
            if ($obj = $db->fetch_object($resql)) {
                return (int) $obj->fk_target_id;
            }
        }
        return 0;
    }

    /**
     * Enlaza una línea de contrato con una factura recurrente usando element_element
     * Sin modificar ni los datos de la línea ni de la factura recurrente
     *
     * @param int $lineId ID de la línea de contrato
     * @param int $invoiceRecId ID de la factura recurrente
     * @param User $user Usuario
     * @return int 1 si se enlazó (o ya existía), -1 si hay error
     */
    public function linkContractLineToInvoiceRec(int $lineId, int $invoiceRecId, User $user)
    {
        global $db;

        if (empty($lineId) || empty($invoiceRecId)) {
            return -1;
        }

        // Verificar si ya existe el enlace
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."element_element";
        $sql .= " WHERE fk_source_type = 'contratdet'";
        $sql .= " AND fk_source_id = ".((int) $lineId);
        $sql .= " AND fk_target_type = 'facturerec'";
        $sql .= " AND fk_target_id = ".((int) $invoiceRecId);
        $resql = $db->query($sql);
        if ($resql && $db->num_rows($resql) > 0) {
            // Ya existe el enlace
            return 1;
        }

        // Crear el enlace bidireccional en element_element
        $sql_insert = "INSERT INTO ".MAIN_DB_PREFIX."element_element ";
        $sql_insert .= "(fk_source_type, fk_source_id, fk_target_type, fk_target_id, date_creation, fk_user_author) ";
        $sql_insert .= "VALUES ('contratdet', ".((int) $lineId).", 'facturerec', ".((int) $invoiceRecId).", '".date('Y-m-d H:i:s')."', ".((int) $user->id).")";

        if ($db->query($sql_insert)) {
            dol_syslog(get_class($this)."::linkContractLineToInvoiceRec Enlace creado en element_element: contratdet-".$lineId." -> facturerec-".$invoiceRecId, LOG_INFO);
            return 1;
        } else {
            dol_syslog(get_class($this)."::linkContractLineToInvoiceRec Error al crear enlace: ".$db->lasterror(), LOG_ERR);
            return -1;
        }
    }

    /**
     * Genera una factura plantilla (recurrente) basada en una línea de contrato activada
     *
     * @param  ContratLigne $contractLine Línea de contrato activada
     * @param  User         $user         Usuario que realiza la acción
     * @param  Translate    $langs        Objeto de traducción
     * @param  Conf         $conf         Objeto de configuración
     * @return int                        <0 if KO, >0 if OK
     */
    public function generateInvoiceTemplateFromContractLine($contractLine, $user, $langs, $conf)
    {
        global $db;

        dol_syslog(get_class($this)."::generateInvoiceTemplateFromContractLine contractline id=".$contractLine->id);
        
        // DEBUG: Log detallado de los valores de fecha al inicio del trigger
        // NOTA: Los campos date_ouverture*, date_cloture, date_fin_validite NO se cargan en ContratLigne
        // Usar en su lugar: date_start, date_start_real, date_end, date_end_real
        dol_syslog(
            get_class($this)."::generateInvoiceTemplateFromContractLine DEBUG ENTRADA - ".
            "date_start=".var_export($contractLine->date_start, true).
            " | date_start_real=".var_export($contractLine->date_start_real, true).
            " | date_end=".var_export($contractLine->date_end, true).
            " | date_end_real=".var_export($contractLine->date_end_real, true).
            " | statut=".$contractLine->statut,
            LOG_DEBUG
        );

        // IMPORTANTE: En caso de LINECONTRACT_MODIFY, NO recargamos la línea desde BD
        // porque eso impediría que Dolibarr guarde los cambios en la línea después del trigger.
        // Solo recargamos si es LINECONTRACT_ACTIVATE o LINECONTRACT_CREATE
        // Para ello, usamos el objeto que ya tenemos
        $contractLineToUse = $contractLine;

        // Cargar el contrato completo
        require_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
        $contract = new Contrat($db);
        $result = $contract->fetch($contractLine->fk_contrat);
        if ($result <= 0) {
            $this->error = "Error al cargar el contrato: ".$contract->error;
            dol_syslog(get_class($this)."::generateInvoiceTemplateFromContractLine ".$this->error, LOG_ERR);
            // Retornar 1 para permitir que Dolibarr guarde los cambios de la línea
            return 1;
        }

        // Verificar que el contrato tiene un tercero asociado
        if (!$contract->socid || $contract->socid <= 0) {
            $this->error = "El contrato no tiene un tercero asociado";
            dol_syslog(get_class($this)."::generateInvoiceTemplateFromContractLine ".$this->error, LOG_WARNING);
            // Retornar 1 para permitir que Dolibarr guarde los cambios de la línea
            return 1;
        }

        // Resolver condiciones de pago (algunas instalaciones tienen facture_rec.fk_cond_reglement NOT NULL)
        require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
        $thirdparty = new Societe($db);
        $thirdpartyFetchRes = $thirdparty->fetch((int) $contract->socid);
        if ($thirdpartyFetchRes <= 0) {
            $this->error = "Error al cargar el tercero del contrato: ".$thirdparty->error;
            dol_syslog(get_class($this)."::generateInvoiceTemplateFromContractLine ".$this->error, LOG_ERR);
            // Retornar 1 para permitir que Dolibarr guarde los cambios de la línea
            return 1;
        }

        $condReglementId = (int) (!empty($contract->cond_reglement_id) ? $contract->cond_reglement_id : 0);
        if ($condReglementId <= 0) {
            $condReglementId = (int) (!empty($thirdparty->cond_reglement_id) ? $thirdparty->cond_reglement_id : 0);
        }
        if ($condReglementId <= 0) {
            $condReglementId = (int) getDolGlobalInt('MAIN_ID_COND_REGLEMENT');
        }
        if ($condReglementId <= 0) {
            $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."c_payment_term";
            $sql .= " WHERE active = 1";
            $sql .= " AND entity IN (0, ".((int) $conf->entity).")";
            $sql .= " ORDER BY sortorder ASC, rowid ASC";
            $sql .= " LIMIT 1";
            $resql = $db->query($sql);
            if ($resql && ($obj = $db->fetch_object($resql))) {
                $condReglementId = (int) $obj->rowid;
            }
        }

        $modeReglementId = (int) (!empty($contract->mode_reglement_id) ? $contract->mode_reglement_id : 0);
        if ($modeReglementId <= 0) {
            $modeReglementId = (int) (!empty($thirdparty->mode_reglement_id) ? $thirdparty->mode_reglement_id : 0);
        }
        if ($modeReglementId <= 0) {
            $modeReglementId = (int) getDolGlobalInt('MAIN_ID_MODE_REGLEMENT');
        }

        // Cargar extrafields del contrato para obtener el día de pago
        $contract->fetch_optionals();
        $diaPago = !empty($contract->array_options['options_psv_dia_pago']) ? (int)$contract->array_options['options_psv_dia_pago'] : 1;

        // Validar día de pago
        if ($diaPago < 1 || $diaPago > 31) {
            $diaPago = 1;
        }

        // Calcular nb_gen_max según meses entre fecha inicio/fin de la línea
        // IMPORTANTE: Los campos en ContratLigne son:
        // - date_start: fecha inicio planeada (de date_ouverture_prevue en BD)
        // - date_start_real: fecha inicio real (de date_ouverture en BD)
        // - date_end: fecha fin planeada (de date_fin_validite en BD) <- ESTE ES EL QUE EL USUARIO MODIFICA
        // - date_end_real: fecha fin real (de date_cloture en BD)
        
        // Buscar la fecha de inicio: prioridad a date_start_real (actual), luego date_start (planeada)
        $startLineTs = 0;
        if (!empty($contractLine->date_start_real)) {
            $startLineTs = $this->normalizeDateToTimestamp($contractLine->date_start_real);
            dol_syslog(get_class($this)."::generateInvoiceTemplateFromContractLine DEBUG fecha inicio de date_start_real: ".dol_print_date($contractLine->date_start_real), LOG_DEBUG);
        }
        if (empty($startLineTs) && !empty($contractLine->date_start)) {
            $startLineTs = $this->normalizeDateToTimestamp($contractLine->date_start);
            dol_syslog(get_class($this)."::generateInvoiceTemplateFromContractLine DEBUG fecha inicio de date_start: ".dol_print_date($contractLine->date_start), LOG_DEBUG);
        }
        if (empty($startLineTs)) {
            $startLineTs = dol_now();
            dol_syslog(get_class($this)."::generateInvoiceTemplateFromContractLine DEBUG fecha inicio por defecto (ahora): ".dol_print_date($startLineTs), LOG_DEBUG);
        }

        // Buscar la fecha de fin: si hay date_end_real (cierre real), usarla; si no, usar date_end (fin planeado)
        // date_end es el que el usuario modifica como "fecha fin del servicio"
        $endLineTs = 0;
        if (!empty($contractLine->date_end_real)) {
            $endLineTs = $this->normalizeDateToTimestamp($contractLine->date_end_real);
            dol_syslog(get_class($this)."::generateInvoiceTemplateFromContractLine DEBUG fecha fin de date_end_real: ".dol_print_date($contractLine->date_end_real), LOG_DEBUG);
        }
        if (empty($endLineTs) && !empty($contractLine->date_end)) {
            $endLineTs = $this->normalizeDateToTimestamp($contractLine->date_end);
            dol_syslog(get_class($this)."::generateInvoiceTemplateFromContractLine DEBUG fecha fin de date_end: ".dol_print_date($contractLine->date_end), LOG_DEBUG);
        }
        $nbGenMax = $this->monthsBetweenInclusive($startLineTs, $endLineTs);

        // Log de diagnóstico para ver el número máximo calculado
        dol_syslog(
            get_class($this)."::generateInvoiceTemplateFromContractLine nb_gen_max calculado=".(int) $nbGenMax.
            " (start=".dol_print_date($startLineTs).", end=".dol_print_date($endLineTs).") para línea ".$contractLine->id,
            LOG_DEBUG
        );

        // Calcular date_when de la recurrente alineada al día de pago, tomando como base la fecha de inicio de la línea
        $baseDate = $startLineTs;
        $y = (int) dol_print_date($baseDate, '%Y');
        $m = (int) dol_print_date($baseDate, '%m');
        $d = (int) dol_print_date($baseDate, '%d');
        $lastDay = (int) cal_days_in_month(CAL_GREGORIAN, $m, $y);
        $dayToUse = $diaPago;
        if ($dayToUse > $lastDay) {
            $dayToUse = $lastDay;
        }
        if ($d > $dayToUse) {
            $m++;
            if ($m > 12) {
                $m = 1;
                $y++;
            }
            $lastDayNext = (int) cal_days_in_month(CAL_GREGORIAN, $m, $y);
            if ($dayToUse > $lastDayNext) {
                $dayToUse = $lastDayNext;
            }
        }
        $dateWhenTs = dol_mktime(0, 0, 0, $m, $dayToUse, $y);

        // Crear la factura plantilla (recurrente)
        // Nota: En Dolibarr 20.x, FactureRec::create() requiere un $facid (factura origen).
        // Creamos una factura temporal mínima (borrador) como origen.
        require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
        require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture-rec.class.php';

        // Título que usaremos para la plantilla (lo reutilizaremos también para buscar existentes)
        $templateTitle = "Factura recurrente - Contrato ".$contract->ref;
        if (!empty($contractLine->label)) {
            $templateTitle .= " - ".$contractLine->label;
        }

        // Si ya existe una recurrente vinculada, reactivarla/actualizarla en lugar de crear duplicados
        $existingInvoiceRecId = 0;

        // Prioridad 1: buscar por element_element (la forma segura y estándar de Dolibarr)
        $existingInvoiceRecId = $this->findInvoiceRecIdFromElementElement((int) $contractLine->id);

        // Fallback 2: buscar por nota privada de la plantilla (Línea de contrato: X)
        if ($existingInvoiceRecId <= 0) {
            $existingInvoiceRecId = $this->findInvoiceRecIdFromLine((int) $contractLine->id);
        }

        // Fallback 3: buscar por título de la plantilla (evita el error de clave única en titre)
        if ($existingInvoiceRecId <= 0 && !empty($templateTitle)) {
            $sqlr = "SELECT rowid FROM ".MAIN_DB_PREFIX."facture_rec";
            $sqlr .= " WHERE titre = '".$db->escape($templateTitle)."'";
            $sqlr .= " AND entity IN (0, ".((int) $conf->entity).")";
            $sqlr .= " ORDER BY rowid DESC";
            $sqlr .= " LIMIT 1";
            $resr = $db->query($sqlr);
            if ($resr && ($objr = $db->fetch_object($resr))) {
                $existingInvoiceRecId = (int) $objr->rowid;
            }
        }

        if ($existingInvoiceRecId > 0) {
            // Reactivar y actualizar parámetros básicos de la recurrente existente
            $sqlUpd = "UPDATE ".MAIN_DB_PREFIX."facture_rec";
            $sqlUpd .= " SET suspended = 0";
            // Actualizar siempre el número máximo de generaciones (0 = ilimitado)
            $sqlUpd .= ", nb_gen_max = ".((int) $nbGenMax);
            if (!empty($dateWhenTs)) {
                $sqlUpd .= ", date_when = '".$db->idate($dateWhenTs)."'";
            }
            // Asegurar que las facturas generadas se validan automáticamente
            $sqlUpd .= ", auto_validate = 1";
            // Añadir (si no estaba) referencia a la línea de contrato en la nota privada (solo informativo, no para buscar)
            $sqlUpd .= ", note_private = CONCAT(COALESCE(note_private, ''), '\nLínea de contrato: ".((int) $contractLine->id)."')";
            $sqlUpd .= " WHERE rowid = ".((int) $existingInvoiceRecId);
            $resUpd = $db->query($sqlUpd);
            if ($resUpd) {
                dol_syslog(get_class($this)."::generateInvoiceTemplateFromContractLine Reactivada facture_rec ".$existingInvoiceRecId." para línea ".$contractLine->id, LOG_INFO);

                // Enlazar la línea de contrato con la factura recurrente usando element_element (forma segura)
                $linkResult = $this->linkContractLineToInvoiceRec((int) $contractLine->id, (int) $existingInvoiceRecId, $user);
                if ($linkResult <= 0) {
                    dol_syslog(get_class($this)."::generateInvoiceTemplateFromContractLine Error al enlazar línea con FactureRec en element_element", LOG_WARNING);
                }

                // Asegurar que la plantilla también está enlazada al contrato (para que aparezca en "Objetos relacionados")
                $invoiceTemplate = new FactureRec($db);
                if ($invoiceTemplate->fetch($existingInvoiceRecId) > 0) {
                    $linkResult = $invoiceTemplate->add_object_linked('contrat', $contract->id, $user, 1);
                    if ($linkResult <= 0) {
                        dol_syslog(get_class($this)."::generateInvoiceTemplateFromContractLine Error al enlazar contrato con FactureRec existente: ".$invoiceTemplate->error, LOG_WARNING);
                    }
                    
                    // También enlazar desde el contrato hacia la factura recurrente (bidireccional)
                    $linkResult2 = $contract->add_object_linked('facturerec', $existingInvoiceRecId, $user, 1);
                    if ($linkResult2 <= 0) {
                        dol_syslog(get_class($this)."::generateInvoiceTemplateFromContractLine Error al enlazar FactureRec con contrato (dirección inversa): ".$contract->error, LOG_WARNING);
                    }
                }

                // Sincronizar también el importe de la(s) línea(s) de la plantilla recurrente existente
                $this->syncInvoiceTemplateLinesFromContractLine((int) $existingInvoiceRecId, $contractLine, $thirdparty, $user);

                return 1;
            }
            // Si falla el UPDATE, seguimos creando una nueva plantilla
        }
        
        $tmpInvoice = new Facture($db);
        $tmpInvoice->socid = $contract->socid;
        $tmpInvoice->type = Facture::TYPE_STANDARD;
        $tmpInvoice->date = dol_now();
        $tmpInvoice->note_private = 'Temporary invoice (PuertaSevilla) used to create recurring template.';
        if ($condReglementId > 0) {
            $tmpInvoice->cond_reglement_id = $condReglementId;
        }
        if ($modeReglementId > 0) {
            $tmpInvoice->mode_reglement_id = $modeReglementId;
        }
        if (!empty($contract->fk_project)) {
            $tmpInvoice->fk_project = $contract->fk_project;
        }

        $facid = $tmpInvoice->create($user, 1); // notrigger=1
        if ($facid <= 0) {
            $this->error = "Error al crear la factura temporal origen para la plantilla: ".$tmpInvoice->error;
            $this->errors = $tmpInvoice->errors;
            dol_syslog(get_class($this)."::generateInvoiceTemplateFromContractLine ".$this->error, LOG_ERR);
            // Retornar 1 para permitir que Dolibarr guarde los cambios de la línea
            // La factura recurrente es un efecto secundario; si falla, no debe cancelar el guardado principal
            return 1;
        }

        $invoiceTemplate = new FactureRec($db);
        
        // Configurar la factura plantilla
        $invoiceTemplate->socid = $contract->socid;
        $invoiceTemplate->type = Facture::TYPE_STANDARD;
        
        // Título de la factura plantilla (usar referencia del contrato, no la provisional de la factura temporal)
        // Esto asegura que todas las facturas generadas tengan una referencia clara al contrato origen
        $invoiceTemplate->title = $templateTitle;
        
        // Guardar en nota pública una referencia clara al contrato (referencia, no ID)
        // para que sea visible en las facturas generadas
        $invoiceTemplate->note_public = "Referencia de contrato: ".$contract->ref;
        if (!empty($contractLine->label)) {
            $invoiceTemplate->note_public .= " - ".$contractLine->label;
        }
        
        // Configurar frecuencia mensual
        $invoiceTemplate->frequency = 1; // 1 = mensual
        $invoiceTemplate->unit_frequency = 'm'; // 'm' = mes
        
        // Fecha de inicio (día de generación) alineada con el día de pago
        $invoiceTemplate->date_when = $dateWhenTs;
        
        // Configurar día de generación (día del mes)
        // auto_validate = 1 para que las facturas generadas salgan ya validadas
        $invoiceTemplate->auto_validate = 1;
        $invoiceTemplate->generate_pdf = 1; // Generar PDF automáticamente
        
        // Número máximo de generaciones (tantos meses como desde inicio a fin de la línea; 0 = ilimitado)
        $invoiceTemplate->nb_gen_max = (int) $nbGenMax;
        
        // Nota privada con información del contrato
        $invoiceTemplate->note_private = "Factura generada automáticamente desde contrato ".$contract->ref;
        $invoiceTemplate->note_private .= "\nLínea de contrato: ".$contractLine->id;
        $invoiceTemplate->note_private .= "\nDía de pago: ".$diaPago;
        
        // Condiciones de pago y modo de pago
        // IMPORTANTE: usar siempre un fk_cond_reglement válido si el campo en BBDD es NOT NULL.
        if ($condReglementId > 0) {
            $invoiceTemplate->cond_reglement_id = $condReglementId;
        }
        if ($modeReglementId > 0) {
            $invoiceTemplate->mode_reglement_id = $modeReglementId;
        }
        
        // Proyecto asociado si existe
        if (!empty($contract->fk_project)) {
            $invoiceTemplate->fk_project = $contract->fk_project;
        }
        
        // Crear la factura plantilla
        $result = $invoiceTemplate->create($user, $facid, 1, array()); // notrigger=1
        if ($result < 0) {
            $this->error = "Error al crear la factura plantilla: ".$invoiceTemplate->error;
            $this->errors = $invoiceTemplate->errors;
            dol_syslog(get_class($this)."::generateInvoiceTemplateFromContractLine ".$this->error, LOG_ERR);
            // Intentar limpiar factura temporal
            $tmpInvoice->fetch($facid);
            $tmpInvoice->delete($user, 1);
            // Retornar 1 para permitir que Dolibarr guarde los cambios de la línea
            return 1;
        }

        $invoiceTemplateId = (int) $invoiceTemplate->id;
        $invoiceTemplate->nb_gen_max = (int) $nbGenMax;
        
        // IMPORTANTE: Actualizar siempre nb_gen_max en la BD (0 = ilimitado)
        $sqlUpd = "UPDATE ".MAIN_DB_PREFIX."facture_rec SET nb_gen_max = ".((int) $nbGenMax)." WHERE rowid = ".((int) $invoiceTemplateId);
        $resUpd = $db->query($sqlUpd);
        if (!$resUpd) {
            dol_syslog(get_class($this)."::generateInvoiceTemplateFromContractLine Error al actualizar nb_gen_max: ".$db->lasterror(), LOG_WARNING);
        }
        
        // Enlazar la factura recurrente con el contrato para que aparezca en "Objetos relacionados"
        $linkResult = $invoiceTemplate->add_object_linked('contrat', $contract->id, $user, 1);
        if ($linkResult <= 0) {
            dol_syslog(get_class($this)."::generateInvoiceTemplateFromContractLine Error al enlazar contrato con FactureRec: ".$invoiceTemplate->error, LOG_WARNING);
        }
        
        // También enlazar desde el contrato hacia la factura recurrente (bidireccional)
        $linkResult2 = $contract->add_object_linked('facturerec', $invoiceTemplateId, $user, 1);
        if ($linkResult2 <= 0) {
            dol_syslog(get_class($this)."::generateInvoiceTemplateFromContractLine Error al enlazar FactureRec con contrato (dirección inversa): ".$contract->error, LOG_WARNING);
        }
        
        // Añadir línea a la factura plantilla basada en la línea del contrato
        $desc = $contractLine->label;
        if (!empty($contractLine->description)) {
            $desc .= "\n".$contractLine->description;
        }
        
        // Precio unitario de la línea
        $pu_ht = $contractLine->subprice;
        $qty = !empty($contractLine->qty) ? $contractLine->qty : 1;
        $tva_tx = $contractLine->tva_tx;
        
        // Cargar extrafields de la línea para obtener CCC y entidad
        require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
        $extrafields = new ExtraFields($db);
        $extrafields->fetch_name_optionals_label('contratdet');
        
        $contractLine->fetch_optionals($contractLine->id, $extrafields);
        
        $ccc = !empty($contractLine->array_options['options_psv_ccc']) ? $contractLine->array_options['options_psv_ccc'] : '';
        
        // Añadir información bancaria a la descripción si existe
        if (!empty($ccc)) {
			$cccLabel = $ccc; // psv_ccc contiene directamente el IBAN
			if (!empty($cccLabel)) {
				$desc .= "\nCuenta bancaria: " . $cccLabel;
			}
        }
        
        $result = $invoiceTemplate->addline(
            $desc,
            $pu_ht,
            $qty,
            $tva_tx,
            0,
            0,
            !empty($contractLine->fk_product) ? $contractLine->fk_product : 0,
            0,
            'HT',
            0,
            0,
            0,
            1,
            -1,
            0,
            '',
            !empty($contractLine->fk_unit) ? $contractLine->fk_unit : null,
            0,
            0,
            0,
            null,
            0,
            0
        );
        
        if ($result < 0) {
            $this->error = "Error al añadir línea a la factura plantilla: ".$invoiceTemplate->error;
            $this->errors = $invoiceTemplate->errors;
            dol_syslog(get_class($this)."::generateInvoiceTemplateFromContractLine ".$this->error, LOG_ERR);
            // Intentar limpiar factura temporal
            $tmpInvoice->fetch($facid);
            $tmpInvoice->delete($user, 1);
            // Retornar 1 para permitir que Dolibarr guarde los cambios de la línea
            return 1;
        }

        // Enlazar la línea recurrente con la línea de contrato (clave para futuras renovaciones)
        $sqlLinkLine = 'UPDATE '.MAIN_DB_PREFIX.'facturedet_rec SET fk_contract_line = '.((int) $contractLine->id).' WHERE rowid = '.((int) $result);
        $resLinkLine = $db->query($sqlLinkLine);
        if (!$resLinkLine) {
            dol_syslog(get_class($this)."::generateInvoiceTemplateFromContractLine No se pudo setear fk_contract_line en facturedet_rec ".$result." (contratdet ".$contractLine->id."): ".$db->lasterror(), LOG_WARNING);
        }

        // IMPORTANTE: Validar la factura temporal AHORA que FactureRec ya la ha usado como base
        // Esto hace que obtenga una referencia/número válido (ya no será PROV*)
        // Necesitamos que tenga un número válido porque las facturas generadas desde la recurrente
        // lo heredarán como referencia base.
        // 
        // Primero recargar la factura temporal para tener los datos actualizados
        $tmpInvoice->fetch($facid);
        
        // Añadir una línea a la factura temporal si no la tiene
        if (count($tmpInvoice->lines) == 0) {
            $desc = $contractLine->label;
            if (!empty($contractLine->description)) {
                $desc .= "\n".$contractLine->description;
            }
            $pu_ht = $contractLine->subprice;
            $qty = !empty($contractLine->qty) ? $contractLine->qty : 1;
            $tva_tx = $contractLine->tva_tx;
            
            $addResult = $tmpInvoice->addline(
                $desc,
                $pu_ht,
                $qty,
                $tva_tx,
                0,
                0,
                !empty($contractLine->fk_product) ? $contractLine->fk_product : 0,
                0,
                'HT',
                0,
                0,
                0,
                1,
                -1,
                0,
                '',
                !empty($contractLine->fk_unit) ? $contractLine->fk_unit : null,
                0,
                0,
                0,
                null,
                0,
                0
            );
            if ($addResult < 0) {
                dol_syslog(get_class($this)."::generateInvoiceTemplateFromContractLine Error al añadir línea a factura temporal: ".$tmpInvoice->error, LOG_WARNING);
            }
        }
        
        // Luego intentar validarla
        $validateResult = $tmpInvoice->validate($user, 1); // notrigger=1
        if ($validateResult < 0) {
            $this->error = "Error al validar la factura temporal origen: ".$tmpInvoice->error;
            dol_syslog(get_class($this)."::generateInvoiceTemplateFromContractLine ".$this->error, LOG_ERR);
            // No es crítico para la creación de la recurrente, solo log
        } else {
            dol_syslog(get_class($this)."::generateInvoiceTemplateFromContractLine Factura temporal validada: ".$tmpInvoice->ref, LOG_INFO);
        }

        // NO BORRAR la factura temporal validada - es la que FactureRec usa como base para generar nuevas facturas
        // Si la borramos, las facturas generadas desde la recurrente perderán la referencia de origen.
        // La factura temporal se mantiene como histórico y puede ser útil para auditoría.
        
        // Enlazar la línea de contrato con la factura recurrente usando element_element (forma segura)
        // NO guardamos en el comentario para no afectar los datos de la línea
        $linkResult = $this->linkContractLineToInvoiceRec((int) $contractLine->id, (int) $invoiceTemplateId, $user);
        if ($linkResult <= 0) {
            dol_syslog(get_class($this)."::generateInvoiceTemplateFromContractLine Error al enlazar línea con FactureRec en element_element", LOG_WARNING);
        }
        
        // LOG DE SALIDA: Verificar que el objeto no ha sido modificado
        dol_syslog(
            get_class($this)."::generateInvoiceTemplateFromContractLine DEBUG SALIDA - ".
            "date_end=".var_export($contractLine->date_end, true),
            LOG_DEBUG
        );
        
        // NO llamar a call_trigger desde un trigger activo
        // Podría causar problemas recursivos o interferir con el guardado posterior de la línea
        // $contract->call_trigger('CONTRACT_INVOICE_TEMPLATE_CREATED', $user);
        
        return 1;
    }

    /**
     * Suspende la factura recurrente asociada a una línea de contrato cuando se cierra/desactiva.
     *
     * @param  ContratLigne $contractLine
     * @param  User         $user
     * @param  Translate    $langs
     * @param  Conf         $conf
     * @return int
     */
    public function suspendInvoiceTemplateFromContractLine($contractLine, $user, $langs, $conf)
    {
        global $db;

        dol_syslog(get_class($this)."::suspendInvoiceTemplateFromContractLine contractline id=".$contractLine->id);

        $invoiceRecId = 0;
        $lineComment = '';
        
        // Intentar obtener el comentario del objeto (puede no estar cargado en memoria)
        if (!empty($contractLine->commentaire)) {
            $lineComment = $contractLine->commentaire;
        }
        
        // Si no está en memoria, cargar desde BD
        if (empty($lineComment)) {
            $sqlc = "SELECT commentaire FROM ".MAIN_DB_PREFIX."contratdet WHERE rowid = ".((int) $contractLine->id);
            $resc = $db->query($sqlc);
            if ($resc) {
                $objc = $db->fetch_object($resc);
                if ($objc && !empty($objc->commentaire)) {
                    $lineComment = $objc->commentaire;
                }
            }
        }
        
        dol_syslog(get_class($this)."::suspendInvoiceTemplateFromContractLine línea ".$contractLine->id." comentario: ".$lineComment);
        
        if (!empty($lineComment)) {
            $invoiceRecId = $this->extractInvoiceRecIdFromComment($lineComment);
        }

        if ($invoiceRecId <= 0) {
            $invoiceRecId = $this->findInvoiceRecIdFromLine($contractLine->id);
            if ($invoiceRecId > 0) {
                dol_syslog(get_class($this)."::suspendInvoiceTemplateFromContractLine ID de factura recuperado desde nota privada: " . $invoiceRecId, LOG_DEBUG);
            }
        }

        if ($invoiceRecId <= 0) {
            dol_syslog(get_class($this)."::suspendInvoiceTemplateFromContractLine No se encontró ID de factura recurrente en línea ".$contractLine->id, LOG_WARNING);
            // Retornar 1 (éxito sin acción) para permitir que Dolibarr guarde los cambios de la línea
            return 1;
        }

        $sql = "UPDATE ".MAIN_DB_PREFIX."facture_rec SET suspended = 1 WHERE rowid = ".((int) $invoiceRecId);
        $resql = $db->query($sql);
        if (!$resql) {
            $this->error = "Error al suspender la factura recurrente ".$invoiceRecId.": ".$db->lasterror();
            dol_syslog(get_class($this)."::suspendInvoiceTemplateFromContractLine ".$this->error, LOG_ERR);
            // Retornar 1 para permitir que Dolibarr guarde los cambios de la línea
            return 1;
        }

        dol_syslog(get_class($this)."::suspendInvoiceTemplateFromContractLine Factura recurrente ".$invoiceRecId." suspendida correctamente", LOG_INFO);
        return 1;
    }
}
