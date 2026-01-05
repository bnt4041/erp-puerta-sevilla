# Ejemplo de Uso del Template PuertaSevilla

## Configuración de Datos en Dolibarr

### 1. Crear o Editar Tercero (Arrendatario)

**Datos del Inquilino:**
```
Nombre: Juan García Martínez
Tipo: Persona Física
Email: juan.garcia@email.com
Teléfono: +34 654 123 456
Dirección: Calle Principal 42
Código Postal: 41001
Ciudad: Sevilla
```

### 2. Crear Contrato

**Datos Básicos del Contrato:**
```
Referencia: CONTRATO-2024-001
Tercero: Juan García Martínez
Fecha de Contrato: 01/01/2024
Fecha Inicio: 01/01/2024
Fecha Fin: 31/12/2026
Estado: Validado
```

### 3. Añadir Línea de Contrato

**Línea 1 - Propiedad en Alquiler:**
```
Descripción: Piso de 3 dormitorios, 2 baños y salón
             Ubicado en zona céntrica de Sevilla
             Incluye calefacción y agua caliente
             
Producto: [Opcional - vincular a producto si existe]
Cantidad: 1
Precio Unitario: 850,00 €
IVA: 21%
Fecha de Inicio: 01/01/2024
Fecha de Fin: 31/12/2026
```

### 4. Información de la Empresa (Arrendador)

Configurar en Administración → Configuración → Empresa

```
Nombre: PuertaSevilla Inmobiliaria SL
SIRET: 12345678901234
Dirección: Avenida de Constitución 1
Código Postal: 41001
Ciudad: Sevilla
Teléfono: +34 954 000 000
Email: info@puertasevilla.com
```

### 5. Notas Adicionales

En el campo "Notas Públicas" del contrato:
```
- Se realiza inspección mensual del estado de la propiedad
- El pago debe realizarse entre los días 1 y 5 de cada mes
- Los servicios de agua, electricidad y gas corren por cuenta del inquilino
- Se requiere un depósito de garantía equivalente a 2 meses de renta
- Prohibido subarrendar sin consentimiento expreso del arrendador
```

## Resultado Esperado del PDF

El PDF generado incluirá:

```
═══════════════════════════════════════════════════════════════════
                     CONTRATO DE ALQUILER
                    Referencia: CONTRATO-2024-001
                      Fecha: 01 de enero de 2024
═══════════════════════════════════════════════════════════════════

1. IDENTIFICACIÓN DE PARTES

   ARRENDADOR:                      ARRENDATARIO:
   ─────────────────────────        ─────────────────────────
   PuertaSevilla Inmobiliaria SL    Juan García Martínez
   Avenida de Constitución 1        Calle Principal 42
   41001 Sevilla                    41001 Sevilla
   SIRET: 12345678901234            Teléfono: +34 654 123 456
                                    Email: juan.garcia@email.com


2. OBJETO DEL CONTRATO

   Descripción de la Propiedad:
   
   Piso de 3 dormitorios, 2 baños y salón
   Ubicado en zona céntrica de Sevilla
   Incluye calefacción y agua caliente


3. DURACIÓN

   Fecha de Inicio: 01 de enero de 2024
   Fecha de Fin: 31 de diciembre de 2026


4. RENTA MENSUAL

   Cantidad: 850,00 €
   IVA: 21%


5. CONDICIONES GENERALES

   OBLIGACIONES DEL ARRENDATARIO
   - Pagar la renta a tiempo
   - Mantener la propiedad en buen estado
   - Permitir inspecciones del arrendador

   OBLIGACIONES DEL ARRENDADOR
   - Garantizar que la propiedad sea habitable
   - Realizar las reparaciones necesarias
   - Proporcionar seguridad de tenencia


NOTAS ADICIONALES
   - Se realiza inspección mensual del estado de la propiedad
   - El pago debe realizarse entre los días 1 y 5 de cada mes
   - Los servicios de agua, electricidad y gas corren por cuenta del inquilino
   - Se requiere un depósito de garantía equivalente a 2 meses de renta
   - Prohibido subarrendar sin consentimiento expreso del arrendador


═══════════════════════════════════════════════════════════════════
                              FIRMAS

                                                  
ARRENDADOR: ________________________   ARRENDATARIO: ________________________

PuertaSevilla Inmobiliaria SL         Juan García Martínez


                                      Fecha: ________________


═══════════════════════════════════════════════════════════════════
```

## Personalización Adicional

### Cambiar Condiciones Generales

Si desea cambiar las condiciones, edite el archivo `pdf_puerta_sevilla.modules.php` y busque la sección:

```php
$conditions = array(
    $outputlangs->trans("TenantObligations"),
    "- " . $outputlangs->trans("PayRentOnTime"),
    "- " . $outputlangs->trans("MaintainProperty"),
    "- " . $outputlangs->trans("AllowInspections"),
    ...
);
```

### Agregar Campos Personalizados

Si su empresa tiene campos extra en los contratos o terceros, puede acceder a ellos mediante:

```php
// En $object->array_options['options_nombre_campo']
// En $object->thirdparty->array_options['options_nombre_campo']
```

## Solución de Problemas

### El template no aparece en la lista

1. Verificar que el archivo está en la ruta correcta
2. Limpiar caché de Dolibarr: 
   - Administración → Herramientas → Limpiar cache
3. Recargar la página

### El PDF sale en blanco o con errores

1. Verificar que el módulo de Contratos está habilitado
2. Verificar los logs de Dolibarr: `var/log/dolibarr.log`
3. Asegurarse de que el contrato tiene al menos una línea

### Caracteres especiales se ven mal

1. Verificar que el archivo está guardado en UTF-8
2. Limpiar caché de Dolibarr
3. Probar con otro navegador

## Support

Para reportar problemas o solicitar mejoras, contacte con el equipo de PuertaSevilla.
