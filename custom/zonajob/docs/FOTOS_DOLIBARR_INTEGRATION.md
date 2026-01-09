# Cambios en Gestión de Fotos - ZonaJob

## Descripción del Cambio

Se ha modificado el sistema de almacenamiento de fotos en `order_card.php` para utilizar el **sistema estándar de documentos de Dolibarr** en lugar de almacenar archivos de forma independiente.

## ¿Qué cambió?

### Antes (Sistema Anterior)
- Las fotos se guardaban en tabla específica de ZonaJob
- Se almacenaban en directorio personalizado de ZonaJob
- No aparecían en la ficha de pedidos → documentos de Dolibarr

### Ahora (Sistema Nuevo)
- Las fotos se guardan directamente en el directorio estándar de Dolibarr: `/documents/commandes/{REF_PEDIDO}/`
- Se registra metadatos en tabla ZonaJob para información adicional (geolocalización, tipo, descripción)
- **Las fotos aparecen automáticamente en la ficha de pedidos → documentos**

## Flujo de Guardado

```
1. Usuario sube foto en ZonaJob (order_card.php?tab=photos)
                    ↓
2. Se valida tipo de archivo (jpg, jpeg, png, gif, webp)
                    ↓
3. Se crea directorio estándar Dolibarr si no existe
   /documents/commandes/{REF_PEDIDO}/
                    ↓
4. Se genera nombre único: photo_TIPO_TIMESTAMP.ext
                    ↓
5. Se mueve archivo a ubicación estándar Dolibarr
                    ↓
6. Se guarda metadatos en tabla zonajob_photo:
   - Ruta del archivo
   - Tipo de foto (general, before, after, etc.)
   - Descripción
   - Geolocalización
   - Usuario que subió
                    ↓
7. ✅ Foto disponible en:
   - ZonaJob (con metadatos e información adicional)
   - Ficha de pedido Dolibarr → documentos
```

## Ventajas

✅ **Integración con Dolibarr**: Las fotos aparecen en la ficha estándar de pedidos  
✅ **Compatibilidad**: Sigue el modelo estándar de documentos de Dolibarr  
✅ **Mantenimiento**: Un solo lugar para gestionar documentos  
✅ **Permisos**: Respeta los permisos estándar de Dolibarr  
✅ **Backups**: Las fotos se incluyen en backups estándar de Dolibarr  
✅ **Búsqueda**: Se pueden buscar con el buscador estándar de Dolibarr  

## Archivos Modificados

### `order_card.php`

**Línea 43**: Agregado
```php
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
```

**Acción `upload_photo` (líneas ~100-125)**
- Cambio de método de guardado
- Ahora usa `dol_mkdir()` para crear directorio estándar
- Valida extensiones de archivo
- Genera nombre único con timestamp
- Usa `move_uploaded_file()` a ubicación estándar

**Acción `delete_photo` (líneas ~130-150)**
- Agregada eliminación física del archivo
- Mantiene eliminación de metadatos en tabla ZonaJob

## Campos de la Tabla zonajob_photo

La tabla sigue almacenando metadatos adicionales:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| rowid | int | ID único |
| fk_commande | int | ID del pedido |
| filename | varchar | Nombre del archivo |
| **filepath** | varchar | **Ruta completa al archivo en Dolibarr** |
| filetype | varchar | Tipo MIME |
| filesize | int | Tamaño del archivo |
| description | text | Descripción de la foto |
| photo_type | varchar | Tipo (general, before, after, etc.) |
| latitude | varchar | Latitud (geolocalización) |
| longitude | varchar | Longitud (geolocalización) |
| date_creation | datetime | Fecha de creación |
| fk_user_creat | int | Usuario que subió |
| entity | int | Entidad de Dolibarr |

## Estructura de Directorios

```
/var/www/html/dolpuerta/documents/
├── commandes/
│   ├── PED001/
│   │   ├── PED001.pdf
│   │   ├── photo_general_5a8f3c2e.jpg      ← Foto subida en ZonaJob
│   │   ├── photo_before_5a8f3c2f.jpg       ← Foto subida en ZonaJob
│   │   └── photo_after_5a8f3c30.jpg        ← Foto subida en ZonaJob
│   ├── PED002/
│   └── ...
```

## Cómo Ver las Fotos

### En ZonaJob (Pestaña Fotos)
```
order_card.php?id=XXX&tab=photos
```
- Previsualización con metadatos
- Información de geolocalización
- Opción de eliminar (con validación de permisos)

### En Ficha Estándar Dolibarr
```
Administración → Pedidos → [Seleccionar Pedido] → Documentos
```
- Listado de documentos estándar
- Incluye las fotos subidas en ZonaJob
- Puede descargarse directamente

## Validación de Archivos

```php
// Extensiones permitidas
$allowed_ext = array('jpg', 'jpeg', 'png', 'gif', 'webp');

// Validaciones:
✓ Extensión válida
✓ Archivo existe
✓ Permisos de escritura en directorio
✓ Movimiento a ubicación estándar
```

## Manejo de Errores

Si ocurre un error:

1. **Error de tipo de archivo**: "Tipo de archivo inválido"
2. **Error de subida**: "Error al subir archivo"
3. **Error de metadatos**: Advierte pero el archivo se guarda

## Ejemplo de Uso en Código

```php
// En order_card.php - acción upload_photo

// 1. Crear directorio si no existe
$upload_dir = $conf->commande->dir_output . '/' . $order->ref;
dol_mkdir($upload_dir);

// 2. Generar nombre único
$filename = 'photo_' . $photo_type . '_' . dechex(time()) . '.' . $file_ext;
$filepath = $upload_dir . '/' . $filename;

// 3. Mover archivo a ubicación estándar
move_uploaded_file($_FILES['photo']['tmp_name'], $filepath);

// 4. Guardar metadatos
$photoObj = new ZonaJobPhoto($db);
$photoObj->fk_commande = $order->id;
$photoObj->filename = $filename;
$photoObj->filepath = $filepath;  // Ruta completa estándar
$photoObj->create($user);
```

## Migración de Datos Antiguos (Si Aplica)

Si existía código anterior que guardaba fotos en otra ubicación, se puede migrar:

```sql
-- Copiar archivos antiguos a nueva ubicación
-- Actualizar rutas en base de datos
-- Verificar integridad de archivos
```

## Compatibilidad

✅ Dolibarr 14.0+  
✅ Mantiene compatibilidad con tabla zonajob_photo  
✅ No afecta fotos ya almacenadas  

## Permisos

La acción requiere:
```php
$user->rights->zonajob->photo->upload
```

Mismo permiso anterior, pero ahora también necesita permiso de escritura en `/documents/`

## Consideraciones Seguridad

1. ✅ Validación de extensiones
2. ✅ Nombre único para evitar sobrescritura
3. ✅ Escapado de caracteres en BD
4. ✅ Validación de permisos de usuario
5. ✅ Logging de acciones
6. ✅ Manejo de errores

## Testing

Para verificar que funciona:

1. **Subir foto**:
   - Ir a order_card.php?id=XXX&tab=photos
   - Seleccionar foto
   - Verificar que aparece en documentos estándar

2. **Verificar ubicación**:
   - Archivo en `/documents/commandes/{REF}/photo_*.jpg`
   - Metadatos en tabla zonajob_photo

3. **Ver en Dolibarr**:
   - Abrir ficha de pedido estándar
   - Ir a pestaña "Documentos"
   - Debe aparecer la foto

## Soporte

Para problemas:
- Revisar permisos de directorio `/documents/`
- Revisar logs en `dol_syslog()`
- Verificar que módulo commande está habilitado
