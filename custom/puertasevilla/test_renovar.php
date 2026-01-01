<?php
/**
 * Test simple para verificar que se carga Dolibarr
 * Acceso: /custom/puertasevilla/test_renovar.php
 */

// Encontrar main.inc.php
$rootPath = dirname(dirname(__FILE__));
if (!file_exists($rootPath.'/main.inc.php')) {
	$rootPath = dirname(dirname(dirname(__FILE__)));
}
if (!file_exists($rootPath.'/main.inc.php')) {
	die('Error: No se puede encontrar main.inc.php en: '.$rootPath);
}

// Cargar Dolibarr
require_once $rootPath.'/main.inc.php';

// Si llegamos aquí, Dolibarr se cargó correctamente
?>
<!DOCTYPE html>
<html>
<head>
	<title>Test - Renovación de Contratos</title>
	<style>
		body { font-family: Arial; margin: 20px; }
		.success { color: green; font-weight: bold; }
		.error { color: red; font-weight: bold; }
	</style>
</head>
<body>
	<h1>🧪 Test - Sistema de Renovación</h1>
	
	<h2>Información del Sistema</h2>
	<table border="1" cellpadding="10">
		<tr>
			<td><strong>DOL_DOCUMENT_ROOT</strong></td>
			<td><?php echo defined('DOL_DOCUMENT_ROOT') ? '<span class="success">✓ DEFINIDO</span>' : '<span class="error">✗ NO DEFINIDO</span>'; ?></td>
		</tr>
		<tr>
			<td><strong>Valor</strong></td>
			<td><?php echo DOL_DOCUMENT_ROOT; ?></td>
		</tr>
		<tr>
			<td><strong>Usuario</strong></td>
			<td><?php echo isset($user) ? '<span class="success">✓ Cargado: '.$user->login.'</span>' : '<span class="error">✗ No cargado</span>'; ?></td>
		</tr>
		<tr>
			<td><strong>Base de Datos</strong></td>
			<td><?php echo isset($db) ? '<span class="success">✓ Conectada</span>' : '<span class="error">✗ No conectada</span>'; ?></td>
		</tr>
		<tr>
			<td><strong>Módulo PuertaSevilla</strong></td>
			<td><?php echo isModEnabled('puertasevilla') ? '<span class="success">✓ Habilitado</span>' : '<span class="error">✗ No habilitado</span>'; ?></td>
		</tr>
	</table>
	
	<h2>Prueba del Endpoint AJAX</h2>
	<p>Si todo funciona, verás el IPC actual:</p>
	<button onclick="probarAJAX()">Obtener IPC Actual</button>
	<div id="resultado" style="margin-top: 20px; padding: 10px; border: 1px solid #ccc;"></div>
	
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script>
		function probarAJAX() {
			jQuery.post(
				'/custom/puertasevilla/core/actions/renovar_contrato.php',
				{ action: 'obtenerIPC' },
				function(response) {
					if (response.success) {
						document.getElementById('resultado').innerHTML = 
							'<span class="success">✓ IPC Actual: ' + response.ipc + '%</span>' +
							'<br>Actualizado: ' + response.timestamp;
					} else {
						document.getElementById('resultado').innerHTML = 
							'<span class="error">✗ Error: ' + response.error + '</span>';
					}
				},
				'json'
			).fail(function(xhr) {
				document.getElementById('resultado').innerHTML = 
					'<span class="error">✗ Error AJAX: ' + xhr.status + ' ' + xhr.statusText + '</span>' +
					'<br>Respuesta: ' + xhr.responseText;
			});
		}
	</script>
</body>
</html>
