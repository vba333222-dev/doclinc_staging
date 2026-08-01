<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Compatibility tombstone for the duplicate legacy chat upload controller.
 * Authorized uploads are handled only by Chat::foto().
 */
class Upload extends CI_Controller
{
	public function index()
	{
		show_404();
	}

	public function foto()
	{
		show_404();
	}

	public function video()
	{
		show_404();
	}
}
