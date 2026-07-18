<?php

class ClinicalSchemaConnection
{
	private $mysqli;
	private $databaseName;
	private $serverVersion;
	private $authenticatedUser;
	private $lockHeld = false;

	public static function fromEnvironment(array $config, $confirmedDatabase, $minimumVersion, $writeRequired)
	{
		$names = $config['connection_environment_variables'];
		$host = self::environmentValue($names['host']);
		$portValue = self::environmentValue($names['port']);
		$database = self::environmentValue($names['database']);
		$user = self::environmentValue($names['user']);
		$password = self::environmentValue($names['password']);
		$allowedRaw = self::environmentValue($names['allowed_users']);

		if ($host === '' || $database === '' || $user === '' || $allowedRaw === '') {
			throw new ClinicalSchemaException('connection_configuration_missing', 'Dedicated migration connection configuration is incomplete.');
		}
		$port = $portValue === '' ? 3306 : filter_var($portValue, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 65535)));
		if ($port === false) {
			throw new ClinicalSchemaException('connection_port_invalid', 'Dedicated migration connection port is invalid.');
		}
		if ($database !== $confirmedDatabase) {
			throw new ClinicalSchemaException('database_confirmation_mismatch', 'Confirmed database does not match configured migration target.');
		}

		foreach ($config['hard_rejected_database_users'] as $rejectedUser) {
			if (strcasecmp($user, $rejectedUser) === 0) {
				throw new ClinicalSchemaException('runtime_database_user_rejected', 'Application runtime database identity is prohibited.');
			}
		}
		$allowed = array();
		foreach (explode(',', $allowedRaw) as $candidate) {
			$candidate = trim($candidate);
			if ($candidate !== '') {
				$allowed[$candidate] = true;
			}
		}
		if (!isset($allowed[$user])) {
			throw new ClinicalSchemaException('migration_database_user_not_allowed', 'Configured database identity is not allowlisted.');
		}

		if ($password === '') {
			$password = self::readPasswordFromSafeTty();
		}
		if ($password === '') {
			throw new ClinicalSchemaException('database_password_unavailable', 'Migration database password is unavailable.');
		}

		if (!extension_loaded('mysqli')) {
			throw new ClinicalSchemaException('mysqli_extension_unavailable', 'The mysqli extension is required.');
		}

		mysqli_report(MYSQLI_REPORT_OFF);
		$warning = null;
		set_error_handler(function ($severity, $message) use (&$warning) {
			$warning = $message;
			return true;
		});
		try {
			$mysqli = new mysqli($host, $user, $password, $database, (int) $port);
		} finally {
			restore_error_handler();
			$password = str_repeat("\0", strlen($password));
			$password = null;
		}
		if ($warning !== null || $mysqli->connect_errno) {
			throw new ClinicalSchemaException('database_connection_failed', 'Dedicated migration database connection failed.');
		}
		if (!$mysqli->set_charset('utf8mb4')) {
			$mysqli->close();
			throw new ClinicalSchemaException('database_charset_failed', 'Could not set utf8mb4 for the migration connection.');
		}

		$connection = new self($mysqli);
		$connection->verifyIdentity($confirmedDatabase, $user, $allowed, $config['hard_rejected_database_users']);
		$connection->verifyServer($minimumVersion);
		$connection->verifyInnoDb();
		if ($writeRequired) {
			$connection->verifyPrivileges($config['required_write_privileges']);
		}
		return $connection;
	}

	public function __construct($mysqli)
	{
		$this->mysqli = $mysqli;
	}

	public function getDatabaseName()
	{
		return $this->databaseName;
	}

	public function getServerVersion()
	{
		return $this->serverVersion;
	}

	public function getExecutorIdentityHash()
	{
		return 'db-account-sha256:' . hash('sha256', $this->authenticatedUser);
	}

	public function verifyServer($minimumVersion)
	{
		$rows = $this->queryAll('SELECT VERSION() AS server_version, @@version_comment AS version_comment');
		if (count($rows) !== 1) {
			throw new ClinicalSchemaException('database_server_identity_unavailable', 'Database server identity could not be verified.');
		}
		$raw = (string) $rows[0]['server_version'];
		$comment = (string) $rows[0]['version_comment'];
		if (stripos($raw, 'MariaDB') === false && stripos($comment, 'MariaDB') === false) {
			throw new ClinicalSchemaException('database_product_rejected', 'Database product must be MariaDB.');
		}
		if (preg_match('/([0-9]+\.[0-9]+\.[0-9]+)/', $raw, $match) !== 1) {
			throw new ClinicalSchemaException('database_version_unparseable', 'MariaDB version could not be parsed.');
		}
		$version = $match[1];
		if (version_compare($version, $minimumVersion, '<')) {
			throw new ClinicalSchemaException('database_version_too_old', 'MariaDB version is below the migration minimum.', array('minimum_version' => $minimumVersion));
		}
		$this->serverVersion = $version;
	}

	public function verifyPrivileges(array $required)
	{
		$granteeExpression = "CONCAT(QUOTE(LEFT(CURRENT_USER(),LENGTH(CURRENT_USER())-LENGTH(SUBSTRING_INDEX(CURRENT_USER(),'@',-1))-1)),'@',QUOTE(SUBSTRING_INDEX(CURRENT_USER(),'@',-1)))";
		$globalRows = $this->queryAll(
			'SELECT PRIVILEGE_TYPE FROM information_schema.USER_PRIVILEGES WHERE GRANTEE=' . $granteeExpression
		);
		$schemaRows = $this->queryAll(
			'SELECT PRIVILEGE_TYPE FROM information_schema.SCHEMA_PRIVILEGES WHERE GRANTEE=' . $granteeExpression . ' AND TABLE_SCHEMA=DATABASE()'
		);
		$effective = array();
		foreach (array_merge($globalRows, $schemaRows) as $row) {
			if (isset($row['PRIVILEGE_TYPE'])) {
				$effective[strtoupper((string) $row['PRIVILEGE_TYPE'])] = true;
			}
		}
		$missing = array();
		foreach ($required as $privilege) {
			if (!isset($effective[strtoupper((string) $privilege)])) {
				$missing[] = $privilege;
			}
		}
		$effective = array();
		if (count($missing) > 0) {
			throw new ClinicalSchemaException('database_privileges_insufficient', 'Dedicated migration privileges are insufficient.', array('missing_count' => count($missing)));
		}
	}

	public function acquireLock($name, $timeout)
	{
		$row = $this->queryOnePrepared('SELECT GET_LOCK(?, ?) AS lock_result', 'si', array($name, (int) $timeout));
		if (!$row || (string) $row['lock_result'] !== '1') {
			throw new ClinicalSchemaException('advisory_lock_unavailable', 'Clinical schema advisory lock could not be acquired.');
		}
		$this->lockHeld = true;
	}

	public function releaseLock($name)
	{
		if (!$this->lockHeld) {
			return true;
		}
		$row = $this->queryOnePrepared('SELECT RELEASE_LOCK(?) AS release_result', 's', array($name));
		$this->lockHeld = false;
		return $row && (string) $row['release_result'] === '1';
	}

	public function queryAll($sql)
	{
		$result = $this->mysqli->query($sql);
		if ($result === false) {
			throw new ClinicalSchemaException('database_metadata_query_failed', 'Database metadata operation failed.');
		}
		if ($result === true) {
			return array();
		}
		$rows = array();
		while ($row = $result->fetch_assoc()) {
			$rows[] = $row;
		}
		$result->free();
		return $rows;
	}

	public function queryPrepared($sql, $types, array $parameters)
	{
		$stmt = $this->prepareAndExecute($sql, $types, $parameters);
		$result = method_exists($stmt, 'get_result') ? $stmt->get_result() : false;
		if ($result !== false) {
			$rows = array();
			while ($row = $result->fetch_assoc()) {
				$rows[] = $row;
			}
			$result->free();
			$stmt->close();
			return $rows;
		}

		$metadata = $stmt->result_metadata();
		if ($metadata === false) {
			$stmt->close();
			return array();
		}
		$fields = $metadata->fetch_fields();
		$metadata->free();
		$values = array_fill(0, count($fields), null);
		$references = array();
		foreach ($values as $index => $unused) {
			$references[$index] = &$values[$index];
		}
		if (!call_user_func_array(array($stmt, 'bind_result'), $references)) {
			$stmt->close();
			throw new ClinicalSchemaException('database_result_bind_failed', 'Database metadata result could not be bound.');
		}
		$rows = array();
		while ($stmt->fetch()) {
			$row = array();
			foreach ($fields as $index => $field) {
				$row[$field->name] = $values[$index];
			}
			$rows[] = $row;
		}
		$stmt->close();
		return $rows;
	}

	public function queryOnePrepared($sql, $types, array $parameters)
	{
		$rows = $this->queryPrepared($sql, $types, $parameters);
		return count($rows) > 0 ? $rows[0] : null;
	}

	public function executePrepared($sql, $types, array $parameters)
	{
		$stmt = $this->prepareAndExecute($sql, $types, $parameters);
		$affected = $stmt->affected_rows;
		$stmt->close();
		return $affected;
	}

	public function executeDdl($sql)
	{
		if ($this->mysqli->query($sql) !== true) {
			throw new ClinicalSchemaException('ddl_step_failed', 'Schema DDL step failed.');
		}
	}

	public function begin()
	{
		if (!$this->mysqli->begin_transaction()) {
			throw new ClinicalSchemaException('ledger_transaction_failed', 'Ledger transaction could not begin.');
		}
	}

	public function commit()
	{
		if (!$this->mysqli->commit()) {
			throw new ClinicalSchemaException('ledger_commit_failed', 'Ledger transaction could not commit.');
		}
	}

	public function rollback()
	{
		$this->mysqli->rollback();
	}

	public function close()
	{
		if ($this->mysqli) {
			$this->mysqli->close();
			$this->mysqli = null;
		}
	}

	private function verifyIdentity($confirmedDatabase, $configuredUser, array $allowed, array $hardRejected)
	{
		$rows = $this->queryAll('SELECT DATABASE() AS database_name, CURRENT_USER() AS current_identity');
		if (count($rows) !== 1 || (string) $rows[0]['database_name'] !== $confirmedDatabase) {
			throw new ClinicalSchemaException('authenticated_database_mismatch', 'Authenticated database identity does not match confirmation.');
		}
		$current = (string) $rows[0]['current_identity'];
		$currentUser = explode('@', $current, 2)[0];
		foreach ($hardRejected as $rejected) {
			if (strcasecmp($currentUser, $rejected) === 0) {
				throw new ClinicalSchemaException('runtime_database_user_rejected', 'Application runtime database identity is prohibited.');
			}
		}
		if ($currentUser !== $configuredUser || !isset($allowed[$currentUser])) {
			throw new ClinicalSchemaException('authenticated_database_user_mismatch', 'Authenticated database identity is not the configured allowlisted migration identity.');
		}
		$this->databaseName = (string) $rows[0]['database_name'];
		$this->authenticatedUser = $current;
	}

	private function verifyInnoDb()
	{
		$rows = $this->queryAll("SELECT SUPPORT FROM information_schema.ENGINES WHERE ENGINE = 'InnoDB'");
		if (count($rows) !== 1 || !in_array(strtoupper((string) $rows[0]['SUPPORT']), array('YES', 'DEFAULT'), true)) {
			throw new ClinicalSchemaException('innodb_unavailable', 'InnoDB support is required.');
		}
	}

	private function prepareAndExecute($sql, $types, array $parameters)
	{
		$stmt = $this->mysqli->prepare($sql);
		if ($stmt === false) {
			throw new ClinicalSchemaException('database_statement_prepare_failed', 'Database operation could not be prepared.');
		}
		if ($types !== '') {
			$arguments = array($types);
			foreach ($parameters as $index => $value) {
				$arguments[] = &$parameters[$index];
			}
			if (!call_user_func_array(array($stmt, 'bind_param'), $arguments)) {
				$stmt->close();
				throw new ClinicalSchemaException('database_statement_bind_failed', 'Database operation parameters could not be bound.');
			}
		}
		if (!$stmt->execute()) {
			$stmt->close();
			throw new ClinicalSchemaException('database_statement_execute_failed', 'Database operation failed.');
		}
		return $stmt;
	}

	private static function environmentValue($name)
	{
		$value = getenv($name);
		return $value === false ? '' : (string) $value;
	}

	private static function readPasswordFromSafeTty()
	{
		if (DIRECTORY_SEPARATOR === '\\' || !function_exists('stream_isatty') || !stream_isatty(STDIN)) {
			return '';
		}
		$stty = trim((string) shell_exec('command -v stty 2>/dev/null'));
		if ($stty === '') {
			return '';
		}
		fwrite(STDERR, 'Migration database password: ');
		shell_exec($stty . ' -echo');
		try {
			$value = fgets(STDIN);
		} finally {
			shell_exec($stty . ' echo');
			fwrite(STDERR, PHP_EOL);
		}
		return $value === false ? '' : rtrim($value, "\r\n");
	}
}
