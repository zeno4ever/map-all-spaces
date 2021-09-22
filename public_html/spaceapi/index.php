<?php
//http://localhost:8080/spaceapi/?privateKey=zV6ysJ2NmFVEcbWgPMGx6SuKcVR3eu&status=open

$spaceApiStatus ='';

if ($_GET['privateKey'] == 'zV6ysJ2NmFVEcbWgPMGx6SuKcVR3eu') {
    if ($_GET['status']=='open') {
        $spaceApiStatus = '1';
    } else {
        $spaceApiStatus = '0';
    }
    //store status in file on host
    $spaceApiFile = fopen("spacestatus.txt", "w");
    fwrite($spaceApiFile, $spaceApiStatus);
    fclose($spaceApiFile);
};
?>