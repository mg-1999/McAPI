<?php

class McAPIQuery {

	const STATISTIC = 0x00;
	const HANDSHAKE = 0x09;

	private $canRun = true;

    private $ip;
    private $port;
    private $timeout;
    private $_socket;
    private $_data;
    private $_latency;

    private $result = array(
        'result' => [
            'status'        => null,
            'connection'    => null,
        ],
        'hostname' => null,
        'software' => [
            'name'      => null,
            'version'   => 0.0
        ],
        'protocol' => 0.0,
        'players' => [
            'max' => 0,
            'online' => 0
        ],
        'list' => [
            'motd' => null,
            'motdRaw' => null,
            'favicon' => null,
            'ping' => -1
        ]
    );

    public function __construct($ip, $port, $timeout = 2) {
        $this->safeMode = $safeMode;

        $this->ip = (substr_count($ip, '.') != 4 ? $ip : gethostbyaddr($ip));
        $this->port = $port;
        $this->timeout = $timeout;
        $this->_latency = new McAPILatency();
    
        if(($this->canRun = $this->connect()) === false) {
        	echo "[1]<br />";
        }

    }

    public function fetch() {

    	if(!($this->canRun)) {
    		echo "[2]<br />";
    		return;
    	}

    	//define session id
    	$sessionID = rand(1, 0xFFFFFFFF) & 0x0F0F0F0F;

    	$this->_latency->executeAction(McAPILatencyAction::START);

    	//send handshake
    	$handshake = pack('cccN', 0xFE, 0xFD, 9, $sessionID);
    	$this->send($handshake, 9);

    	$this->_latency->executeAction(McAPILatencyAction::STOP);
    	$this->_latency->executeAction(McAPILatencyAction::CALCULATE);

    	$request = pack('cccNNN', 0xFE, 0xFD, 0, $sessionID, 0);

    }

    private function send($buf, $length, $flags) {
        return @socket_send($this->_socket, $buf, $length, $flags);
    }

    private function read($length, $type = PHP_BINARY_READ) {
        return @socket_read($this->_socket, $length, $type);
    }

    private function receive($buf, $length, $flags = null) {
        return @socket_recv($this->_socket, $buf, $length, $flags);
    }

    private function connect() {
        try {
            $this->_socket = @socket_create(AF_INET, SOCK_STREAM, SOL_UDP);
            return @socket_connect($this->_socket, $this->ip, $this->port);
        } catch (Exception $e) {
            return false;
        }    	
    }

    private function setValue($path, $value) {

        if (substr_count($path, '.')) {
            $split = explode('.', $path);
            $this->result[$split[0]][$split[1]] = $value;
            return true;
        }

        $this->result[$path] = $value;
        return $value;
    }

?>