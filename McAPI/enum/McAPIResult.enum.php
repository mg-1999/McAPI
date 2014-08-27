<?php

abstract class McAPIResult {

	//error
	const FAILED_TO_READ_DATA 	= 'FAILED_TO_READ_DATA';
	const PACKET_TO_SHORT	  	= 'PACKET_TO_SHORT';
    const CANT_CONNECT    	  	= 'CANT_CONNECT';
    const EMPTY_RESULT		 	= 'EMPTY_RESULT';

	//done
	const SUCCESSFULLY_DONE	  	= 'SUCCESSFULLY_DONE';

}

?>