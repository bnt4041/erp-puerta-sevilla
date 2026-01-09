# Configuración del Webhook de GoWA

## 🔍 Diagnóstico: El webhook no está llegando

Si no se registra nada en los logs, significa que GoWA no está enviando las peticiones al webhook de Dolibarr.

---

## ✅ Paso 1: Verificar que el webhook es accesible

Abre en tu navegador:
```
https://tu-dominio.com/custom/whatsapp/public/webhook_test.php
```

Deberías ver una página con:
- ✅ "El webhook es accesible correctamente"
- La URL completa del webhook
- Instrucciones de configuración

Si **NO** ves esta página:
- ❌ Hay un problema con la ruta o permisos
- Verifica que el archivo existe en `/var/www/html/dolpuerta/custom/whatsapp/public/webhook_test.php`

---

## 🔧 Paso 2: Configurar el webhook en GoWA

### Opción A: Configuración via API de GoWA

Ejecuta este comando (reemplaza `TU_DOMINIO` y `TU_PUERTO_GOWA`):

```bash
curl -X POST http://localhost:3000/api/webhook \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://TU_DOMINIO.com/custom/whatsapp/public/webhook.php",
    "events": ["message"]
  }'
```

**Ejemplo real:**
```bash
curl -X POST http://localhost:3000/api/webhook \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://buildai.playhunt.es/custom/whatsapp/public/webhook.php",
    "events": ["message"]
  }'
```

### Opción B: Configuración via archivo de configuración

Si GoWA usa archivo de configuración, edita el archivo (normalmente `config.json` o `.env`):

```json
{
  "webhook": {
    "url": "https://TU_DOMINIO.com/custom/whatsapp/public/webhook.php",
    "events": ["message"]
  }
}
```

### Opción C: Configuración via interfaz web de GoWA

Si GoWA tiene interfaz web:
1. Accede a la interfaz de GoWA (normalmente `http://localhost:3000`)
2. Busca la sección "Webhook" o "Settings"
3. Introduce la URL: `https://TU_DOMINIO.com/custom/whatsapp/public/webhook.php`
4. Activa los eventos: `message`

---

## 🧪 Paso 3: Probar el webhook manualmente

### Prueba 1: Desde el navegador
```
https://tu-dominio.com/custom/whatsapp/public/webhook_test.php
```

### Prueba 2: Con curl (simular GoWA)
```bash
curl -X POST https://tu-dominio.com/custom/whatsapp/public/webhook.php \
  -H "Content-Type: application/json" \
  -d '{
    "type": "message",
    "payload": {
      "from": "34600123456@s.whatsapp.net",
      "fromMe": false,
      "text": "Mensaje de prueba",
      "pushName": "Test User",
      "type": "text"
    }
  }'
```

Si esta prueba funciona:
- ✅ El webhook de Dolibarr está OK
- ❌ El problema está en la configuración de GoWA

---

## 📋 Paso 4: Verificar logs de GoWA

Revisa los logs de GoWA para ver si hay errores:

```bash
# Si GoWA está en Docker
docker logs gowa-container

# Si GoWA está como servicio
journalctl -u gowa -f

# Si GoWA se ejecuta directamente
# Busca el archivo de log en el directorio de GoWA
tail -f /ruta/a/gowa/logs/gowa.log
```

**Busca errores como:**
- ❌ "Failed to send webhook"
- ❌ "Connection refused"
- ❌ "Timeout"
- ❌ "SSL certificate error"

---

## 🔍 Paso 5: Problemas comunes

### Problema 1: GoWA no puede acceder a la URL (localhost)
**Síntoma:** GoWA está en Docker y usa `localhost` o `127.0.0.1`

**Solución:** Usa la IP del host o el nombre del dominio público
```bash
# En lugar de:
http://localhost/custom/whatsapp/public/webhook.php

# Usa:
https://tu-dominio.com/custom/whatsapp/public/webhook.php
# O la IP del servidor:
http://192.168.1.100/custom/whatsapp/public/webhook.php
```

### Problema 2: Error SSL/HTTPS
**Síntoma:** "SSL certificate error" en logs de GoWA

**Solución temporal:** Usa HTTP en lugar de HTTPS (solo para pruebas)
```
http://tu-dominio.com/custom/whatsapp/public/webhook.php
```

**Solución definitiva:** Configura un certificado SSL válido

### Problema 3: Firewall bloqueando
**Síntoma:** "Connection timeout" o "Connection refused"

**Solución:** Verifica que el puerto 80/443 está abierto
```bash
# Verificar firewall
sudo ufw status

# Abrir puerto si es necesario
sudo ufw allow 80
sudo ufw allow 443
```

### Problema 4: GoWA no está configurado para enviar webhooks
**Síntoma:** No hay errores pero tampoco llegan webhooks

**Solución:** Verifica la configuración de GoWA
```bash
# Consultar configuración actual del webhook
curl http://localhost:3000/api/webhook
```

---

## ✅ Paso 6: Verificar que funciona

1. **Configura el webhook en GoWA** (Paso 2)
2. **Envía un WhatsApp** al número conectado
3. **Verifica los logs:**
   - `https://tu-dominio.com/custom/whatsapp/scripts/view_webhook_logs.php`
   - Deberías ver: `========== WhatsApp Webhook START ==========`

---

## 📞 Información adicional

### URL del webhook de producción:
```
https://TU_DOMINIO.com/custom/whatsapp/public/webhook.php
```

### URL del webhook de prueba:
```
https://TU_DOMINIO.com/custom/whatsapp/public/webhook_test.php
```

### Eventos a configurar en GoWA:
- `message` - Para recibir todos los mensajes

### Formato esperado del payload:
```json
{
  "type": "message",
  "payload": {
    "from": "34600123456@s.whatsapp.net",
    "fromMe": false,
    "text": "Texto del mensaje",
    "pushName": "Nombre del contacto",
    "type": "text"
  }
}
```

---

## 🆘 ¿Necesitas ayuda?

Si después de seguir estos pasos el webhook sigue sin funcionar:

1. Ejecuta el test: `webhook_test.php`
2. Revisa los logs de GoWA
3. Prueba con curl manualmente
4. Verifica la configuración de red/firewall
