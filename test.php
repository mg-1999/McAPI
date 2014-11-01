<?php

require_once './McAPI/McAPI.load.php';



$server = new McAPIPing("play.hivemc.eu");
$server->fetch(McAPIVersion::ONEDOTSEVEN);
echo "<pre>", var_dump($server->get(McAPIField::LIST_ALL)), "</pre>";
echo "<hr>";
print_r($server->getError());


//header('Content-Type: application/json');
//print_r($server->get(
//	McAPIField::ALL
//	));
