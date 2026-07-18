<?php

return array(
	'product_name' => 'DocLink',
	'tool_version' => '1.0.0',
	'migrations_directory' => __DIR__ . DIRECTORY_SEPARATOR . 'migrations',
	'ledger_migration_id' => '00000000000000_clinical_schema_ledger',
	'ledger_table' => 'clinical_schema_migrations',
	'lock_prefix' => 'doclink:clinical_schema:',
	'lock_timeout_seconds' => 3,
	'minimum_mariadb_version' => '10.11.0',
	'hard_rejected_database_users' => array('doclinc-staging-user'),
	'required_write_privileges' => array(
		'SELECT',
		'INSERT',
		'UPDATE',
		'CREATE',
		'ALTER',
		'INDEX',
		'REFERENCES',
	),
	'connection_environment_variables' => array(
		'host' => 'DOCLINK_CLINICAL_SCHEMA_DB_HOST',
		'port' => 'DOCLINK_CLINICAL_SCHEMA_DB_PORT',
		'database' => 'DOCLINK_CLINICAL_SCHEMA_DB_NAME',
		'user' => 'DOCLINK_CLINICAL_SCHEMA_DB_USER',
		'password' => 'DOCLINK_CLINICAL_SCHEMA_DB_PASSWORD',
		'allowed_users' => 'DOCLINK_CLINICAL_SCHEMA_ALLOWED_USERS',
	),
);
