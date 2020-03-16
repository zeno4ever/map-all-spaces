<?php

include 'settings.php';

require  './src/Medoo.php';

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

$database->drop("space");

$database->create("space", [
	"source" => [
		"VARCHAR(30)",
		"NOT NULL"
	],
	"sourcekey" => [
		"VARCHAR(30)",
		"NOT NULL"
	],
		"lon" => [
		//"REAL",
		"DECIMAL(3,6)",
	],
	"lat" => [
		"DECIMAL(3,6)",
	],
	"name" => [
		"VARCHAR(30)"
	],
	"lastcurlerror" => [
		"INTEGER"
	],
	"curlerrorcount" => [
		"INTEGER"
	],
	"lastdataupdated" => [
		"INTEGER",
		"NOT NULL"
	],
	"PRIMARY KEY (source,sourcekey)"
]);

$errorlog = $database->error();
if ($errorlog[1] != 0) {
	echo 'SqLite Error '.$errorlog[1];
} else {
	echo 'File created :'.$databasefile;
}
