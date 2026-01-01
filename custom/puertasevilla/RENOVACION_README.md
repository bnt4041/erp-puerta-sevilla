# Renovación de Contratos - PuertaSevilla

## Descripción

La funcionalidad de renovación de contratos permite actualizar rápidamente los términos y precios de los contratos existentes. Incluye:

- ✅ Renovación individual desde la ficha del contrato
- ✅ Renovación masiva desde la lista de contratos
- ✅ Obtención automática del IPC actual (desde APIs públicas)
- ✅ Dos modos: Por IPC (%) o Importe nuevo (€)
- ✅ Actualización automática de la factura recurrente asociada
- ✅ Modal de Dolibarr con vista previa de cambios

## Ubicaciones de Archivos

```
/custom/puertasevilla/
├── admin/
│   └── renovacion.php              # Configuración de IPC por defecto
├── core/
│   ├── actions/
│   │   └── renovar_contrato.php    # Lógica de renovación (AJAX)
│   ├── hooks/
│   │   └── interface_99_modPuertaSevilla_Hooks.class.php  # Hooks para UI
│   ├── modules/
│   │   └── modPuertaSevilla.php    # Definición del módulo
│   └── triggers/
│       └── interface_99_modPuertaSevilla_PuertaSevillaTriggers.class.php
└── js/
    └── renovar_contrato_modal.js   # Modal JavaScript
```

## Uso

### Renovación Individual

1. Abre la ficha de un contrato
2. Busca el botón "Renovar contrato" en la sección de acciones
3. Se abre un modal con las opciones:
   - **Fecha de Inicio:** Selecciona la nueva fecha de inicio
   - **Fecha de Fin:** Selecciona la nueva fecha de fin
   - **Tipo de Renovación:**
     - `IPC (%)`: Aumenta el precio actual por un porcentaje
     - `Importe Nuevo`: Fija un nuevo precio unitario
   - **Valor:** 
     - Si IPC: Porcentaje a aplicar (se obtiene automáticamente del IPC actual)
     - Si Importe: Nuevo precio unitario en €

4. El modal muestra una vista previa de los cambios
5. Haz clic en "Renovar" para confirmar

### Renovación Masiva

1. Ve a la lista de contratos
2. Selecciona los contratos a renovar usando los checkboxes
3. En el dropdown de "Acciones masivas", selecciona "Renovar contratos (masivo)"
4. Se abre un modal similar al anterior (próximamente con mejoras para renovar múltiples)

## Configuración

### IPC por Defecto

Si la API de IPC no está disponible, se usará el valor configurado:

1. Ve a **Configuración > PuertaSevilla > Renovación de Contratos**
2. Ingresa el IPC por defecto (por ejemplo: 2.4%)
3. Guarda los cambios

## APIs Utilizadas

### IPC Actual

Actualmente se intenta obtener el IPC de:

1. **FRED (Federal Reserve Economic Data):** 
   - API abierta sin autenticación
   - Usa datos de inflación de la Zona Euro
   - URL: `https://api.stlouisfed.org/fred/series/FPCPITOTLZGEUR/observations`

2. **Fallback:** Si la API falla, se usa el valor configurado en Dolibarr

### Caché

El IPC obtenido se cachea durante 24 horas para evitar múltiples llamadas a la API.

## Lógica de Renovación

### Cuando se renueva un contrato:

1. **Se actualiza cada línea del contrato:**
   - `date_start` (fecha inicio)
   - `date_end` (fecha fin)
   - `subprice` (precio unitario, si aplica)

2. **Se actualiza el contrato principal:**
   - `date_start`
   - `date_end`

3. **Se dispara el trigger LINECONTRACT_MODIFY:**
   - Para cada línea, se actualiza su factura recurrente asociada
   - Se recalcula `nb_gen_max` con las nuevas fechas
   - Se actualiza el precio si fue cambiado

## Permisos Requeridos

- ✅ `Contratos: Crear/Actualizar` (para poder renovar)
- ✅ Ser propietario del contrato o administrador

## Errores Comunes

### "Error al obtener IPC"
- Comprueba que tu servidor tiene acceso a Internet
- Verifica que no hay firewall bloqueando conexiones a `api.stlouisfed.org`
- Usa el valor por defecto configurado en `admin/renovacion.php`

### "Contrato no encontrado"
- Verifica que el contrato existe
- Comprueba los permisos de acceso al contrato

### "Error al actualizar línea"
- Puede haber algún campo requerido incompleto
- Revisa los logs de Dolibarr para más detalles

## Logs

Los eventos de renovación se registran en `syslog` con prefijo `renovar_contrato`:

```
Contrato renovado correctamente: contrato_id=123
Error al actualizar línea: Error message
```

## Mejoras Futuras

- [ ] Renovación masiva real (múltiples contratos a la vez)
- [ ] API del INE (Instituto Nacional de Estadística) para España
- [ ] Histórico de renovaciones
- [ ] Email de confirmación
- [ ] Plantillas de renovación
- [ ] Renovación automática periódica

## Desarrollo

### Agregar soporte para otra API de IPC

Edita `/core/actions/renovar_contrato.php`, función `obtenerIPCActual()`:

```php
function obtenerIPCActual() {
    // Agregar tu lógica de API aquí
    // Retorna el IPC como float (ej: 2.4)
    return $ipc;
}
```

### Personalizar el modal

Edita `/js/renovar_contrato_modal.js` para cambiar estilos, validaciones o comportamiento.

## Soporte

Para reportar bugs o sugerencias, contacta al equipo de desarrollo de PuertaSevilla.
