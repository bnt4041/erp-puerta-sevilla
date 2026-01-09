# Vista General de Cambios

## Estructura de Archivos

```
/var/www/html/dolpuerta/
├── core/
│   └── modules/
│       └── commande/
│           └── doc/
│               └── pdf_zonajob.modules.php  ← COPIA AUTOMÁTICA
│
└── custom/
    └── zonajob/
        ├── core/
        │   └── modules/
        │       ├── modZonaJob.class.php     ← MODIFICADO (descriptor)
        │       └── commande/
        │           └── doc/
        │               └── pdf_zonajob.modules.php  ← ORIGINAL
        ├── scripts/
        │   └── verify_pdf_template.sh       ← NUEVO (verificación)
        ├── PLANTILLA_PDF_INSTALACION.md    ← NUEVA (documentación)
        └── CAMBIOS_PLANTILLA_PDF.md        ← NUEVA (resumen)
```

## Flujo de Instalación/Desinstalación

```
┌─────────────────────────────────────────┐
│   Usuario activa módulo ZonaJob         │
│   (Admin > Módulos > Activar)           │
└────────────────┬────────────────────────┘
                 │
                 ▼
        ┌───────────────────┐
        │ init() ejecutado  │
        └────────┬──────────┘
                 │
        ┌────────▼────────────────┐
        │ _createDirectories()    │
        ├────────────────────────┤
        │ - Crear /signatures    │
        │ - Crear /photos        │
        │ - Crear /temp          │
        │ ↓↓↓ NUEVO ↓↓↓         │
        │ - Copiar plantilla PDF │
        └────────┬───────────────┘
                 │
        ┌────────▼──────────────────────┐
        │ _copyPDFTemplate() ejecutado  │
        ├───────────────────────────────┤
        │ Origen:                       │
        │ custom/zonajob/core/.../      │
        │ pdf_zonajob.modules.php       │
        │          │                     │
        │          ▼                     │
        │ Destino:                      │
        │ core/modules/commande/doc/    │
        │ pdf_zonajob.modules.php       │
        │          │                     │
        │          ▼                     │
        │ ✓ Copia exitosa!             │
        │ ✓ Logs registrados           │
        └────────┬──────────────────────┘
                 │
                 ▼
        ┌───────────────────────┐
        │ registerOrderDocModel()│
        │ Registra en BD        │
        └───────┬───────────────┘
                 │
                 ▼
        ┌───────────────────────┐
        │ ✓ Módulo Activado     │
        │ ✓ PDF disponible      │
        └───────────────────────┘
```

## Desactivación

```
┌─────────────────────────────────────────┐
│  Usuario desactiva módulo ZonaJob       │
│  (Admin > Módulos > Desactivar)         │
└────────────────┬────────────────────────┘
                 │
                 ▼
        ┌───────────────────┐
        │ remove() ejecutado│
        └────────┬──────────┘
                 │
        ┌────────▼────────────────────┐
        │ _removePDFTemplate()        │
        ├────────────────────────────┤
        │ Eliminar:                  │
        │ core/modules/commande/doc/ │
        │ pdf_zonajob.modules.php    │
        │          │                  │
        │          ▼                  │
        │ ✓ Eliminado exitosamente   │
        │ ✓ Logs registrados         │
        └────────┬────────────────────┘
                 │
        ┌────────▼──────────────────┐
        │ _remove() ejecutado       │
        │ Limpieza adicional        │
        └────────┬──────────────────┘
                 │
                 ▼
        ┌──────────────────────┐
        │ ✓ Módulo Desactivado │
        │ ✓ Sistema limpio     │
        └──────────────────────┘
```

## Comparación de Estados

### Antes del Cambio ❌

```
Instalación:
  - Usuario activa ZonaJob
  - Plantilla NO se copia automáticamente
  - Usuario intenta generar PDF
  - ERROR: Plantilla no encontrada
  - ❌ Fallo en la generación

Desactivación:
  - Usuario desactiva ZonaJob
  - Archivo copia en core/ queda huérfano
  - ❌ Residuos en el sistema
```

### Después del Cambio ✅

```
Instalación:
  - Usuario activa ZonaJob
  - ✓ init() se ejecuta
  - ✓ _copyPDFTemplate() copia automáticamente
  - ✓ Plantilla lista en core/modules/commande/doc/
  - Usuario genera PDF
  - ✓ ÉXITO: PDF generado correctamente
  - ✓ Log registrado

Desactivación:
  - Usuario desactiva ZonaJob
  - ✓ remove() se ejecuta
  - ✓ _removePDFTemplate() elimina copia
  - ✓ Sistema limpio sin residuos
  - ✓ Log registrado
```

## Métodos del Descriptor

### Nuevo Método 1: `_copyPDFTemplate()`

```php
private function _copyPDFTemplate()
{
    // Ruta origen (en custom/)
    $source = DOL_DOCUMENT_ROOT.'/custom/zonajob/core/modules/commande/doc/pdf_zonajob.modules.php';
    
    // Ruta destino (en core/)
    $destination = DOL_DOCUMENT_ROOT.'/core/modules/commande/doc/pdf_zonajob.modules.php';

    // Validaciones
    if (!file_exists($source)) {
        dol_syslog('ZonaJob: Source PDF template not found', LOG_WARNING);
        return;
    }

    // Copia si no existe
    if (!file_exists($destination)) {
        if (copy($source, $destination)) {
            dol_syslog('ZonaJob: PDF template copied successfully', LOG_INFO);
        } else {
            dol_syslog('ZonaJob: Failed to copy PDF template', LOG_ERR);
        }
    }
}
```

### Nuevo Método 2: `_removePDFTemplate()`

```php
private function _removePDFTemplate()
{
    // Ruta del archivo a eliminar
    $destination = DOL_DOCUMENT_ROOT.'/core/modules/commande/doc/pdf_zonajob.modules.php';

    // Elimina si existe
    if (file_exists($destination)) {
        if (unlink($destination)) {
            dol_syslog('ZonaJob: PDF template removed successfully', LOG_INFO);
        } else {
            dol_syslog('ZonaJob: Failed to remove PDF template', LOG_WARNING);
        }
    }
}
```

## Líneas de Código

- **Líneas añadidas**: ~70
- **Líneas modificadas**: ~5
- **Métodos nuevos**: 2
- **Métodos modificados**: 2
- **Complejidad**: BAJA
- **Riesgo**: MÍNIMO (solo operaciones de archivo)

## Beneficios

| Beneficio | Impacto |
|-----------|--------|
| Instalación automática | ⭐⭐⭐⭐⭐ |
| Sin errores de PDF | ⭐⭐⭐⭐⭐ |
| Limpieza automática | ⭐⭐⭐⭐ |
| Logs completos | ⭐⭐⭐⭐ |
| Cero configuración manual | ⭐⭐⭐⭐⭐ |

---

**Implementación completada y lista para producción**
