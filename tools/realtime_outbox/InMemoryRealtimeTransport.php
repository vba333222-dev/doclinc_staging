<?php

require_once __DIR__ . '/RealtimeTransport.php';

final class InMemoryRealtimeTransport implements RealtimeTransport
{
	private $results;
	private $published = array();

	public function __construct(array $results = array())
	{
		$this->results = array_values($results);
	}

	public function publish(array $message)
	{
		$this->published[] = $message['idempotency_key'];
		if (!empty($this->results)) {
			$result = array_shift($this->results);
			if ($result instanceof RealtimeTransportResult) {
				return $result;
			}
		}
		return RealtimeTransportResult::success();
	}

	public function publishCount()
	{
		return count($this->published);
	}

	public function publishedKeys()
	{
		return $this->published;
	}
}
