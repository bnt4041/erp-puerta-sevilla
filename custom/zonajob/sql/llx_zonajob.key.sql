-- Copyright (C) 2025 ZonaJob Dev
-- Key file for SQL tables

ALTER TABLE llx_zonajob_signature ADD CONSTRAINT fk_zonajob_signature_fk_commande FOREIGN KEY (fk_commande) REFERENCES llx_commande(rowid);
ALTER TABLE llx_zonajob_signature ADD CONSTRAINT fk_zonajob_signature_fk_soc FOREIGN KEY (fk_soc) REFERENCES llx_societe(rowid);

ALTER TABLE llx_zonajob_photo ADD CONSTRAINT fk_zonajob_photo_fk_commande FOREIGN KEY (fk_commande) REFERENCES llx_commande(rowid);

ALTER TABLE llx_zonajob_send_history ADD CONSTRAINT fk_zonajob_send_history_fk_commande FOREIGN KEY (fk_commande) REFERENCES llx_commande(rowid);

ALTER TABLE llx_zonajob_doc_tokens ADD CONSTRAINT fk_zonajob_doc_tokens_fk_commande FOREIGN KEY (fk_commande) REFERENCES llx_commande(rowid);
