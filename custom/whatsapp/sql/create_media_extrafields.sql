-- Script para crear los campos extrafields para media en WhatsApp
-- Ejecutar este script para habilitar el almacenamiento de archivos multimedia

-- Crear la tabla de extrafields para actioncomm si no existe
CREATE TABLE IF NOT EXISTS llx_actioncomm_extrafields (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_object INT NOT NULL,
    import_key VARCHAR(14),
    UNIQUE KEY uk_actioncomm_extrafields (fk_object)
) ENGINE=InnoDB;

-- Añadir columnas para media de WhatsApp
ALTER TABLE llx_actioncomm_extrafields 
ADD COLUMN IF NOT EXISTS wa_media_type VARCHAR(32) DEFAULT NULL COMMENT 'Tipo de media: image, video, audio, document';

ALTER TABLE llx_actioncomm_extrafields 
ADD COLUMN IF NOT EXISTS wa_media_url VARCHAR(512) DEFAULT NULL COMMENT 'URL del archivo';

ALTER TABLE llx_actioncomm_extrafields 
ADD COLUMN IF NOT EXISTS wa_media_filename VARCHAR(255) DEFAULT NULL COMMENT 'Nombre original del archivo';

ALTER TABLE llx_actioncomm_extrafields 
ADD COLUMN IF NOT EXISTS wa_media_size INT DEFAULT NULL COMMENT 'Tamaño del archivo en bytes';

ALTER TABLE llx_actioncomm_extrafields 
ADD COLUMN IF NOT EXISTS wa_media_mime VARCHAR(128) DEFAULT NULL COMMENT 'Tipo MIME del archivo';

-- Crear índice para búsquedas rápidas
ALTER TABLE llx_actioncomm_extrafields 
ADD INDEX IF NOT EXISTS idx_wa_media_type (wa_media_type);

-- Añadir tipo de evento para media de WhatsApp
SET @next_id = (SELECT COALESCE(MAX(id), 0) + 1 FROM llx_c_actioncomm);

INSERT INTO llx_c_actioncomm (id, code, type, libelle, module, active, position)
SELECT @next_id, 'AC_WA_MEDIA', 'whatsapp', 'WhatsApp media sent', 'whatsapp', 1, 12
WHERE NOT EXISTS (
    SELECT 1 FROM llx_c_actioncomm WHERE code = 'AC_WA_MEDIA'
);

-- Verificar resultado
SELECT id, code, type, libelle, module, active, position 
FROM llx_c_actioncomm 
WHERE code LIKE 'AC_WA%';

SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_COMMENT 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'llx_actioncomm_extrafields' 
AND COLUMN_NAME LIKE 'wa_%';
