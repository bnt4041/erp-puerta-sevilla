# Template PDF PuertaSevilla para Contratos de Alquiler

## Descripción

Template PDF personalizado para Dolibarr que genera contratos de alquiler en formato estándar de PuertaSevilla Inmobiliaria.

## Características

### Contenido Estructura

El template genera un documento PDF con las siguientes secciones:

1. **Identificación de Partes**
   - Información del Arrendador (empresa)
   - Información del Arrendatario (tercero/cliente)

2. **Objeto del Contrato**
   - Descripción de la propiedad a alquilar
   - Dirección y características principales

3. **Duración**
   - Fecha de inicio del contrato
   - Fecha de finalización del contrato

4. **Renta Mensual**
   - Importe de la renta
   - Alícuota de IVA (si aplica)

5. **Condiciones Generales**
   - Obligaciones del arrendatario
   - Obligaciones del arrendador

6. **Notas**
   - Campo de texto libre para notas adicionales

7. **Firmas**
   - Espacio para firma del arrendador
   - Espacio para firma del arrendatario

## Datos Dinámicos

El template utiliza los siguientes campos dinámicos del contrato en Dolibarr:

### De la Cabecera del Contrato:
- `ref` - Referencia/número del contrato
- `date_contrat` - Fecha del contrato
- `statut` - Estado (borrador, activo, cerrado)
- `note_public` - Notas públicas

### Del Tercero (Arrendatario):
- `name` - Nombre de la empresa/persona
- `address` - Dirección
- `zip` - Código postal
- `town` - Ciudad
- `phone` - Teléfono
- `email` - Email

### De la Empresa (Arrendador):
- `name` - Nombre de la empresa
- `address` - Dirección
- `zip` - Código postal
- `town` - Ciudad
- `siren` - Número SIRET/SIREN

### De las Líneas del Contrato:
- Primera línea:
  - `desc` - Descripción de la propiedad
  - `product_label` - Etiqueta del producto/propiedad
  - `subprice` - Importe de la renta
  - `tva_tx` - Alícuota de IVA
  - `date_start` - Fecha de inicio
  - `date_end` - Fecha de finalización

## Instalación

1. Copiar el archivo `pdf_puerta_sevilla.modules.php` a:
   ```
   /var/www/html/dolpuerta/htdocs/core/modules/contract/doc/
   ```

2. Copiar el archivo de idioma `pdf_puerta_sevilla.lang` a:
   ```
   /var/www/html/dolpuerta/htdocs/core/modules/contract/doc/langs/es_ES/
   ```

3. Crear el directorio de idioma si no existe:
   ```bash
   mkdir -p /var/www/html/dolpuerta/htdocs/core/modules/contract/doc/langs/es_ES/
   ```

## Uso

1. En Dolibarr, dirigirse a Contratos
2. Crear o editar un contrato de alquiler
3. Completar los datos:
   - Arrendatario (tercero)
   - Propiedades
   - Fechas de inicio y fin
   - Renta mensual
   - Otros detalles

4. Generar PDF desde:
   - Botón "Generar PDF" en la página del contrato
   - Seleccionar "PuertaSevilla" como template
   - El PDF se generará automáticamente

## Personalización

### Modificar Condiciones Generales

Las condiciones generales están hardcodeadas en el método `_renderContractContent()`. Para personalizarlas, editar el array `$conditions` en ese método.

### Agregar Más Secciones

Dentro del método `_renderContractContent()` pueden agregarse nuevas secciones siguiendo el patrón:

```php
$pdf->SetXY($leftmargin, $curY);
$pdf->SetFont('', 'B', $default_font_size);
$pdf->MultiCell($contentwidth, 5, 'Nueva Sección', 0, 'L');
$curY = $pdf->GetY() + 3;

// Contenido de la sección
$pdf->SetXY($leftmargin, $curY);
$pdf->SetFont('', '', $default_font_size - 1);
$pdf->MultiCell($contentwidth, 4, 'Contenido', 0, 'L');
$curY = $pdf->GetY() + 5;
```

## Requisitos

- Dolibarr 15.0 o superior
- TCPDF (incluido en Dolibarr)
- Módulo de Contratos habilitado

## Estructura de Ficheros

```
/var/www/html/dolpuerta/htdocs/core/modules/contract/doc/
├── pdf_puerta_sevilla.modules.php      # Template principal
└── langs/es_ES/
    └── pdf_puerta_sevilla.lang         # Traducciones español
```

## Changelog

### v1.0.0 (2024-12-29)
- Versión inicial del template
- Estructura base de contrato de alquiler
- Soporte para identificación de partes
- Secciones de duración, renta y condiciones
- Campos de firma

## Notas

- Las traducciones pueden extenderse para otros idiomas copiando el archivo `.lang` a otros directorios de idioma
- El template respeta los márgenes y configuraciones globales de PDF de Dolibarr
- Soporta el sistema de gancho (hooks) de Dolibarr para antes y después de la generación
