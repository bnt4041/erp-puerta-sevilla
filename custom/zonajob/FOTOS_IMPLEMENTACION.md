# 📸 Integración de Fotos con Sistema Estándar de Dolibarr

## Resumen del Cambio

Las fotos subidas en la pestaña **Fotos** del parte de trabajo (order_card.php) ahora se guardan directamente en el **sistema estándar de documentos de Dolibarr**, en lugar de en una ubicación personalizada de ZonaJob.

**Resultado**: Las fotos aparecen automáticamente en la ficha de pedidos → documentos de Dolibarr.

## ¿Por Qué Este Cambio?

| Aspecto | Antes | Ahora |
|--------|-------|-------|
| **Almacenamiento** | Ubicación personalizada | Sistema estándar Dolibarr |
| **Visibilidad** | Solo en ZonaJob | ZonaJob + Ficha Dolibarr |
| **Integración** | Independiente | Integrada con Dolibarr |
| **Backups** | Especial | Incluido en backups estándar |
| **Permisos** | Personalizados | Estándares de Dolibarr |

## 🚀 Implementación

### Cambios Realizados

**Archivo**: `/custom/zonajob/order_card.php`

1. **Línea 43**: Agregado
   ```php
   require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
   ```

2. **Acción `upload_photo`** (modificada)
   - Crea directorio estándar: `/documents/commandes/{REF}/`
   - Genera nombre único: `photo_TIPO_TIMESTAMP.ext`
   - Valida extensiones: jpg, jpeg, png, gif, webp
   - Guarda metadatos en tabla zonajob_photo
   - ✅ Archivos ahora en ubicación estándar de Dolibarr

3. **Acción `delete_photo`** (modificada)
   - Elimina archivo físico de ubicación estándar
   - Mantiene limpieza de metadatos

### Compatibilidad

✅ **Backward Compatible**: Fotos existentes no se ven afectadas  
✅ **Tabla zonajob_photo**: Se mantiene para metadatos adicionales  
✅ **Dolibarr 14.0+**: Compatible con versiones estándar  

## 📂 Estructura de Archivos

### Antes
```
/var/www/html/dolpuerta/
├── custom/
│   └── zonajob/
│       └── fotos/           ← Ubicación personalizada
│           ├── foto1.jpg
│           └── foto2.jpg
```

### Ahora
```
/var/www/html/dolpuerta/documents/
└── commandes/
    └── PED001/              ← Ubicación estándar
        ├── PED001.pdf
        ├── photo_general_5a8f3c2e.jpg
        ├── photo_before_5a8f3c2f.jpg
        └── photo_after_5a8f3c30.jpg
```

## 💾 Base de Datos

### Tabla `zonajob_photo`

Se mantiene para almacenar metadatos:

```sql
CREATE TABLE IF NOT EXISTS llx_zonajob_photo (
  rowid int AUTO_INCREMENT PRIMARY KEY,
  fk_commande int NOT NULL,
  filename varchar(255),
  filepath varchar(255),          -- ← Ahora ruta estándar Dolibarr
  filetype varchar(50),
  filesize int,
  description text,
  photo_type varchar(50),
  latitude varchar(50),
  longitude varchar(50),
  date_creation datetime,
  fk_user_creat int,
  entity int,
  FOREIGN KEY (fk_commande) REFERENCES llx_commande(rowid)
);
```

## 🔧 Migración de Datos Antiguos

Si tienes fotos anteriores guardadas en la ubicación personalizada:

### Opción 1: Script de Migración (Automático)

```bash
# Simular migración (sin cambios)
php /var/www/html/dolpuerta/custom/zonajob/scripts/migrate_photos_to_dolibarr.php --dry-run --verbose

# Ejecutar migración real
php /var/www/html/dolpuerta/custom/zonajob/scripts/migrate_photos_to_dolibarr.php
```

El script:
- ✅ Lee todas las fotos de la tabla zonajob_photo
- ✅ Mueve archivos a ubicación estándar Dolibarr
- ✅ Actualiza rutas en la base de datos
- ✅ Reporta progreso y errores

### Opción 2: Manual

```bash
# 1. Copiar archivos
cd /var/www/html/dolpuerta/documents/commandes/PED001/
cp /var/www/html/dolpuerta/custom/zonajob/fotos/photo_*.jpg .

# 2. Actualizar base de datos
UPDATE llx_zonajob_photo 
SET filepath = CONCAT('/var/www/html/dolpuerta/documents/commandes/', 
                       (SELECT ref FROM llx_commande WHERE rowid = fk_commande), 
                       '/', filename)
WHERE filepath NOT LIKE '/var/www/html/dolpuerta/documents/%';

# 3. Verificar
SELECT rowid, filepath FROM llx_zonajob_photo LIMIT 5;
```

## 📸 Cómo Usar

### Subir Fotos en ZonaJob

1. Abrir parte de trabajo: `order_card.php?id=XXX&tab=photos`
2. Seleccionar foto
3. Llenar información (tipo, descripción, geolocalización)
4. Hacer clic en **Subir**
5. ✅ Foto se guarda en `/documents/commandes/{REF}/`

### Ver Fotos en Dolibarr Estándar

1. Ir a **Administración → Pedidos**
2. Seleccionar el pedido
3. Ir a pestaña **Documentos**
4. ✅ Ver todas las fotos subidas en ZonaJob

## ⚙️ Configuración de Permisos

### Requerimientos

```php
// El usuario necesita:
$user->rights->zonajob->photo->upload   // Para subir fotos

// Y acceso de escritura a:
{DOCUMENT_ROOT}/documents/commandes/    // Directorio estándar
```

### Archivo `.htaccess`

Si uses Apache, asegúrate de que `/documents/` tiene permisos correctos:

```
<Directory /var/www/html/dolpuerta/documents>
    Options -Indexes
    AllowOverride All
    Require all granted
</Directory>
```

## 🔒 Seguridad

Medidas implementadas:

✅ **Validación de extensiones**: jpg, jpeg, png, gif, webp  
✅ **Nombre único**: `photo_TIPO_TIMESTAMP.ext` evita sobrescrituras  
✅ **Escapado SQL**: Previene inyección  
✅ **Validación de permisos**: Verifica derechos del usuario  
✅ **Logging**: Registra acciones en syslog  
✅ **Limpieza**: Al eliminar, se borra archivo físico  

## 📊 Ejemplo de Flujo

```
Usuario sube foto en ZonaJob
    ↓
order_card.php (acción: upload_photo)
    ↓
Validar extensión (.jpg, .png, etc.)
    ↓
Crear directorio /documents/commandes/PED001/
    ↓
Generar nombre: photo_general_5a8f3c2e.jpg
    ↓
Mover archivo a ubicación estándar
    ↓
Guardar metadatos en zonajob_photo table
    ↓
✅ Foto visible en:
   - ZonaJob: order_card.php?tab=photos
   - Dolibarr: Ficha pedido → Documentos
```

## 🐛 Troubleshooting

### "Error al subir archivo"

**Causa**: Permisos de directorio insuficientes

**Solución**:
```bash
chmod 755 /var/www/html/dolpuerta/documents/commandes/
chown www-data:www-data /var/www/html/dolpuerta/documents/commandes/
```

### "Fotos no aparecen en Dolibarr"

**Causa**: Ruta incorrecta en BD

**Solución**:
```sql
-- Verificar rutas
SELECT rowid, filepath FROM llx_zonajob_photo LIMIT 5;

-- Deben empezar con /documents/commandes/
```

### "Tipo de archivo inválido"

**Causa**: Extensión no permitida

**Solución**: Use extensiones soportadas
- ✅ jpg, jpeg, png, gif, webp
- ❌ pdf, doc, zip, etc.

## 📋 Checklist de Implementación

- [ ] Revisar cambios en `order_card.php`
- [ ] Verificar permisos de `/documents/commandes/`
- [ ] Si hay fotos antiguas: Ejecutar script de migración
- [ ] Probar subida de foto en ZonaJob
- [ ] Verificar que aparece en ficha Dolibarr → Documentos
- [ ] Verificar que se puede eliminar
- [ ] Verificar geolocalización (si aplica)
- [ ] Revisar logs en Administración → Módulos → Logs

## 📚 Documentación Relacionada

- [Integración Detallada](FOTOS_DOLIBARR_INTEGRATION.md)
- [Script de Migración](../scripts/migrate_photos_to_dolibarr.php)

## 🔄 Versiones

| Versión | Fecha | Cambios |
|---------|-------|---------|
| 1.0 | 2025-01-08 | Implementación inicial |

## 📞 Soporte

Para problemas:
1. Revisar `/documents/admin/logs/` para errores
2. Verificar permisos de directorio
3. Ejecutar script de migración con `--verbose`
4. Revisar documentación en `docs/`

---

**Estado**: ✅ Implementado y Documentado  
**Compatible**: Dolibarr 14.0+  
**Autor**: ZonaJob Dev
