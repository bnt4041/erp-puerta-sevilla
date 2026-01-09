#!/bin/bash
#
# Script de diagnóstico para problema de plantilla PDF ZonaJob
#

DOLIBARR_ROOT="/var/www/html/dolpuerta"
CUSTOM_TEMPLATE="$DOLIBARR_ROOT/custom/zonajob/core/modules/commande/doc/pdf_zonajob.modules.php"
CORE_TEMPLATE="$DOLIBARR_ROOT/core/modules/commande/doc/pdf_zonajob.modules.php"
CORE_FUNCTIONS="$DOLIBARR_ROOT/core/lib/pdf.lib.php"

echo "========================================"
echo "Diagnóstico de Plantilla PDF ZonaJob"
echo "========================================"
echo ""

# 1. Verificar que el archivo original existe
echo "[1] Verificando plantilla original..."
if [ -f "$CUSTOM_TEMPLATE" ]; then
    echo "✓ Plantilla original encontrada en custom/"
else
    echo "✗ ERROR: Plantilla original NO encontrada"
    exit 1
fi

echo ""

# 2. Verificar que el archivo está copiado en core
echo "[2] Verificando plantilla en core/"
if [ -f "$CORE_TEMPLATE" ]; then
    echo "✓ Plantilla copiada encontrada en core/"
else
    echo "✗ ERROR: Plantilla NO fue copiada a core/"
    echo "  El módulo ZonaJob podría no estar activado"
    exit 1
fi

echo ""

# 3. Verificar que las funciones necesarias existen
echo "[3] Verificando funciones PDF requeridas..."
FUNCTIONS=("pdf_getPDFFontSize" "pdf_getInstance" "pdf_getPDFFont" "pdf_pagehead")

for func in "${FUNCTIONS[@]}"; do
    if grep -q "function $func" "$CORE_FUNCTIONS"; then
        echo "✓ Función $func encontrada"
    else
        echo "✗ ADVERTENCIA: Función $func NO encontrada en pdf.lib.php"
    fi
done

echo ""

# 4. Verificar require_once en la plantilla
echo "[4] Verificando require_once en plantilla..."
REQUIRES=(
    "core/modules/commande/modules_commande.php"
    "core/lib/company.lib.php"
    "core/lib/functions2.lib.php"
    "core/lib/pdf.lib.php"
)

for req in "${REQUIRES[@]}"; do
    if grep -q "require_once.*$req" "$CORE_TEMPLATE"; then
        FULL_PATH="$DOLIBARR_ROOT/$req"
        if [ -f "$FULL_PATH" ]; then
            echo "✓ Incluido: $req (encontrado)"
        else
            echo "✗ ADVERTENCIA: Incluido: $req (NO encontrado en $FULL_PATH)"
        fi
    else
        echo "⚠ NO incluido: $req"
    fi
done

echo ""

# 5. Verificar clase PDF
echo "[5] Verificando clase pdf_zonajob..."
if grep -q "class pdf_zonajob" "$CORE_TEMPLATE"; then
    if grep -q "extends ModelePDFCommandes" "$CORE_TEMPLATE"; then
        echo "✓ Clase pdf_zonajob encontrada y extiende ModelePDFCommandes"
    else
        echo "✗ ERROR: Clase pdf_zonajob existe pero no extiende ModelePDFCommandes"
    fi
else
    echo "✗ ERROR: Clase pdf_zonajob NO encontrada"
fi

echo ""

# 6. Verificar que el archivo copiado es idéntico
echo "[6] Verificando integridad de archivos..."
if diff "$CUSTOM_TEMPLATE" "$CORE_TEMPLATE" > /dev/null 2>&1; then
    echo "✓ Archivos son idénticos (integridad OK)"
else
    echo "✗ ADVERTENCIA: Archivos difieren (posible corrupción)"
fi

echo ""
echo "========================================"
echo "✓ DIAGNÓSTICO COMPLETADO"
echo "========================================"
