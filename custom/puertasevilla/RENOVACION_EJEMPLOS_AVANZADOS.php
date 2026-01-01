<?php
/**
 * Ejemplos Avanzados de Uso - Renovación de Contratos
 * 
 * Este archivo contiene ejemplos para desarrolladores
 * sobre cómo extender o personalizar la funcionalidad
 */

// ============================================================================
// EJEMPLO 1: Renovar un contrato programáticamente
// ============================================================================

function renovarContratoProgramaticamente($db, $user, $langs, $conf, $contratId, $dateStart, $dateEnd, $ipcPorcentaje) {
    global $db;
    
    // Incluir clases necesarias
    require_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
    require_once DOL_DOCUMENT_ROOT.'/custom/puertasevilla/core/triggers/interface_99_modPuertaSevilla_PuertaSevillaTriggers.class.php';
    
    // Cargar contrato
    $contrat = new Contrat($db);
    if ($contrat->fetch($contratId) <= 0) {
        return array('error' => 'Contrato no encontrado', 'success' => false);
    }
    
    // Iniciar transacción
    $db->begin();
    
    try {
        // Convertir fechas
        $tsStart = dol_stringtotime($dateStart, 1);
        $tsEnd = dol_stringtotime($dateEnd, 1);
        
        // Actualizar líneas
        foreach ($contrat->lines as $line) {
            // Nuevas fechas
            $line->date_start = $tsStart;
            $line->date_end = $tsEnd;
            
            // Aplicar IPC
            if ($ipcPorcentaje > 0) {
                $multiplicador = 1 + ($ipcPorcentaje / 100);
                $line->subprice = $line->subprice * $multiplicador;
            }
            
            // Guardar línea
            if ($line->update($user, 1) < 0) {
                throw new Exception("Error al actualizar línea: ".$line->error);
            }
            
            // Disparar trigger para actualizar factura recurrente
            $triggers = new InterfacePuertaSevillaTriggers($db);
            $line->fetch($line->id); // Recargar para obtener datos frescos
            $triggers->runTrigger('LINECONTRACT_MODIFY', $line, $user, $langs, $conf);
        }
        
        // Actualizar contrato
        $contrat->date_start = $tsStart;
        $contrat->date_end = $tsEnd;
        $contrat->update($user, 1);
        
        $db->commit();
        
        return array(
            'success' => true,
            'message' => 'Contrato renovado correctamente',
            'contrat_id' => $contratId,
            'nuevas_fechas' => array(
                'inicio' => dol_print_date($tsStart),
                'fin' => dol_print_date($tsEnd)
            )
        );
    } catch (Exception $e) {
        $db->rollback();
        return array('error' => $e->getMessage(), 'success' => false);
    }
}

// Uso:
// $resultado = renovarContratoProgramaticamente($db, $user, $langs, $conf, 123, '2025-01-01', '2025-12-31', 2.4);
// if ($resultado['success']) echo "Contrato renovado";


// ============================================================================
// EJEMPLO 2: Renovación masiva personalizada
// ============================================================================

function renovarContratosEnLote($db, $user, $langs, $conf, $arrayIds, $opciones = array()) {
    /**
     * Parámetros:
     * - arrayIds: array de IDs de contratos [123, 456, 789, ...]
     * - opciones: array(
     *      'fecha_inicio' => 'YYYY-MM-DD',
     *      'fecha_fin' => 'YYYY-MM-DD',
     *      'ipc' => 2.4,
     *      'importe_nuevo' => false, (si true, usar 'valor_nuevo' en lugar de 'ipc')
     *      'valor_nuevo' => 100,
     *      'actualizar_solo_fechas' => false,
     *      'actualizar_solo_precios' => false,
     *      'notificar_usuarios' => true,
     *      'log_historial' => true
     *  )
     */
    
    $resultados = array(
        'exitosos' => 0,
        'errores' => 0,
        'detalles' => array()
    );
    
    foreach ($arrayIds as $contratId) {
        try {
            $contrat = new Contrat($db);
            if ($contrat->fetch($contratId) <= 0) {
                $resultados['detalles'][] = "Contrato {$contratId}: NO ENCONTRADO";
                $resultados['errores']++;
                continue;
            }
            
            // Usar la función renovarContratoProgramaticamente
            $resultado = renovarContratoProgramaticamente(
                $db,
                $user,
                $langs,
                $conf,
                $contratId,
                $opciones['fecha_inicio'],
                $opciones['fecha_fin'],
                $opciones['ipc'] ?? 0
            );
            
            if ($resultado['success']) {
                $resultados['exitosos']++;
                $resultados['detalles'][] = "Contrato {$contratId}: ✓ RENOVADO";
            } else {
                $resultados['errores']++;
                $resultados['detalles'][] = "Contrato {$contratId}: ERROR - ".$resultado['error'];
            }
        } catch (Exception $e) {
            $resultados['errores']++;
            $resultados['detalles'][] = "Contrato {$contratId}: EXCEPCIÓN - ".$e->getMessage();
        }
    }
    
    return $resultados;
}

// Uso:
// $ids = array(123, 124, 125);
// $opts = array(
//     'fecha_inicio' => '2025-01-01',
//     'fecha_fin' => '2025-12-31',
//     'ipc' => 2.4
// );
// $resultados = renovarContratosEnLote($db, $user, $langs, $conf, $ids, $opts);
// echo "Renovados: ".$resultados['exitosos']." | Errores: ".$resultados['errores'];


// ============================================================================
// EJEMPLO 3: Personalizar IPC desde una API diferente
// ============================================================================

function obtenerIPCDesdeINE() {
    /**
     * Obtiene el IPC desde el Instituto Nacional de Estadística (España)
     * 
     * NOTA: El INE requiere autenticación y es más complejo.
     * Este es un ejemplo conceptual.
     */
    
    // Simulado - en producción, llamar a API real del INE
    // https://www.ine.es/jaxiT3/Tabla.htm?t=50903
    
    $ipc = 2.4; // Valor hardcoded como fallback
    
    // Aquí iría la lógica real de API del INE
    // ...
    
    return $ipc;
}

function obtenerIPCDesdeEurostat() {
    /**
     * Obtiene IPC desde Eurostat (datos europeos)
     */
    $url = 'https://ec.europa.eu/eurostat/api/dissemination/statistics/1.0/data/prc_hicp_aind';
    // Parámetros: freq=M, geo=ES, unit=RCH_A, etc.
    
    // Implementar llamada a API...
    return 2.4;
}


// ============================================================================
// EJEMPLO 4: Hook personalizado para validar renovación
// ============================================================================

function validarRenovacionPersonalizada($contrat, $opciones) {
    /**
     * Validación de negocio personalizada antes de renovar
     * Retorna array con 'valido' => true/false y 'mensaje'
     */
    
    $errores = array();
    
    // Validación 1: Contrato debe estar activo
    if ($contrat->statut != 4) { // 4 = ACTIVO
        $errores[] = "El contrato no está activo";
    }
    
    // Validación 2: No más de N renovaciones en un año
    $renovacionesUltimoAno = obtenerRenovacionesDelUltimoAno($contrat->id);
    if ($renovacionesUltimoAno >= 2) {
        $errores[] = "Máximo 2 renovaciones por año permitidas";
    }
    
    // Validación 3: IPC dentro de rango permitido
    if ($opciones['ipc'] > 10) {
        $errores[] = "IPC máximo permitido: 10%";
    }
    
    // Validación 4: Fechas coherentes
    $tsStart = dol_stringtotime($opciones['fecha_inicio'], 1);
    $tsEnd = dol_stringtotime($opciones['fecha_fin'], 1);
    if ($tsEnd <= $tsStart) {
        $errores[] = "La fecha de fin debe ser posterior a la de inicio";
    }
    
    // Validación 5: Cliente tiene crédito suficiente (si aplica)
    if (getDolGlobalInt('PSV_VALIDAR_CREDITO_CLIENTE')) {
        $cliente = new Societe($GLOBALS['db']);
        $cliente->fetch($contrat->socid);
        if ($cliente->outstanding_limit_amount > 0 && $cliente->outstanding_amount >= $cliente->outstanding_limit_amount) {
            $errores[] = "Cliente superó límite de crédito";
        }
    }
    
    return array(
        'valido' => count($errores) == 0,
        'errores' => $errores
    );
}

// Uso:
// $validacion = validarRenovacionPersonalizada($contrat, $opciones);
// if (!$validacion['valido']) {
//     foreach ($validacion['errores'] as $error) {
//         echo "Error: ".$error;
//     }
// }


// ============================================================================
// EJEMPLO 5: Registrar en tabla de auditoría
// ============================================================================

function registrarRenovacionEnHistorico($db, $user, $contratId, $dateStartOld, $dateStartNew, $dateEndOld, $dateEndNew, $tipo, $valor) {
    global $db;
    
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."puertasevilla_contract_renewal ";
    $sql .= "(fk_contrat, date_renewal, user_renewal_id, ";
    $sql .= "date_start_old, date_start_new, date_end_old, date_end_new, ";
    $sql .= "type_renovation, value_applied, status) ";
    $sql .= "VALUES ";
    $sql .= "(".(int)$contratId.", NOW(), ".(int)$user->id.", ";
    $sql .= "'".$db->idate($dateStartOld)."', '".$db->idate($dateStartNew)."', ";
    $sql .= "'".$db->idate($dateEndOld)."', '".$db->idate($dateEndNew)."', ";
    $sql .= "'".$db->escape($tipo)."', ".(float)$valor.", 'success')";
    
    $resultado = $db->query($sql);
    
    return $resultado ? true : false;
}


// ============================================================================
// EJEMPLO 6: Generar informe de renovaciones
// ============================================================================

function generarInformeRenovaciones($db, $fechaInicio, $fechaFin) {
    /**
     * Genera informe de renovaciones en un período
     */
    
    $sql = "SELECT ";
    $sql .= "cr.rowid, cr.fk_contrat, c.ref as contrat_ref, ";
    $sql .= "cr.date_renewal, u.firstname, u.lastname, ";
    $sql .= "cr.type_renovation, cr.value_applied, ";
    $sql .= "DATE(cr.date_start_new) - DATE(cr.date_start_old) as dias_ajuste ";
    $sql .= "FROM ".MAIN_DB_PREFIX."puertasevilla_contract_renewal cr ";
    $sql .= "JOIN ".MAIN_DB_PREFIX."contrat c ON c.rowid = cr.fk_contrat ";
    $sql .= "JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = cr.user_renewal_id ";
    $sql .= "WHERE cr.date_renewal BETWEEN '".$db->idate($fechaInicio)."' AND '".$db->idate($fechaFin)."' ";
    $sql .= "ORDER BY cr.date_renewal DESC";
    
    $resultado = $db->query($sql);
    $renovaciones = array();
    
    while ($row = $db->fetch_object($resultado)) {
        $renovaciones[] = array(
            'contrato' => $row->contrat_ref,
            'fecha' => $row->date_renewal,
            'usuario' => $row->firstname." ".$row->lastname,
            'tipo' => $row->type_renovation,
            'valor' => $row->value_applied
        );
    }
    
    return $renovaciones;
}


// ============================================================================
// EJEMPLO 7: Cron job para renovación automática
// ============================================================================

// Este código iría en un cron job ejecutado diariamente
/*

// Obtener contratos próximos a vencer (30 días antes)
$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."contrat ";
$sql .= "WHERE statut = 4 "; // Activos
$sql .= "AND date_end BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)";

$resultado = $db->query($sql);

while ($row = $db->fetch_object($resultado)) {
    // Obtener datos de renovación automática si están configurados
    $contrat = new Contrat($db);
    $contrat->fetch($row->rowid);
    
    // Buscar configuración de renovación automática en extrafields
    if (!empty($contrat->array_options['options_psv_renovar_automaticamente'])) {
        // Renovar automáticamente
        $ipc = obtenerIPCActual();
        renovarContratoProgramaticamente(
            $db, 
            $user, // Usuario especial de sistema
            $langs, 
            $conf,
            $row->rowid,
            date('Y-m-d', time()), // Desde hoy
            date('Y-m-d', strtotime('+1 year')), // Hasta en 1 año
            $ipc
        );
        
        // Notificar
        // enviarEmailConfirmacion($contrat->socid, $row->rowid);
    }
}

*/


// ============================================================================
// EJEMPLO 8: Integración con terceros (Webhooks)
// ============================================================================

function enviarWebhookRenovacion($contratId, $evento, $datos) {
    /**
     * Envía webhook a sistema externo cuando se renueva
     * Útil para integración con contabilidad, CRM, etc.
     */
    
    $webhookUrl = getDolGlobalString('PSV_WEBHOOK_RENOVACION');
    
    if (empty($webhookUrl)) {
        return false;
    }
    
    $payload = array(
        'evento' => $evento, // 'renovacion_iniciada', 'renovacion_completada', 'renovacion_error'
        'contrato_id' => $contratId,
        'timestamp' => date('c'),
        'datos' => $datos
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhookUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode >= 200 && $httpCode < 300;
}

?>
