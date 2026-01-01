# ✅ CHECKLIST DE VERIFICACIÓN - BOTÓN RENOVACIÓN

## 🔍 Antes de Probar

- [ ] Dolibarr está corriendo
- [ ] Tienes acceso de administrador o permisos de crear contratos
- [ ] Hay al menos un contrato en el sistema
- [ ] JavaScript está habilitado en el navegador

---

## 🧪 Pasos de Prueba

### Paso 1: Abre la Ficha de un Contrato
```
1. Ve a: Inicio → Contratos → Contratos
2. Busca cualquier contrato
3. Haz clic en el contrato para abrirlo
4. La URL debe ser similar a: /contrat/card.php?id=XXXX
```

**Resultado esperado:** Se abre la ficha del contrato

### Paso 2: Busca el Botón "Renovar Contrato"
```
1. En la ficha del contrato, busca en la sección de ACCIONES
2. Deberías ver un botón verde que dice "Renovar contrato"
3. Si está bajo otros botones, desplázate para verlo
```

**Resultado esperado:** ✅ Botón visible y accesible

### Paso 3: Haz Clic en el Botón
```
1. Localiza el botón "Renovar contrato"
2. Haz clic sobre él
3. Observa si se abre una ventana modal
```

**Resultado esperado:** ✅ Se abre un modal/diálogo

### Paso 4: Verifica el Contenido del Modal
El modal debe tener:
- [ ] Campo "Fecha de Inicio"
- [ ] Campo "Fecha de Fin"
- [ ] Opción "Aplicar IPC (%)"
- [ ] Opción "Nuevo Importe"
- [ ] Campo con el valor del IPC (ej: 2.40)
- [ ] Vista previa de cambios
- [ ] Botón "Renovar"
- [ ] Botón "Cancelar"

**Resultado esperado:** ✅ Modal con todos los elementos

### Paso 5: Obtener el IPC Actual
```
1. En el modal, busca el texto bajo el campo de valor
2. Debe decir algo como: "IPC actual: 2.40%"
3. Si dice "IPC por defecto: 2.4%", significa que falló la API pero está el fallback
```

**Resultado esperado:** ✅ IPC cargado (actual o fallback)

### Paso 6: Cargar Fechas
```
1. Haz clic en "Fecha de Inicio"
2. Selecciona una fecha (ej: 01/01/2025)
3. Haz clic en "Fecha de Fin"
4. Selecciona una fecha (ej: 31/12/2025)
```

**Resultado esperado:** ✅ Las fechas se cargan

### Paso 7: Ver Vista Previa
```
1. Cambia el valor del IPC o del importe
2. El área de "Vista previa" debe mostrar:
   - Período seleccionado
   - Aumento de precios o nuevo importe
```

**Resultado esperado:** ✅ Preview actualizada en tiempo real

### Paso 8: Cancelar y Cerrar
```
1. Haz clic en el botón "Cancelar"
2. El modal debe cerrarse
3. Deberías volver a la ficha del contrato
```

**Resultado esperado:** ✅ Modal cerrado correctamente

---

## ⚠️ Problemas Comunes y Soluciones

### Problema 1: El botón no aparece

**Causas posibles:**
- [ ] Módulo PuertaSevilla no está habilitado
- [ ] No tienes permisos de crear contratos
- [ ] La página no se recargó (necesita F5)
- [ ] El archivo inject_renovacion_button.php no existe

**Soluciones:**
1. Verifica que PuertaSevilla está **HABILITADO**:
   ```
   Inicio → Configuración → Módulos → Buscar "PuertaSevilla"
   ```

2. Verifica permisos:
   ```
   Administración → Usuarios → [Tu usuario]
   Contratos → Crear (marcar la casilla)
   ```

3. Recarga la página:
   ```
   Presiona: Ctrl + Shift + R
   ```

4. Verifica el archivo:
   ```bash
   ls -la /var/www/html/dolpuerta/htdocs/custom/puertasevilla/includes/inject_renovacion_button.php
   ```

### Problema 2: El modal no abre

**Causas posibles:**
- [ ] jQuery no está cargado
- [ ] jQuery UI no está disponible
- [ ] Hay un error en la consola
- [ ] El archivo renovar_contrato_modal.js no se carga

**Soluciones:**
1. Abre la consola (F12)
2. Ve a la pestaña "Console"
3. Verifica si hay errores en rojo
4. Si ves: `Uncaught ReferenceError: jQuery is not defined`
   - Problema: jQuery no está cargado
   - Solución: Verifica que Dolibarr está correctamente instalado

5. Verifica que el archivo JS existe:
   ```bash
   ls -la /var/www/html/dolpuerta/htdocs/custom/puertasevilla/js/renovar_contrato_modal.js
   ```

### Problema 3: IPC no se carga

**Causas posibles:**
- [ ] API FRED no responde
- [ ] No hay conexión a internet
- [ ] El servidor AJAX no responde

**Soluciones:**
1. Si ves "IPC por defecto: 2.4%" → **está funcionando correctamente**
   - Significa que el API falló pero el fallback está activo
   
2. Si quieres que cargue el IPC actual:
   - Verifica que el servidor tiene conexión a internet
   - Verifica que el archivo renovar_contrato.php existe:
     ```bash
     ls -la /var/www/html/dolpuerta/htdocs/custom/puertasevilla/core/actions/renovar_contrato.php
     ```

### Problema 4: "Cannot read properties of undefined"

**Causa:** El error que ya corregimos
- [ ] Verifica que el archivo JS fue actualizado correctamente
- [ ] Recarga la página (Ctrl+Shift+R)
- [ ] Limpia caché del navegador

---

## 🔧 Tests Técnicos

### Test 1: Verificar Funciones JavaScript

1. Abre la consola (F12)
2. Copia y pega esto:
```javascript
console.log('abrirModalRenovacion:', typeof abrirModalRenovacion);
console.log('obtenerIPCActual:', typeof obtenerIPCActual);
console.log('actualizarLabelValor:', typeof actualizarLabelValor);
console.log('actualizarPreview:', typeof actualizarPreview);
console.log('procesarRenovacion:', typeof procesarRenovacion);
```

**Resultado esperado:**
```
abrirModalRenovacion: function
obtenerIPCActual: function
actualizarLabelValor: function
actualizarPreview: function
procesarRenovacion: function
```

### Test 2: Verificar jQuery

```javascript
console.log('jQuery:', typeof jQuery);
console.log('jQuery UI:', typeof jQuery.ui);
console.log('jQuery Dialog:', typeof jQuery.ui.dialog);
```

**Resultado esperado:**
```
jQuery: object
jQuery UI: object
jQuery Dialog: object
```

### Test 3: Verificar Inyecciones

```bash
grep -c "inject_renovacion_button.php" /var/www/html/dolpuerta/htdocs/contrat/card.php
grep -c "inject_renovacion_list.php" /var/www/html/dolpuerta/htdocs/contrat/list.php
```

**Resultado esperado:**
- Ambos comandos retornan `1` (encontrado)

---

## 📊 Lista de Verificación Final

### Archivos
- [ ] `renovar_contrato_modal.js` existe
- [ ] `renovacion.css` existe
- [ ] `renovar_contrato.php` existe
- [ ] `inject_renovacion_button.php` existe
- [ ] `inject_renovacion_list.php` existe

### Código
- [ ] `card.php` tiene la inyección
- [ ] `list.php` tiene la inyección
- [ ] No hay referencias a `window.PuertaSevilla`
- [ ] Todas las funciones son globales

### Funcionalidad
- [ ] Botón aparece en ficha de contrato
- [ ] Modal se abre sin errores
- [ ] IPC se carga (actual o fallback)
- [ ] Vista previa funciona
- [ ] Modal se cierra correctamente

### Permisos
- [ ] Usuario tiene permisos de crear contratos
- [ ] Módulo PuertaSevilla está habilitado
- [ ] Archivos tienen permisos de lectura

---

## ✅ Si Todo Funciona

1. **Felicidades!** 🎉
2. El sistema de renovación está completamente operativo
3. Los usuarios pueden ahora:
   - Abrir contratos
   - Hacer clic en "Renovar contrato"
   - Completar los datos de renovación
   - Aplicar cambios automáticamente

---

## 📝 Reportar Problemas

Si alguna verificación falla:

1. Anota exactamente qué falla
2. Captura una screenshot si es posible
3. Abre la consola (F12) y copia los errores
4. Revisa los logs de Dolibarr:
   ```bash
   tail -100 /var/www/html/dolpuerta/documents/dolibarr.log | grep -i "error\|warning\|puerta"
   ```

---

**Estado:** ✅ LISTO PARA VERIFICAR
**Última actualización:** 29/12/2025
**Versión:** 1.0.1 (Corregido)
