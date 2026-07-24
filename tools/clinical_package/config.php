<?php

return array(
	'allowed_roots_environment' => 'DOCLINK_CLINICAL_PACKAGE_ALLOWED_ROOTS',
	'database_environment_variables' => array(
		'host' => 'DOCLINK_CLINICAL_SCHEMA_DB_HOST',
		'port' => 'DOCLINK_CLINICAL_SCHEMA_DB_PORT',
		'database' => 'DOCLINK_CLINICAL_SCHEMA_DB_NAME',
		'user' => 'DOCLINK_CLINICAL_SCHEMA_DB_USER',
		'password' => 'DOCLINK_CLINICAL_SCHEMA_DB_PASSWORD',
		'allowed_users' => 'DOCLINK_CLINICAL_SCHEMA_ALLOWED_USERS',
	),
	'established_validator_file' => dirname(__DIR__) . '/clinical_master/ClinicalMasterValidator.php',
	'established_rules_file' => dirname(__DIR__) . '/clinical_master/rules.php',
);
