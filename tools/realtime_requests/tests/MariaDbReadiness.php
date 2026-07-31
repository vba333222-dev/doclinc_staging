<?php

final class MariaDbReadinessResult
{
	public $connection;
	public $attempts;
	public $elapsed_ms;

	public function __construct($connection, $attempts, $elapsed_ms)
	{
		$this->connection = $connection;
		$this->attempts = (int) $attempts;
		$this->elapsed_ms = (int) $elapsed_ms;
	}
}

final class MariaDbReadiness
{
	public static function wait(array $config, ?callable $connector = null, ?callable $clock = null, ?callable $sleeper = null)
	{
		$timeout_ms = self::boundedInteger($config['timeout_ms'] ?? 45000, 100, 60000, 'readiness_timeout_invalid');
		$interval_ms = self::boundedInteger($config['interval_ms'] ?? 500, 10, 5000, 'readiness_interval_invalid');
		$clock = $clock ?: static function () { return (int) floor(hrtime(true) / 1000000); };
		$sleeper = $sleeper ?: static function ($milliseconds) { usleep((int) $milliseconds * 1000); };
		$connector = $connector ?: static function ($host, $user, $password, $port, $connect_timeout_seconds) {
			$connection = mysqli_init();
			if (!$connection) {
				throw new RuntimeException('database_connection_initialization_failed');
			}
			$connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, $connect_timeout_seconds);
			$connection->real_connect($host, $user, $password, '', $port);
			return $connection;
		};

		$host = (string) ($config['host'] ?? '127.0.0.1');
		$user = (string) ($config['user'] ?? 'root');
		$password = (string) ($config['password'] ?? '');
		$port = self::boundedInteger($config['port'] ?? 3306, 1, 65535, 'database_port_invalid');
		$started_at = (int) $clock();
		$deadline = $started_at + $timeout_ms;
		$attempts = 0;

		while (true) {
			$attempts++;
			$connection = null;
			try {
				$remaining_ms = max(1, $deadline - (int) $clock());
				$connect_timeout_seconds = max(1, min(5, (int) ceil($remaining_ms / 1000)));
				$connection = $connector($host, $user, $password, $port, $connect_timeout_seconds);
				if (!is_object($connection) || !method_exists($connection, 'query')
					|| (property_exists($connection, 'connect_errno') && (int) $connection->connect_errno !== 0)) {
					throw new RuntimeException('database_connection_not_ready');
				}
				$query = $connection->query('SELECT 1 AS ready_value');
				$row = is_object($query) && method_exists($query, 'fetch_assoc') ? $query->fetch_assoc() : null;
				if (is_object($query) && method_exists($query, 'close')) {
					$query->close();
				}
				if (!is_array($row) || (int) ($row['ready_value'] ?? 0) !== 1) {
					throw new RuntimeException('database_readiness_query_failed');
				}
				return new MariaDbReadinessResult($connection, $attempts, max(0, (int) $clock() - $started_at));
			} catch (Throwable $exception) {
				if (is_object($connection) && method_exists($connection, 'close')) {
					try { $connection->close(); } catch (Throwable $ignored) {}
				}
			}

			$now = (int) $clock();
			if ($now >= $deadline) {
				throw new RuntimeException('database_readiness_timeout');
			}
			$sleeper(min($interval_ms, $deadline - $now));
		}
	}

	private static function boundedInteger($value, $minimum, $maximum, $safe_error_code)
	{
		if (filter_var($value, FILTER_VALIDATE_INT) === false) {
			throw new InvalidArgumentException($safe_error_code);
		}
		$value = (int) $value;
		if ($value < $minimum || $value > $maximum) {
			throw new InvalidArgumentException($safe_error_code);
		}
		return $value;
	}
}
