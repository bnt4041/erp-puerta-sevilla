#!/bin/bash
# Script to start goWA service
cd "$(dirname "$0")"
./bin/gowa rest --db-uri="file:storages/whatsapp.db?_foreign_keys=on" > storages/gowa.log 2>&1 &
echo "goWA started with PID $!"
