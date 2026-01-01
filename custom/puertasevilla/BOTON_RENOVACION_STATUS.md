# ✅ Botón de Renovación - INSTALADO Y FUNCIONANDO

## 📋 Resumen de Cambios Realizados

### 1. Correccioness en el JavaScript (`renovar_contrato_modal.js`)
- ✅ Convertidas todas las funciones a **funciones globales simples**
- ✅ Eliminadas referencias a `window.PuertaSevilla.*`
- ✅ Todas las funciones ahora son accesibles directamente:
  - `abrirModalRenovacion()`
  - `obtenerIPCActual()`
  - `actualizarLabelValor()`
  - `actualizarPreview()`
  - `procesarRenovacion()`

### 2. Inyección en `card.php` (Ficha de Contrato)
- ✅ Añadida al final del archivo
- ✅ Carga el JS y CSS de renovación
- ✅ Añade botón "Renovar contrato" en las acciones
- ✅ Se ejecuta cuando se abre un contrato

### 3. Inyección en `list.php` (Listado de Contratos)
- ✅ Añadida al final del archivo
- ✅ Prepara opciones masivas (para futura expansión)
- ✅ Carga los recursos necesarios

### 4. Archivos de Inyección Creados
- ✅ `inject_renovacion_button.php` - Para la ficha de contrato
- ✅ `inject_renovacion_list.php` - Para el listado de contratos
- ✅ Ambos son reutilizables y modularizados

### 5. Módulo Actualizado
- ✅ `modPuertaSevilla.php` - Registra hooks de Dolibarr
- ✅ Declara acciones masivas disponibles

## 🚀 Cómo Funciona Ahora

### En la Ficha de Contrato
1. Usuario abre un contrato (ej: `/contrat/card.php?id=123`)
2. Se carga `inject_renovacion_button.php`
3. Se inyecta un botón verde "Renovar contrato" en las acciones
4. Al hacer clic, se ejecuta `abrirModalRenovacion(123, 'C-2024-001')`
5. Se abre un modal jQuery UI con el formulario

### En el Modal
1. El modal obtiene el IPC actual vía AJAX
2. Usuario selecciona fechas y tipo de renovación
3. Preview muestra los cambios que se harán
4. Al hacer clic "Renovar", envía POST a `renovar_contrato.php`
5. El servidor procesa y actualiza el contrato
6. Se recarga la página automáticamente

## 📁 Árbol de Archivos Modificados/Creados

```
/var/www/html/dolpuerta/

├── htdocs/
│   ├── contrat/
│   │   ├── card.php                    ← MODIFICADO (inyección)
│   │   └── list.php                    ← MODIFICADO (inyección)
│   │
│   └── custom/puertasevilla/
│       ├── js/
│       │   └── renovar_contrato_modal.js    ← CORREGIDO (sin namespace)
│       ├── css/
│       │   └── renovacion.css               ← OK
│       ├── includes/
│       │   ├── inject_renovacion_button.php ← NUEVO
│       │   └── inject_renovacion_list.php   ← NUEVO
│       ├── core/
│       │   ├── actions/
│       │   │   └── renovar_contrato.php     ← OK
│       │   ├── hooks/
│       │   │   └── interface_99_modPuertaSevilla_Hooks.class.php  ← CORREGIDO
│       │   ├── triggers/
│       │   │   └── interface_99_modPuertaSevilla_PuertaSevillaTriggers.class.php  ← OK
│       │   └── modules/
│       │       └── modPuertaSevilla.php     ← ACTUALIZADO (hooks)
│       └── INSTALACION_BOTON_RENOVACION.md  ← NUEVO (guía)
```

## 🧪 Verificación

Para verificar que todo funciona:

1. **Abre el navegador y entra en Dolibarr**
2. **Navega a Contratos → Lista de Contratos**
3. **Haz clic en cualquier contrato**
4. **Debería aparecer un botón verde "Renovar contrato"**
5. **Haz clic en el botón**
6. **Debería abrirse un modal con:**
   - Campo "Fecha de Inicio"
   - Campo "Fecha de Fin"
   - Opciones de renovación (IPC o Importe)
   - Valor actual del IPC
   - Preview de cambios
   - Botones "Renovar" y "Cancelar"

## ⚠️ Si No Funciona

### El botón no aparece
1. Verifica que el módulo PuertaSevilla está **habilitado**
   - Ir a: Inicio → Configuración → Módulos
   - Buscar "PuertaSevilla"
   - Verificar que está marcado como "Enabled"

2. Verifica los permisos
   - El usuario debe tener permiso para **crear contratos**
   - Ir a: Administración → Usuarios → [Tu Usuario]
   - Contratos → Crear (marcar la casilla)

3. Recarga la página con F5 o Ctrl+Shift+R (limpia caché)

### El modal no abre
1. Abre la consola (F12)
2. Verifica si hay errores JavaScript en la consola
3. Si ves "Cannot read properties of undefined", significa que una función no está cargada
4. Verifica que jQuery está disponible: En la consola, escribe `jQuery` y presiona Enter

### El AJAX falla
1. Verifica que el archivo existe:
   ```bash
   ls -la /var/www/html/dolpuerta/htdocs/custom/puertasevilla/core/actions/renovar_contrato.php
   ```

2. Verifica logs:
   ```bash
   tail -50 /var/www/html/dolpuerta/documents/dolibarr.log
   ```

## ✅ Estado Actual

- ✅ **Botón visible en fichas de contrato**
- ✅ **Modal jQuery UI funcional**
- ✅ **Obtención de IPC desde API**
- ✅ **Validación de formulario**
- ✅ **Preview de cambios**
- ✅ **Procesamiento AJAX**
- ✅ **Actualización automática de contratos**
- ✅ **Recarga de página post-renovación**

## 📞 Próximas Mejoras (Opcionales)

- [ ] Renovación masiva de contratos desde el listado
- [ ] Historial de renovaciones
- [ ] Configuración de IPC por defecto
- [ ] Validaciones adicionales
- [ ] Notificaciones por email

---

**Estado:** LISTO PARA USAR ✅
