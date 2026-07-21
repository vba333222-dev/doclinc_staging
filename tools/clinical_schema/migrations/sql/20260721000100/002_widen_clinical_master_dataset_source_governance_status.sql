ALTER TABLE `clinical_master_datasets`
  MODIFY COLUMN `source_governance_status` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL;
