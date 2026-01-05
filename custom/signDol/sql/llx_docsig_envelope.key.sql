-- Copyright (C) 2026 DocSig Module
--
-- Claves e índices para llx_docsig_envelope

ALTER TABLE llx_docsig_envelope ADD UNIQUE INDEX uk_docsig_envelope_ref (ref, entity);
ALTER TABLE llx_docsig_envelope ADD INDEX idx_docsig_envelope_fk_object (fk_object, element);
ALTER TABLE llx_docsig_envelope ADD INDEX idx_docsig_envelope_status (status);
ALTER TABLE llx_docsig_envelope ADD INDEX idx_docsig_envelope_entity (entity);
ALTER TABLE llx_docsig_envelope ADD INDEX idx_docsig_envelope_date_creation (date_creation);
ALTER TABLE llx_docsig_envelope ADD INDEX idx_docsig_envelope_fk_user_create (fk_user_create);

ALTER TABLE llx_docsig_envelope ADD CONSTRAINT fk_docsig_envelope_fk_user_create FOREIGN KEY (fk_user_create) REFERENCES llx_user(rowid);
