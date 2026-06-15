<?php
defined('BASEPATH') or exit('No direct script access allowed');

class ApiClient
{
	private $ci;
	private $accessToken;

	public function __construct()
	{
		$this->ci = &get_instance();
		$this->accessToken = $this->ci->config->item('api_access_token'); // Ambil token dari config
	}

	public function getData($url, $params = [])
	{
		$ch = curl_init();

		// Tambahkan parameter ke URL jika ada
		$queryString = http_build_query($params);
		$finalUrl = $queryString ? "$url?$queryString" : $url;

		curl_setopt($ch, CURLOPT_URL, $finalUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Authorization: ' . $this->accessToken // Tambahkan access token di header
		]);

		$response = curl_exec($ch);
		curl_close($ch);

		return json_decode($response, true);
	}
}
