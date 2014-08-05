<?php

require_once 'enum/McAPIVersion.class.php';
require_once 'enum/McAPIResult.class.php';
require_once 'enum/McLatencyAction.class.php';

require_once 'util/McLatency.class.php';

class McAPIPing {
    
    private $ip;
    private $port;
    private $timeout;
    
    private $_socket;    
    private $_data;

    private $_latency;

    private $result = array(
        'result'        => null,
        'hostname'		=> null,
        'version'		=> 0.0,
        'protocol'		=> 0.0,
        'players' => [	
            'max'		=> 0,
            'online'	=> 0
        ],
        'list' => [
            'motd'		=> null,
            'motdRaw'	=> null,
            'favicon'	=> null,
            'ping'		=> -1
        ]
    );
    
    public function __construct($ip, $port, $timeout = 2) {
        $this->ip = (substr_count($ip, '.') != 4 ? $ip : gethostbyaddr($ip));
        $this->port = $port;
        $this->timeout = $timeout;
        $this->_latency = new McLatency();

        if($this->connect() === false) {
            exit;
        }
    }
    
    public function fetch($version) {
        
        switch($version) {
            
            //1.7 and 1.8
            case McVersion::ONEDOTSEVEN || McVersion::ONEDOTEIGHT:
                
                $this->_latency->executeAction(McLatencyAction::START);
                
                //say hello to the server
                $handshake = pack('cccca*', hexdec(strlen($this->ip)), 0, 0x04, strlen($this->ip), $this->ip).pack('nc', $this->port, 0x01);
                $this->send($handshake, strlen($handshake), 0);
                $this->send("\x01\x00", 2, 0);
                $this->read(1);
                
                $this->_latency->executeAction(McLatencyAction::STOP);
                $this->_latency->executeAction(McLatencyAction::CALCULATE);
                
                $packetLength = $this->packetLength();

                if($packetLength < 10) {
                    return $this->setValue('result', McAPIResult::PACKET_TO_SHORT);
                }

                $this->read(1);
                $packetLength = $this->packetLength();
                $this->_data = $this->read($packetLength, PHP_NORMAL_READ);

                if(!($this->_data)) {
                    return $this->setValue('result', McAPIResult::FAILED_TO_READ_DATA);
                }
                
                $this->_data = json_decode($this->_data);

                $this->setValue('hostname',       $this->ip);
                $this->setValue('version',        $this->_data->version->name);
                $this->setValue('protocol',       $this->_data->version->protocol);
                $this->setValue('players.max',    $this->_data->players->online);
                $this->setValue('players.online', $this->_data->players->max);
                $this->setValue('list.motd',      self::clearColour($this->_data->description));
                $this->setValue('list.motdRaw',   $this->_data->description);
                $this->setValue('list.favicon',   $this->_data->favicon);
                $this->setValue('list.ping',      $this->_latency->getLatency());
                $this->setValue('result', McAPIResult::SUCCESSFULLY_DONE); 

            break;//1.7 and 1.8 
            
            case McVersion::ONEDOTTHREE:

                $this->_latency->executeAction(McLatencyAction::START); //start

                $this->send(chr(254).chr(1), 2, null);


            break; //1.3
            
        }
        
    }

    public function test() {
    	//header("Content-type: application/json");
    	print_r(json_encode($this->result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    
    private function send($buf, $length, $flags) {
        return socket_send($this->_socket, $buf, $length, $flags);
    }
    
    private function read($length, $type = PHP_BINARY_READ) {
        return socket_read($this->_socket, $length, $type);
    }
    
    private function receive($buf, $length, $flags = null) {
        return socket_recv($this->_socket, $buf, $length, $flags);
    }
    
    private function connect() {
        $this->_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        try {
            return socket_connect($this->_socket, $this->ip, $this->port);
        } catch(Exception $e) {
            return false;
        }

    }
    
    private function disconnect() {
        if(!(is_null($this->_socket))) {
            socket_close($this->_socket);
        }
    }
    
    private function packetLength() {
            $a = 0;
            $b = 0;
            while(true) {
                $c = $this->read(1);
 
                if(!$c) {
                    return 0;
                }
                $c = ord($c);
                $a |= ($c & 0x7F) << $b++ * 7;
                if( $b > 5 ) {
                    return false;
                }
                if(($c & 0x80) != 128) {
                    break;
                }
            }
            return $a;
    }
    
    private static function clearColour($motd) {
        $motd = preg_replace_callback('/\\\\u([0-9a-z]{3,4})/i', function ($matches) {
            return mb_convert_encoding(pack('H*', $matches[1]), 'UTF-8', 'UCS-2BE');
        }, $motd);
        $motd = preg_replace('/(§[0-9a-z])/i', '', $motd); //replace color codes
        $motd = preg_replace('/(\\\\n?\\\\r|\\\\n)/', '', $motd); //replace line breaks
        $motd = preg_replace('/(Â)?(«|»)?/', '', $motd); //replace more special chars
        $motd = preg_replace("/(\\\u[a-f0-9]{4})/", "iconv('UCS-4LE','UTF-8',pack('V', hexdec('U$1')))", $motd); //replace unicodes
        return (mb_detect_encoding($motd, 'UTF-8, ISO-8859-1') === 'UTF-8' ? $motd : utf8_decode($motd));
    }

    private function startLatencyListener() {
        $this->_start = microtime(true);
    }

    private function endLatenceListener() {
        $this->_latency = (microtime(true) - $this->_start);
    }
    
    protected function setValue($path, $value) {

        if(substr_count($path, '.')) {
            $split = explode('.', $path);
            $this->result[$split[0]][$split[1]] = $value;
            return true; 
        }
        
        $this->result[$path] = $value;
        return $value;
    }
    
}

?>