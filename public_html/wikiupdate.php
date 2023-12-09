<?php

require '../private/init.php'; //get secret settings
require $PRIVATE.'/wiki.php';

/* If started from the command line, wrap parameters to $_POST and $_GET */
// if (!isset($_SERVER["HTTP_HOST"])) {
// 	parse_str($argv[1], $_GET);
// 	parse_str($argv[1], $_POST);
// 	$_COOKIE['wikipw'] = substr(sha1($wikiPasswd), 0, 20);
// }

$hackerspace = $_POST['hackerspace'];
$status = $_POST['status'];   
$action = $_POST['action'];

// $result = $database->update("wikispace", ['status'=>$status] ,["name" => $hackerspace]);
$result = $db->update("wikispace", array('status'=>$status ,"name" => $hackerspace));

//just to be sure, only when user is loged in
if ($_COOKIE['wikipw'] == substr(sha1($wikiPasswd),0,20)) {
	updateOneHackerSpace($hackerspace,$action);
} 

return true;
?>