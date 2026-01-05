-- Copyright (C) 2026 DocSig Module
--
-- Claves e índices para llx_docsig_otp

ALTER TABLE llx_docsig_otp ADD INDEX idx_docsig_otp_fk_signer (fk_signer);
ALTER TABLE llx_docsig_otp ADD INDEX idx_docsig_otp_status (status);
ALTER TABLE llx_docsig_otp ADD INDEX idx_docsig_otp_expires_at (expires_at);
ALTER TABLE llx_docsig_otp ADD INDEX idx_docsig_otp_sent_at (sent_at);
ALTER TABLE llx_docsig_otp ADD INDEX idx_docsig_otp_ip (ip_address);

ALTER TABLE llx_docsig_otp ADD CONSTRAINT fk_docsig_otp_fk_signer FOREIGN KEY (fk_signer) REFERENCES llx_docsig_signer(rowid) ON DELETE CASCADE;
