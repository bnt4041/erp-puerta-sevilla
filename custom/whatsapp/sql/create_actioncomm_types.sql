-- Script para crear los tipos de eventos de WhatsApp
-- Ejecutar este script si los tipos no se crearon automáticamente

-- Obtener el siguiente ID disponible
SET @next_id = (SELECT COALESCE(MAX(id), 0) + 1 FROM llx_c_actioncomm);

-- Verificar si AC_WA existe, si no, crearlo
INSERT INTO llx_c_actioncomm (id, code, type, libelle, module, active, position)
SELECT @next_id, 'AC_WA', 'whatsapp', 'WhatsApp message sent', 'whatsapp', 1, 10
WHERE NOT EXISTS (
    SELECT 1 FROM llx_c_actioncomm WHERE code = 'AC_WA'
);

-- Obtener el siguiente ID disponible para el segundo registro
SET @next_id = (SELECT COALESCE(MAX(id), 0) + 1 FROM llx_c_actioncomm);

-- Verificar si AC_WA_IN existe, si no, crearlo
INSERT INTO llx_c_actioncomm (id, code, type, libelle, module, active, position)
SELECT @next_id, 'AC_WA_IN', 'whatsapp', 'WhatsApp message received', 'whatsapp', 1, 11
WHERE NOT EXISTS (
    SELECT 1 FROM llx_c_actioncomm WHERE code = 'AC_WA_IN'
);

-- Verificar que se crearon correctamente
SELECT id, code, type, libelle, module, active, position 
FROM llx_c_actioncomm 
WHERE code IN ('AC_WA', 'AC_WA_IN');
