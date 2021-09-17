<?php

// Live 
$databasefile = '/project/database.db';
$geojson_path = '/project/public_html/';
$error_logfile = '/project/errorlog.txt';

//You can create a bot by visiting https://wiki.hackerspaces.org/Special:BotPasswords
$botUser = 'user@botuser'; 
$botPasswd ='';

//login for wiki close/update
$wikiPasswd =''; //secret password to enable wiki edit shortcuts

//Twitter settings
$twitterSettings = array(
    'oauth_access_token' => "",
    'oauth_access_token_secret' => "",
    'consumer_key' => "",
    'consumer_secret' => ""
);


//init database 
use Medoo\Medoo;
$database = new Medoo([
    'database_type' => 'sqlite',
    'database_file' => $databasefile
]);
?>