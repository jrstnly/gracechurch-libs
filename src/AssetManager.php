<?php

namespace GraceChurch;

class AssetManager {
	private $db;
	private $root;
	private $tools;

	function __construct() {
		$this->root = substr(dirname(__FILE__), 0, strpos(dirname(__FILE__), "gracechurch-")) . "../files/";
		$this->db = new DatabaseHandler("grace");
		$this->tools = new Tools();
	}

	private function uploadFile($filename, $temp, $args = null) {
		$parent = 0;
		$allowed_file_types = ['*'];
		if (is_array($args)) {
			if (array_key_exists('parent', $args)) { $parent = $args['parent']; }
			if (array_key_exists('allowed_file_types', $args)) { $allowed_file_types = $args['allowed_file_types']; }
		}

		$type = mime_content_type($temp);
		$extension = explode("/", $type)[1];
		if (in_array($extension, $allowed_file_types) || in_array('*', $allowed_file_types)) {
			$hash = sha1_file($temp);
			$folder = substr_replace(substr($hash, 0, 4), '/', 2, 0).'/';
			$enc_file = substr($hash, 4);
			$file = $folder.$enc_file;

			/* Create directory(s) if it does not exist */
			if (!is_dir($this->root.$folder)) { mkdir($this->root.$folder, 0777, true); }

			/* Deduplication */
			if (file_exists($this->root.$file)) {
				$fid = $this->getByLocation($file);
				$this->registerCDNAsset($fid, $file, $filename, $type);
				return $fid;
			} else {
				/* Move file to proper storage location and register asset */
				if (copy($temp, $this->root.$file)) {
					$fid = $this->register($filename, $file, $type, $parent);
					$this->registerCDNAsset($fid, $file, $filename, $type);
					return $fid;
				} else {
					return false;
				}
			}
		}
		unlink($temp);
	}

	private function getByLocation($location) {
		if ($result = $this->db->getRecords("SELECT id FROM asset_files WHERE file = '$location'")) {
			return $result[0]["id"];
		} else {
			return false;
		}
	}

	private function generateAccessKey($fid) {
		$key = $this->tools->generateRandomString(30);
		$data = array(
			'id'=>uniqid(),
			'access_key'=>$key,
			'asset_id'=>$fid,
			'accessed'=>0
		);
		$table_name = "asset_access_keys";
		$column_names = array('id', 'access_key', 'asset_id', 'accessed');
		$result = $this->db->insertIntoTable($data, $column_names, $table_name);
		return $key;
	}

	public function register($filename, $file, $type, $parent = '0') {
		$id = uniqid();
		$data = array(
			'id'=>$id,
			'filename'=>$filename,
			'file'=>$file,
			'parent'=>$parent,
			'type'=>$type,
			'registered'=>date("Y-m-d H:i:s")
		);
		$table_name = "asset_files";
		$column_names = array('id', 'filename', 'file', 'parent', 'type', 'registered');
		$result = $this->db->insertIntoTable($data, $column_names, $table_name);
		return $id;
	}
	public function registerCDNAsset($id, $file, $filename, $type) {
		$query = "INSERT INTO `asset_files_cdn` (`ID`,`File`,`Filename`,`Type`)
					VALUES ('$id','$file','$filename','$type')
					ON DUPLICATE KEY UPDATE `File`='$file', `Filename`='$filename', `Type`='$type';";
		$this->db->performQuery($query);
	}

	public function upload($filename, $temp, $args = null) {
		if ($fid = $this->uploadFile($filename, $temp, $args)) {
			$status = array('status' => 'success', 'access_key' => $this->getAccessKey($fid), 'filename' => $filename);
			return json_encode($status);
		} else {
			$status = array('status' => 'error');
			return json_encode($status);
		}
	}

	public function getAccessKey($fid) {
		if ($result = $this->db->getRecords("SELECT access_key FROM asset_access_keys WHERE asset_id = '$fid' LIMIT 1")) {
			return $result[0]["access_key"];
		} else {
			return $this->generateAccessKey($fid);
		}
	}

	public function getFile($fid) {
		$filename = $this->db->getOneRecord("SELECT file FROM asset_files WHERE id = '$fid'");
		$file = $this->root.$filename['file'];
		return ["type"=>mime_content_type($file),"content"=>readfile($file)];
	}

	/**
	 * Download remote file and register it as an asset
	 * @param array $args
	 * Options:
	 *   (string)filename - Set the filename of the asset
	 *   (string)parent - Set the parent of the asset
	 *   (array)allowed_file_types - Define the file types the system is allowed to download
	 */
	public function downloadFromRemoteServer($remote_file, $args = null) {
		/* Get file and store temporarily */
		$temp = sys_get_temp_dir() . "/" . uniqid();
		$ch = curl_init();
		$fp = fopen($temp, 'wb');
		curl_setopt_array($ch, array(
			CURLOPT_FILE => $fp,
			CURLOPT_URL => $remote_file,
			CURLOPT_HEADER => 0,
			CURLOPT_FOLLOWLOCATION => true
		));
		curl_exec($ch);
		curl_close($ch);
		fclose($fp);

		$type = mime_content_type($temp);
		$extension = explode("/", $type)[1];

		$filename = $this->tools->generateRandomString().'.'.$extension;
		if (is_array($args)) {
			if (array_key_exists('filename', $args)) { $filename = $args['filename']; }
		}

		return $this->uploadFile($filename, $temp, $args);
	}

}

?>
