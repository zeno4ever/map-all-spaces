<?php

require '../private/init.php'; //get secret settings


$hackerspace = $_POST['hackerspace'];
$status = $_POST['status'];   
$action = $_POST['action'];   

$result = $database->update("wikispace", ['status'=>$status] ,["name" => $hackerspace]);

//just to be sure, only when user is loged in
if ($_COOKIE['wikipw'] == substr(sha1($wikiPasswd),0,20)) {
	updateOneHackerSpace($hackerspace,$action);
} 

return true;
?>