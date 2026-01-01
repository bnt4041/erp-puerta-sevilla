-- Copyright (C) 2024 PuertaSevilla
--
-- Diccionario: Categorías Contables
--

CREATE TABLE IF NOT EXISTS llx_c_psv_categoria_contable (
    rowid integer AUTO_INCREMENT PRIMARY KEY,
    code varchar(16) NOT NULL,
    label varchar(128) NOT NULL,
    active tinyint DEFAULT 1 NOT NULL
) ENGINE=innodb;

DELETE FROM llx_c_psv_categoria_contable;

INSERT INTO llx_c_psv_categoria_contable (code, label, active) VALUES
('alquiler', 'Alquiler', 1),
('comunidad', 'Comunidad', 1),
('mantenimiento', 'Mantenimiento', 1),
('suministros', 'Suministros', 1),
('otros', 'Otros', 1);
