<?php
/* Copyright (C) 2024 PuertaSevilla
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    core/actions/renovar_contrato.php
 * \ingroup puertasevilla
 * \brief   Acción de renovación de contratos (masiva y individual)
 */

// Este es un endpoint AJAX que requiere sesión autenticada
// NO establecemos NOREQUIREUSER ni NOREQUIREDB para permitir que Dolibarr
// cargue correctamente la sesión y la base de datos

// Forzar validación CSRF en este endpoint
define('CSRFCHECK_WITH_TOKEN', true);

// Encontrar la ruta a main.inc.php
// El archivo está en: /htdocs/custom/puertasevilla/core/actions/renovar_contrato.php
// main.inc.php está en: /htdocs/main.inc.php
$rootPath = dirname(dirname(dirname(dirname(__FILE__))));
if (!file_exists($rootPath.'/main.inc.php')) {
	// Fallback si la estructura es diferente
	$rootPath = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
}

require_once $rootPath.'/main.inc.php';

// Validar usuario y sesión
if (empty($user) || empty($user->id)) {
	http_response_code(403);
	echo json_encode(['error' => 'Acceso denegado', 'user_id' => $user->id ?? null]);
	exit;
}

// Responder con JSON
header('Content-Type: application/json; charset=utf-8');

// Obtener el IPC actual de una API abierta
// Usamos la API del Banco Central Europeo como fallback universal
function obtenerIPCActual() {
	global $conf;
	
	// Intentar obtener del caché primero
	$cacheKey = 'puertasevilla_ipc_actual';
	$ipcCache = getDolGlobalString($cacheKey);
	
	if (!empty($ipcCache)) {
		$data = @json_decode($ipcCache, true);
		if (!empty($data['timestamp']) && (time() - $data['timestamp']) < 86400) { // Cache de 24 horas
			return $data['ipc'];
		}
	}
	
	$ipc = 0;
	
	// Intentar usar API del INE (España)
	// Si no está disponible, usar un valor por defecto del 3.5% (IPC medio europeo)
	try {
		// Endpoint del INE para IPC - requiere autenticación normalmente
		// Por seguridad, usaremos una API sin autenticación como fallback
		
		// API de Inflation-API.com (requiere clave, no recomendado para producción)
		// API del Banco Central Europeo (sin autenticación)
		$url = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';
		
		// Para obtener IPC real, necesitaríamos API específica
		// Por ahora, usaremos FRED (Federal Reserve Economic Data) que es libre
		$url = 'https://api.stlouisfed.org/fred/series/FPCPITOTLZGEUR/observations?api_key=public&limit=1&sort_order=desc';
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		
		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		
		if ($httpCode == 200 && !empty($response)) {
			// Para FRED, parseamos JSON
			$data = @json_decode($response, true);
			if (!empty($data['observations']) && is_array($data['observations'])) {
				$lastObs = end($data['observations']);
				if (!empty($lastObs['value'])) {
					$ipc = (float) $lastObs['value'];
					
					// Guardar en caché
					$cacheData = [
						'ipc' => $ipc,
						'timestamp' => time()
					];
					setConfigParam($cacheKey, json_encode($cacheData));
					
					return $ipc;
				}
			}
		}
	} catch (Exception $e) {
		// Silenciar excepciones
	}
	
	// IPC por defecto (media europea 2024)
	$ipc = 2.4;
	
	// Intentar obtener del caché local de Dolibarr
	$ipcConfig = getDolGlobalString('PSV_IPC_DEFAULT');
	if (!empty($ipcConfig)) {
		$ipc = (float) $ipcConfig;
	}
	
	return $ipc;
}

/**
 * Ejecuta la renovación de un contrato (líneas + cabecera) y fuerza actualización de factura recurrente asociada.
 *
 * @param DoliDB $db
 * @param User $user
 * @param Translate $langs
 * @param Conf $conf
 * @param int $contratId
 * @param int $tsStart
 * @param int $tsEnd
 * @param string $tipoRenovacion
 * @param float $valor
 * @return array{success:bool,error?:string}
 */
function renovarContratoYRecurrente($db, $user, $langs, $conf, $contratId, $tsStart, $tsEnd, $tipoRenovacion, $valor)
{
	require_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
	require_once DOL_DOCUMENT_ROOT.'/custom/puertasevilla/core/triggers/interface_99_modPuertaSevilla_PuertaSevillaTriggers.class.php';

	$contratId = (int) $contratId;
	if ($contratId <= 0 || empty($tsStart) || empty($tsEnd)) {
		return array('success' => false, 'error' => 'Parámetros inválidos');
	}

	$contrat = new Contrat($db);
	if ($contrat->fetch($contratId) <= 0) {
		return array('success' => false, 'error' => 'Contrato no encontrado');
	}

	$db->begin();
	try {
		// Actualizar líneas del contrato
		if (!empty($contrat->lines)) {
			foreach ($contrat->lines as $line) {
				$line->date_start = $tsStart;
				$line->date_end = $tsEnd;

				if ($tipoRenovacion === 'ipc' && $valor > 0) {
					$multiplicador = 1 + ($valor / 100);
					$line->subprice = $line->subprice * $multiplicador;
				} elseif ($tipoRenovacion === 'importe' && $valor > 0) {
					$line->subprice = $valor;
				}

				$updateResult = $line->update($user, 1); // notrigger=1
				if ($updateResult < 0) {
					throw new Exception("Error al actualizar línea: ".$line->error);
				}
			}
		}

		// Recargar contrato y actualizar cabecera
		$contrat->fetch($contratId);
		$contrat->date_start = $tsStart;
		$contrat->date_end = $tsEnd;
		$contratUpdateRes = $contrat->update($user, 1); // notrigger=1
		if ($contratUpdateRes < 0) {
			throw new Exception("Error al actualizar contrato: ".$contrat->error);
		}

		// Forzar trigger para recalcular/actualizar recurrentes por línea
		$triggers = new InterfacePuertaSevillaTriggers($db);
		if (!empty($contrat->lines)) {
			foreach ($contrat->lines as $line) {
				$contractLine = new ContratLigne($db);
				$contractLine->fetch($line->id);

				$triggerResult = $triggers->runTrigger('LINECONTRACT_MODIFY', $contractLine, $user, $langs, $conf);
				if ($triggerResult < 0) {
					dol_syslog("Error al actualizar factura recurrente de línea ".$line->id, LOG_WARNING);
				}
			}
		}

		$db->commit();
		return array('success' => true);
	} catch (Exception $e) {
		$db->rollback();
		return array('success' => false, 'error' => $e->getMessage());
	}
}

// Procesar solicitud AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
	// NOTA: La validación del token CSRF se realiza automáticamente en main.inc.php
	// Si llegamos aquí es porque el token fue validado correctamente.
	// No es necesario llamar a validateToken() manualmente.
	
	// Sanear el valor de action (remover caracteres especiales)
	$action = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['action']);
	
	if ($action === 'obtenerIPC') {
		// Obtener IPC actual
		$ipc = obtenerIPCActual();
		echo json_encode([
			'success' => true,
			'ipc' => $ipc,
			'timestamp' => date('Y-m-d H:i:s')
		]);
		exit;
	}
	
	if ($action === 'renovarContrato') {
		// Renovar un contrato individual
		require_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
		
		$contratId = !empty($_POST['contrat_id']) ? (int) $_POST['contrat_id'] : 0;
		$dateStart = !empty($_POST['date_start']) ? $_POST['date_start'] : '';
		$dateEnd = !empty($_POST['date_end']) ? $_POST['date_end'] : '';
		// Validar tipo de renovación (solo permite 'ipc' o 'importe')
		$tipoRenovacion = !empty($_POST['tipo_renovacion']) && in_array($_POST['tipo_renovacion'], array('ipc', 'importe')) ? $_POST['tipo_renovacion'] : 'ipc';
		$valor = !empty($_POST['valor']) ? (float) $_POST['valor'] : 0;
		
		if ($contratId <= 0 || empty($dateStart) || empty($dateEnd)) {
			http_response_code(400);
			echo json_encode(['error' => 'Parámetros inválidos']);
			exit;
		}
		
		// Verificar permisos
		if (!$user->rights->contrat->creer) {
			http_response_code(403);
			echo json_encode(['error' => 'Permisos insuficientes']);
			exit;
		}

		// Convertir fechas
		$tsStart = dol_stringtotime($dateStart, 1);
		$tsEnd = dol_stringtotime($dateEnd, 1);

		$res = renovarContratoYRecurrente($db, $user, $langs, $conf, $contratId, $tsStart, $tsEnd, $tipoRenovacion, $valor);
		if (empty($res['success'])) {
			http_response_code(500);
			echo json_encode(['error' => $res['error'] ?? 'Error desconocido']);
			exit;
		}

		echo json_encode([
			'success' => true,
			'message' => 'Contrato renovado correctamente',
			'contrat_id' => $contratId
		]);
		exit;
	}

	if ($action === 'renovarContratosMasivo') {
		$dateStart = !empty($_POST['date_start']) ? $_POST['date_start'] : '';
		$dateEnd = !empty($_POST['date_end']) ? $_POST['date_end'] : '';
		$tipoRenovacion = !empty($_POST['tipo_renovacion']) && in_array($_POST['tipo_renovacion'], array('ipc', 'importe')) ? $_POST['tipo_renovacion'] : 'ipc';
		$valor = !empty($_POST['valor']) ? (float) $_POST['valor'] : 0;
		$rawIds = !empty($_POST['contrat_ids']) ? $_POST['contrat_ids'] : '[]';

		$ids = @json_decode($rawIds, true);
		if (!is_array($ids)) {
			http_response_code(400);
			echo json_encode(['error' => 'contrat_ids inválido']);
			exit;
		}

		if (empty($dateStart) || empty($dateEnd) || empty($ids)) {
			http_response_code(400);
			echo json_encode(['error' => 'Parámetros inválidos']);
			exit;
		}

		if (!$user->rights->contrat->creer) {
			http_response_code(403);
			echo json_encode(['error' => 'Permisos insuficientes']);
			exit;
		}

		$tsStart = dol_stringtotime($dateStart, 1);
		$tsEnd = dol_stringtotime($dateEnd, 1);

		$renovados = 0;
		$errores = array();
		foreach ($ids as $id) {
			$cid = (int) $id;
			if ($cid <= 0) {
				continue;
			}
			$res = renovarContratoYRecurrente($db, $user, $langs, $conf, $cid, $tsStart, $tsEnd, $tipoRenovacion, $valor);
			if (!empty($res['success'])) {
				$renovados++;
			} else {
				$errores[] = array('contrat_id' => $cid, 'error' => $res['error'] ?? 'Error');
			}
		}

		echo json_encode([
			'success' => ($renovados > 0),
			'renovados' => $renovados,
			'errores' => $errores
		]);
		exit;
	}
}

http_response_code(400);
echo json_encode(['error' => 'Acción no especificada']);
exit;
