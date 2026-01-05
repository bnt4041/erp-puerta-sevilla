/**
 * Modal de renovación de contratos PuertaSevilla
 * Funciones globales para renovación de contratos
 */

/**
 * Abre el modal de renovación de contrato
 * @param {number} contratId ID del contrato a renovar
 * @param {string} contratRef Referencia del contrato
 */
function abrirModalRenovacion(contratId, contratRef) {
	// Obtener token CSRF
	var token = obtenerTokenCSRF();
	
	// Crear HTML del modal
	var html = `
		<div id="dialog-renovar-contrato" style="display:none;">
			<form id="form-renovar-contrato">
				<input type="hidden" name="contrat_id" value="` + contratId + `">
				<input type="hidden" name="token" value="` + token + `">
				
				<div class="form-group">
					<label for="date-start-renovacion">Fecha de Inicio</label>
					<input type="date" id="date-start-renovacion" name="date_start" class="form-control" required>
				</div>
				
				<div class="form-group">
					<label for="date-end-renovacion">Fecha de Fin</label>
					<input type="date" id="date-end-renovacion" name="date_end" class="form-control" required>
				</div>
				
				<div class="form-group">
					<label>Tipo de Renovación</label>
					<div>
						<label style="margin-right: 20px;">
							<input type="radio" name="tipo_renovacion" value="ipc" checked> 
							Aplicar IPC (%)
						</label>
						<label>
							<input type="radio" name="tipo_renovacion" value="importe"> 
							Nuevo Importe
						</label>
					</div>
				</div>
				
				<div class="form-group">
					<label for="input-valor-renovacion">Valor</label>
					<div style="display: flex; gap: 10px;">
						<input type="number" id="input-valor-renovacion" name="valor" class="form-control" step="0.01" placeholder="0.00" required>
						<span id="label-valor-unit" style="padding-top: 6px;">%</span>
					</div>
					<small id="help-valor-renovacion" class="form-text text-muted">Obteniendo IPC actual...</small>
				</div>
				
				<div id="preview-renovacion" class="alert alert-info" style="display:none;">
					<h5>Vista previa de cambios</h5>
					<div id="preview-content"></div>
				</div>
			</form>
		</div>
	`;
	
	// Agregar HTML al DOM si no existe
	if (!document.getElementById('dialog-renovar-contrato')) {
		document.body.insertAdjacentHTML('beforeend', html);
	}
	
	// Configurar diálogo con jQuery UI
	jQuery('#dialog-renovar-contrato').dialog({
		title: 'Renovar Contrato: ' + contratRef,
		autoOpen: true,
		width: 500,
		modal: true,
		buttons: {
			'Renovar': function() {
				procesarRenovacion(contratId);
			},
			'Cancelar': function() {
				jQuery(this).dialog('close');
			}
		},
		close: function() {
			jQuery(this).dialog('destroy');
			jQuery(this).remove();
		}
	});
	
	// Obtener IPC actual
	obtenerIPCActual();
	
	// Event listeners para los radios
	document.querySelectorAll('input[name="tipo_renovacion"]').forEach(function(radio) {
		radio.addEventListener('change', function() {
			actualizarLabelValor(this.value);
		});
	});
	
	// Actualizar preview al cambiar valores
	document.getElementById('input-valor-renovacion').addEventListener('change', function() {
		actualizarPreview(contratId);
	});
	
	document.getElementById('date-start-renovacion').addEventListener('change', function() {
		actualizarPreview(contratId);
	});
	
	document.getElementById('date-end-renovacion').addEventListener('change', function() {
		actualizarPreview(contratId);
	});
}

/**
 * Obtiene el token CSRF de Dolibarr desde múltiples fuentes
 * @returns {string} Token CSRF
 */
function obtenerTokenCSRF() {
	// Fuente 1: Variable global 'newtoken' (forma estándar en Dolibarr)
	if (typeof newtoken !== 'undefined' && newtoken) {
		console.debug('Token CSRF obtenido de newtoken:', newtoken.substring(0, 10) + '...');
		return newtoken;
	}
	
	// Fuente 2: Input hidden con name="token"
	var tokenInput = document.querySelector('input[name="token"]');
	if (tokenInput && tokenInput.value) {
		console.debug('Token CSRF obtenido de input hidden');
		return tokenInput.value;
	}
	
	// Fuente 3: Meta tag con name="csrf-token"
	var metaToken = document.querySelector('meta[name="csrf-token"]');
	if (metaToken && metaToken.getAttribute('content')) {
		console.debug('Token CSRF obtenido de meta tag');
		return metaToken.getAttribute('content');
	}
	
	// Fuente 4: Buscar en cualquier elemento con data-csrf
	var dataToken = document.querySelector('[data-csrf]');
	if (dataToken && dataToken.getAttribute('data-csrf')) {
		console.debug('Token CSRF obtenido de data-csrf');
		return dataToken.getAttribute('data-csrf');
	}
	
	console.warn('No se pudo encontrar token CSRF');
	return '';
}

/**
 * Obtiene el IPC actual desde el servidor
 */
function obtenerIPCActual() {
	var token = obtenerTokenCSRF();
	jQuery.post(
		'/custom/puertasevilla/core/actions/renovar_contrato.php',
		{ action: 'obtenerIPC', token: token },
		function(response) {
			if (response.success) {
				var ipcValue = parseFloat(response.ipc).toFixed(2);
				var inputField = document.getElementById('input-valor-renovacion');
				if (inputField) {
					inputField.value = ipcValue;
					inputField.placeholder = ipcValue;
				}
				var helpField = document.getElementById('help-valor-renovacion');
				if (helpField) {
					helpField.textContent = 'IPC actual: ' + ipcValue + '% (actualizado: ' + response.timestamp + ')';
				}
			}
		},
		'json'
	).fail(function() {
		// Si falla, usar IPC por defecto (2.4%)
		var inputField = document.getElementById('input-valor-renovacion');
		if (inputField) {
			inputField.value = '2.4';
		}
		var helpField = document.getElementById('help-valor-renovacion');
		if (helpField) {
			helpField.textContent = 'IPC por defecto: 2.4% (no pudo obtenerse el actual)';
		}
	});
}

/**
 * Actualiza el label del valor según el tipo de renovación
 */
function actualizarLabelValor(tipo) {
	var label = document.getElementById('label-valor-unit');
	if (label) {
		if (tipo === 'ipc') {
			label.textContent = '%';
		} else {
			label.textContent = '€';
		}
	}
}

/**
 * Actualiza la vista previa de cambios
 */
function actualizarPreview(contratId) {
	var dateStartField = document.getElementById('date-start-renovacion');
	var dateEndField = document.getElementById('date-end-renovacion');
	var valorField = document.getElementById('input-valor-renovacion');
	var tipoRadio = document.querySelector('input[name="tipo_renovacion"]:checked');
	
	if (!dateStartField || !dateEndField || !valorField || !tipoRadio) {
		return;
	}
	
	var dateStart = dateStartField.value;
	var dateEnd = dateEndField.value;
	var valor = valorField.value;
	var tipo = tipoRadio.value;
	
	if (dateStart && dateEnd && valor) {
		var preview = document.getElementById('preview-renovacion');
		var previewContent = document.getElementById('preview-content');
		
		if (!preview || !previewContent) {
			return;
		}
		
		var html = '<ul>';
		html += '<li>Período: ' + dateStart + ' a ' + dateEnd + '</li>';
		if (tipo === 'ipc') {
			html += '<li>Aumento de precios: +' + valor + '%</li>';
		} else {
			html += '<li>Nuevo precio unitario: ' + valor + ' €</li>';
		}
		html += '</ul>';
		
		previewContent.innerHTML = html;
		preview.style.display = 'block';
	}
}

/**
 * Procesa la renovación del contrato
 */
function procesarRenovacion(contratId) {
	var form = document.getElementById('form-renovar-contrato');
	if (!form) {
		alert('Error: No se encontró el formulario');
		return;
	}
	
	var formData = new FormData(form);
	
	// Obtener token CSRF
	var token = obtenerTokenCSRF();
	
	// Preparar datos para enviar
	var data = {
		action: 'renovarContrato',
		token: token,
		contrat_id: formData.get('contrat_id'),
		date_start: formData.get('date_start'),
		date_end: formData.get('date_end'),
		tipo_renovacion: formData.get('tipo_renovacion'),
		valor: formData.get('valor')
	};
	
	// Mostrar loading
	var dialog = jQuery('#dialog-renovar-contrato');
	var buttons = dialog.dialog('option', 'buttons');
	dialog.dialog('option', 'buttons', {});
	
	jQuery.post(
		'/custom/puertasevilla/core/actions/renovar_contrato.php',
		data,
		function(response) {
			if (response.success) {
				// Cerrar modal
				dialog.dialog('close');
				
				// Mostrar mensaje de éxito
				alert('Contrato renovado correctamente');
				
				// Recargar página
				window.location.reload();
			} else {
				alert('Error: ' + (response.error || 'Error desconocido'));
			}
		},
		'json'
	).fail(function(xhr) {
		var errorMsg = 'Error en la solicitud';
		try {
			var response = JSON.parse(xhr.responseText);
			errorMsg = response.error || errorMsg;
		} catch (e) {}
		alert(errorMsg);
	}).always(function() {
		// Restaurar botones
		dialog.dialog('option', 'buttons', buttons);
	});
}
