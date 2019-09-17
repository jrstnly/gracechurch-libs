<?php

namespace GraceChurch;

class Tools {

	public function isAssoc(array $arr) {
		if (array() === $arr) return false;
		return array_keys($arr) !== range(0, count($arr) - 1);
	}

	public function generateRandomString($length = 32) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}

	public static function LogIt($message, $type = "INFO", $stdout = false) {
		$prefix = "";
		switch ($type) {
			case "ERROR":
				$prefix = "\e[0;31m[ERROR]\e[0m ";
				break;
			case "WARNING":
				$prefix = "\e[1;33m[WARNING]\e[0m ";
				break;
			case "SUCCESS":
				$prefix = "\e[0;32m[SUCCESS]\e[0m ";
				break;
			case "COMPLETE":
				$prefix = "\e[0;32m[COMPLETE]\e[0m ";
				break;
			case "INFO":
				$prefix = "\e[0;34m[INFO]\e[0m ";
				break;
		}
		error_log($prefix.$message, 0);
		//if ($stdout) echo $prefix.$message."\n";
	}

}

?>
