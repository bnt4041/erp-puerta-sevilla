#!/bin/bash

# Script para diagnosticar y configurar el webhook de GoWA
# Ejecutar: bash /var/www/html/dolpuerta/custom/whatsapp/scripts/setup_gowa_webhook.sh

echo "=========================================="
echo "🔍 Diagnóstico de GoWA Webhook"
echo "=========================================="
echo ""

# Detectar el dominio actual
DOMAIN=$(hostname -f 2>/dev/null || echo "localhost")
if [ "$DOMAIN" = "localhost" ]; then
    # Intentar obtener de Apache
    DOMAIN=$(grep -r "ServerName" /etc/apache2/sites-enabled/ 2>/dev/null | head -1 | awk '{print $2}')
fi

if [ -z "$DOMAIN" ] || [ "$DOMAIN" = "localhost" ]; then
    echo "⚠️  No se pudo detectar el dominio automáticamente"
    echo "Por favor, introduce tu dominio (ejemplo: buildai.playhunt.es):"
    read DOMAIN
fi

WEBHOOK_URL="https://$DOMAIN/custom/whatsapp/public/webhook.php"

echo "📍 Dominio detectado: $DOMAIN"
echo "📍 URL del webhook: $WEBHOOK_URL"
echo ""

# Verificar si GoWA está corriendo
echo "1️⃣  Verificando si GoWA está corriendo..."
echo ""

GOWA_RUNNING=false

# Verificar Docker
if command -v docker &> /dev/null; then
    GOWA_CONTAINER=$(docker ps --filter "name=gowa" --format "{{.Names}}" 2>/dev/null | head -1)
    if [ ! -z "$GOWA_CONTAINER" ]; then
        echo "✅ GoWA encontrado en Docker: $GOWA_CONTAINER"
        GOWA_RUNNING=true
        GOWA_TYPE="docker"
    fi
fi

# Verificar proceso
if [ "$GOWA_RUNNING" = false ]; then
    if pgrep -f "gowa" > /dev/null; then
        echo "✅ GoWA encontrado como proceso"
        GOWA_RUNNING=true
        GOWA_TYPE="process"
    fi
fi

# Verificar systemd
if [ "$GOWA_RUNNING" = false ]; then
    if systemctl is-active --quiet gowa 2>/dev/null; then
        echo "✅ GoWA encontrado como servicio systemd"
        GOWA_RUNNING=true
        GOWA_TYPE="systemd"
    fi
fi

if [ "$GOWA_RUNNING" = false ]; then
    echo "❌ GoWA no está corriendo"
    echo ""
    echo "Por favor, inicia GoWA primero:"
    echo "  - Si es Docker: docker start gowa"
    echo "  - Si es systemd: sudo systemctl start gowa"
    echo "  - Si es manual: cd /ruta/gowa && ./gowa"
    exit 1
fi

echo ""

# Detectar puerto de GoWA
echo "2️⃣  Detectando puerto de GoWA..."
echo ""

GOWA_PORT=""

# Intentar puertos comunes
for port in 3000 8080 5000 9000; do
    if curl -s http://localhost:$port/health > /dev/null 2>&1 || \
       curl -s http://localhost:$port/api > /dev/null 2>&1 || \
       curl -s http://localhost:$port > /dev/null 2>&1; then
        GOWA_PORT=$port
        echo "✅ GoWA respondiendo en puerto: $port"
        break
    fi
done

if [ -z "$GOWA_PORT" ]; then
    echo "⚠️  No se pudo detectar el puerto automáticamente"
    echo "Por favor, introduce el puerto de GoWA (normalmente 3000):"
    read GOWA_PORT
fi

GOWA_API="http://localhost:$GOWA_PORT"

echo ""

# Verificar configuración actual del webhook
echo "3️⃣  Verificando configuración actual del webhook..."
echo ""

CURRENT_WEBHOOK=$(curl -s $GOWA_API/api/webhook 2>/dev/null)

if [ ! -z "$CURRENT_WEBHOOK" ]; then
    echo "📋 Configuración actual:"
    echo "$CURRENT_WEBHOOK" | jq . 2>/dev/null || echo "$CURRENT_WEBHOOK"
else
    echo "⚠️  No se pudo obtener la configuración actual"
fi

echo ""

# Configurar webhook
echo "4️⃣  Configurando webhook en GoWA..."
echo ""

echo "URL a configurar: $WEBHOOK_URL"
echo ""
echo "¿Deseas configurar el webhook ahora? (s/n):"
read CONFIRM

if [ "$CONFIRM" = "s" ] || [ "$CONFIRM" = "S" ]; then
    
    # Intentar configurar via API
    RESPONSE=$(curl -s -X POST $GOWA_API/api/webhook \
        -H "Content-Type: application/json" \
        -d "{\"url\": \"$WEBHOOK_URL\", \"events\": [\"message\"]}" 2>&1)
    
    if [ $? -eq 0 ]; then
        echo "✅ Webhook configurado correctamente"
        echo "Respuesta: $RESPONSE"
    else
        echo "❌ Error al configurar webhook via API"
        echo ""
        echo "Configuración manual necesaria:"
        echo ""
        echo "Opción 1 - Via curl:"
        echo "curl -X POST $GOWA_API/api/webhook \\"
        echo "  -H \"Content-Type: application/json\" \\"
        echo "  -d '{\"url\": \"$WEBHOOK_URL\", \"events\": [\"message\"]}'"
        echo ""
        echo "Opción 2 - Editar archivo de configuración de GoWA"
        echo "Busca el archivo config.json o .env y añade:"
        echo "{"
        echo "  \"webhook\": {"
        echo "    \"url\": \"$WEBHOOK_URL\","
        echo "    \"events\": [\"message\"]"
        echo "  }"
        echo "}"
    fi
else
    echo "⏭️  Configuración omitida"
    echo ""
    echo "Para configurar manualmente, ejecuta:"
    echo "curl -X POST $GOWA_API/api/webhook \\"
    echo "  -H \"Content-Type: application/json\" \\"
    echo "  -d '{\"url\": \"$WEBHOOK_URL\", \"events\": [\"message\"]}'"
fi

echo ""

# Probar webhook
echo "5️⃣  Probando webhook..."
echo ""

TEST_RESPONSE=$(curl -s -X POST $WEBHOOK_URL \
    -H "Content-Type: application/json" \
    -d '{
        "type": "message",
        "payload": {
            "from": "34600123456@s.whatsapp.net",
            "fromMe": false,
            "text": "Test desde script de configuración",
            "pushName": "Script Test",
            "type": "text"
        }
    }')

if [ $? -eq 0 ]; then
    echo "✅ Webhook de Dolibarr responde correctamente"
    echo "Respuesta: $TEST_RESPONSE"
else
    echo "❌ Error al probar webhook"
fi

echo ""

# Ver logs de GoWA
echo "6️⃣  Logs de GoWA (últimas 20 líneas):"
echo ""

if [ "$GOWA_TYPE" = "docker" ]; then
    docker logs --tail 20 $GOWA_CONTAINER
elif [ "$GOWA_TYPE" = "systemd" ]; then
    journalctl -u gowa -n 20 --no-pager
else
    echo "⚠️  No se pueden mostrar logs automáticamente"
    echo "Revisa manualmente los logs de GoWA"
fi

echo ""
echo "=========================================="
echo "✅ Diagnóstico completado"
echo "=========================================="
echo ""
echo "📋 Resumen:"
echo "  - GoWA: $GOWA_TYPE en puerto $GOWA_PORT"
echo "  - Webhook URL: $WEBHOOK_URL"
echo ""
echo "🔍 Próximos pasos:"
echo "  1. Envía un WhatsApp al número conectado"
echo "  2. Verifica los logs: https://$DOMAIN/custom/whatsapp/scripts/view_webhook_logs.php"
echo "  3. Si no aparece nada, revisa los logs de GoWA arriba"
echo ""
