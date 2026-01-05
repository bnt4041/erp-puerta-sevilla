-- Copyright (C) 2026 DocSig Module
--
-- Claves e índices para llx_docsig_signer

ALTER TABLE llx_docsig_signer ADD INDEX idx_docsig_signer_fk_envelope (fk_envelope);
ALTER TABLE llx_docsig_signer ADD INDEX idx_docsig_signer_fk_socpeople (fk_socpeople);
ALTER TABLE llx_docsig_signer ADD INDEX idx_docsig_signer_fk_soc (fk_soc);
ALTER TABLE llx_docsig_signer ADD INDEX idx_docsig_signer_status (status);
ALTER TABLE llx_docsig_signer ADD INDEX idx_docsig_signer_token_hash (token_hash);
ALTER TABLE llx_docsig_signer ADD INDEX idx_docsig_signer_email (email);
ALTER TABLE llx_docsig_signer ADD INDEX idx_docsig_signer_order (fk_envelope, order_index);

ALTER TABLE llx_docsig_signer ADD CONSTRAINT fk_docsig_signer_fk_envelope FOREIGN KEY (fk_envelope) REFERENCES llx_docsig_envelope(rowid) ON DELETE CASCADE;
ALTER TABLE llx_docsig_signer ADD CONSTRAINT fk_docsig_signer_fk_socpeople FOREIGN KEY (fk_socpeople) REFERENCES llx_socpeople(rowid) ON DELETE SET NULL;
ALTER TABLE llx_docsig_signer ADD CONSTRAINT fk_docsig_signer_fk_soc FOREIGN KEY (fk_soc) REFERENCES llx_societe(rowid) ON DELETE SET NULL;
