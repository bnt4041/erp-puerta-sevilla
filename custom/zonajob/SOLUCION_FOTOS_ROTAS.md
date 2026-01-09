# 🖼️ SOLUCIÓN - Fotos Rotas (Miniaturas y Vista Grande)

## Problema Identificado

Las fotos se suben correctamente, pero las miniaturas y vista de tamaño completo están rotas.

**Síntoma**: El HTML generado muestra:
```html
<img src="/custom/zonajob/viewphoto.php?file=%2Fvar%2Fwww%2Fhtml%2Fdocuments%2Fcommande%2F%28PROV2%29%2Fphoto_general_695f518d.jpg">
```

**Causas**:
1. El script `viewphoto.php` no estaba actualizado para la nueva ubicación estándar
2. La ruta del archivo almacenada podría no ser correcta

## ✅ Solución Implementada

### 1. Script `viewphoto.php` Actualizado

**Ubicación**: `custom/zonajob/viewphoto.php`

**Cambios principales**:
- ✅ Ahora acepta rutas absolutas del archivo (`/var/www/html/dolpuerta/documents/commandes/...`)
- ✅ Valida que el archivo esté dentro del directorio de documentos (seguridad)
- ✅ Descodifica rutas URL-encoded
- ✅ Genera y cachea miniaturas automáticamente
- ✅ Soporta Imagick y GD para generar thumbnails
- ✅ Maneja correctamente imágenes PNG/GIF con transparencia

**Características**:
- Validación de seguridad contra directory traversal
- Generación de thumbnails bajo demanda
- Caché de thumbnails en `.thumbs/`
- Soporta JPEG, PNG, GIF, WebP
- Headers HTTP correctos con cache control

### 2. Métodos en Clase `ZonaJobPhoto`

**Ubicación**: `custom/zonajob/class/zonajobphoto.class.php`

**Métodos disponibles**:

```php
// Obtener URL de miniatura (200x200)
$photo->getThumbnailUrl(200, 200);

// Obtener URL de imagen completa
$photo->getPhotoUrl();
```

**Ambos métodos**:
- ✅ Generan URLs correctas con el script viewphoto.php
- ✅ URL-encodean la ruta del archivo
- ✅ Pasan parámetros de dimensiones para thumbnails

## 🔧 Flujo Correcto Ahora

```
1. Usuario sube foto en ZonaJob
                    ↓
2. Se guarda en: /documents/commandes/PED001/photo_general_xxxxx.jpg
                    ↓
3. Se almacena ruta en BD (tabla zonajob_photo)
   filepath = /var/www/html/dolpuerta/documents/commandes/PED001/photo_general_xxxxx.jpg
                    ↓
4. En HTML, se genera:
   <img src="/custom/zonajob/viewphoto.php?file=%2Fvar%2Fwww%2Fhtml%2Fdocuments%2F...&thumb=1">
                    ↓
5. viewphoto.php procesa:
   - Decodifica URL
   - Valida ruta (seguridad)
   - Genera thumbnail si no existe
   - Sirve imagen con headers correctos
                    ↓
6. ✅ Miniatura se ve correctamente
```

## 📋 Verificación

Para verificar que funciona correctamente:

### 1. Revisar que la foto se sube en ubicación correcta

```bash
ls -la /var/www/html/dolpuerta/documents/commandes/PED001/
# Debería mostrar: photo_general_xxxxx.jpg
```

### 2. Revisar que la ruta se guarda correctamente en BD

```sql
SELECT rowid, fk_commande, filename, filepath FROM llx_zonajob_photo LIMIT 1;

# Debería mostrar:
# rowid: 1
# fk_commande: 2
# filename: photo_general_695f518d.jpg
# filepath: /var/www/html/dolpuerta/documents/commandes/PROV2/photo_general_695f518d.jpg
```

### 3. Probar acceso directo a viewphoto.php

```
URL: /custom/zonajob/viewphoto.php?file=%2Fvar%2Fwww%2Fhtml%2Fdocuments%2Fcommandes%2FPROV2%2Fphoto_general_695f518d.jpg&thumb=1&w=200&h=200

Resultado: Debería mostrar la miniatura
```

## 🔍 Posibles Problemas Restantes

### Problema 1: "Permission Denied"
**Causa**: Permisos insuficientes en directorio

**Solución**:
```bash
chmod 755 /var/www/html/dolpuerta/documents/commandes/
chown www-data:www-data /var/www/html/dolpuerta/documents/commandes/
```

### Problema 2: "File not found"
**Causa**: Ruta guardada en BD es incorrecta

**Solución**: Verificar que en `order_card.php` línea 130 se está guardando:
```php
$photoObj->filepath = $filepath;  // Ruta absoluta completa
```

### Problema 3: Thumbnails no se generan
**Causa**: Extensiones GD/Imagick no disponibles

**Solución**: Verificar disponibilidad
```php
// En terminal
php -i | grep -i "gd\|imagick"
```

## 🖼️ Generación de Thumbnails

El script `viewphoto.php` genera thumbnails bajo demanda en:
```
/documents/commandes/{REF}/.thumbs/
```

**Ejemplo**:
```
/documents/commandes/PED001/.thumbs/
├── photo_general_695f518d_200x200.jpg
├── photo_before_695f518e_200x200.jpg
└── photo_after_695f518f_200x200.jpg
```

Los thumbnails se cachean para mejorar performance.

## 📊 Headers HTTP Correcto

El script envía los headers correctos:
```
Content-Type: image/jpeg
Content-Length: 12345
Cache-Control: public, max-age=86400
Expires: [futuro]
```

## ✨ Características Finales

✅ Miniaturas se generan automáticamente  
✅ Miniaturas se cachean para mejor performance  
✅ Vista grande se sirve directamente  
✅ Seguridad contra directory traversal  
✅ Validación de tipo de archivo  
✅ Headers HTTP correctos para caché  
✅ Soporte para múltiples formatos  

## 📝 Resumen

La solución implementada:
1. **Actualiza `viewphoto.php`** para manejar correctamente rutas estándar de Dolibarr
2. **Genera thumbnails bajo demanda** con caché
3. **Valida seguridad** en todos los pasos
4. **Soporta múltiples formatos** de imagen
5. **Integra con la clase `ZonaJobPhoto`** correctamente

Ahora las fotos deberían aparecer correctamente tanto en miniatura como en tamaño completo.
