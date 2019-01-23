<?php

namespace GraceChurch;

class DatabaseHandler {
  private $conn;

  function __construct($db_name) {
    require_once 'dbConnect.php';
    // opening db connection
    $db = new dbConnect();
    $this->conn = $db->connect($db_name);
    $this->conn->set_charset("utf8mb4");
    $this->conn->query("SET collation_connection = utf8mb4_unicode_ci");
  }

	public function sanitize($input) {
		return $this->conn->real_escape_string($input);
	}

	public function getOneRecord($query) {
    $r = $this->conn->query($query.' LIMIT 1') or die($this->conn->error.__LINE__);
    return $result = $r->fetch_assoc();
  }
  public function getRecords($query) {
		$return = array();
    $r = $this->conn->query($query) or die($this->conn->error.__LINE__);
    while ($result = $r->fetch_assoc()) {
      array_push($return, $result);
		}
	  return $return;
  }
  public function getNameValueRecords($query) {
		$return = array();
    $r = $this->conn->query($query) or die($this->conn->error.__LINE__);
    while ($result = $r->fetch_assoc()) {
      $return[$result['Name']] = $result['Value'];
		}
	  return $return;
  }
  public function getRecordsWithPermission($query) {
		$return = array();
    $r = $this->conn->query($query) or die($this->conn->error.__LINE__);
    while ($result = $r->fetch_assoc()) {
      array_push($return, $result);
		}
	  return $return;
  }
  public function performQuery($query) {
    return $this->conn->query($query) or die($this->conn->error.__LINE__);
  }
  public function insertID() {
    return $this->conn->insert_id;
  }
  /**
  * Creating new record
  */
  public function insertIntoTable($obj, $column_names, $table_name) {
    $c = (array) $obj;
    $keys = array_keys($c);
    $columns = '';
    $values = '';
    foreach($column_names as $desired_key){ // Check the obj received. If blank insert blank into the array.
      if(!in_array($desired_key, $keys)) {
        $$desired_key = '';
      } else {
        $$desired_key = $c[$desired_key];
      }
      $columns = $columns.$desired_key.',';
      $values = $values."'".$$desired_key."',";
    }
    $query = "INSERT INTO ".$table_name." (".trim($columns,',').") VALUES(".trim($values,',').")";
    $r = $this->conn->query($query) or die($this->conn->error.__LINE__);
    //$r = $this->conn->query($query) or die($query);

    if ($r) {
      //$new_row_id = $this->conn->insert_id;
      //return $new_row_id;
      return true;
    } else {
      return false;
    }
  }
}

?>
