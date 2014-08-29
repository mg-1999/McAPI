<?php
abstract class McAPIVersion {
    
    const ONEDOTEIGHT = '1.8';
    const ONEDOTSEVEN = '1.7';
    CONST ONEDOTSIX   = '1.6';
    const TEST_VERSION = 'TEST_VERSION';

    public static function getVersion($patterString) {

    	$reflection = new ReflectionClass("McAPIVersion");

    	foreach($reflection->getConstants() as $v) {

    		if(preg_match("/({$v})(.*)?/", $patterString) == 1) {
    			return $v;
    		}

    	}

    	return McAPIVersion::ONEDOTSEVEN;
    }
    
}
?>