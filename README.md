# map-all-spaces

Map with all hackerspaces, fablabs and makerspaces. Check it out [here](https://mapall.space)!

Combine several sources and put them together on one map. This will result in a uptodate map.

Sources :
- [SpaceAPI](https://spaceapi.io/)
- [Fablab.io](https://fablab.io)
- [Hackerspace.org wiki](https://wiki.hackerspaces.org)

If you want to be added/removed/changed check one of the above source and modify it there.


For details about what 'rules' apply check the FAQ html page.

Any questions or remarks contact me mapall@daveborghuis.nl.

## Instal for local use/test

- Copy 'settings_example.php' to 'settings.php' and change paths to reflect your local system. 
- Run 'php setup.php' to create a sqlite database with table.
- You can now get all the data and proces it with 'php update.php'. If you run it for the first time you want to use the options "--init --all"

With the '--all' option it will : 
	-get api data, check if json could be retrieved and procesed.
	-get fablab.io data, only include the 'active' fablabs
	-get wiki.hackerspaces.org, only active hackerspaces with a location
	-dedupe above resuls, check name match 45% and distance <200m

You can only do one part of the processing by giving eg '--wiki' to do only the part of getting and processing the wiki.hackerspaces.org data.

Options of update.php
  --all    Process all options
  --wiki   Update data from wiki
  --fablab Update data from fablab.io
  --log=0  Define loglevel, 0 for all message, 5 only errors
  --init   Delete all records and logfile
  --api    Spaceapi
  --comp   Dedupe wiki

The above steps will generate \*.geojson files that will be read by the maps leaflet. You have to have a webserver that points to the 'public_html' directory of alternative you can use the php server to do this. Go the the 'public_html' directory and enter 'php -S localhost:8000', this will start a local webserver where you can see the results.


# Used components 

- [Leaflet](https://leafletjs.com/)
- [Control groups]()
- [Search](https://github.com/stefanocudini/leaflet-search)