<?php

include "../settings.php"; //get secret settings

//twitter feed
require '../vendor/autoload.php';

use Medoo\Medoo;

if (isset($databasefile)) {
	$database = new Medoo([
	    'database_type' => 'sqlite',
	    'database_file' => $databasefile
	]);
} else {	
	echo 'Set $databasefile in settings.php';
	exit;
};

$hackerspace = $_POST['hackerspace'];
$status = $_POST['status'];   

$result = $database->update("wikispace", ['status'=>$status] ,["name" => $hackerspace]);

return true;
?>