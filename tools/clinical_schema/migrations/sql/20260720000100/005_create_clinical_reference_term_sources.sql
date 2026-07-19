CREATE TABLE `clinical_reference_term_sources` (
  `clinical_reference_term_source_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `clinical_reference_term_id` BIGINT UNSIGNED NOT NULL,
  `clinical_master_record_revision_id` BIGINT UNSIGNED NOT NULL,
  `source_role` VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `mapping_status` VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'proposed',
  `active_record_revision_id` BIGINT UNSIGNED GENERATED ALWAYS AS (
    CASE
      WHEN `mapping_status` IN ('proposed','in_review','confirmed')
      THEN `clinical_master_record_revision_id`
      ELSE NULL
    END
  ) PERSISTENT,
  `decision_reason` VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  `decision_actor` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
  `decided_at` DATETIME(6) NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`clinical_reference_term_source_id`),
  UNIQUE KEY `uq_clinical_reference_term_source_active_revision` (`active_record_revision_id`),
  KEY `idx_clinical_reference_term_source_revision` (`clinical_master_record_revision_id`),
  KEY `idx_clinical_reference_term_source_term_status` (`clinical_reference_term_id`, `mapping_status`),
  KEY `idx_clinical_reference_term_source_status_time` (`mapping_status`, `decided_at`),
  CONSTRAINT `fk_clinical_reference_term_source_term` FOREIGN KEY (`clinical_reference_term_id`) REFERENCES `clinical_reference_terms` (`clinical_reference_term_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_clinical_reference_term_source_revision` FOREIGN KEY (`clinical_master_record_revision_id`) REFERENCES `clinical_master_record_revisions` (`clinical_master_record_revision_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `chk_clinical_reference_term_source_role` CHECK (`source_role` IN ('primary','supporting','translation','legacy')),
  CONSTRAINT `chk_clinical_reference_term_source_status` CHECK (`mapping_status` IN ('proposed','in_review','confirmed','rejected','retired')),
  CONSTRAINT `chk_clinical_reference_term_source_decision` CHECK (`mapping_status` NOT IN ('confirmed','rejected','retired') OR `decision_actor` IS NOT NULL AND OCTET_LENGTH(`decision_actor`) > 0 AND OCTET_LENGTH(`decision_actor`) = OCTET_LENGTH(TRIM(`decision_actor`)) AND `decided_at` IS NOT NULL),
  CONSTRAINT `chk_clinical_reference_term_source_actor` CHECK (`decision_actor` IS NULL OR OCTET_LENGTH(`decision_actor`) > 0 AND OCTET_LENGTH(`decision_actor`) = OCTET_LENGTH(TRIM(`decision_actor`))),
  CONSTRAINT `chk_clinical_reference_term_source_reason` CHECK (`decision_reason` IS NULL OR OCTET_LENGTH(`decision_reason`) > 0 AND OCTET_LENGTH(`decision_reason`) = OCTET_LENGTH(TRIM(`decision_reason`)))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci;
