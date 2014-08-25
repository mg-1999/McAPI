<?php

require_once __DIR__ . '/../enum/McLatencyAction.class.php';

class McLatency {

	private $_latceny;
	private $_start;
	private $_stop;

	public function __construct() {}

	public function executeAction($action) {

		switch ($action) {
			case McLatencyAction::START:
				$this->_start = microtime(true);
				break;
			
			case McLatencyAction::STOP:
				$this->_stop = microtime(true);
				break;

			case McLatencyAction::CALCULATE:
				$this->_latceny = (double) number_format(($this->_stop - $this->_start) * 1000, 0);
				break;

			default:break;
		}

	}

	public function getLatency() {
		return $this->_latceny;
	}

}

?>