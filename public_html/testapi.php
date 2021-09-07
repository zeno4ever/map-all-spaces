<?php
header("Access-Control-Allow-Origin: *");

echo '
{
    "api": "0.13",
    "api_compatibility": [
        "14"
    ],
    "space": "TkkrLabTest",
    "logo": "https://spaceapi.tkkrlab.nl/tkkrlab.png",
    "url": "http://twenspace.nl",
    "location": {
        "address": "Westerstraat 178, Enschede",
        "lon": 9.236,
        "lat": 48.777
    },
    "contact": {
        "email": "info@shackspace.de",
        "irc": "irc://irc.freenode.net/shackspace",
        "ml": "public@lists.shackspace.de",
        "twitter": "@shackspace"
    },
    "issue_report_channels": [
        "email"
    ],
    "state": {
        "icon": {
            "open": "http://spaceapi.tkkrlab.nl/spaceapi_icon_open.png",
            "closed": "http://spaceapi.tkkrlab.nl/spaceapi_icon_closed.png"
        },
        "open": false
    },
    "projects": [
        "http://github.com/shackspace",
        "http://shackspace.de/wiki/doku.php?id=projekte"
    ],
    "sensors": {
        "temperature": [
            {
                "value": 20.94,
                "unit": "°C",
                "location": "Space"
            },
            {
                "value": 15.94,
                "unit": "°C",
                "location": "Werkplaats"
            }
        ],
        "network_connections": 1337, 
        "people_now_present": { "names": [ "piet", "jan", "ben" ] } 
    }
}';
?>