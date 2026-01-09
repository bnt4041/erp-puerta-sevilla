# Solución: Instalación Automática de Plantilla PDF en ZonaJob

## Problema
La plantilla PDF de ZonaJob (`pdf_zonajob.modules.php`) estaba ubicada en:
```
custom/zonajob/core/modules/commande/doc/pdf_zonajob.modules.php
```

Pero Dolibarr espera encontrar las plantillas de documentos en:
```
core/modules/commande/doc/pdf_zonajob.modules.php
```

Esto causaba que la generación de PDFs fallara porque el sistema no encontraba la plantilla.

## Solución Implementada

Se ha modificado el descriptor del módulo `modZonaJob.class.php` para:

### 1. **Durante la Instalación (init)**
- Crear un nuevo método `_copyPDFTemplate()` que copia automáticamente la plantilla desde `custom/zonajob/` a `core/modules/commande/doc/` cuando se activa el módulo.
- El copiado solo ocurre si el archivo no existe en el destino.
- Se registran logs informativas sobre el proceso.

### 2. **Durante la Desinstalación (remove)**
- Crear un nuevo método `_removePDFTemplate()` que elimina la plantilla copiada cuando se desactiva el módulo.
- Se mantiene la limpieza ordenada del sistema.

## Cambios Realizados

### Archivo: `custom/zonajob/core/modules/modZonaJob.class.php`

#### Modificación 1: Método `_createDirectories()`
```php
private function _createDirectories()
{
    // ... código existente para crear subdirectorios ...
    
    // Nuevo: Copiar plantilla PDF a ubicación esperada por Dolibarr
    $this->_copyPDFTemplate();
}
```

#### Modificación 2: Nuevo método `_copyPDFTemplate()`
```php
private function _copyPDFTemplate()
{
    $source = DOL_DOCUMENT_ROOT.'/custom/zonajob/core/modules/commande/doc/pdf_zonajob.modules.php';
    $destination = DOL_DOCUMENT_ROOT.'/core/modules/commande/doc/pdf_zonajob.modules.php';

    if (!file_exists($source)) {
        dol_syslog('ZonaJob: Source PDF template not found at '.$source, LOG_WARNING);
        return;
    }

    if (!file_exists($destination)) {
        if (copy($source, $destination)) {
            dol_syslog('ZonaJob: PDF template copied to '.$destination, LOG_INFO);
        } else {
            dol_syslog('ZonaJob: Failed to copy PDF template to '.$destination, LOG_ERR);
        }
    }
}
```

#### Modificación 3: Método `remove()`
```php
public function remove($options = '')
{
    // Limpiar plantilla copiada
    $this->_removePDFTemplate();

    return $this->_remove(array(), $options);
}
```

#### Modificación 4: Nuevo método `_removePDFTemplate()`
```php
private function _removePDFTemplate()
{
    $destination = DOL_DOCUMENT_ROOT.'/core/modules/commande/doc/pdf_zonajob.modules.php';

    if (file_exists($destination)) {
        if (unlink($destination)) {
            dol_syslog('ZonaJob: PDF template removed from '.$destination, LOG_INFO);
        } else {
            dol_syslog('ZonaJob: Failed to remove PDF template from '.$destination, LOG_WARNING);
        }
    }
}
```

## Ventajas

1. **Automático**: La plantilla se copia automáticamente cuando se instala/activa el módulo.
2. **Seguro**: Solo copia si el archivo ya no existe en el destino (evita sobrescrituras).
3. **Limpieza**: Elimina la plantilla copiada cuando se desactiva el módulo.
4. **Logs**: Registra todas las operaciones para debugging.
5. **Transparent**: No requiere acciones manuales del administrador.

## Instalación

El proceso es totalmente automático. Solo necesita:

1. **Activar el módulo ZonaJob** desde el panel de administración de Dolibarr.
   - La plantilla se copiará automáticamente a la ubicación correcta.

2. **Usar la plantilla en PDFs** sin problemas:
   - La plantilla estará disponible en la generación de documentos.
   - Se podrá seleccionar "ZonaJob PDF" en el constructor de documentos.

## Verificación

Para verificar que la plantilla se ha instalado correctamente:

```bash
# Verificar si la plantilla existe en la ubicación correcta
ls -la /var/www/html/dolpuerta/core/modules/commande/doc/pdf_zonajob.modules.php
```

También puede verificar los logs en:
```
Dolibarr Admin > Herramientas > Logs > Búsqueda por "ZonaJob"
```

## Desinstalación

Cuando se desactiva el módulo ZonaJob:
- La plantilla se elimina automáticamente de `core/modules/commande/doc/`.
- No quedan archivos residuales.

## Notas Técnicas

- El método `DOL_DOCUMENT_ROOT` define la raíz de Dolibarr.
- La función `copy()` preserva permisos de archivo.
- La función `unlink()` solo elimina si el archivo existe.
- Los logs utilizan `dol_syslog()` para mantener consistencia con Dolibarr.
