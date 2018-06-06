<?php
/**
 * Simple server class which manage WebSocket protocols
 * @author Sann-Remy Chea <http://srchea.com>
 * @license This program is free software: you can redistribute it and/or modify
 * 	it under the terms of the GNU General Public License as published by
 * 	the Free Software Foundation, either version 3 of the License, or
 * 	(at your option) any later version.
 * 	This program is distributed in the hope that it will be useful,
 * 	but WITHOUT ANY WARRANTY; without even the implied warranty of
 * 	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * 	GNU General Public License for more details.
 * 	You should have received a copy of the GNU General Public License
 * 	along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * @version 1.0.0
 */

namespace PushWebSocket;

class Server {

	/**
	 * The address of the server
	 * @var String
	 */
	private $address;

	/**
	 * The port for the master socket
	 * @var int
	 */
	private $port;

	/**
	 * The master socket
	 * @var Resource
	 */
	private $master;

	/**
	 * The array of sockets (1 socket = 1 client)
	 * @var Array of resource
	 */
	private $sockets;

	/**
	 * The array of connected clients
	 * @var Array of clients
	 */
	private $clients;

	/**
	 * If true, the server will print messages to the terminal
	 * @var Boolean
	 */
	private $verboseMode;

	private $bytes = 0;

	/**
	 * Server constructor
	 * @param $address The address IP or hostname of the server (default: 127.0.0.1).
	 * @param $port The port for the master socket (default: 5001)
	 */
	public function __construct($address = '127.0.0.1', $port = 5001, $verboseMode = false) {
		$this->console("Server starting...");
		$this->address = $address;
		$this->port = $port;
		$this->verboseMode = $verboseMode;

		// socket creation
		$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

		if(!is_resource($socket)) {
			$this->console("socket_create() failed: ".socket_strerror(socket_last_error()), true);
		}

		if(!socket_bind($socket, $this->address, $this->port)) {
			$this->console("socket_bind() failed: ".socket_strerror(socket_last_error()), true);
		}

		if(!socket_listen($socket, 20)) {
			$this->console("socket_listen() failed: ".socket_strerror(socket_last_error()), true);
		}

		$this->master = $socket;
		$this->sockets = array($socket);
		$this->console("Server started on {$this->address}:{$this->port}");
	}

	/**
	 * Create a client object with its associated socket
	 * @param $socket
	 */
	private function connect($socket) {
		if (is_null($socket)){

			return;
		}

		$this->console("Creating client...");
		$client = new \PushWebSocket\Client(uniqid(), $socket);
		$this->clients[] = $client;
		$this->sockets[] = $socket;
		$this->console("Client #{$client->getId()} is successfully created!");
	}


    /**
     * @param $headers
     * @return bool
     */
    private function requestFilter($headers)
    {
        $this->console("Getting client WebSocket version...");

        if(preg_match("/Sec-WebSocket-Version: (.*)\r\n/", $headers, $match)) {
            $version = $match[1];
        }
        else {
            $this->console("The client doesn't support WebSocket");
            return false;
        }

        if($version != 13) {
            $this->console("WebSocket version 13 required (the client supports version {$version})");
            return false;
        }

        $this->console("Client WebSocket version is {$version}, (required: 13)");

        return true;
	}

    /**
     * Do the handshaking between client and server
     * @param Client $client
     * @param string $headers
     * @return bool
     */
	private function handshake($client, $headers) {

		$upgrade = $this->handshakeBuilder($headers);

		$this->console("Sending this response to the client #{$client->getId()}:\r\n".$upgrade);

		socket_write($client->getSocket(), $upgrade);

		$client->setHandshake(true);

		$this->console("Handshake is successfully done!");

		return true;

	}

    private function handshakeBuilder($headers)
    {
        $this->console("Generating Sec-WebSocket-Accept key...");
        $acceptKey = $this->getWebSocketKeys($headers).'258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
        $acceptKey = base64_encode(sha1($acceptKey, true));

        return "HTTP/1.1 101 Switching Protocols\r\n".
            "Upgrade: websocket\r\n".
            "Connection: Upgrade\r\n".
            "Sec-WebSocket-Accept: $acceptKey".
            "\r\n\r\n";
	}

    private function getWebSocketKeys($headers)
    {
        if(preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $headers, $match)){

            return $match[1];
		}
	}

    private function getHandshakeInfo($headers)
    {
        // Extract header variables
		$this->console("Getting headers...");
		if(preg_match("/GET (.*) HTTP/", $headers, $match))
			$root = $match[1];
		if(preg_match("/Host: (.*)\r\n/", $headers, $match))
			$host = $match[1];
		if(preg_match("/Origin: (.*)\r\n/", $headers, $match))
			$origin = $match[1];

		$key = $this->getWebSocketKeys($headers);

		$this->console("Client headers are:");
		$this->console("\t- Root: ".$root);
		$this->console("\t- Host: ".$host);
		$this->console("\t- Origin: ".$origin);
		$this->console("\t- Sec-WebSocket-Key: ".$key);

    }

	/**
	 * Disconnect a client and close the connection
	 * @param Client $client
	 */
	private function disconnect($client)
	{
		$this->console("Disconnecting client #{$client->getId()}");

		$client->setIsConnected(false);

		$i = array_search($client, $this->clients);
		$j = array_search($client->getSocket(), $this->sockets);

		if($j >= 0) {
			if($client->getSocket()) {
				array_splice($this->sockets, $j, 1);
				socket_shutdown($client->getSocket(), 2);
				socket_close($client->getSocket());
				$this->console("Socket closed");
			}
		}

		if($i >= 0) {
			array_splice($this->clients, $i, 1);
		}

		$this->console("Client #{$client->getId()} disconnected");
	}

	/**
	 * Get the client associated with the socket
	 * @param $socket
	 * @return Client|Null A client object if found, if not false
	 */
	private function getClientBySocket($socket)
	{
		foreach($this->clients as $client) {

			/** @var Client $client */
            if ($client->getSocket() == $socket) {

                $this->console("Client found");

                return $client;
            }
        }
		return null;
	}

	/**
	 * Do an action
	 * @param $client
	 * @param $action
	 */
	private function action($client, $action)
	{
		$action = $this->unmask($action);

		$this->console("Performing action: ".$action);

		if($action == "exit" || $action == "quit") {

			$this->console("Killing a child process");

			posix_kill($client->getPid(), SIGTERM);

			$this->console("Process {$client->getPid()} is killed!");
		}
	}

	/**
	 * Run the server
	 */
	public function run() {

		$this->console("Start running...");

		$this->console("Open in a browser: websocket_client.html (http)");

		while(true) {

			$changed_sockets = $this->sockets;

			if(empty($changed_sockets)) {

				continue;
			}

			@socket_select($changed_sockets, $write = NULL, $except = NULL, 1);

			foreach($changed_sockets as $socket) {

				if($socket == $this->master) {

                    $acceptedSocket = $this->getAcceptedSocket();

                    $this->connect($acceptedSocket);

					continue;
				}

				$this->connectClient($socket);
			}

		}
	}

    private function getAcceptedSocket()
    {
        if(($acceptedSocket = socket_accept($this->master)) < 0) {

            $this->console("Socket error: ".socket_strerror(socket_last_error($acceptedSocket)));

            return false;
        }

		return $acceptedSocket;
	}

    private function connectClient($socket)
    {
        $this->console("Finding the socket that associated to the client...");

        $client = $this->getClientBySocket($socket);

        if(!$client) {

        	return;
        }

        $this->console("Receiving data from the client");

        $data = $this->collectData($socket);

        if(!$client->getHandshake()) {

            $this->attemptHandshake($client,$data);

            return;
        }

        if($this->bytes === 0) {

            $this->disconnect($client);

            return;
        }

        // When received data from client
        $this->action($client, $data);
	}

    private function attemptHandshake($client,$data)
    {
        $this->console("Doing the handshake");

        if(!$this->requestFilter($data)) {

            $this->disconnect($client);

            return;
        }

        $this->handshake($client, $data);

        $this->getHandshakeInfo($data);

        $this->startProcess($client);

        return;
	}

    private function collectData($socket)
    {
        $data = null;

		while ($this->receiveSocket($socket,$r_data)) {
            $data .= $r_data;
		}

        return $data;
	}

    private function receiveSocket($socket,&$r_data)
    {
        if($this->bytes = @socket_recv($socket, $r_data, 2048, MSG_DONTWAIT)) {

			return true;
        }

        return false;
	}

	/**
	 * Start a child process for pushing data
	 * @param Client $client
	 */
	private function startProcess($client) {

		$this->console("Start a client process");

		$pid = pcntl_fork();

		if($pid == -1) {

			die('could not fork');
		}

		if($pid) { // process

			$client->setPid($pid);

			return;
		}

		// we are the child
		while(true) {

			// check if the client is connected
			if(!$client->isConnected()){

				break;
			}

			// push something to the client
			$seconds = rand(2, 5);

			$this->send($client, "I am waiting {$seconds} seconds");

			sleep($seconds);
		}
	}

	/**
	 * Send a text to client
	 * @param $client
	 * @param $text
	 */
	private function send($client, $text) {
		$this->console("Send '".$text."' to client #{$client->getId()}");
		$text = $this->encode($text);
		if(socket_write($client->getSocket(), $text, strlen($text)) === false) {
			$this->console("Unable to write to client #{$client->getId()}'s socket");
			$this->disconnect($client);
		}
	}

	/**
	 * Encode a text for sending to clients via ws://
	 * @param $text
	 * @param $messageType
	 */
	function encode($message, $messageType='text') {

		switch ($messageType) {
			case 'continuous':
				$b1 = 0;
				break;
			case 'text':
				$b1 = 1;
				break;
			case 'binary':
				$b1 = 2;
				break;
			case 'close':
				$b1 = 8;
				break;
			case 'ping':
				$b1 = 9;
				break;
			case 'pong':
				$b1 = 10;
				break;
		}

			$b1 += 128;


		$length = strlen($message);
		$lengthField = "";

		if($length < 126) {
			$b2 = $length;
		} elseif($length <= 65536) {
			$b2 = 126;
			$hexLength = dechex($length);
			//$this->stdout("Hex Length: $hexLength");
			if(strlen($hexLength)%2 == 1) {
				$hexLength = '0' . $hexLength;
			}

			$n = strlen($hexLength) - 2;

			for($i = $n; $i >= 0; $i=$i-2) {
				$lengthField = chr(hexdec(substr($hexLength, $i, 2))) . $lengthField;
			}

			while(strlen($lengthField) < 2) {
				$lengthField = chr(0) . $lengthField;
			}

		} else {

			$b2 = 127;
			$hexLength = dechex($length);

			if(strlen($hexLength) % 2 == 1) {
				$hexLength = '0' . $hexLength;
			}

			$n = strlen($hexLength) - 2;

			for($i = $n; $i >= 0; $i = $i - 2) {
				$lengthField = chr(hexdec(substr($hexLength, $i, 2))) . $lengthField;
			}

			while(strlen($lengthField) < 8) {
				$lengthField = chr(0) . $lengthField;
			}
		}

		return chr($b1) . chr($b2) . $lengthField . $message;
	}


	/**
	 * Unmask a received payload
	 * @param $buffer
	 */
	private function unmask($payload) {
		$length = ord($payload[1]) & 127;

		if($length == 126) {
			$masks = substr($payload, 4, 4);
			$data = substr($payload, 8);
		}
		elseif($length == 127) {
			$masks = substr($payload, 10, 4);
			$data = substr($payload, 14);
		}
		else {
			$masks = substr($payload, 2, 4);
			$data = substr($payload, 6);
		}

		$text = '';
		for($i = 0; $i < strlen($data); ++$i) {
			$text .= $data[$i] ^ $masks[$i%4];
		}
		return $text;
	}

	/**
	 * Print a text to the terminal
	 * @param $text the text to display
	 * @param $exit if true, the process will exit
	 */
	private function console($text, $exit = false) {
		$text = date('[Y-m-d H:i:s] ').$text."\r\n";
		if($exit) {
			die($text);
		}

		if($this->verboseMode) {
			echo $text;
		}
	}
}

?>
