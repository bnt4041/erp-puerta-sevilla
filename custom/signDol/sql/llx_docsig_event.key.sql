-- Copyright (C) 2026 DocSig Module
--
-- Claves e índices para llx_docsig_event

ALTER TABLE llx_docsig_event ADD INDEX idx_docsig_event_fk_envelope (fk_envelope);
ALTER TABLE llx_docsig_event ADD INDEX idx_docsig_event_fk_signer (fk_signer);
ALTER TABLE llx_docsig_event ADD INDEX idx_docsig_event_type (event_type);
ALTER TABLE llx_docsig_event ADD INDEX idx_docsig_event_created_at (created_at);
ALTER TABLE llx_docsig_event ADD INDEX idx_docsig_event_ip (ip_address);

ALTER TABLE llx_docsig_event ADD CONSTRAINT fk_docsig_event_fk_envelope FOREIGN KEY (fk_envelope) REFERENCES llx_docsig_envelope(rowid) ON DELETE CASCADE;
ALTER TABLE llx_docsig_event ADD CONSTRAINT fk_docsig_event_fk_signer FOREIGN KEY (fk_signer) REFERENCES llx_docsig_signer(rowid) ON DELETE SET NULL;

-- Trigger para prevenir UPDATE (append-only)
-- DELIMITER //
-- CREATE TRIGGER prevent_docsig_event_update BEFORE UPDATE ON llx_docsig_event
-- FOR EACH ROW
-- BEGIN
--     SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'UPDATE not allowed on audit table llx_docsig_event';
-- END//
-- DELIMITER ;

-- Trigger para prevenir DELETE (append-only)
-- DELIMITER //
-- CREATE TRIGGER prevent_docsig_event_delete BEFORE DELETE ON llx_docsig_event
-- FOR EACH ROW
-- BEGIN
--     SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DELETE not allowed on audit table llx_docsig_event';
-- END//
-- DELIMITER ;
