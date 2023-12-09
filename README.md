# map-all-spaces

This site exist of tree parts: the map, the heatmap and the hackerspace wiki cences.

## Map

Map with all hackerspaces, fablabs and makerspaces. Check it out [here](https://mapall.space)!

Combine several sources and put them together on one map. This will result in a up-to-date map.

Sources :
- [SpaceAPI](https://spaceapi.io/)
- [Fablab.io](https://fablab.io)
- [Fablab Quebec (Canada)](https://wiki.fablabs-quebec.org/)
- [Hackerspace.org wiki](https://wiki.hackerspaces.org)

If you want to be added/removed/changed check one of the above source and modify it there.

For details about what 'rules' apply check the FAQ html page.

Any questions or remarks contact me mapall@daveborghuis.nl.

## Heatmap
See heatmap of open/closed status of spaces with a spaceapi. Api status is read every 10 min and stored in database, statistics is calculated once you request page. Original made by [Folkert van Heusden](https://github.com/folkertvanheusden/spaceapi), integrated into mapall site on end september 2021.

## Hackerspace Cencus
Check if a hackerspace is still active (and edit status if not)
This is done by reading wiki page of space and determine if site is up, twitter/mastadon is active and more. If program is sure of the activity the wiki page is set to status 'suspented inactivity' or 'active'.

Source : 
* [Hackerspace.org wiki](https://wiki.hackerspaces.org)

Create bot via 
* https://wiki.hackerspaces.org/Special:BotPasswords

Curl Errors
* https://curl.haxx.se/libcurl/c/libcurl-errors.html

### Instal for local use/test
Clone this github to a local directory. I asume you have php and composer installed.

-- run 'composer install' to install database libary's. 
- Copy 'init_example.php' to 'init.php' and change paths to reflect your local system. Add the needed api keys/logins. 
- Run 'mapall_setupdatabase.sql'  to set up database and used tables.
- You can now get all the data and proces it with 'php update.php'. If you run it for the first time you want to use the options "--init --all"
- go to 'public_html' directory and use the php server to enjoy the results ('php -S localhost:8000')

Options of update.php
*  --all    Process all options
*  --wiki   Update data from wiki
*  --fablab Update data from fablab.io
*  --log=0  Define loglevel, 0 for all message, 5 only errors
*  --init   Delete all records and logfile
*  --api    Spaceapi
*  --comp   Dedupe wiki

With the '--all' option it will : 
*  -get api data, check if json could be retrieved and procesed.
*  -get fablab.io data, only include the 'active' fablabs
*  -get wiki.hackerspaces.org, only active hackerspaces with a location
*  -dedupe above resuls, check name match 45% and distance <200m

You can only do one part of the processing by giving eg '--wiki' to do only the part of getting and processing the wiki.hackerspaces.org data.

The above steps will generate \*.geojson files that will be read by the maps leaflet. You have to have a webserver that points to the 'public_html' directory of alternative you can use the php server to do this.


### Used components 

- [Leaflet](https://leafletjs.com/)
- [Control groups](https://github.com/Leaflet/Leaflet.markercluster)
- [Search](https://github.com/stefanocudini/leaflet-search)
- [database layer](http://github.com/joshcam/PHP-MySQLi-Database-Class) installed via composer

## API

You can use the JSON API of this repository:

- url: `https://mapall.space/heatmap/json.php`
- parameter `id`: id of the hackerspace, example: `TkkrLab`
- parameter `period`: period of data, one of `week`, `month`, `year`, `everything`

example: https://mapall.space/heatmap/json.php?id=TkkrLab&period=week
