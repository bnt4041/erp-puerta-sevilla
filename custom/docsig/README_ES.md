# Módulo Docsig - Firma de Documentos

## Descripción General

Docsig es un módulo completo de firma de documentos para Dolibarr ERP/CRM que proporciona:

- **Sobres multi-firmante** (firma paralela o secuencial)
- **Doble autenticación** (NIF/CIF/NIE + OTP por Email)
- **Captura de firma manuscrita** mediante canvas
- **Firma PDF compatible con PAdES**
- **Soporte de sello de tiempo TSA RFC3161**
- **Registro de auditoría inmutable** (tipo blockchain)
- **Generación de certificados de cumplimiento**
- **Seguimiento de notificaciones** por contacto
- **Limitación de intentos** y funciones de seguridad

## Instalación

### 1. Copiar Archivos del Módulo

```bash
# Copiar módulo al directorio custom de Dolibarr
cp -r docsig /ruta/a/dolibarr/htdocs/custom/

# Establecer permisos adecuados
chown -R www-data:www-data /ruta/a/dolibarr/htdocs/custom/docsig
chmod -R 755 /ruta/a/dolibarr/htdocs/custom/docsig
```

### 2. Activar Módulo

1. Inicia sesión en Dolibarr como administrador
2. Ve a **Inicio → Configuración → Módulos/Aplicaciones**
3. Busca "Docsig"
4. Haz clic en **Activar**

El módulo automáticamente:
- Crea las tablas de la base de datos
- Genera el certificado RSA del sistema
- Configura los directorios necesarios
- Utiliza el campo nativo tva_intra de contactos para NIF/CIF/NIE

### 3. Configurar Módulo

Ve a **Docsig → Configuración** y configura:

#### Configuración General
- **Modo de Firma**: paralelo (por defecto) u ordenado
- **Días de Expiración**: 30 (por defecto)
- **Minutos de Expiración OTP**: 10 (por defecto)
- **Intentos Máximos OTP**: 5 (por defecto)

#### Configuración TSA (Autoridad de Sellado de Tiempo)
- **Habilitar TSA**: Sí/No
- **URL TSA**: ej., `http://timestamp.digicert.com`
- **Usuario TSA**: (si es necesario)
- **Contraseña TSA**: (si es necesario)
- **OID de Política TSA**: (opcional)

Servidores TSA gratuitos populares:
- DigiCert: `http://timestamp.digicert.com`
- Sectigo: `http://timestamp.sectigo.com`
- FreeTSA: `https://freetsa.org/tsr`

#### Visualización de Firma
- **Habilitar Firma Visible**: Sí/No
- **Posición por Defecto**: inferior-izquierda, inferior-derecha, superior-izquierda, superior-derecha, centro

### 4. Requisitos del Sistema

**Extensiones PHP Requeridas:**
```bash
- openssl (para generación de certificados y firma de PDF)
- gd o imagick (para procesamiento de imágenes de firma)
- curl (para peticiones TSA)
```

**Configuración PHP:**
```ini
memory_limit = 256M
max_execution_time = 300
upload_max_filesize = 50M
post_max_size = 50M
```

**Base de Datos:**
- MariaDB 10.3+ o MySQL 5.7+

### 5. Verificar Instalación

Comprueba que:
1. Todas las tablas SQL están creadas (revisar Herramientas de Base de Datos)
2. El certificado del sistema está generado (Configuración → Certificado del Sistema)
3. Los permisos están configurados correctamente (página de Permisos)
4. Los hooks funcionan (probar viendo una factura)

## Uso

### Crear una Solicitud de Firma

#### Desde Lista de Documentos
1. Abre cualquier lista de documentos (facturas, pedidos, contratos, etc.)
2. Haz clic en el **icono de firma** (🖊️) junto al documento
3. Se abre un modal con dos opciones:
   - **Crear nuevo sobre** (si no existe ninguno)
   - **Ver sobre existente** (si ya se solicitó)

#### Desde Ficha de Documento
1. Abre un documento (factura, pedido, contrato, etc.)
2. Busca la sección **Firmas**
3. Haz clic en "**Solicitar firma**"

#### Opciones de Configuración
- **Documento**: Selecciona el PDF a firmar
- **Modo de Firma**:
  - **Paralelo**: Todos los firmantes pueden firmar simultáneamente
  - **Ordenado**: Firma secuencial (el 1º debe firmar antes que el 2º, etc.)
- **Expiración**: Días hasta que expire la solicitud (por defecto 30)
- **Mensaje Personalizado**: Mensaje opcional para los firmantes
- **Firmantes**: Añade uno o más firmantes
  - Buscar contactos existentes
  - Crear nuevo contacto en línea (AJAX)
  - El sistema usa automáticamente el campo tva_intra del contacto como NIF/CIF/NIE (recomendado rellenarlo)

### Experiencia del Firmante (Página Pública)

#### Paso 1: Verificación de Identidad
1. El firmante recibe un email con el enlace
2. Abre el enlace (no requiere inicio de sesión)
3. Introduce:
   - **NIF/CIF/NIE** (debe coincidir con el tva_intra del contacto si está configurado)
   - **Email** (debe coincidir con el email registrado)
4. El sistema valida y envía el código OTP

#### Paso 2: Verificación OTP
1. El firmante recibe un código de 6 dígitos por email
2. Introduce el código (expira en 10 min, máximo 5 intentos)
3. El sistema valida el código

#### Paso 3: Firmar Documento
1. El firmante dibuja su firma en el canvas
2. Revisa la información del documento
3. Marca la casilla de acuerdo
4. Envía la firma

#### Paso 4: Completar
1. Se muestra mensaje de confirmación
2. Si todos los firmantes completan: descargar documento firmado
3. Si hay otros pendientes: notificación enviada cuando se complete

### Gestionar Sobres

#### Ver Estado del Sobre
- Haz clic en el icono de firma en el documento
- Ver modal mostrando:
  - Referencia y estado del sobre
  - Lista de firmantes con estado individual
  - Enlaces de firma (copiables)
  - Opciones de descarga (cuando estén disponibles)

#### Cancelar Sobre
1. Abre el modal del sobre
2. Haz clic en "**Cancelar Sobre**"
3. Introduce la razón de cancelación
4. Todos los enlaces de firma se invalidan

#### Descargar Documentos
- **PDF Firmado**: Disponible cuando todos (o subconjunto configurado) firmen
- **Certificado de Cumplimiento**: Auto-generado al completarse, guardado en la misma carpeta que el PDF firmado (nombre: `documento_signed_certificate.pdf`)
- Documentos almacenados en `/documents/docsig/` o en la carpeta original del documento

### Registro de Auditoría

Accede mediante **Docsig → Registro de Auditoría**

Ver registro inmutable de todos los eventos:
- Sobre creado/enviado/cancelado
- Enlace abierto
- OTP enviado/validado/fallido
- Firma completada
- Documento firmado
- Certificado generado

Cada evento incluye:
- Marca de tiempo
- Dirección IP
- Navegador (user agent)
- Datos del evento (JSON)
- Hash criptográfico (integridad tipo blockchain)

### Notificaciones

Todas las notificaciones se registran en la base de datos y son visibles en las fichas de contactos.

#### Tipos de Notificación
- **Solicitud**: Solicitud de firma enviada
- **OTP**: Código de verificación enviado
- **Recordatorio**: Recordatorio enviado (manual)
- **Completado**: Todas las firmas recopiladas
- **Cancelado**: Sobre cancelado

#### Ver Notificaciones de Contacto
1. Abre la ficha del contacto
2. Ve a la pestaña **Firmas**
3. Ver todas las notificaciones enviadas a este contacto
   - Asunto y cuerpo visibles
   - Fecha/hora de envío
   - Sobre asociado

## Arquitectura Técnica

### Tablas de Base de Datos

- `llx_docsig_envelope`: Sobres de firma
- `llx_docsig_signature`: Firmas individuales
- `llx_docsig_audit_trail`: Registro de auditoría inmutable
- `llx_docsig_notification`: Notificaciones por email enviadas
- `llx_docsig_certificate`: Certificados de cumplimiento
- `llx_docsig_key`: Certificados/claves del sistema
- `llx_docsig_rate_limit`: Registros de limitación de intentos

### Clases

- **DocsigEnvelope**: Gestión principal de sobres
- **DocsigSignature**: Manejo de firmas individuales
- **DocsigAuditTrail**: Registro de auditoría
- **DocsigNotification**: Gestión de notificaciones
- **DocsigPDFSigner**: Motor de firma PDF (PAdES + TSA)
- **DocsigCertificate**: Generación de certificados de cumplimiento

### Hooks

El módulo se integra mediante hooks:
- `printFieldListOption`: Añade cabecera de columna en listas
- `printFieldListValue`: Añade botón de firma a filas
- `formObjectOptions`: Añade sección de firma a fichas
- `printTabsHead`: Añade pestaña de firmas a contactos
- `addHtmlHeader`: Incluye CSS y JS

Contextos soportados:
- invoicelist, invoicecard
- orderlist, ordercard
- contractlist, contractcard
- propallist, propalcard
- supplierinvoicelist, supplierinvoicecard
- contactlist, contactcard

### Características de Seguridad

#### Seguridad de Tokens
- Tokens aleatorios de 64+ caracteres (configurable)
- Hasheados en base de datos (SHA-256)
- Un solo uso o con expiración
- No expuestos en logs

#### Limitación de Intentos
- OTP: Máximo 10 peticiones por hora por email/IP
- Intentos de firma: Configurable
- Auto-bloqueo al alcanzar el umbral

#### Autenticación
- Doble factor: DNI + Email
- OTP con expiración temporal
- Limitación de intentos
- Validación de sesión

#### Integridad del PDF
- Hash SHA-256 del original
- Hash SHA-256 de la versión firmada
- Cualquier modificación invalida la firma
- El sello de tiempo TSA vincula la fecha

#### Integridad del Registro de Auditoría
- Cadena de hash tipo blockchain
- Cada evento hashea el evento anterior
- Inmutable (tabla solo-añadir)
- Verificación de integridad comprobable

#### Protección de Datos
- Claves privadas cifradas (AES-256-GCM)
- Almacenadas con IV y etiqueta de autenticación
- Clave de cifrado derivada del ID de instancia de Dolibarr
- Datos sensibles sanitizados en entrada

## API / Endpoints AJAX

### envelope.php

**create_envelope**
```json
POST /custom/docsig/ajax/envelope.php?action=create_envelope
{
  "element_type": "invoice",
  "element_id": 123,
  "document_path": "facture/FA2401-0001/FA2401-0001.pdf",
  "document_name": "Factura FA2401-0001.pdf",
  "signature_mode": "parallel",
  "expiration_days": 30,
  "custom_message": "Por favor firme",
  "signers": [
    {
      "id": 1,
      "name": "Juan Pérez",
      "email": "juan@ejemplo.com",
      "dni": "12345678A"
    }
  ]
}
```

**get_envelope_status**
```
GET /custom/docsig/ajax/envelope.php?action=get_envelope_status&envelope_id=1
```

**cancel_envelope**
```
POST /custom/docsig/ajax/envelope.php?action=cancel_envelope&envelope_id=1&reason=Ya+no+es+necesario
```

**create_contact**
```
POST /custom/docsig/ajax/envelope.php?action=create_contact
{
  "name": "Pérez",
  "firstname": "Juan",
  "email": "juan@ejemplo.com",
  "dni": "12345678A",
  "socid": 123
}
```

## Cumplimiento Normativo y Legal

### Estándares de Firma Electrónica

Este módulo implementa:
- **PAdES-BES**: PDF Advanced Electronic Signature - Basic
- **RFC 3161**: Protocolo de Sellado de Tiempo (TSP)
- Compatible con **eIDAS** (regulación UE)

### Certificado de Cumplimiento

PDF auto-generado conteniendo:
- Referencia del sobre
- Hash del documento original
- Hash del documento firmado
- Lista de todos los firmantes con marcas de tiempo
- Detalles del sello de tiempo TSA
- Firma del sistema
- Registro de auditoría completo

**Ubicación**: El certificado se guarda automáticamente en la misma carpeta que el PDF firmado, con el nombre `{nombre_documento}_signed_certificate.pdf`

**Ejemplo**: Si el PDF firmado es `/documents/facture/FA2401-0001/FA2401-0001_signed.pdf`, el certificado será `/documents/facture/FA2401-0001/FA2401-0001_signed_certificate.pdf`

### Validez Legal

Las firmas electrónicas son legalmente vinculantes en:
- **UE**: Reglamento eIDAS (UE) nº 910/2014
- **USA**: ESIGN Act, UETA
- **Internacional**: Ley Modelo UNCITRAL

Requisitos cumplidos:
- Verificación de identidad (doble autenticación)
- Intención de firmar (casilla explícita)
- Protección de integridad (hash + TSA)
- No repudio (registro de auditoría)

## Solución de Problemas

### El Módulo No se Activa
- Verifica que el usuario de base de datos tiene permiso CREATE TABLE
- Verifica que todos los archivos SQL están presentes
- Revisa el log de errores de PHP

### Los Emails No se Envían
- Verifica la configuración SMTP en Dolibarr
- Comprueba que `MAIN_MAIL_EMAIL_FROM` está configurado
- Prueba el email desde Herramientas → Prueba de email

### El Sello de Tiempo TSA Falla
- Verifica que la URL TSA es accesible
- Comprueba configuración de firewall/proxy
- Prueba con proveedor TSA alternativo
- Desactiva TSA temporalmente para pruebas

### Los Enlaces de Firma No Funcionan
- Comprueba que Apache/Nginx permite acceso a `/custom/docsig/public/`
- Verifica que no hay .htaccess bloqueando
- Comprueba que el token es válido y no ha expirado

### Falla la Firma del PDF
- Verifica que la extensión PHP openssl está cargada
- Comprueba que memory_limit es suficiente (256M+)
- Asegura que el directorio de documentos es escribible
- Comprueba que existe el certificado (Configuración → Certificado del Sistema)

### Limitación de Intentos Bloqueando Usuarios
- Ajusta `DOCSIG_RATE_LIMIT_MAX` más alto
- Limpia la tabla de rate limit manualmente
- Comprueba actividad maliciosa

### El Canvas de Firma No Funciona
- Verifica que JavaScript está habilitado
- Comprueba la consola del navegador
- Prueba en navegador diferente
- Verifica que Signature Pad se carga correctamente

### Certificado del Sistema No se Genera
- Verifica extensión PHP openssl
- Comprueba permisos de escritura en directorio de documentos
- Revisa logs de PHP para errores OpenSSL
- Genera manualmente desde configuración del módulo

## Soporte y Desarrollo

### Información del Módulo
- **Versión**: 1.0.0
- **Autor**: Equipo Docsig
- **Licencia**: GPL v3+
- **Dolibarr**: 15.0+
- **PHP**: 8.1+

### Código Fuente
Ubicado en `/htdocs/custom/docsig/`

### Logs
- Log de Dolibarr: Revisar syslog
- Errores Apache/PHP: `/var/log/apache2/error.log`
- Registro de auditoría: Tabla de base de datos `llx_docsig_audit_trail`

### Mejoras Futuras
- [ ] Opciones de firma biométrica
- [ ] Alternativa OTP por SMS
- [ ] Solicitudes de firma masivas
- [ ] Certificado avanzado (PAdES-LT con OCSP/CRL)
- [ ] Integración con proveedores de firma externos
- [ ] Soporte de app móvil
- [ ] Plantillas de firma
- [ ] Notificaciones por webhook
- [ ] Recordatorios automáticos
- [ ] Firma en cadena (workflows)

## Preguntas Frecuentes

### ¿Puedo usar múltiples certificados?
Actualmente el sistema usa un certificado por entidad. Para múltiples certificados, se recomienda usar entidades separadas de Dolibarr.

### ¿Es obligatorio el NIF/CIF/NIE (tva_intra)?
No es obligatorio, pero se recomienda encarecidamente rellenar el campo tva_intra en los contactos para mayor seguridad y validez legal de las firmas.

### ¿Cuántos firmantes puedo añadir?
Sin límite técnico, pero se recomienda máximo 20 firmantes por sobre para mantener la usabilidad.

### ¿Puedo personalizar el email?
Actualmente el email usa plantilla predeterminada. La personalización completa se añadirá en futuras versiones.

### ¿Funciona en móviles?
Sí, la interfaz de firma pública es completamente responsive y funciona en tablets y móviles.

### ¿Qué pasa si expira el sobre?
Los enlaces de firma se invalidan automáticamente. Debes crear una nueva solicitud de firma.

### ¿Puedo reenviar la solicitud?
Sí, desde el modal del sobre puedes copiar y reenviar los enlaces manualmente. La función de reenvío automático está planificada.

### ¿Los documentos se almacenan de forma segura?
Sí, todos los documentos se almacenan en el directorio estándar de Dolibarr con permisos adecuados y hashes de integridad.

## Cumplimiento RGPD

El módulo procesa datos personales (nombre, email, DNI, firma manuscrita). Asegúrate de:

- Informar a los usuarios sobre el procesamiento de datos
- Obtener consentimiento explícito para el tratamiento
- Proporcionar acceso a los datos almacenados
- Implementar derecho al olvido (eliminar datos cuando se solicite)
- Mantener registros de procesamiento de datos
- Designar DPO si es necesario
- Realizar evaluaciones de impacto para datos sensibles

## Licencia

Este módulo se distribuye bajo licencia **GPL v3+**.

```
Docsig - Document Signature Module for Dolibarr
Copyright (C) 2026 Docsig Team

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

---

**Nota Importante**: Este módulo maneja operaciones sensibles y documentos legales. Siempre prueba exhaustivamente en un entorno de desarrollo antes del uso en producción. Asegura el cumplimiento con las regulaciones locales respecto a firmas electrónicas.

## Créditos

- **TCPDF**: Generación y manipulación de PDF
- **Signature Pad**: Biblioteca de captura de firma manuscrita
- **Dolibarr**: Framework base ERP/CRM
- **OpenSSL**: Criptografía y generación de certificados

## Contacto

Para soporte, reportes de bugs o solicitudes de características:
- Crea un issue en el repositorio
- Contacta con el equipo de desarrollo
- Revisa la documentación completa

---

**¡Comienza a firmar documentos de forma segura y legal con Docsig!** 🚀
