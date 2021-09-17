<?php
header("Access-Control-Allow-Origin: *");

echo '
{
  "api_compatibility": [
    "14"
  ],
  "api": "0.13",
  "space": "TkkrLab",
  "logo": "https://spaceapi.tkkrlab.nl/tkkrlab.png",
  "url": "https://tkkrlab.nl",
  "location": {
    "address": "Marssteden 98, 7547TD Enschede, The Netherlands",
    "lat": 52.21633,
    "lon": 6.82053,
    "timezone": "Europe/Amsterdam"
  },
  "contact": {
    "email": "bestuur@tkkrlab.nl",
    "irc": "irc://irc.libera.chat:6697/tkkrlab",
    "twitter": "@tkkrlab",
    "phone": "+31532030532"
  },
  "issue_report_channels": [
    "email"
  ],
  "state": {
    "icon": {
      "open": "https://spaceapi.tkkrlab.nl/spaceapi_icon_open.png",
      "closed": "https://spaceapi.tkkrlab.nl/spaceapi_icon_closed.png"
    },
    "mqtt": {
      "open": "1",
      "closed": "0",
      "host": "mqtt.tkkrlab.nl",
      "topic": "tkkrlab/spacestate",
      "port": 1883,
      "tls": false
    },
    "open": false,
    "lastchange": 1631659247
  },
  "projects": [
    "https://github.com/tkkrlab",
    "https://tkkrlab.nl/projects"
  ],
  "spacefed": {
    "spacenet": false,
    "spacephone": false,
    "spacesaml": false
  },
  "membership_plans": [
    {
      "name": "Normal member",
      "value": 30,
      "currency": "EUR",
      "billing_interval": "monthly",
      "description": "Member of TkkrLab (https://tkkrlab.nl/deelnemer-worden/)"
    },
    {
      "name": "Student member",
      "value": 15,
      "currency": "EUR",
      "billing_interval": "monthly",
      "description": "Member of TkkrLab, discount for students (https://tkkrlab.nl/deelnemer-worden/)"
    },
    {
      "name": "Student member",
      "value": 15,
      "currency": "EUR",
      "billing_interval": "monthly",
      "description": "Junior member of TkkrLab, discount for people aged 16 or 17 (https://tkkrlab.nl/deelnemer-worden/)"
    }
  ],
  "sensors": {
    "people_now_present": [
      {
        "value": 4,
        "names": [
            "Jan",
            "Piet",
            "1 unknown device"
        ]
      }
    ]
  },
  "feeds": {
    "blog": {
      "type": "rss",
      "url": "https://tkkrlab.nl/blog/index.xml"
    },
    "calendar": {
      "type": "ical",
      "url": "http://www.google.com/calendar/ical/ij2r5jaqg6l2bdt5uv6ltngfq0%40group.calendar.google.com/public/basic.ics"
    }
  },
  "links": [
    {
      "name": "Blog",
      "url": "https://tkkrlab.nl/blog"
    },
    {
      "name": "Becoming a participant",
      "url": "https://tkkrlab.nl/deelnemer-worden"
    },
    {
      "name": "CyberSaturday",
      "url": "https://tkkrlab.nl/cybersaturdays/cybersaturday"
    }
  ]
}
';
?>