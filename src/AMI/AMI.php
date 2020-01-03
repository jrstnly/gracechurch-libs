<?php

namespace GraceChurch\AMI;


class AMI {
	private $socket;
	private $timeout = 10;

	public function __construct($host, $user, $pass) {
		$this->socket = fsockopen($host,"5038", $errno, $errstr, $this->timeout);
		fputs($this->socket, "Action: Login\r\n");
		fputs($this->socket, "Username: $user\r\n");
		fputs($this->socket, "Secret: $pass\r\n");
		fputs($this->socket, "Events: off\r\n\r\n");
	}
	public function __destruct()  { fclose($this->socket); }

	public function originate($from, $to, $caller_id_name, $caller_id_number) {
		fputs($this->socket, "Action: Originate\r\n" );
		fputs($this->socket, "Channel: Local/$from@from-internal\r\n" );
		fputs($this->socket, "Exten: $to\r\n" );
		fputs($this->socket, "Context: from-internal\r\n" );
		fputs($this->socket, "Priority: 1\r\n" );
		fputs($this->socket, "Callerid: $caller_id_name<$caller_id_number>\r\n");
		fputs($this->socket, "Async: yes\r\n\r\n" );
	}

}
?>
