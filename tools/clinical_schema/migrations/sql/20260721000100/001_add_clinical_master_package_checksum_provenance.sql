ALTER TABLE `clinical_master_packages`
  ADD COLUMN `package_checksum` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL AFTER `manifest_checksum`,
  ADD COLUMN `package_checksum_profile` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL AFTER `package_checksum`,
  ADD CONSTRAINT `chk_clinical_master_package_semantic_checksum` CHECK (`package_checksum` REGEXP '^[0-9a-f]{64}$'),
  ADD CONSTRAINT `chk_clinical_master_package_checksum_profile` CHECK (`package_checksum_profile` = 'doclink-package-jcs-v1');
