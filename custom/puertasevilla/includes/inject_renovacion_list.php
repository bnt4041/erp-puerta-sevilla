<?php
/**
 * Inyección de acciones masivas en la lista de contratos
 * 
 * Este archivo agrega la opción "Renovar" a las acciones masivas
 * disponibles en la lista de contratos
 */

if (!defined('DOL_DOCUMENT_ROOT')) {
    die('Access denied');
}

global $conf, $user, $db, $langs;

// Verificar permisos
if (empty($user->rights->contrat->creer)) {
    return;
}

// Verificar que el módulo esté habilitado
if (!isModEnabled('puertasevilla')) {
    return;
}

// Cargar recursos una sola vez
static $recursos_cargados = false;

if (!$recursos_cargados) {
    ?>
    <!-- PuertaSevilla Renovación List Resources -->
    <script src="<?php echo DOL_URL_ROOT; ?>/custom/puertasevilla/js/renovar_contrato_modal.js"></script>
    <link rel="stylesheet" href="<?php echo DOL_URL_ROOT; ?>/custom/puertasevilla/css/renovacion.css">
    <?php
    $recursos_cargados = true;
}
?>

<!-- PuertaSevilla Acciones Masivas -->
<script>
(function() {
    'use strict';

    function getSelectedContractIdsFromListForm(form) {
        var checkboxes = form.querySelectorAll('input[type="checkbox"][name*="toselect"]:checked');
        var selectedIds = [];

        checkboxes.forEach(function(checkbox) {
            var match = String(checkbox.value || '').match(/(\d+)/);
            if (match) {
                selectedIds.push(parseInt(match[1]));
            }
        });

        return selectedIds;
    }
    
    function setupMasiveRenewal() {
        // SOLO tocar el formulario de listado (en esta versión es #searchFormList)
        // Mantener compatibilidad con posibles nombres antiguos.
        var forms = document.querySelectorAll('form#searchFormList, form[name="listform"], form#listform');

        if (!forms.length) {
            setTimeout(setupMasiveRenewal, 200);
            return;
        }

        forms.forEach(function(form) {
            // 1) Inyectar opción solo en el select massaction del listform
            var massactionSelect = form.querySelector('select#massaction[name="massaction"], select[name="massaction"]');
            if (massactionSelect && !massactionSelect.querySelector('option[value="renovar_masivo"]')) {
                var option = document.createElement('option');
                option.value = 'renovar_masivo';
                option.textContent = 'Renovar contratos (masivo)';
                massactionSelect.appendChild(option);
            }

            // 2) Interceptar el submit/click de confirmación de acción masiva.
            if (form.getAttribute('data-psv-renov-masivo-bound') === '1') {
                return;
            }
            form.setAttribute('data-psv-renov-masivo-bound', '1');

            function handleMassActionConfirm(e) {
                var select = form.querySelector('select#massaction[name="massaction"], select[name="massaction"]');
                if (!select || select.value !== 'renovar_masivo') {
                    return;
                }

                // Evitar submit/refresh
                if (e && typeof e.preventDefault === 'function') e.preventDefault();
                if (e && typeof e.stopPropagation === 'function') e.stopPropagation();
                if (e && typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();

                var selectedIds = getSelectedContractIdsFromListForm(form);
                if (selectedIds.length === 0) {
                    alert('Selecciona al menos un contrato');
                    return;
                }
                if (selectedIds.length === 1) {
                    alert('Para un solo contrato, usa la opción de renovación individual del contrato');
                    return;
                }

                abrirModalRenovacionMasiva(selectedIds, form);
            }

            // Caso 1: click en el botón Confirmar (input submit)
            form.addEventListener('click', function(e) {
                var target = e.target;
                if (!target) return;
                var clickedConfirm = null;

                if (target.matches && target.matches('input[name="confirmmassaction"], button[name="confirmmassaction"]')) {
                    clickedConfirm = target;
                } else if (target.closest) {
                    clickedConfirm = target.closest('input[name="confirmmassaction"], button[name="confirmmassaction"]');
                }

                if (!clickedConfirm) return;
                handleMassActionConfirm(e);
            }, true);

            // Caso 2: submit del formulario (Enter o submit normal)
            form.addEventListener('submit', function(e) {
                handleMassActionConfirm(e);
            }, true);
        });
    }
    
    // Ejecutar cuando esté listo el DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupMasiveRenewal);
    } else {
        setupMasiveRenewal();
    }
})();

/**
 * Abre el modal de renovación masiva de contratos
 * @param {Array} contratIds Array de IDs de contratos a renovar
 * @param {HTMLFormElement} form Formulario para reiniciar después
 */
function abrirModalRenovacionMasiva(contratIds, form) {
    // Obtener token CSRF
    var token = obtenerTokenCSRF();
    
    // Crear HTML del modal
    var html = `
        <div id="dialog-renovar-masivo" style="display:none;">
            <form id="form-renovar-masivo">
                <input type="hidden" name="token" value="` + token + `">
                <input type="hidden" name="contrat_ids" value="` + JSON.stringify(contratIds) + `">
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <p><strong>Renovando ` + contratIds.length + ` contratos</strong></p>
                    <p style="color: #666; font-size: 12px;">Se aplicarán los mismos cambios a todos los contratos seleccionados.</p>
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="date-start-masivo">Fecha de Inicio</label>
                    <input type="date" id="date-start-masivo" name="date_start" class="form-control" required>
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="date-end-masivo">Fecha de Fin</label>
                    <input type="date" id="date-end-masivo" name="date_end" class="form-control" required>
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>Tipo de Renovación</label>
                    <div style="padding: 10px 0;">
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
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="input-valor-masivo">Valor</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="number" id="input-valor-masivo" name="valor" class="form-control" step="0.01" placeholder="0.00" required>
                        <span id="label-valor-masivo" style="padding-top: 6px;">%</span>
                    </div>
                    <small id="help-valor-masivo" class="form-text text-muted">Obteniendo IPC actual...</small>
                </div>
                
                <div id="preview-renovacion-masiva" class="alert alert-info" style="display:none; margin-bottom: 15px;">
                    <h5>Vista previa de cambios</h5>
                    <div id="preview-content-masiva"></div>
                </div>
            </form>
        </div>
    `;
    
    // Agregar HTML al DOM si no existe
    if (!document.getElementById('dialog-renovar-masivo')) {
        document.body.insertAdjacentHTML('beforeend', html);
    }
    
    // Configurar diálogo con jQuery UI
    jQuery('#dialog-renovar-masivo').dialog({
        title: 'Renovar ' + contratIds.length + ' contratos',
        autoOpen: true,
        width: 500,
        modal: true,
        buttons: {
            'Renovar': function() {
                procesarRenovacionMasiva(contratIds, form);
            },
            'Cancelar': function() {
                jQuery(this).dialog('close');
            }
        },
        close: function() {
            jQuery(this).dialog('destroy');
            jQuery(this).remove();
            // Reiniciar el form si se cancela
            if (form) {
                form.reset();
                var massactionSelect = form.querySelector('select[name="massaction"]');
                if (massactionSelect) {
                    massactionSelect.value = '';
                }
            }
        }
    });
    
    // Obtener IPC actual
    obtenerIPCActualMasivo();
    
    // Event listeners para los radios
    document.querySelectorAll('input[name="tipo_renovacion"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            actualizarLabelValorMasivo(this.value);
        });
    });
    
    // Actualizar preview al cambiar valores
    document.getElementById('input-valor-masivo').addEventListener('change', function() {
        actualizarPreviewMasiva();
    });
    
    document.getElementById('date-start-masivo').addEventListener('change', function() {
        actualizarPreviewMasiva();
    });
    
    document.getElementById('date-end-masivo').addEventListener('change', function() {
        actualizarPreviewMasiva();
    });
}

/**
 * Obtiene el IPC actual desde el servidor para renovación masiva
 */
function obtenerIPCActualMasivo() {
    var token = obtenerTokenCSRF();
    jQuery.post(
        '/custom/puertasevilla/core/actions/renovar_contrato.php',
        { action: 'obtenerIPC', token: token },
        function(response) {
            if (response.success) {
                var ipcValue = parseFloat(response.ipc).toFixed(2);
                var inputField = document.getElementById('input-valor-masivo');
                if (inputField) {
                    inputField.value = ipcValue;
                    inputField.placeholder = ipcValue;
                }
                var helpField = document.getElementById('help-valor-masivo');
                if (helpField) {
                    helpField.textContent = 'IPC actual: ' + ipcValue + '% (actualizado: ' + response.timestamp + ')';
                }
            }
        },
        'json'
    ).fail(function() {
        // Si falla, usar IPC por defecto (2.4%)
        var inputField = document.getElementById('input-valor-masivo');
        if (inputField) {
            inputField.value = '2.4';
        }
        var helpField = document.getElementById('help-valor-masivo');
        if (helpField) {
            helpField.textContent = 'IPC por defecto: 2.4% (no pudo obtenerse el actual)';
        }
    });
}

/**
 * Actualiza el label del valor según el tipo de renovación para masivo
 */
function actualizarLabelValorMasivo(tipo) {
    var label = document.getElementById('label-valor-masivo');
    if (label) {
        if (tipo === 'ipc') {
            label.textContent = '%';
        } else {
            label.textContent = '€';
        }
    }
}

/**
 * Actualiza la vista previa de cambios para renovación masiva
 */
function actualizarPreviewMasiva() {
    var dateStartField = document.getElementById('date-start-masivo');
    var dateEndField = document.getElementById('date-end-masivo');
    var valorField = document.getElementById('input-valor-masivo');
    var tipoRadio = document.querySelector('input[name="tipo_renovacion"]:checked');
    
    if (!dateStartField || !dateEndField || !valorField || !tipoRadio) {
        return;
    }
    
    var dateStart = dateStartField.value;
    var dateEnd = dateEndField.value;
    var valor = valorField.value;
    var tipo = tipoRadio.value;
    
    if (dateStart && dateEnd && valor) {
        var preview = document.getElementById('preview-renovacion-masiva');
        var previewContent = document.getElementById('preview-content-masiva');
        
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
 * Procesa la renovación masiva de contratos
 */
function procesarRenovacionMasiva(contratIds, form) {
    var formElement = document.getElementById('form-renovar-masivo');
    if (!formElement) {
        alert('Error: No se encontró el formulario');
        return;
    }
    
    var formData = new FormData(formElement);
    
    // Obtener token CSRF
    var token = obtenerTokenCSRF();
    
    // Preparar datos para enviar
    var data = {
        action: 'renovarContratosMasivo',
        token: token,
        contrat_ids: JSON.stringify(contratIds),
        date_start: formData.get('date_start'),
        date_end: formData.get('date_end'),
        tipo_renovacion: formData.get('tipo_renovacion'),
        valor: formData.get('valor')
    };
    
    // Mostrar loading
    var dialog = jQuery('#dialog-renovar-masivo');
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
                alert('Se renovaron ' + response.renovados + ' contratos correctamente');
                
                // Recargar página
                window.location.reload();
            } else {
                alert('Error: ' + (response.error || 'Error desconocido'));
                // Restaurar botones
                dialog.dialog('option', 'buttons', buttons);
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
        // Restaurar botones
        dialog.dialog('option', 'buttons', buttons);
    });
}
</script>

<?php
// Fin del archivo
