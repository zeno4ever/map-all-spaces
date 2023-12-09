<?php

$PRIVATE = realpath(dirname(__FILE__));
$PUBLIC = realpath('../public_html').'/';

require_once $PRIVATE.'/../vendor/autoload.php'; //composer components
require_once $PRIVATE . '/shared/mapall_functions.php';

// Live 
// $databasefile = '/project/database.db';
$geojson_path = $PUBLIC;
$error_logfile = $PUBLIC . '/errorlog.txt';

//You can create a bot by visiting https://wiki.hackerspaces.org/Special:BotPasswords
$botUser = 'user@botuser'; 
$botPasswd ='';

//login for wiki close/update
$wikiPasswd =''; //secret password to enable wiki edit shortcuts

//database settings
$databaseHost = 'localhost';
$databaseUser = 'user';
$databasePassword = 'setpasswd';
$databaseName = 'dabase_for_mapall';
$databasePort = 3306;

//init database 
$db = new MysqliDb (Array (
    'host' => $databaseHost,
    'username' => $databaseUser, 
    'password' => $databasePassword,
    'db'=> $databaseName,
    'port' => $databasePort,
));

// Register for free api key on timezonedb.com
$timezoneApiKey = '';

?>