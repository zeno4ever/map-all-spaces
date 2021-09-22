<?php

//read current status
$spaceApiFile = fopen("spacestatus.txt", "r");
$spaceApiStatus = fread($spaceApiFile, filesize("spacestatus.txt"));
fclose($spaceApiFile);

//Last change
$spaceLastChange = '';
if (file_exists("spacestatus.txt")) {
    $spaceLastChange = filemtime("spacestatus.txt");
}

echo '
{
  "api": "0.13",
  "api_compatibility": ["14"],
  "space": "YourSpace",
  "logo": "http://yourspace.org/images/logo.png",
  "url": "http://yourspace.org/",
  "location": {
    "address": "Streetname 42, Zipcode Place, Country",
    "lat": 52.21633,
    "lon": 6.82053
  },
  "contact": {
    "email": "info@yourspace.org"
  },
  "issue_report_channels": [
    "email"
  ],
  "state": {
    "open": ';
    if ($spaceApiStatus == '1') {
        echo 'true';
    }  else {
        echo 'false';
    };
    echo '},';
    if ($spaceLastChange<>0) {
        echo '"lastchange": '. $spaceLastChange;
    }
    echo '
}
';

?>