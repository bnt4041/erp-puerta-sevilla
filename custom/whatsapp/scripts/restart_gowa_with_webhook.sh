#!/bin/bash
# Script para reiniciar GoWA con el Webhook configurado
# Generado por Antigravity

WEBHOOK_URL="https://buildai.playhunt.es/dolpuerta/custom/whatsapp/public/webhook.php"
BASE_DIR="/var/www/html/dolpuerta/custom/whatsapp"

echo "Deteniendo procesos GoWA actuales..."
pkill -f "gowa rest"
sleep 2

echo "Iniciando GoWA con Webhook: $WEBHOOK_URL"
cd $BASE_DIR
nohup sh -c "echo gowa-supervisor-whatsapp started; while true; do echo \"[supervisor] starting gowa...\" >> storages/gowa-supervisor.log; ./bin/gowa rest --db-uri=\"file:storages/whatsapp.db?_foreign_keys=on\" -w=\"$WEBHOOK_URL\" >> storages/gowa.log 2>&1; echo \"[supervisor] gowa exited, restarting in 2s\" >> storages/gowa-supervisor.log; sleep 2; done" > storages/gowa-supervisor.out 2>&1 &

echo "¡GoWA reiniciado con éxito!"
ps aux | grep "gowa rest" | grep -v grep
