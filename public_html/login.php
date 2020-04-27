<?php
include '../settings.php';

if ($_GET['pwd'] == $wikiPasswd) {
	setcookie('wikipw', substr(sha1($wikiPasswd),0,20) , time() + (86400 * 30), "/");
} else {
	//remove session
	setcookie('wikipw','', time() - 3600);
}

header("Location: /hswikilist.php");

?>