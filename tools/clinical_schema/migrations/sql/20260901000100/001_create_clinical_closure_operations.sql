CREATE TABLE `clinical_closure_operations` (
  `closure_operation_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` INT(11) NOT NULL,
  `closure_mode` ENUM('visit','non_visit') NOT NULL,
  `idempotency_key` VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `operation_fingerprint` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `closed_by_user_id` INT(11) NOT NULL,
  `authority_source` ENUM('responsible_doctor','doctor_visit_performer') NOT NULL,
  `responsible_assignment_id` BIGINT UNSIGNED NULL,
  `visit_assignment_id` BIGINT UNSIGNED NULL,
  `closed_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`closure_operation_id`),
  UNIQUE KEY `uq_clinical_closure_operation_request` (`request_id`),
  UNIQUE KEY `uq_clinical_closure_operation_idempotency` (`idempotency_key`),
  KEY `idx_clinical_closure_operation_actor` (`closed_by_user_id`, `closed_at`),
  CONSTRAINT `chk_clinical_closure_operation_fingerprint` CHECK (`operation_fingerprint` REGEXP '^[0-9a-f]{64}$'),
  CONSTRAINT `chk_clinical_closure_operation_shape` CHECK (
    (`closure_mode` = 'non_visit' AND `authority_source` = 'responsible_doctor' AND `responsible_assignment_id` IS NOT NULL AND `visit_assignment_id` IS NULL)
    OR (`closure_mode` = 'visit' AND `authority_source` = 'responsible_doctor' AND `responsible_assignment_id` IS NOT NULL AND `visit_assignment_id` IS NULL)
    OR (`closure_mode` = 'visit' AND `authority_source` = 'doctor_visit_performer' AND `visit_assignment_id` IS NOT NULL)
  ),
  CONSTRAINT `fk_clinical_closure_operation_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`request_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_clinical_closure_operation_responsible` FOREIGN KEY (`responsible_assignment_id`) REFERENCES `request_responsible_doctor_assignments` (`responsible_assignment_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_clinical_closure_operation_visit` FOREIGN KEY (`visit_assignment_id`) REFERENCES `request_visit_performer_assignments` (`visit_assignment_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci;
