<?php

namespace GraceChurch\CCB;

use GraceChurch\DatabaseHandler;
use GraceChurch\AssetManager;

class CCBv2 {
	private $db;
	private $am;
	private $curl;
	private $church;
	private $base_url;
	private $headers = ['Accept: application/vnd.ccbchurch.v2+json'];
	private $debug = false;

	/**
	 * Initialize the CCB connection (Only after first OAuth step)
	 * @param string $church (Your Subdomain)
	 * @param string $client (OAuth Client ID)
	 * @param string $secret (OAuth Client Secret)
	 * @param string $code (OAuth Client Code - retrieved in first OAuth step)
	 */
	public function __construct($church, $client, $secret, $code) {
		$this->db = new DatabaseHandler("grace");
		$this->am = new AssetManager;
		$this->church = $church;
		$this->base_url = "https://" . $this->church . ".ccbchurch.com/api";
		$this->curl = curl_init();
	}
	public function __destruct()  { curl_close($this->curl); }

	private function getToken() {
		$code_key = 'API-v2-Code';
		$token_key = 'API-v2-Token';
		$token_expiration_key = 'API-v2-Token-Expiration';
		$token_data = $this->db->getNameValueRecords("SELECT * FROM `ccb_settings` WHERE `Name`='$code_key' OR `Name`='$token_key' OR `Name`='$token_expiration_key'");

		$tz = new \DateTimeZone("GMT");
		$now = new \DateTime("now", $tz);
		$expires = \DateTime::createFromFormat(\DateTime::ISO8601, $token_data[$token_expiration_key], $tz);
		if ($expires) $remaining = $expires->getTimestamp() - $now->getTimestamp();

		if (isset($token_data[$token_key]) && isset($token_data[$token_expiration_key]) && $token_data[$token_key] != "" && $expires && $remaining > 300) {
			return $token_data[$token_key];
		} else {
			$parameters = [
				"grant_type" => "client_credentials",
				"subdomain" => "atgrace",
				"client_id" => CCB_CLIENT_ID,
				"client_secret" => CCB_CLIENT_SECRET,
				"code" => $token_data[$code_key]
			];
			curl_setopt_array($this->curl, array(
				CURLOPT_HTTPHEADER => $this->headers,
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_URL => "https://api.ccbchurch.com/oauth/token",
				CURLOPT_POST => $this->buildPOST($parameters, 'count'),
				CURLOPT_POSTFIELDS => $this->buildPOST($parameters)
			));
			$data = json_decode(curl_exec($this->curl));

			$token = $data->access_token;
			$now = new \DateTime("now", $tz);
			$expiration = $now->add(new \DateInterval('PT'.$data->expires_in.'S'))->format(\DateTime::ISO8601);
			$this->db->performQuery("INSERT INTO ccb_settings (Name,Value) VALUES('$token_key', '$token') ON DUPLICATE KEY UPDATE Value='$token'");
			$this->db->performQuery("INSERT INTO ccb_settings (Name,Value) VALUES('$token_expiration_key', '$expiration') ON DUPLICATE KEY UPDATE Value='$expiration'");

			return $token;
		}

	}

	private function buildGET($route, $parameters = null) {
		$url = $this->base_url . $route;
		if ($parameters && is_array($parameters)) {
			$url .= '?';
			$count = 0;
			foreach ($parameters as $key => $value) {
				$count++;
				$url .= $key . '=' . urlencode($value);
				if ($count < count($parameters)) $url .= '&';
			}
		}
		return $url;
	}
	private function buildPOST($parameters, $return = NULL) {
		$data = "";
		foreach ($parameters as $keyA => $value) {
			if (is_array($value)) {
				foreach ($value as $keyB => $val) {
					$data .= '&' . $keyA . '[' . $keyB . ']=' . urlencode($val);
				}
			} else {
				$data .= '&' . $keyA . '=' . urlencode($value);
			}
		}
		if ($return == 'count') { return count(explode('&', $data)) - 1; }
		else { return $data; }
	}
	private function buildFILE($file) {
		return curl_file_create($file, mime_content_type($file), basename($file));
	}

	private function get($route, $parameters = NULL) {
		curl_setopt_array($this->curl, array(
			CURLOPT_HTTPHEADER => [...$this->headers, 'Authorization: Bearer '.$this->getToken()],
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_URL => $this->buildGET($route, $parameters)
		));
		$ret = $this->execute($this->curl);
		return $ret["body"];
	}
	private function post($srv, $parameters, $get_params = null) {
		if ($get_params != null) $url = $this->buildGET($srv, $get_params);
		else $url = $this->buildGET($srv);
		curl_setopt_array($this->curl, array(
			CURLOPT_HTTPHEADER => [...$this->headers, 'Authorization: Bearer '.$this->getToken()],
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_URL => $url,
			CURLOPT_POST => $this->buildPOST($parameters, 'count'),
			CURLOPT_POSTFIELDS => $this->buildPOST($parameters)
		));
		$ret = $this->execute($this->curl);
		return $ret["body"];
	}
	private function send($srv, $file) {
		curl_setopt_array($this->curl, array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_URL => $this->buildGET($srv),
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => array('filedata' => curl_file_create($file,mime_content_type($file),basename($file)))
		));
		$ret = $this->execute($this->curl);
		return $ret["body"];
	}
	private function execute($ch) {
		$ret = curl_exec($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		$headers = [];
		$header_len = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$header_data = explode("\n", substr($ret, 0, $header_len));
		array_shift($header_data); // Remove status code from headers
		$body = substr($ret, $header_len);
		$return = ["code"=>$code,"headers"=>$headers,"body"=>json_decode($ret)];

		return $return;
	}
	public function parseDate($date) {
		if ($date == '0000-00-00 00:00:00') $date = '1970-01-01 00:00:00';
		return date_create_from_format("Y-m-d H:i:s", $date);
	}



	/***************** INDIVIDUAL PROFILE SERVICES *****************/
	public function getIndividuals() {
		return $this->get("/individuals");
	}
	public function getIndividual($id) {
		return $this->get("/individuals/".$id);
	}

}
?>
