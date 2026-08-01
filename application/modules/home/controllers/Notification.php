<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Compatibility tombstone for the retired Firebase notification surface.
 *
 * Durable notifications are delivered through notification_helper and the
 * realtime outbox. Keeping these method names as explicit 404 responses
 * prevents CI3 implicit routing from reviving the legacy sender.
 */
class Notification extends CI_Controller
{
	public function index()
	{
		show_404();
	}

	public function test()
	{
		show_404();
	}

	public function send()
	{
		show_404();
	}

	public function service_worker()
	{
		show_404();
	}
}
