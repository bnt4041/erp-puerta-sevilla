#!/bin/bash

# Script de Verificación - Integración de Fotos con Dolibarr
# Verifica que la implementación se haya realizado correctamente

echo "=========================================="
echo "Verificación - Fotos en Dolibarr"
echo "=========================================="
echo ""

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

ERRORS=0
WARNINGS=0
CHECKS=0

# Función para verificar
check() {
    CHECKS=$((CHECKS + 1))
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓${NC} $1"
    else
        echo -e "${RED}✗${NC} $1"
        ERRORS=$((ERRORS + 1))
    fi
}

warn() {
    echo -e "${YELLOW}⚠${NC} $1"
    WARNINGS=$((WARNINGS + 1))
}

# 1. Verificar archivos
echo "1. Verificando archivos..."
test -f "/var/www/html/dolpuerta/custom/zonajob/order_card.php"
check "Archivo order_card.php existe"

test -f "/var/www/html/dolpuerta/custom/zonajob/FOTOS_IMPLEMENTACION.md"
check "Documentación FOTOS_IMPLEMENTACION.md existe"

test -f "/var/www/html/dolpuerta/custom/zonajob/CHANGELOG_FOTOS.md"
check "Changelog FOTOS_IMPLEMENTACION.md existe"

test -f "/var/www/html/dolpuerta/custom/zonajob/docs/FOTOS_DOLIBARR_INTEGRATION.md"
check "Documentación técnica existe"

test -f "/var/www/html/dolpuerta/custom/zonajob/scripts/migrate_photos_to_dolibarr.php"
check "Script de migración existe"

echo ""

# 2. Verificar contenido de order_card.php
echo "2. Verificando contenido de order_card.php..."
grep -q "core/lib/files.lib.php" "/var/www/html/dolpuerta/custom/zonajob/order_card.php"
check "Include de files.lib.php presente"

grep -q "conf->commande->dir_output" "/var/www/html/dolpuerta/custom/zonajob/order_card.php"
check "Uso de directorio estándar Dolibarr"

grep -q "dol_mkdir" "/var/www/html/dolpuerta/custom/zonajob/order_card.php"
check "Creación de directorios con dol_mkdir"

grep -q "move_uploaded_file" "/var/www/html/dolpuerta/custom/zonajob/order_card.php"
check "Movimiento de archivo a ubicación estándar"

grep -q "photo_.*dechex.*time" "/var/www/html/dolpuerta/custom/zonajob/order_card.php"
check "Generación de nombres únicos con timestamp"

grep -q "allowed_ext" "/var/www/html/dolpuerta/custom/zonajob/order_card.php"
check "Validación de extensiones presente"

echo ""

# 3. Verificar permisos de directorios
echo "3. Verificando permisos de directorios..."

if [ -d "/var/www/html/dolpuerta/documents/commandes" ]; then
    test -w "/var/www/html/dolpuerta/documents/commandes"
    check "Directorio documents/commandes tiene permisos de escritura"
else
    warn "Directorio documents/commandes no existe (se creará al subir foto)"
fi

test -d "/var/www/html/dolpuerta/custom/zonajob"
check "Directorio zonajob existe"

test -w "/var/www/html/dolpuerta/custom/zonajob"
check "Directorio zonajob tiene permisos de escritura"

echo ""

# 4. Verificar clase ZonaJobPhoto
echo "4. Verificando clase ZonaJobPhoto..."
test -f "/var/www/html/dolpuerta/custom/zonajob/class/zonajobphoto.class.php"
check "Clase ZonaJobPhoto existe"

grep -q "public \$filepath" "/var/www/html/dolpuerta/custom/zonajob/class/zonajobphoto.class.php"
check "Campo filepath en clase ZonaJobPhoto"

grep -q "function create" "/var/www/html/dolpuerta/custom/zonajob/class/zonajobphoto.class.php"
check "Método create existe en ZonaJobPhoto"

echo ""

# 5. Verificar documentación
echo "5. Verificando documentación..."

grep -q "Integración de Fotos" "/var/www/html/dolpuerta/custom/zonajob/FOTOS_IMPLEMENTACION.md"
check "Documentación de implementación completa"

grep -q "FOTOS_DOLIBARR_INTEGRATION" "/var/www/html/dolpuerta/custom/zonajob/docs/FOTOS_DOLIBARR_INTEGRATION.md"
check "Documentación técnica completa"

grep -q "migrate_photos_to_dolibarr" "/var/www/html/dolpuerta/custom/zonajob/scripts/migrate_photos_to_dolibarr.php"
check "Script de migración correctamente nombrado"

echo ""

# 6. Resumen
echo "=========================================="
echo "RESUMEN DE VERIFICACIÓN"
echo "=========================================="
echo "Verificaciones totales: $CHECKS"
echo -e "Errores: ${RED}$ERRORS${NC}"
echo -e "Advertencias: ${YELLOW}$WARNINGS${NC}"
echo ""

if [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}✓ IMPLEMENTACIÓN COMPLETADA CORRECTAMENTE${NC}"
    echo ""
    echo "Próximos pasos:"
    echo "1. Verificar permisos de /documents/commandes/"
    echo "2. Probar subida de foto en ZonaJob"
    echo "3. Verificar que aparece en Dolibarr → Documentos"
    echo "4. Si hay fotos antiguas, ejecutar script de migración"
    exit 0
else
    echo -e "${RED}✗ ERRORES ENCONTRADOS${NC}"
    echo ""
    echo "Por favor revisar:"
    echo "1. Que todos los archivos existan"
    echo "2. Que el contenido sea correcto"
    echo "3. Que los permisos sean adecuados"
    exit 1
fi
