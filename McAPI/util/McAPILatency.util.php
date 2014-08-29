<?php

class McAPILatency {

	private $_latceny;
	private $_start;
	private $_stop;

	public function __construct() {}

	public function executeAction($action) {

		switch ($action) {
			case McAPILatencyAction::START:
				$this->_start = microtime(true);
				break;
			
			case McAPILatencyAction::STOP:
				$this->_stop = microtime(true);
				break;

			case McAPILatencyAction::CALCULATE:
				$this->_latceny = (double) number_format(($this->_stop - $this->_start) * 100, 0);
				break;

			default:break;
		}

	}

	public function getLatency() {
		return $this->_latceny;
	}

}

?>