<?php

namespace GraceChurch\Zebra;

class ZebraPrinter
{
    /**
     * The endpoint.
     *
     * @var resource
     */
    protected $socket;

    /**
     * Create an instance.
     *
     * @param string $host
     * @param int $port
     */
    public function __construct($host, $port = 9100)
    {
        $this->connect($host, $port);
    }

    /**
     * Destroy an instance.
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Create an instance statically.
     *
     * @param string $host
     * @param int $port
     * @return self
     */
    public static function printer($host, $port = 9100)
    {
        return new static($host, $port);
    }

    /**
     * Connect to printer.
     *
     * @param string $host
     * @param int $port
     * @return bool
     * @throws ZebraCommunicationException if the connection fails.
     */

    protected function connect($host, $port)
    {
        $this->socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array("sec"=>2, "usec"=>0));
        if (!$this->socket || !@socket_connect($this->socket, $host, $port)) {
            $error = $this->getLastError();
            throw new ZebraCommunicationException($error['message'], $error['code']);
        }
    }
    /*
    protected function connect($host, $port, $timeout = 10, $send_timeout = 2, $recv_timeout = 2)
    {
      $success = false;
      $this->socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
      socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, array("sec"=>$send_timeout, "usec"=>0));
      socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array("sec"=>$recv_timeout, "usec"=>0));
      socket_set_nonblock($this->socket);
      for($i=0; $i<($timeout * 1); $i++) {
        $connected = socket_connect($this->socket, $host, $port);
        echo $connected."\n";
        if ($connected) {
          $success = true;
          break 1;
        }
        usleep(1000000);
      }
      socket_set_block($this->socket);

      if (!$success) {
        $error = $this->getLastError();
        throw new ZebraCommunicationException($error['message'], $error['code']);
      }
    }
    */

    /**
     * Close connection to printer.
     */
    protected function disconnect()
    {
        @socket_close($this->socket);
    }

    /**
     * Send ZPL data to printer.
     *
     * @param string $zpl
     * @throws ZebraCommunicationException if writing to the socket fails.
     */
    public function send($zpl)
    {
        if (false === @socket_write($this->socket, $zpl)) {
            $error = $this->getLastError();
            throw new ZebraCommunicationException($error['message'], $error['code']);
        } else {
          if ($buf = socket_read($this->socket, 2048)) {
            return $buf;
          }
        }
    }

    /**
     * Get the last socket error.
     *
     * @return array
     */
    protected function getLastError()
    {
        $code = socket_last_error($this->socket);
        $message = socket_strerror($code);

        return compact('code', 'message');
    }
}

?>
