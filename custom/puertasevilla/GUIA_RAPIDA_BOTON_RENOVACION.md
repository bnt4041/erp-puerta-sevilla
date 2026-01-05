# 🎯 INSTRUCCIONES: Cómo Ver el Botón de Renovación

## ✅ Verificación Rápida (2 minutos)

### Paso 1: Abre tu Dolibarr
```
1. Ve a tu navegador
2. Abre http://tu-dolibarr-url/contrat/list.php
3. Haz clic en cualquier contrato existente
```

### Paso 2: Recarga la página
```
4. Presiona F5 (o Ctrl+R en Mac)
5. Espera 2-3 segundos a que cargue completamente
```

### Paso 3: Busca el botón
```
6. Mira en la parte superior derecha donde están los botones:
   [✏️ Editar] [👁️ Ver] [🔄 Renovar contrato] ← ESTE BOTÓN
```

### Paso 4: ¡Prueba!
```
7. Haz clic en "Renovar contrato"
8. Se abre un modal con un formulario
9. Llena los datos y haz clic en "Renovar"
```

---

## 🔧 Si el Botón NO Aparece

### Solución 1: Limpia Cache del Navegador

**Chrome/Edge**:
```
1. Presiona Ctrl+Shift+Del
2. Selecciona "Cookies y otros datos de sitios"
3. Haz clic en "Borrar datos"
4. Recarga la página (F5)
```

**Firefox**:
```
1. Presiona Ctrl+Shift+Del
2. Selecciona "Cookies" y "Caché"
3. Haz clic en "Limpiar ahora"
4. Recarga la página (F5)
```

### Solución 2: Verifica que el Módulo está Habilitado

```
1. Ve a: Inicio → Configuración → Módulos
2. Busca "PuertaSevilla" en la lista
3. Debe estar en VERDE (habilitado)
4. Si está en gris, haz clic en la casilla para habilitarlo
5. Recarga la ficha de contrato
```

### Solución 3: Abre la Consola del Navegador

```
1. Presiona F12 (abre herramientas de desarrollador)
2. Ve a la pestaña "Consola"
3. Si ves errores en rojo, copialos y envíalos
4. Si NO ves errores, el JavaScript cargó correctamente
```

### Solución 4: Verifica que Tienes Permisos

```
1. Ve a: Inicio → Administración → Usuarios → [Tu usuario]
2. Busca la sección "Contratos"
3. Debe estar habilitado "Crear/Modificar"
4. Si no, pide a un administrador que lo active
```

---

## 📋 Verificación Técnica (Para Administradores)

Si eres administrador y quieres verificar que todo está correcto:

### 1. Verificar archivos en el servidor

```bash
# Conecta por SSH a tu servidor
ssh tu_usuario@tu_servidor

# Ve a la carpeta de Dolibarr
cd /var/www/html/dolpuerta

# Ejecuta el script de verificación
bash verificar_renovacion.sh
```

Debes ver todos los checks en ✓ (verde).

### 2. Verificar permisos de archivos

```bash
# Ver permisos
ls -la htdocs/custom/puertasevilla/js/renovar_contrato_modal.js
ls -la htdocs/custom/puertasevilla/css/renovacion.css

# Deben ser legibles (r--), si no están así:
chmod 644 htdocs/custom/puertasevilla/js/renovar_contrato_modal.js
chmod 644 htdocs/custom/puertasevilla/css/renovacion.css
```

### 3. Verificar que las inyecciones están en place

```bash
# Verificar card.php
grep -n "inject_renovacion_button" htdocs/contrat/card.php

# Verificar list.php
grep -n "inject_renovacion_list" htdocs/contrat/list.php

# Ambas búsquedas deben retornar un número de línea
```

### 4. Revisar logs

```bash
# Ver últimos 50 errores
tail -50 documents/dolibarr.log | grep -i error

# Ver específicamente errores de PuertaSevilla
tail -100 documents/dolibarr.log | grep -i puertasevilla
```

---

## 🎬 Demo del Botón en Acción

### Cuando hagas clic en "Renovar contrato":

1. **Se abre un modal** (ventana popup) con este formulario:

```
┌──────────────────────────────────────┐
│  📝 Renovar Contrato [X]            │
├──────────────────────────────────────┤
│                                      │
│  Fecha de Inicio: [2025-01-01]      │
│  Fecha de Fin:    [2025-12-31]      │
│                                      │
│  Tipo de Renovación:                │
│  ○ Aplicar IPC (%)  [2.40] %        │
│  ○ Nuevo Importe    [1000.00] €     │
│                                      │
│  ┌─────────────────┬────────────┐   │
│  │    Renovar      │  Cancelar  │   │
│  └─────────────────┴────────────┘   │
│                                      │
└──────────────────────────────────────┘
```

2. **Llenas los datos** y haces clic en "Renovar"

3. **El servidor procesa** la renovación:
   - Actualiza las fechas del contrato
   - Recalcula el número máximo de facturas
   - Aplica el IPC o nuevo importe
   - Actualiza la factura recurrente asociada

4. **La página recarga** mostrando los cambios realizados

---

## 📞 Contacto y Soporte

Si tienes problemas:

1. **Verifica el checklist anterior**
2. **Ejecuta**: `bash verificar_renovacion.sh`
3. **Abre F12 en el navegador** y busca errores
4. **Revisa los logs**: `tail -50 documents/dolibarr.log`
5. **Contacta con soporte** incluyendo:
   - Output del script verificar_renovacion.sh
   - Errores de la consola del navegador (F12)
   - Últimas líneas del log de Dolibarr

---

## 🎉 ¡Listo!

El botón "Renovar contrato" ya está disponible. 

**Próximos pasos**:
- ✅ Ve a una ficha de contrato
- ✅ Busca el botón "Renovar contrato" 
- ✅ ¡Pruébalo!

¡Que disfrutes la nueva funcionalidad! 🚀
