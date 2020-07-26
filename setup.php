<?php

include 'settings.php';

//composer components
require 'vendor/autoload.php';

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

//for mapall.space map
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


//check for hackerspace wiki 
$database->drop("wikispace");
$database->create("wikispace", [
	"wikiurl" => [
		"TEXT"
	],
	"name" => [
		"TEXT"
	],
	"lastcurlerror" => [
		"INTEGER"
	],
	// "curlerrorcount" => [
	// 	"INTEGER"
	// ],
	"lastdataupdated" => [
		"DATETIME",
		"NOT NULL"
	],
	// "emailsenddate" => [
	// 	"TEXT"
	// ],
	"status" => [
		"TEXT"
	],

	"PRIMARY KEY (wikiurl,lastdataupdated)"
]);


$errorlog = $database->error();
if ($errorlog[1] != 0) {
	echo 'SqLite Error '.$errorlog[1];
} else {
	echo 'File created :'.$databasefile;
}
