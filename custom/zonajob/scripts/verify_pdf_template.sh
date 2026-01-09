#!/bin/bash
#
# Script de verificación para la instalación de plantilla PDF de ZonaJob
# Este script verifica que la plantilla se haya copiado correctamente
#

DOLIBARR_ROOT="/var/www/html/dolpuerta"
CUSTOM_TEMPLATE="$DOLIBARR_ROOT/custom/zonajob/core/modules/commande/doc/pdf_zonajob.modules.php"
CORE_TEMPLATE="$DOLIBARR_ROOT/core/modules/commande/doc/pdf_zonajob.modules.php"

echo "================================"
echo "Verificación de Plantilla PDF ZonaJob"
echo "================================"
echo ""

# Verificar plantilla original
echo "[1] Verificando plantilla original en custom..."
if [ -f "$CUSTOM_TEMPLATE" ]; then
    echo "✓ Plantilla original encontrada"
    echo "  Ubicación: $CUSTOM_TEMPLATE"
    echo "  Tamaño: $(du -h "$CUSTOM_TEMPLATE" | cut -f1)"
else
    echo "✗ ERROR: Plantilla original no encontrada"
    exit 1
fi

echo ""

# Verificar plantilla copiada
echo "[2] Verificando plantilla copiada en core..."
if [ -f "$CORE_TEMPLATE" ]; then
    echo "✓ Plantilla copiada encontrada"
    echo "  Ubicación: $CORE_TEMPLATE"
    echo "  Tamaño: $(du -h "$CORE_TEMPLATE" | cut -f1)"
    
    # Verificar integridad
    if diff "$CUSTOM_TEMPLATE" "$CORE_TEMPLATE" > /dev/null 2>&1; then
        echo "✓ Las plantillas son idénticas"
    else
        echo "⚠ ADVERTENCIA: Las plantillas tienen diferencias"
    fi
else
    echo "✗ ERROR: Plantilla copiada no encontrada"
    echo "  Asegúrese de que el módulo ZonaJob está ACTIVADO"
    exit 1
fi

echo ""

# Verificar clase PDF
echo "[3] Verificando clase PDF_ZonaJob..."
if grep -q "class pdf_zonajob" "$CORE_TEMPLATE"; then
    echo "✓ Clase PDF_ZonaJob encontrada en la plantilla"
else
    echo "✗ ERROR: Clase PDF_ZonaJob no encontrada"
    exit 1
fi

echo ""

# Verificar permisos
echo "[4] Verificando permisos..."
PERMS=$(stat -c "%a" "$CORE_TEMPLATE" 2>/dev/null)
if [ -n "$PERMS" ]; then
    echo "✓ Permisos: $PERMS"
else
    echo "⚠ No se pudieron verificar permisos"
fi

echo ""
echo "================================"
echo "✓ VERIFICACIÓN COMPLETADA CON ÉXITO"
echo "================================"
echo ""
echo "La plantilla PDF de ZonaJob está correctamente instalada."
echo "Puede comenzar a generar PDFs desde los pedidos."
