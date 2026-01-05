# Instalación del Botón de Renovación

## Opción 1: Inyección Automática via Hook (RECOMENDADO)

Si tu instalación de Dolibarr tiene habilitado el sistema de hooks, el botón debería aparecer automáticamente.

**Verificar que el hook esté activo:**
1. Ir a Inicio → Configuración → Módulos
2. Buscar "PuertaSevilla"
3. Verificar que el módulo esté habilitado
4. Esperar 10-15 segundos y recargar una ficha de contrato

## Opción 2: Inyección Manual (Si la Opción 1 no funciona)

Si los hooks no funcionan, añade manualmente esta línea al final de `/contrat/card.php`:

### Paso 1: Editar el archivo card.php

```bash
nano /var/www/html/dolpuerta/htdocs/contrat/card.php
```

### Paso 2: Ir al final del archivo

Presiona `Ctrl+End` para ir al final del archivo.

### Paso 3: Añadir estas líneas antes de la etiqueta de cierre `?>`

```php
// === PuertaSevilla Renovación Button Injection ===
if (isModEnabled('puertasevilla') && file_exists(DOL_DOCUMENT_ROOT.'/custom/puertasevilla/includes/inject_renovacion_button.php')) {
    include DOL_DOCUMENT_ROOT.'/custom/puertasevilla/includes/inject_renovacion_button.php';
}
```

### Paso 4: Guardar y salir

Presiona `Ctrl+O` para guardar, luego `Ctrl+X` para salir.

### Paso 5: Verificar

1. Recarga una página de contrato en el navegador
2. El botón "Renovar contrato" debe aparecer en la sección de acciones

## Opción 3: Via mod_puertasevilla.php (Alternativa)

Si tienes acceso a editar el módulo, también puedes usar:

```bash
# Ir a la carpeta del módulo
cd /var/www/html/dolpuerta/htdocs/custom/puertasevilla

# Crear/editar el archivo de módulo PHP
nano core/modules/mod_puertasevilla.php
```

Y asegurarse que contiene:

```php
$this->hooks = array(
    'printActionButtons',
    'printFieldListAction', 
    'printActionButtons2'
);
```

## Troubleshooting

### El botón no aparece

1. **Verificar que el módulo esté habilitado:**
   ```bash
   mysql -u dolibarr_user -p dolibarr_db -e "SELECT value FROM llx_const WHERE name='MAIN_MODULE_PUERTASEVILLA';"
   ```
   Debe retornar `1`

2. **Verificar que el archivo JavaScript existe:**
   ```bash
   ls -la /var/www/html/dolpuerta/htdocs/custom/puertasevilla/js/renovar_contrato_modal.js
   ```

3. **Verificar que el CSS existe:**
   ```bash
   ls -la /var/www/html/dolpuerta/htdocs/custom/puertasevilla/css/renovacion.css
   ```

4. **Verificar permisos:**
   - El usuario debe tener permiso para crear contratos (`user->rights->contrat->creer`)
   - Ir a Administración → Usuarios → [Tu usuario] → Derechos → Contratos → Crear

5. **Ver console del navegador:**
   - Presiona F12
   - Ir a Consola
   - Si hay errores JavaScript, verás el mensaje específico

### El modal no abre

1. Verifica en la consola (F12) que no haya errores JavaScript
2. Asegúrate de que jQuery está cargado (Dolibarr lo incluye por defecto)
3. Verifica que jQuery UI está disponible:
   ```javascript
   // En la consola del navegador:
   jQuery.ui // Debe retornar un objeto, no undefined
   ```

### El archivo de AJAX no responde

1. Verifica que el archivo existe:
   ```bash
   ls -la /var/www/html/dolpuerta/htdocs/custom/puertasevilla/core/actions/renovar_contrato.php
   ```

2. Verifica permisos:
   ```bash
   chmod 755 /var/www/html/dolpuerta/htdocs/custom/puertasevilla/core/actions/renovar_contrato.php
   ```

3. Revisa logs de Dolibarr:
   ```bash
   tail -50 /var/www/html/dolpuerta/documents/dolibarr.log
   ```

## Verificación Final

Para verificar que todo está correctamente instalado:

1. Abre una ficha de contrato
2. Busca el botón "Renovar contrato" en las acciones
3. Haz clic en el botón
4. Debes ver un modal con:
   - Campo "Fecha de Inicio"
   - Campo "Fecha de Fin"
   - Opciones de renovación (IPC o Importe)
   - Botón "Renovar"

Si todo esto aparece, ¡la instalación es correcta! ✅
