# Guía de Instalación Rápida - Módulo PuertaSevilla

## 🚀 Instalación en 3 pasos

### 1. Verificar ubicación del módulo

El módulo debe estar en:
```
/var/www/html/dolpuerta/htdocs/custom/puertasevilla/
```

### 2. Activar el módulo

1. Acceder a Dolibarr como administrador
2. Ir a: **Inicio → Configuración → Módulos**
3. Buscar "**PuertaSevilla**" en la lista de módulos
4. Click en **Activar/Desactivar** (botón ON)

### 3. Verificar la instalación

Al activar el módulo, se crearán automáticamente:

✅ **Campos Extra (Extrafields):**
- Terceros: 5 campos (rol, id_origen, nacionalidad, forma_pago, autoinforme)
- Proyectos: 11 campos (vivienda, dirección, superficie, baños, dormitorios, etc.)
- Contratos: 4 campos (id_origen, día_pago, inventario, autofactura)
- Líneas de Contrato: 2 campos (ccc, entidad_ccc)
- Facturas: 2 campos (id_origen, tipo)
- Pedidos: 4 campos (id_origen, tipo_mantenimiento, horas, observaciones)

✅ **Diccionarios:**
- Tipos de Mantenimiento (6 valores)
- Categorías Contables (5 valores)
- Estados de Vivienda (4 valores)
- Formas de Pago (5 valores)

✅ **Trigger automático:**
- Generación de facturas plantilla al activar líneas de contrato

## 📋 Verificar que funciona

### Test 1: Verificar campos extra

1. Ir a **Terceros → Nuevo tercero**
2. Click en pestaña "**Campos Extra**"
3. Deberías ver: "Rol (Propietario/Inquilino/Administrador)"

### Test 2: Verificar diccionarios

1. Ir a **Inicio → Configuración → Diccionarios**
2. Buscar: "TipoMantenimiento", "EstadoVivienda", etc.

### Test 3: Probar generación de facturas

1. Crear un tercero (inquilino)
2. Crear un contrato asociado a ese tercero
3. En campos extra del contrato: poner "Día de Pago" = 5
4. Añadir una línea al contrato con precio 800€
5. **Activar la línea del contrato**
6. Ir a **Facturas → Facturas recurrentes/plantillas**
7. Deberías ver una nueva factura plantilla creada automáticamente

## ⚙️ Configuración opcional

Ir a: **Inicio → Configuración → Módulos → PuertaSevilla → Configuración**

Parámetros disponibles:
- Activar/desactivar generación automática de facturas
- ID de plantilla de factura por defecto

## 📚 Documentación completa

Ver [README.md](README.md) para documentación detallada de uso.

## 🐛 Solución de problemas

### El módulo no aparece en la lista
- Verificar permisos de archivos: `chmod -R 755 /var/www/html/dolpuerta/htdocs/custom/puertasevilla`
- Verificar propietario: `chown -R www-data:www-data /var/www/html/dolpuerta/htdocs/custom/puertasevilla`

### Los campos extra no se crean
- Desactivar y volver a activar el módulo
- Verificar logs en: Inicio → Configuración → Otro → Syslog

### La factura plantilla no se genera automáticamente
1. Verificar que el módulo está activado
2. Verificar que el contrato tiene un tercero asociado
3. Verificar que el trigger está activo en logs
4. Verificar que la línea del contrato tiene precio > 0

### Ver logs del módulo
```sql
SELECT * FROM llx_events 
WHERE type LIKE '%PuertaSevilla%' 
ORDER BY dateevent DESC 
LIMIT 20;
```

## 📞 Soporte

Para más ayuda:
- Revisar [README.md](README.md)
- Contactar: info@puertasevillainmobiliaria.online

---

✨ **¡Listo!** El módulo está instalado y funcionando.
