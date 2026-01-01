# Instalación de la Funcionalidad de Renovación

## Pasos de Instalación

### 1. Archivos Creados

Los siguientes archivos han sido creados y configurados:

```
✅ /custom/puertasevilla/core/actions/renovar_contrato.php
✅ /custom/puertasevilla/core/hooks/interface_99_modPuertaSevilla_Hooks.class.php
✅ /custom/puertasevilla/core/modules/modPuertaSevilla.php
✅ /custom/puertasevilla/js/renovar_contrato_modal.js
✅ /custom/puertasevilla/css/renovacion.css
✅ /custom/puertasevilla/admin/renovacion.php
✅ /custom/puertasevilla/includes/renovacion_buttons.php
✅ /custom/puertasevilla/sql/renovacion.sql
```

### 2. Crear la Tabla de Auditoría (Opcional)

Para mantener histórico de renovaciones, ejecuta:

```sql
-- En phpmyadmin o línea de comandos MySQL:
-- source /var/www/html/dolpuerta/htdocs/custom/puertasevilla/sql/renovacion.sql

-- O manualmente:
CREATE TABLE IF NOT EXISTS `llx_puertasevilla_contract_renewal` (
  `rowid` int(11) NOT NULL AUTO_INCREMENT,
  `fk_contrat` int(11) NOT NULL,
  `date_renewal` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_renewal_id` int(11) NOT NULL,
  `date_start_old` datetime,
  `date_start_new` datetime,
  `date_end_old` datetime,
  `date_end_new` datetime,
  `type_renovation` varchar(50),
  `value_applied` float,
  `note` text,
  `status` varchar(20) DEFAULT 'success',
  PRIMARY KEY (`rowid`),
  KEY `fk_contrat` (`fk_contrat`),
  KEY `user_renewal_id` (`user_renewal_id`),
  KEY `date_renewal` (`date_renewal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3. Configurar Permisos de Archivo

```bash
chmod 755 /var/www/html/dolpuerta/htdocs/custom/puertasevilla/core/actions/
chmod 755 /var/www/html/dolpuerta/htdocs/custom/puertasevilla/admin/
chmod 755 /var/www/html/dolpuerta/htdocs/custom/puertasevilla/js/
chmod 755 /var/www/html/dolpuerta/htdocs/custom/puertasevilla/css/
```

### 4. Verificar que el Módulo está Habilitado

1. Ve a **Configuración → Módulos**
2. Busca "PuertaSevilla"
3. Verifica que está habilitado (debe tener un ✓)

### 5. Configurar IPC por Defecto (Opcional)

1. Ve a **Configuración → PuertaSevilla → Renovación de Contratos**
2. Establece un IPC por defecto (ej: 2.4%)
3. Guarda

### 6. Inyectar Botón en Ficha de Contrato

Para que el botón aparezca en la ficha del contrato, necesitas editar el archivo:
`/var/www/html/dolpuerta/htdocs/contrat/card.php`

Busca la sección donde se muestran los botones de acción (normalmente cerca del final):

```php
// Busca la sección que dice algo como:
print "</div>\n"; // Cierre de botones/acciones

// Y justo antes de ese cierre, agrega:
if (file_exists(DOL_DOCUMENT_ROOT.'/custom/puertasevilla/includes/renovacion_buttons.php')) {
    include DOL_DOCUMENT_ROOT.'/custom/puertasevilla/includes/renovacion_buttons.php';
}
```

Alternativa: Si prefieres no editar archivos principales, los hooks también pueden agregar el botón automáticamente.

## Verificación de Instalación

### 1. Verifica que los archivos existen

```bash
ls -la /var/www/html/dolpuerta/htdocs/custom/puertasevilla/core/actions/renovar_contrato.php
ls -la /var/www/html/dolpuerta/htdocs/custom/puertasevilla/js/renovar_contrato_modal.js
ls -la /var/www/html/dolpuerta/htdocs/custom/puertasevilla/admin/renovacion.php
```

### 2. Verifica sintaxis PHP

```bash
php -l /var/www/html/dolpuerta/htdocs/custom/puertasevilla/core/actions/renovar_contrato.php
php -l /var/www/html/dolpuerta/htdocs/custom/puertasevilla/js/renovar_contrato_modal.js
```

### 3. Prueba en el navegador

1. Abre un contrato
2. Busca el botón "Renovar contrato"
3. Si aparece, haz clic
4. Verifica que se abre el modal

## Requisitos

- Dolibarr 20.x
- PHP 7.4 o superior
- jQuery UI (viene con Dolibarr)
- Acceso a Internet para obtener IPC actual (opcional, tiene fallback)
- Permisos de edición de contratos

## Solución de Problemas

### El botón no aparece

**Causa:** No está incluido en card.php

**Solución:**
1. Ve a `/var/www/html/dolpuerta/htdocs/contrat/card.php`
2. Busca el cierre del div de botones
3. Agrega la inclusión del archivo de botones

### "Error: Acción no especificada"

**Causa:** La solicitud POST no tiene el parámetro `action`

**Solución:** Comprueba el JavaScript en la consola del navegador (F12)

### Modal no responde

**Causa:** jQuery UI no está cargado

**Solución:** Verifica que jQuery UI está disponible en tu Dolibarr

### No se obtiene el IPC actual

**Causa:** Sin acceso a Internet o API no disponible

**Solución:** Usa el valor por defecto configurado en `admin/renovacion.php`

## Prueba Manual

### Prueba del endpoint AJAX

```bash
# Obtener IPC actual
curl -X POST http://localhost/custom/puertasevilla/core/actions/renovar_contrato.php \
  -d "action=obtenerIPC"

# Debería devolver algo como:
# {"success":true,"ipc":2.4,"timestamp":"2024-12-29 10:30:00"}
```

## Siguientes Pasos

1. ✅ Instalación completada
2. ⏳ Prueba la renovación de un contrato
3. ⏳ Configura el IPC por defecto según tus necesidades
4. ⏳ Documenta el proceso en tu wiki/help interna
5. ⏳ Capacita al equipo en el uso de la funcionalidad

## Soporte

Para problemas o preguntas, revisa:
- `/custom/puertasevilla/RENOVACION_README.md` - Documentación completa
- Logs de Dolibarr en `/admin/tools/errorlog.php`
- Consola JavaScript (F12) para errores AJAX
