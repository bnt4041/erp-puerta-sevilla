-- Copyright (C) 2026 DocSig Module
--
-- Claves e índices para llx_docsig_document

-- Índice único: un documento (por file_path) solo puede estar en un envelope activo
ALTER TABLE llx_docsig_document ADD UNIQUE INDEX uk_docsig_document_file_envelope (file_path, fk_envelope);

-- Índice para referencia dentro del envelope
ALTER TABLE llx_docsig_document ADD UNIQUE INDEX uk_docsig_document_ref_envelope (ref, fk_envelope);

-- Índices de búsqueda
ALTER TABLE llx_docsig_document ADD INDEX idx_docsig_document_fk_envelope (fk_envelope);
ALTER TABLE llx_docsig_document ADD INDEX idx_docsig_document_status (status);
ALTER TABLE llx_docsig_document ADD INDEX idx_docsig_document_sign_order (sign_order);
ALTER TABLE llx_docsig_document ADD INDEX idx_docsig_document_download_token (download_token);
ALTER TABLE llx_docsig_document ADD INDEX idx_docsig_document_date_creation (date_creation);

-- Foreign key al envelope
ALTER TABLE llx_docsig_document ADD CONSTRAINT fk_docsig_document_envelope 
    FOREIGN KEY (fk_envelope) REFERENCES llx_docsig_envelope(rowid) ON DELETE CASCADE;

-- Foreign key al usuario
ALTER TABLE llx_docsig_document ADD CONSTRAINT fk_docsig_document_fk_user_creat 
    FOREIGN KEY (fk_user_creat) REFERENCES llx_user(rowid);
