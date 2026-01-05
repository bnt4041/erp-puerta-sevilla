# Guía de Integración - Template PDF PuertaSevilla

## Resumen Ejecutivo

Se ha creado un nuevo template PDF personalizado para Dolibarr que genera contratos de alquiler con el formato estándar de PuertaSevilla Inmobiliaria. El template es completamente dinámico y utiliza los datos del contrato almacenados en Dolibarr.

## Archivos Creados

```
/var/www/html/dolpuerta/htdocs/core/modules/contract/doc/
├── pdf_puerta_sevilla.modules.php              # Template principal (clase PHP)
├── pdf_puerta_sevilla_config.php                # Configuración personalizable
├── langs/es_ES/pdf_puerta_sevilla.lang         # Traducciones español
├── PDF_PUERTA_SEVILLA_README.md                 # Documentación técnica
└── EJEMPLO_USO_PUERTA_SEVILLA.md                # Ejemplo de uso
```

## Datos Dinámicos Utilizados

### De la Empresa (Arrendador)
```php
$mysoc->name          // Nombre de la empresa
$mysoc->address       // Dirección
$mysoc->zip           // Código postal
$mysoc->town          // Ciudad
$mysoc->siren         // Número SIRET/SIREN
$mysoc->logo          // Logo (si existe)
```

### Del Contrato
```php
$object->ref                // Referencia del contrato
$object->date_contrat       // Fecha de creación
$object->statut             // Estado (borrador, activo, cerrado)
$object->note_public        // Notas públicas
$object->description        // Descripción general
$object->date_contrat       // Fecha del contrato
```

### Del Tercero (Inquilino/Arrendatario)
```php
$object->thirdparty->name        // Nombre del inquilino
$object->thirdparty->address     // Dirección
$object->thirdparty->zip         // Código postal
$object->thirdparty->town        // Ciudad
$object->thirdparty->phone       // Teléfono
$object->thirdparty->email       // Email
$object->thirdparty->code_client // Código de cliente
```

### De las Líneas del Contrato (Primera línea)
```php
$object->lines[0]->desc          // Descripción de la propiedad
$object->lines[0]->product_label // Etiqueta del producto
$object->lines[0]->qty           // Cantidad
$object->lines[0]->subprice      // Precio unitario (renta)
$object->lines[0]->tva_tx        // Alícuota de IVA
$object->lines[0]->date_start    // Fecha de inicio
$object->lines[0]->date_end      // Fecha de finalización
```

## Estructura del PDF Generado

### Página 1: Encabezado

```
┌─────────────────────────────────────────────┐
│  [LOGO]              CONTRATO DE ALQUILER   │
│                        Ref: CONTRATO-001    │
│                      Fecha: 01/01/2024      │
└─────────────────────────────────────────────┘
```

### Secciones de Contenido

1. **Identificación de Partes** (2 columnas)
   - Arrendador (datos de la empresa)
   - Arrendatario (datos del tercero)

2. **Objeto del Contrato**
   - Descripción de la propiedad
   - Ubicación y características

3. **Duración**
   - Fecha de inicio
   - Fecha de finalización

4. **Renta Mensual**
   - Importe
   - Porcentaje de IVA

5. **Condiciones Generales**
   - Obligaciones del arrendatario
   - Obligaciones del arrendador

6. **Notas** (si existe)
   - Texto de notas públicas del contrato

### Pie de Página

```
┌─────────────────────┬─────────────────────┐
│ ARRENDADOR          │ ARRENDATARIO        │
│ ________________    │ ________________    │
│                     │                     │
│                     │                     │
│ Firma               │ Firma               │
│ Fecha: _________    │ Fecha: _________    │
└─────────────────────┴─────────────────────┘
```

## Clase Principal: `pdf_puerta_sevilla`

### Métodos Principales

#### `__construct($db)`
Inicializa el template con configuración estándar de Dolibarr.

#### `write_file($object, $outputlangs, ...)`
Método principal que genera el PDF en disco.

**Parámetros:**
- `$object` - Objeto Contrat a generar
- `$outputlangs` - Objeto de traducción
- `$srctemplatepath` - Ruta de template (opcional)
- `$hidedetails` - Ocultar detalles
- `$hidedesc` - Ocultar descripción
- `$hideref` - Ocultar referencia

**Retorna:**
- `1` si es éxito
- `0` si hay error

#### `_renderContractContent(&$pdf, $object, $outputlangs, $tab_top, $default_font_size)`
Renderiza el contenido principal del contrato.

**Parámetros:**
- `&$pdf` - Objeto TCPDF
- `$object` - Objeto Contrat
- `$outputlangs` - Objeto traducción
- `$tab_top` - Posición Y inicial
- `$default_font_size` - Tamaño de fuente por defecto

**Retorna:**
- Nueva posición Y después del contenido

#### `_getPropertyDescription($object)`
Extrae la descripción de la propiedad del contrato.

**Retorna:**
- String con descripción de propiedad

#### `_pagehead(&$pdf, $object, $showaddress, ...)`
Renderiza el encabezado de la página.

#### `_tableau(&$pdf, $tab_top, $tab_height, ...)`
Dibuja el marco/tabla de contenido.

#### `tabSignature(&$pdf, $tab_top, $tab_height, $outputlangs)`
Dibuja las cajas de firma.

#### `_pagefoot(&$pdf, $object, $outputlangs, ...)`
Renderiza el pie de página.

## Variables de Configuración

El archivo `pdf_puerta_sevilla_config.php` contiene:

### Fuentes
```php
PS_PDF_TITLE_FONT_SIZE_OFFSET = 3      // Tamaño títulos
PS_PDF_SECTION_FONT_SIZE_OFFSET = 0    // Tamaño secciones
PS_PDF_BODY_FONT_SIZE_OFFSET = -1      // Tamaño cuerpo
PS_PDF_SMALL_FONT_SIZE_OFFSET = -2     // Tamaño pequeño
```

### Espaciado
```php
PS_PDF_SECTION_SPACING = 5         // Entre secciones
PS_PDF_LINE_SPACING = 3            // Entre líneas
PS_PDF_PARAGRAPH_SPACING = 4       // Entre párrafos
```

### Secciones Visibles
```php
PS_PDF_SHOW_PROPERTY_DESC = true   // Mostrar descripción propiedad
PS_PDF_SHOW_DURATION = true        // Mostrar duración
PS_PDF_SHOW_RENT = true            // Mostrar renta
PS_PDF_SHOW_CONDITIONS = true      // Mostrar condiciones
PS_PDF_SHOW_SIGNATURES = true      // Mostrar firmas
PS_PDF_SHOW_NOTES = true           // Mostrar notas
```

### Colores (RGB)
```php
PS_PDF_TITLE_COLOR_R/G/B           // Color títulos
PS_PDF_SECTION_COLOR_R/G/B         // Color secciones
PS_PDF_TEXT_COLOR_R/G/B            // Color texto
PS_PDF_BORDER_COLOR_R/G/B          // Color bordes
```

## Flujo de Generación

```
1. Usuario abre contrato en Dolibarr
   ↓
2. Click en "Generar PDF"
   ↓
3. Dolibarr carga el template pdf_puerta_sevilla
   ↓
4. Instancia clase pdf_puerta_sevilla
   ↓
5. Ejecuta write_file() con los datos del contrato
   ↓
6. _pagehead() - Renderiza encabezado
   ↓
7. _renderContractContent() - Renderiza secciones:
   - Identificación de partes
   - Objeto del contrato
   - Duración
   - Renta mensual
   - Condiciones generales
   - Notas
   ↓
8. _tableau() - Dibuja marco
   ↓
9. tabSignature() - Dibuja firmas
   ↓
10. _pagefoot() - Renderiza pie
    ↓
11. PDF guardado en servidor
    ↓
12. Usuario descarga/visualiza PDF
```

## Traducción de Etiquetas

Las etiquetas mostradas en el PDF se traducen usando el sistema de idiomas de Dolibarr:

```php
$outputlangs->trans("NombreEtiqueta")
$outputlangs->transnoentities("NombreEtiqueta")
```

Archivo de traducción: `langs/es_ES/pdf_puerta_sevilla.lang`

Para agregar otro idioma, copiar el archivo `.lang` a:
```
langs/[codigo_idioma]/pdf_puerta_sevilla.lang
```

Y traducir las cadenas necesarias.

## Validación de Datos

Antes de generar el PDF, se valida:

1. **Contrato válido**: Debe tener referencia y fecha
2. **Líneas**: Debe tener al menos una línea de contrato
3. **Tercero**: Debe tener tercero/inquilino asignado
4. **Empresa**: Datos básicos de empresa configurados

Si faltan datos críticos, se genera un error y no se crea el PDF.

## Manejo de Errores

Los errores se capturan y se almacenan en:
```php
$this->error    // Mensaje de error último
$this->errors   // Array de errores
```

El resultado se retorna:
```php
return 1;  // Éxito
return 0;  // Error
```

## Ubicación de Archivos Generados

Los PDFs se guardan en:
```
[DOLIBARR_DOC_FOLDER]/contracts/[REFERENCIA_CONTRATO]/[REFERENCIA].pdf
```

Ejemplo:
```
/var/www/html/dolpuerta/documents/contracts/CONTRATO-2024-001/CONTRATO-2024-001.pdf
```

## Compatibilidad

- **Dolibarr**: 15.0 y superior
- **PHP**: 7.4 y superior
- **TCPDF**: Incluido en Dolibarr
- **Navegadores**: Todos (para visualización PDF)

## Soporte Multiidioma

El template soporta automáticamente el idioma del usuario:

```php
$outputlangs->trans("LabelName")     // Traducción actual
$outputlangs->defaultlang            // Idioma por defecto
$langs->defaultlang                  // Idioma servidor
```

## Extensiones y Customizaciones

### Agregar Nueva Sección

En `_renderContractContent()`:

```php
$pdf->SetXY($leftmargin, $curY);
$pdf->SetFont('', 'B', $default_font_size);
$pdf->MultiCell($contentwidth, 5, "Nueva Sección", 0, 'L');
$curY = $pdf->GetY() + 3;

// Contenido...
$curY = $pdf->GetY() + 5;
```

### Usar Campos Personalizados (Extrafields)

```php
$value = $object->array_options['options_mi_campo'];
```

### Modificar Condiciones

Editar el array `$conditions` en `_renderContractContent()`.

## Checklist de Implementación

- [x] Archivo template principal creado
- [x] Archivo configuración creado
- [x] Archivo idioma español creado
- [x] Documentación técnica completada
- [x] Ejemplo de uso documentado
- [x] Guía de integración completada

## Siguiente Paso

Para activar el template:
1. Copiar archivos a directorios correctos
2. Limpiar caché de Dolibarr
3. Crear un contrato de prueba
4. Generar PDF y verificar formato

¡Listo para usar!
