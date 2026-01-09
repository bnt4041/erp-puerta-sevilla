# Ejemplos Prácticos - Sistema de Notificaciones

## Ejemplo 1: Configurar Notificación de Nuevo Usuario

Cuando se registra un nuevo empleado, se envía un email de bienvenida.

### Pasos:

1. **Ir a**: Administración → Notificaciones y eventos de email
2. **Buscar**: ZONAEMPLEADO_USER_REGISTRATION
3. **Configurar**:
   - Destinatario: Todos los usuarios
   - Seleccionar/crear plantilla de bienvenida
   - Guardar

### Resultado:
Cuando se crea un nuevo usuario, todos reciben una notificación de bienvenida con la información del nuevo empleado.

---

## Ejemplo 2: Notificación de Solicitud de Vacaciones

Notificar a gerentes cuando un empleado solicita vacaciones.

### Pasos:

1. **Ir a**: Administración → Notificaciones y eventos de email
2. **Buscar**: ZONAEMPLEADO_HOLIDAY_REQUEST_SUBMITTED
3. **Configurar**:
   - Destinatario: Usuarios con rol de "Gerente"
   - Plantilla: Incluir detalles del solicitante y fechas
   - Guardar

### Resultado:
Los gerentes reciben email cuando un empleado solicita vacaciones.

---

## Ejemplo 3: Notificación de Publicación de Nómina

Informar a empleados cuando sus nóminas están disponibles.

### Pasos:

1. **Ir a**: Administración → Notificaciones y eventos de email
2. **Buscar**: ZONAEMPLEADO_PAYSLIP_PUBLISHED
3. **Configurar**:
   - Destinatario: Todos los usuarios
   - Plantilla: Incluir enlace a descargar nómina
   - Guardar

### Resultado:
Los empleados reciben notificación cuando se publica una nueva nómina.

---

## Ejemplo 4: Eventos de Anuncios Importantes

Mantener a todos informados sobre anuncios corporativos.

### Pasos:

1. **Configurar dos eventos**:
   - ZONAEMPLEADO_ANNOUNCEMENT_CREATED
   - ZONAEMPLEADO_ANNOUNCEMENT_UPDATED

2. **Para cada evento**:
   - Destinatario: Todos los usuarios
   - Incluir título y contenido del anuncio
   - Guardar

### Resultado:
Los empleados son notificados automáticamente de anuncios nuevos y actualizaciones.

---

## Ejemplo 5: Auditoría de Accesos

Registrar todos los accesos a la Zona de Empleado.

### Pasos:

1. **Configurar**:
   - ZONAEMPLEADO_USER_LOGIN
   - ZONAEMPLEADO_USER_LOGOUT

2. **Destinatario**: Administrador del sistema

3. **Frecuencia**: Todas las veces (sin filtros)

### Resultado:
El administrador recibe emails de todos los accesos y salidas del sistema.

---

## Ejemplo 6: Integración con Módulo de Mensaje

Cuando se recibe un mensaje importante.

### Pasos:

1. **Buscar**: ZONAEMPLEADO_MESSAGE_RECEIVED
2. **Configurar**:
   - Destinatario: Destinatario del mensaje
   - Plantilla: Mostrar remitente y asunto
   - Guardar

### Resultado:
Los empleados reciben notificación de nuevos mensajes importantes.

---

## Plantilla de Email Personalizada - Ejemplo

Aquí hay un ejemplo de plantilla HTML personalizada para notificaciones:

```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background-color: #2c3e50; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #ecf0f1; }
        .footer { text-align: center; padding: 10px; font-size: 12px; color: #7f8c8d; }
        .button { background-color: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Zona de Empleado</h1>
        </div>
        <div class="content">
            <p>Estimado {firstname} {lastname},</p>
            
            <p>Le notificamos que ha ocurrido el siguiente evento:</p>
            
            <h2>{event_label}</h2>
            <p>{event_description}</p>
            
            <p><strong>Detalles:</strong></p>
            <ul>
                <li><strong>Fecha:</strong> {date}</li>
                <li><strong>Usuario:</strong> {user}</li>
                <li><strong>Descripción:</strong> {details}</li>
            </ul>
            
            <p>
                <a href="{server_url}/zonaempleado/" class="button">
                    Acceder a Zona de Empleado
                </a>
            </p>
            
            <p>Si tiene alguna pregunta, contacte al departamento de RRHH.</p>
        </div>
        <div class="footer">
            <p>&copy; 2025 Zona Empleado. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
```

---

## Filtros Avanzados

### Notificaciones por Departamento

Si tu instalación de Dolibarr permite, puedes filtrar notificaciones por:
- Departamento
- Ubicación
- Rol/Puesto
- Grupo de usuarios

Ejemplo en la interfaz de notificaciones:
```
Evento: ZONAEMPLEADO_HOLIDAY_REQUEST_SUBMITTED
Destinatarios: Usuarios en grupo "Gerentes"
Solo si: El solicitante pertenece al grupo "Empleados"
```

### Horarios de Notificación

Algunas instalaciones permiten establecer horarios para notificaciones:
- Enviar notificaciones solo en horario laboral
- Resumen diario a horas específicas
- Reportes semanales

---

## Integración con Otros Módulos

### Con Módulo de Email (CMailFile)

Las notificaciones pueden incluir adjuntos:
- PDFs de nóminas
- Documentos compartidos
- Reportes

### Con Módulo de Historial

Cada notificación enviada se registra en:
- Tabla `llx_notify` (base de datos)
- Logs del sistema (archivos)

---

## Variables Disponibles en Plantillas

Según el tipo de evento:

### Para eventos de usuario:
- `{user}` - Nombre del usuario
- `{firstname}` - Nombre de pila
- `{lastname}` - Apellido
- `{email}` - Email del usuario
- `{login}` - Login

### Para eventos generales:
- `{date}` - Fecha del evento
- `{time}` - Hora del evento
- `{server_url}` - URL base del servidor
- `{event_label}` - Etiqueta del evento
- `{event_description}` - Descripción del evento

---

## Resolución de Problemas

### ¿Por qué no recibo notificaciones?

1. Verifica que tu usuario tiene email configurado
2. Revisa que el módulo de notificaciones está habilitado
3. Comprueba que el evento está configurado
4. Revisa los logs en Administración → Módulos → Logs del sistema

### ¿Cómo puedo desactivar notificaciones específicas?

En la interfaz de Notificaciones:
1. Busca el evento
2. En "Destinatarios", selecciona "Nadie"
3. O simplemente no configures el evento

### ¿Puedo enviar notificaciones sin email?

No. Actualmente ZonaEmpleado usa email como canal de notificación. 

Futuras mejoras podrían incluir:
- Notificaciones en la plataforma (panel)
- SMS
- Notificaciones push

---

## Mejores Prácticas

1. **Configurar con cuidado**: No enviar demasiadas notificaciones para evitar spam
2. **Personalizar plantillas**: Usar plantillas profesionales y claras
3. **Usar filtros**: Especificar destinatarios exactos
4. **Revisar logs**: Verificar que las notificaciones se envían correctamente
5. **Documentar cambios**: Mantener registro de qué eventos están activos

