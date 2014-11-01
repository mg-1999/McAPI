<?php

require_once __DIR__ . '/enum/McAPIVersion.enum.php';
require_once __DIR__ . '/enum/McAPIResult.enum.php';
require_once __DIR__ . '/enum/McAPILatencyAction.enum.php';
require_once __DIR__ . '/enum/McAPIField.enum.php';
require_once __DIR__ . '/enum/McAPILatencyAction.enum.php';

require_once __DIR__ . '/util/McAPILatency.util.php';

class McAPIPing {

    private static $unicodes = [
        '\\\\u00c4' => 'A',
        '\\\\u00e4' => 'ä',
        '\\\\u00d6' => 'Ö',
        '\\\\u00f6' => 'ö',
        '\\\\u00dc' => 'Ü',
        '\\\\u00fc' => 'ü',
        '\\\\u00df' => 'ß'
    ];

    private $safeMode   = true;
    private $canRun     = true;

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
            'script'        => [
                'executionTime' => -1
            ]
        ],
        'hostname' => null,
        'software' => [
            'name'      => null,
            'version'   => 0.0
        ],
        'protocol'  => 0.0,
        'players'   => [
            'max'       => 0,
            'online'    => 0
        ],
        'list' => [
            'motd'      => null,
            'motdRaw'   => null,
            'favicon'   => null,
            'ping'      => -1
        ]
    );

    /**
    *   @param string $ip The ip address of the requested server
    *   @param int    $port The port of the requested server
    *   @param int    $timeout The time that have the server to answer the request
    *   @param bool $safeMode The save mode protects you for issues
    */
    public function __construct($ip, $port = 25565, $timeout = 2, $safeMode = true) {

        $this->safeMode = $safeMode;

        $this->ip = (substr_count($ip, '.') != 4 ? $ip : gethostbyaddr($ip));
        $this->port = $port;
        $this->timeout = $timeout;
        $this->_latency = new McAPILatency();

        if (($this->canRun = $this->connect()) === false) {
            return $this->setResult(McAPIResult::CANT_CONNECT);
        }
    }

    /**
     * Fetching information from the given Minecraft server
     * @param String|McAPIVersion $version  The Minecraft-Version depends on the used version on the request server. If it's null the script tries to calculate the version, but this can takes a while.
     * @param bool $userVersion If it true the script tries to read the given version
     * @return bool|void If it true the fetch worked successfully and it returns data
     */
    public function fetch($version = null, $userVersion = false) {

        if($this->safeMode && !($this->canRun)) {
            return $this->setResult(McAPIResult::CANT_CONNECT);
        }

        if(is_null($version)) {
            return $this->calculateVersion(); //fetching data with unknown version
        }

        if($userVersion) {
            
            $version = McAPIVersion::getVersion($version);

        }

        switch ($version) {

            //1.7 and 1.8 but I will add a own script-part for 1.8 if the protocol changed maybe
            case McAPIVersion::ONEDOTEIGHT:
            case McAPIVersion::ONEDOTSEVEN:

                $this->_latency->executeAction(McAPILatencyAction::START);

                //say hello to the server
                $handshake = pack('cccca*', hexdec(strlen($this->ip)), 0, 0x04, strlen($this->ip), $this->ip) . pack('nc', $this->port, 0x01);
                $this->send($handshake, strlen($handshake), 0);
                $this->send("\x01\x00", 2, 0);

                //get the packet-length
                $packetLength = $this->packetLength();

                //check the length of packet
                if ($packetLength < 10) {
                    return $this->setResult(McAPIResult::PACKET_TO_SHORT);
                }


                //Stops the Latency-Listener
                $this->_latency->executeAction(McAPILatencyAction::STOP);
                //Calculates the needed time for execute this script
                $this->_latency->executeAction(McAPILatencyAction::CALCULATE);

                //read the datas
                $this->read(1);
                $packetLength = $this->packetLength();
                $this->_data = $this->read($packetLength, PHP_NORMAL_READ);

                //validate the datas
                if (!($this->_data)) {
                    return $this->setResult(McAPIResult::FAILED_TO_READ_DATA);
                }

                //decode the received datas
                $this->_data = json_decode($this->_data);

                if(empty($this->_data)) {
                    return $this->setResult(McAPIResult::EMPTY_RESULT);
                }

                //set values
                $this->setValue('hostname', $this->ip);

                $versionSplit = explode(' ', $this->_data->version->name);
                $this->setValue('software.name', (count($versionSplit) >= 2 ? $versionSplit[0] : null) );
                $this->setValue('software.version', (count($versionSplit) >= 2 ? $versionSplit[1] : $this->_data->version->name));

                $this->setValue('protocol', (int) $this->_data->version->protocol);
                $this->setValue('players.max', $this->_data->players->max);
                $this->setValue('players.online', $this->_data->players->online);
                $this->setValue('list.motd', self::clearColour($this->_data->description));
                $this->setValue('list.motdRaw', $this->_data->description);
                $this->setValue('list.favicon', (isset($this->_data->favicon) ? $this->_data->favicon : null));
                $this->setValue('list.ping', $this->_latency->getLatency());

                $this->setResult(McAPIResult::SUCCESSFULLY_DONE);

                return true; //1.7 and 1.8 

            //1.6
            case McAPIVersion::ONEDOTSIX:

                //Starts the Latency-Listener
                $this->_latency->executeAction(McAPILatencyAction::START); //start

                //Opens the connection
                $handle = fsockopen($this->ip, $this->port, $eerno, $errstr, $this->timeout);

                if (!($handle)) {
                    return $this->setResult(McAPIResult::CANT_CONNECT);
                }
                
                //Set timeout
                stream_set_timeout($handle, $this->timeout);

                //Send the packet
                fwrite($handle, "\xFE\x01");

                //read the return
                $data = fread($handle, 1024);
                
                //validate datas
                if ($data === false && substr($data, 0, 1) == "\xFF") {
                    return $this->setResult(McAPIResult::FAILED_TO_READ_DATA);
                }

                $data = substr($data, 3);
                $data = mb_convert_encoding($data, 'auto', 'UCS-2');
                $data = explode("\x00", $data);

                //Stopss the Latency-Listener
                $this->_latency->executeAction(McAPILatencyAction::STOP);
                //Calculates the needed time for execute this script
                $this->_latency->executeAction(McAPILatencyAction::CALCULATE);

                $this->_data = $data;

                if(count($data) === 1) {
                    return $this->setResult(McAPIResult::EMPTY_RESULT);
                }

                //setvalues
                $this->setValue('hostname', $this->ip);

                $versionSplit = explode(' ', $this->_data[2]);
                $this->setValue('software.name', (count($versionSplit) >= 2 ? $versionSplit[0] : null) );
                $this->setValue('software.version', (count($versionSplit) >= 2 ? $versionSplit[1] : $this->_data[2]));

                $this->setValue('protocol', (int) $this->_data[1]);

                $this->setValue('players.max', $this->_data[4]);
                $this->setValue('players.online', $this->_data[5]);
                
                $this->setValue('list.motd', self::clearColour($this->_data[3]));
                $this->setValue('list.motdRaw', $this->_data[3]);
                $this->setValue('list.ping', $this->_latency->getLatency());
                
                $this->setResult(McAPIResult::SUCCESSFULLY_DONE);

                return true; //1.6

            default: return false;
        }

    }

    /**
    * @param McAPIField, array $field The requested Datas
    */
    public function get($field = null) {

        if(is_null($field)) {
            return $this->result;
        }

        if(is_array($field)) {

            $result = array();

            foreach ($field as $f) {
    
                $split = explode('_', strtolower($f));
                $running = $this->result;

                foreach ($split as $key) {
                    $running = $running[$key];
                }

                $result[$f] = $running;

            }

            return $result;

        }

        $split = explode('_', strtolower($field));
        $result = $this->result;
        
        foreach ($split as $key) {
            $result = $result[$key];
        }
    
        return $result;        

    }

    private function calculateVersion() {
        $reflection = new ReflectionClass('McAPIVersion');

        foreach($reflection->getConstants() as $version) {

            if($version === McAPIVersion::TEST_VERSION) {
                continue;
            }

            if($this->fetch($version)) {
                return true;
            }

        }

        return false;
    }

    public function getError() {
        return self::arrayToObject([
                "id" => socket_last_error($this->_socket),
                "description" => socket_strerror(socket_last_error($this->_socket))
            ]);
    }

    private function setResult($result) {
        $this->setValue('result.status', $result);
        $this->setValue('result.connection', str_replace('operation', 'connection', $this->getError()->description));
        $this->setValue('result.script', [
            'executionTime' => (double) number_format((microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"]) * 1000, 0)
        ]);
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
            $this->_socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            return @socket_connect($this->_socket, $this->ip, $this->port);
        } catch (Exception $e) {
            return false;
        }
    }

    private function disconnect() {
        if (!(is_null($this->_socket))) {
            @socket_close($this->_socket);
        }
    }

    private function packetLength($read = 1) {
        $a = 0;
        $b = 0;
        while (true) {
            $c = $this->read($read);

            if (!$c) {
                return 0;
            }
            $c = ord($c);
            $a |= ($c & 0x7F) << $b++ * 7;
            if ($b > 5) {
                return false;
            }
            if (($c & 0x80) != 128) {
                break;
            }
        }
        return $a;
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

    private static function clearColour($motd) {

        $parts = explode('\n', json_encode($motd));

        for($i = 0; $i < count($parts); $i++) {

            $temp = preg_replace('/([&§][0-9a-z])/', '', $parts[$i]); //remove colour codes

            foreach(self::$unicodes as $unicode => $char) {
                $temp = preg_replace('/(' . $unicode . ')/', $char, $temp);
            }

            $temp = preg_replace('/((\\\\u)([a-zA-Z0-9]{1,5}))/', '', $temp); //remove unicodes
            $temp = preg_replace("/(\")/", '', $temp); //remove useless quotes
            $temp = preg_replace("/(  )/", ' ', $temp); //remove useless spaces
            $temp = preg_replace("/(\\\\\\/)/", '/', $temp); //remove useless backslahes

            $parts[$i] = utf8_decode($temp);

        }

        return $parts;
    }

    private static function arrayToObject($array) {
        $object = new stdClass();

        foreach ($array as $key => $value) {
            $object->$key = $value;
        }

        return $object;
    }

}

?>