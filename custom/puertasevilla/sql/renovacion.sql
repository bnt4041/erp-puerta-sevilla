-- Tabla de auditoría para renovaciones de contratos
-- Esta tabla es opcional pero recomendada para mantener histórico de cambios

CREATE TABLE IF NOT EXISTS `llx_puertasevilla_contract_renewal` (
  `rowid` int(11) NOT NULL AUTO_INCREMENT,
  `fk_contrat` int(11) NOT NULL,
  `date_renewal` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_renewal_id` int(11) NOT NULL,
  `date_start_old` datetime,
  `date_start_new` datetime,
  `date_end_old` datetime,
  `date_end_new` datetime,
  `type_renovation` varchar(50) COLLATE utf8mb4_unicode_ci,
  `value_applied` float,
  `note` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'success',
  PRIMARY KEY (`rowid`),
  KEY `fk_contrat` (`fk_contrat`),
  KEY `user_renewal_id` (`user_renewal_id`),
  KEY `date_renewal` (`date_renewal`),
  CONSTRAINT `llx_puertasevilla_contract_renewal_ibfk_1` FOREIGN KEY (`fk_contrat`) REFERENCES `llx_contrat` (`rowid`) ON DELETE CASCADE,
  CONSTRAINT `llx_puertasevilla_contract_renewal_ibfk_2` FOREIGN KEY (`user_renewal_id`) REFERENCES `llx_user` (`rowid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índice para búsquedas frecuentes
CREATE INDEX idx_contrat_date ON `llx_puertasevilla_contract_renewal` (fk_contrat, date_renewal DESC);
