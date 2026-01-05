-- Copyright (C) 2026 DocSig Module
--
-- Claves e índices para llx_docsig_notification

ALTER TABLE llx_docsig_notification ADD INDEX idx_docsig_notification_fk_envelope (fk_envelope);
ALTER TABLE llx_docsig_notification ADD INDEX idx_docsig_notification_fk_signer (fk_signer);
ALTER TABLE llx_docsig_notification ADD INDEX idx_docsig_notification_fk_socpeople (fk_socpeople);
ALTER TABLE llx_docsig_notification ADD INDEX idx_docsig_notification_fk_actioncomm (fk_actioncomm);
ALTER TABLE llx_docsig_notification ADD INDEX idx_docsig_notification_type (notification_type);
ALTER TABLE llx_docsig_notification ADD INDEX idx_docsig_notification_status (status);
ALTER TABLE llx_docsig_notification ADD INDEX idx_docsig_notification_envelope_ref (envelope_ref);
ALTER TABLE llx_docsig_notification ADD INDEX idx_docsig_notification_sent_at (sent_at);

ALTER TABLE llx_docsig_notification ADD CONSTRAINT fk_docsig_notification_fk_envelope FOREIGN KEY (fk_envelope) REFERENCES llx_docsig_envelope(rowid) ON DELETE SET NULL;
ALTER TABLE llx_docsig_notification ADD CONSTRAINT fk_docsig_notification_fk_signer FOREIGN KEY (fk_signer) REFERENCES llx_docsig_signer(rowid) ON DELETE SET NULL;
ALTER TABLE llx_docsig_notification ADD CONSTRAINT fk_docsig_notification_fk_socpeople FOREIGN KEY (fk_socpeople) REFERENCES llx_socpeople(rowid) ON DELETE SET NULL;
