#!/bin/bash
# Script de verificación del módulo PuertaSevilla
# Ejecutar con: bash verificar_modulo.sh

echo "=================================================="
echo "  VERIFICACIÓN MÓDULO PUERTASEVILLA"
echo "=================================================="
echo ""

# Colores
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Contador de checks
TOTAL=0
PASSED=0

# Función para verificar
check() {
    TOTAL=$((TOTAL + 1))
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✓${NC} $2"
        PASSED=$((PASSED + 1))
    else
        echo -e "${RED}✗${NC} $2"
    fi
}

# 1. Verificar estructura de directorios
echo "1. Verificando estructura de directorios..."
[ -d "core/modules" ] && check 0 "Directorio core/modules existe" || check 1 "Directorio core/modules existe"
[ -d "core/triggers" ] && check 0 "Directorio core/triggers existe" || check 1 "Directorio core/triggers existe"
[ -d "admin" ] && check 0 "Directorio admin existe" || check 1 "Directorio admin existe"
[ -d "lib" ] && check 0 "Directorio lib existe" || check 1 "Directorio lib existe"
[ -d "langs/es_ES" ] && check 0 "Directorio langs/es_ES existe" || check 1 "Directorio langs/es_ES existe"
[ -d "sql" ] && check 0 "Directorio sql existe" || check 1 "Directorio sql existe"
[ -d "class" ] && check 0 "Directorio class existe" || check 1 "Directorio class existe"
echo ""

# 2. Verificar archivos principales
echo "2. Verificando archivos principales..."
[ -f "core/modules/modPuertaSevilla.class.php" ] && check 0 "Descriptor del módulo (modPuertaSevilla.class.php)" || check 1 "Descriptor del módulo"
[ -f "core/triggers/interface_99_modPuertaSevilla_PuertaSevillaTriggers.class.php" ] && check 0 "Trigger de contratos" || check 1 "Trigger de contratos"
[ -f "admin/setup.php" ] && check 0 "Página de configuración" || check 1 "Página de configuración"
[ -f "admin/about.php" ] && check 0 "Página acerca de" || check 1 "Página acerca de"
[ -f "lib/puertasevilla.lib.php" ] && check 0 "Librería de funciones" || check 1 "Librería de funciones"
[ -f "langs/es_ES/puertasevilla.lang" ] && check 0 "Archivo de traducciones" || check 1 "Archivo de traducciones"
[ -f "index.php" ] && check 0 "Archivo index.php de protección" || check 1 "Archivo index.php"
echo ""

# 3. Verificar archivos SQL
echo "3. Verificando archivos SQL de diccionarios..."
[ -f "sql/llx_c_psv_tipo_mantenimiento.sql" ] && check 0 "SQL Tipos de Mantenimiento" || check 1 "SQL Tipos de Mantenimiento"
[ -f "sql/llx_c_psv_categoria_contable.sql" ] && check 0 "SQL Categorías Contables" || check 1 "SQL Categorías Contables"
[ -f "sql/llx_c_psv_estado_vivienda.sql" ] && check 0 "SQL Estados de Vivienda" || check 1 "SQL Estados de Vivienda"
[ -f "sql/llx_c_psv_forma_pago.sql" ] && check 0 "SQL Formas de Pago" || check 1 "SQL Formas de Pago"
echo ""

# 4. Verificar documentación
echo "4. Verificando documentación..."
[ -f "README.md" ] && check 0 "README.md" || check 1 "README.md"
[ -f "INSTALL.md" ] && check 0 "INSTALL.md (Guía de instalación)" || check 1 "INSTALL.md"
[ -f "CHANGELOG.md" ] && check 0 "CHANGELOG.md" || check 1 "CHANGELOG.md"
[ -f "RESUMEN_EJECUTIVO.md" ] && check 0 "RESUMEN_EJECUTIVO.md" || check 1 "RESUMEN_EJECUTIVO.md"
[ -f "EJEMPLOS.md" ] && check 0 "EJEMPLOS.md (Casos prácticos)" || check 1 "EJEMPLOS.md"
echo ""

# 5. Verificar sintaxis PHP (si php está disponible)
if command -v php &> /dev/null; then
    echo "5. Verificando sintaxis PHP..."
    
    if php -l core/modules/modPuertaSevilla.class.php &> /dev/null; then
        check 0 "Sintaxis PHP del descriptor"
    else
        check 1 "Sintaxis PHP del descriptor"
    fi
    
    if php -l core/triggers/interface_99_modPuertaSevilla_PuertaSevillaTriggers.class.php &> /dev/null; then
        check 0 "Sintaxis PHP del trigger"
    else
        check 1 "Sintaxis PHP del trigger"
    fi
    
    if php -l admin/setup.php &> /dev/null; then
        check 0 "Sintaxis PHP de setup.php"
    else
        check 1 "Sintaxis PHP de setup.php"
    fi
    
    if php -l admin/about.php &> /dev/null; then
        check 0 "Sintaxis PHP de about.php"
    else
        check 1 "Sintaxis PHP de about.php"
    fi
    
    if php -l lib/puertasevilla.lib.php &> /dev/null; then
        check 0 "Sintaxis PHP de lib"
    else
        check 1 "Sintaxis PHP de lib"
    fi
    echo ""
else
    echo "5. PHP no disponible, saltando verificación de sintaxis..."
    echo ""
fi

# 6. Verificar permisos
echo "6. Verificando permisos de archivos..."
PERMS_OK=0
for file in $(find . -type f -name "*.php"); do
    if [ ! -r "$file" ]; then
        PERMS_OK=1
        break
    fi
done
[ $PERMS_OK -eq 0 ] && check 0 "Permisos de lectura en archivos PHP" || check 1 "Permisos de lectura en archivos PHP"
echo ""

# 7. Información adicional
echo "7. Información del módulo..."
TOTAL_FILES=$(find . -type f | wc -l)
TOTAL_SIZE=$(du -sh . | cut -f1)
PHP_FILES=$(find . -name "*.php" | wc -l)
SQL_FILES=$(find . -name "*.sql" | wc -l)
MD_FILES=$(find . -name "*.md" | wc -l)

echo -e "${YELLOW}Total de archivos:${NC} $TOTAL_FILES"
echo -e "${YELLOW}Tamaño total:${NC} $TOTAL_SIZE"
echo -e "${YELLOW}Archivos PHP:${NC} $PHP_FILES"
echo -e "${YELLOW}Archivos SQL:${NC} $SQL_FILES"
echo -e "${YELLOW}Archivos MD:${NC} $MD_FILES"
echo ""

# Resumen
echo "=================================================="
echo "  RESUMEN"
echo "=================================================="
echo -e "${GREEN}Checks pasados: $PASSED / $TOTAL${NC}"

if [ $PASSED -eq $TOTAL ]; then
    echo -e "${GREEN}✓ MÓDULO VERIFICADO CORRECTAMENTE${NC}"
    echo ""
    echo "Siguiente paso:"
    echo "1. Ir a Dolibarr → Inicio → Configuración → Módulos"
    echo "2. Buscar 'PuertaSevilla'"
    echo "3. Activar el módulo"
    echo ""
    echo "Ver INSTALL.md para más información"
    exit 0
else
    FAILED=$((TOTAL - PASSED))
    echo -e "${RED}✗ $FAILED checks fallaron${NC}"
    echo ""
    echo "Por favor, revisa los errores anteriores"
    exit 1
fi
