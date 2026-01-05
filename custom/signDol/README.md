# DocSig - Document Signature Dolibarr

Módulo para firma digital de documentos PDF en Dolibarr con PAdES, sello de tiempo TSA RFC3161, doble autenticación y certificado de cumplimiento.

## Características

- **Firma digital PAdES** con sello de tiempo TSA RFC3161
- **Doble autenticación**: DNI/NIE + Email/Teléfono + código OTP
- **Firma manuscrita** capturada en canvas HTML5
- **Certificado de cumplimiento** con registro de auditoría completo
- **Notificaciones por email** con historial en fichas de contactos
- **Soporte multi-firmante** (paralelo o secuencial)
- **Arquitectura hexagonal** preparada para SMS/WhatsApp

## Requisitos

- Dolibarr 19.0 o superior
- PHP 8.1 o superior
- Extensiones PHP: openssl, curl, gd (opcional)
- MariaDB/MySQL

## Instalación

### 1. Copiar archivos

Copiar la carpeta `signDol` a `htdocs/custom/`:

```bash
cp -r signDol /ruta/dolibarr/htdocs/custom/
```

### 2. Permisos

Asegurar que el directorio de documentos tiene permisos de escritura:

```bash
chmod -R 755 /ruta/dolibarr/documents/docsig
```

### 3. Activar módulo

1. Ir a **Inicio > Configuración > Módulos/Aplicaciones**
2. Buscar "DocSig" en la categoría "Técnico"
3. Activar el módulo

### 4. Configurar

1. Ir a **DocSig > Configuración** o desde el menú del módulo
2. Configurar:
   - URL del servidor TSA (por defecto: https://freetsa.org/tsr)
   - Días de expiración de enlaces
   - Configuración de OTP
   - Notificaciones por tipo de objeto
   - Plantillas de email

### 5. Permisos de usuario

Asignar los permisos necesarios a los usuarios:

- **Leer solicitudes de firma**: Ver listado y detalle
- **Crear solicitudes de firma**: Crear nuevas solicitudes
- **Cancelar solicitudes de firma**: Cancelar solicitudes activas
- **Descargar documentos firmados**: Descargar PDFs firmados
- **Administrar configuración**: Acceso a la configuración

## Uso básico

### Solicitar firma

1. Desde cualquier listado de documentos (facturas, pedidos, presupuestos, contratos), hacer clic en el icono de firma
2. Seleccionar firmantes (contactos existentes o crear nuevos)
3. Configurar modo de firma (paralelo/secuencial)
4. Enviar solicitud

### Proceso de firma (firmante)

1. El firmante recibe un email con el enlace de firma
2. Accede al enlace e introduce su DNI/NIE
3. Verifica su identidad con email/teléfono + código OTP
4. Dibuja su firma manuscrita en el canvas
5. Confirma la firma

### Documentos generados

- **PDF firmado**: Documento original con firmas digitales PAdES y sello TSA
- **Certificado de cumplimiento**: PDF con evidencias de todo el proceso

## Estructura del módulo

```
signDol/
├── admin/           # Páginas de administración
│   ├── setup.php    # Configuración
│   └── about.php    # Acerca de
├── ajax/            # Endpoints AJAX
├── class/           # Clases PHP
├── core/
│   ├── modules/     # Descriptor del módulo
│   └── triggers/    # Triggers
├── css/             # Estilos CSS
├── js/              # JavaScript
├── langs/           # Traducciones
│   ├── es_ES/
│   └── en_US/
├── lib/             # Librerías auxiliares
├── public/          # Páginas públicas (firma)
├── sql/             # Scripts SQL
└── index.php        # Página principal
```

## Configuración TSA

### Servidores TSA gratuitos

- https://freetsa.org/tsr (predeterminado)
- http://timestamp.digicert.com
- http://timestamp.sectigo.com

### Servidores TSA con autenticación

Configurar usuario y contraseña en la página de configuración si el servidor lo requiere.

## Seguridad

- Tokens de firma: 64 caracteres aleatorios, hasheados en BD
- OTP: Códigos de 6 dígitos con expiración de 10 minutos
- Rate limiting: Protección contra ataques de fuerza bruta
- Certificado interno: Par de claves RSA 2048+ bits cifrado
- Registro de auditoría: Tabla append-only para trazabilidad

## API

El módulo expone endpoints para integración:

- `POST /public/api.php?action=start` - Iniciar proceso de firma
- `POST /public/api.php?action=send_otp` - Enviar código OTP
- `POST /public/api.php?action=verify_otp` - Verificar OTP
- `POST /public/api.php?action=submit_signature` - Enviar firma

## Licencia

GPL v3 o posterior

## Soporte

Para reportar bugs o solicitar funcionalidades, crear un issue en el repositorio.

---

**Versión**: 1.0.0  
**ID Módulo**: 60000010  
**Compatibilidad**: Dolibarr 19.0+
