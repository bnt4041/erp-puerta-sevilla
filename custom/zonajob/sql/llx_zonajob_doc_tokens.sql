-- Table for public document download tokens (ZonaJob)
-- Created via module descriptor on activation

CREATE TABLE IF NOT EXISTS llx_zonajob_doc_tokens (
  token varchar(64) NOT NULL,
  fk_commande integer NOT NULL,
  filename varchar(255) NOT NULL,
  filepath text NOT NULL,
  date_creation integer NOT NULL,
  date_expiration integer NOT NULL,
  downloads integer DEFAULT 0,
  active tinyint DEFAULT 1,
  PRIMARY KEY (token)
) ENGINE=innodb;