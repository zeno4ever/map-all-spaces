<?php

//system settings
set_time_limit(0);// in secs, 0 for infinite
date_default_timezone_set('Europe/Amsterdam');

require 'init.php';

$loglevel = 0; //all
$loglevelfile = 2; //log to logfile

$cliOptions = getopt('',['all','wiki', 'api' ,'fablab','fablabq','log::','comp','init','test','val']);
if ($cliOptions == null) {
echo "Usage update.php [options]
    --init Delete all records and logfile

    --all    Process all options, following options are included
        --wiki    Update data from wiki
        --fablab  Update data from fablab.io
        --fablabq Update data from fablab quebec
        --comp    Dedupe wiki
        --api     Spaceapi geojson

    --val      Validate/check  all api space (check code !!)
    --log=1  Define loglevel, 0 everything, 5 only errors
    \n";
    exit;
};

message('Start update '.date("H:i:sa d-m-Y"),5);
$startTime = time();

if (isset($cliOptions['log'])) {
  $loglevel =  $cliOptions['log'];
  echo 'Log level set to '.$cliOptions['log'].PHP_EOL;
};

if (isset($cliOptions['init']) or array_search('all', $argv)) {
    $db = MysqliDb::getInstance();
    $db->delete('mapspace');
    if(!file_exists ( $geojson_path.'errorlog.txt' )) {
        unlink($geojson_path.'errorlog.txt');
    }
    echo('Init : database empty and logfile removed');
};

if (isset($cliOptions['api']) or isset($cliOptions['all'])) {
    getSpaceApi();
};

if (isset($cliOptions['fablab']) or isset($cliOptions['all'])) {
  getFablabJson();
};

if (isset($cliOptions['fablabq']) or isset($cliOptions['all'])) {
    getFablabQuebecJson();
};

if (isset($cliOptions['wiki'])) {
  getHackerspacesOrgJson();
};

if (isset($cliOptions['val'])) {
    validateSpaceApi();
};
  

if (isset($cliOptions['comp']) or isset($cliOptions['all'])) {
    message('Start Compare',0);
    compareDistance();
    if (isset($cliOptions['all'])) {
        getHackerspacesOrgJson();
    }
};

if (isset($cliOptions['test'])) {
    // getSpaceApi2();
    // echo "TEST FUNCTIE ".PHP_EOL;
    // exit;
};


message('End '.date("H:i:s").' process time '.date("i:s", time() - $startTime)."\n",5);

/**
 * Fill all the spaceapi spaces
 */
function getSpaceApi() {
    $db = MysqliDb::getInstance();

    $array_geo = array ("type"=> "FeatureCollection");

    message("## Update Space api json file",5);

    $getApiDirResult = getJSON('https://raw.githubusercontent.com/SpaceApi/directory/master/directory.json');
    $hs_array = $getApiDirResult['json'];

    if ($getApiDirResult['error']!=0) {
        message('Space api dir not found, curl error  ',$getApiDirResult['error'],4);
    } else {

        //loop hackerspaces
        foreach ($hs_array as $space => $url) {

            message('Space '.$space);

            $nextTimeDate = '';
            $lon = $lat = 0;
            $icon = '/image/hs.png';
            $state = false;

            $getApiResult = getJSON($url,null,20);
         
            if ( isset($getApiResult['json']) && $getApiResult['error']==0) {
                $apiJson = $getApiResult['json'];            

                if (isset($apiJson['location']['lon']) && isset($apiJson['location']['lat'])) {
                    $lon = $apiJson['location']['lon'];
                    $lat = $apiJson['location']['lat'];
                } elseif (isset($apiJson['lon']) && isset($apiJson['lat'])) {
                    //<v12 api
                    $lon = $apiJson['lon'];
                    $lat = $apiJson['lat'];
                };

                if (isset($apiJson['state']['open'])) {
                    //api v13=>
                    if ($apiJson['state']['open'] === true) {
                        $state = true;
                        $icon = '/image/hs_open.png';
                    } elseif($apiJson['state']['open'] === false) {
                        $state = false;
                        $icon = '/image/hs_closed.png';
                    };
                } elseif(isset($apiJson['open'])){
                    //api v<13
                    if ($apiJson['open'] === true) {
                        $state = true;
                        $icon = '/image/hs_open.png';
                    } else {
                        $state = false;
                        $icon = '/image/hs_closed.png';
                    };
                };

                //translate spaceapi array to geojson array
                $full_address = array_map('trim', explode(',', ($apiJson['location']['address'] ?? '')));

                $address = $full_address[0] ?? '';
                $zip = $full_address[1] ?? '';
                $city = $full_address[2] ?? '';

                $email = $apiJson['contact']['email'] ?? '' ;
                $phone = $apiJson['contact']['phone'] ?? '';

                addspace($array_geo, $space, $lon, $lat, $address, $zip, $city, $apiJson['url'], $email, $phone, $icon, $url, 'A');

                updateSpaceDatabase('A',cleanUrl($url),$space,0,$lon,$lat);
                updateSpaceHeatmap($space, $state, 1, $url ,$getApiResult['json']);

            } else {
                //no realtime api file found, use previous data 
                $db->where("source", "A"); 
                $db->where("sourcekey", cleanUrl($url)); 
                $result = $db->get("mapspace");

                if ($db->count >0) {
                    //message('Space added to map, not open/close status.',4);
                    //message(" add to GEO  $space lon= ".$result[0]['lon']."lat = ".$result[0]['lat']. " icon = ". $icon. "Next check :". $nextTimeDate,4);
                    addspace($array_geo, $space, $result[0]["lat"], $result[0]["lon"], "Last Error :" . $result[0]["lastcurlerror"], "Next check : " . $nextTimeDate, "", "", "", "", $icon, $url, 'A');
                };
                message('SKIP (in database with ' . $result[0]["curlerrorcount"] . ' errors, last error ' . $result[0]["lastcurlerror"] . ' ) for ' . $space . " next check on " . $nextTimeDate, 4);

                //message("Skip $space - error ".$getApiResult['error'],5);
                updateSpaceDatabase('A',cleanurl($url),$space,$getApiResult['error'],$lon,$lat);

                updateSpaceHeatmap($space, $state, 0, $url ,$getApiResult['json']);

            };
        };
        saveGeoJSON('api',$array_geo);
    };
    
};

function updateSpaceHeatmap($space,$openstate,$status,$jsonUrl,$json) {
	$db = MysqliDb::getInstance();

    $hashname = md5($space);
    $open = (int) $openstate;
    
    if (isset($json["location"]["lat"])) {
        $lat=$json["location"]["lat"];
    } else {
        $lat=$json['lon'] ?? 0;
    }
    if (isset($json["location"]["lon"])) {
        $lon=$json["location"]["lon"];
    } else {
        $lon=$json['lon'] ?? 0;
    }

    //table data_* aanmaken als deze nog niet bestaat
    if (! $db->tableExists ("data_$hashname") ) {
        message(" New space $space,create heatmap table.", 5);

        $logo = $json['logo'] ?? '';
        $timezone = $json['location']['timezone'] ?? null;

        if ($timezone == null) {
            $timezone = getTimeZone($lon, $lat);
            message(" No timezone set, found zone ".$timezone, 5);
        };

        $sql = "CREATE TABLE data_$hashname(ts DATETIME NOT NULL, open INT(3) NOT NULL DEFAULT '0')";

        $db->rawQuery ( $sql);
        if ($db->getLastErrno() !== 0) {
            echo "Error  $space. Error: ". $db->getLastError().'  '.__LINE__;
        }

        $data = Array(
            "key" => $hashname,
            "name" => $space,
            "logo" => $logo,
            "timezone" =>$timezone,        
        );
        $db->insert('heatmspaces',$data);
        if ($db->getLastErrno() !== 0) {
            echo "Insert Error  $space. Error: ". $db->getLastError().'  '.__LINE__;
        };
    };





    $data_space = array(
        'get_total' => $db->inc(1),
        'sa' => json_encode($json), 
        'url' => $jsonUrl,
        'lns' => $open,
        "lat" => $lat,
        "lon" => $lon,
        'name' => $space,
        'lastupdated' => $db->now(),
    );

    //found api file, update data_*
    if ($status==1) {
        $data = array(
            "ts" => $db->now(),
            "open" => $open,
        );
        $db->insert("data_$hashname",$data);
        if ($db->getLastErrno() !== 0) {
            echo "Insert Error  $space. Error: ". $db->getLastError().'  '.__LINE__;
        };

        $data_space['get_ok'] = $db->inc(1);
    } else {
        $data_space['get_err'] = $db->inc(1);
    };

    $db->where("`key`",$hashname);
    $db->update('heatmspaces',$data_space);
    if ($db->getLastErrno() !== 0) {
        echo "Insert Error  $space. Error: ". $db->getLastError().'  '.__LINE__;
        echo "SQL ". $db->getLastQuery();
    };
};

function getTimeZone($lon,$lat) {
    global $timezoneApiKey;
    $timezone = null;

    $jsonResult = getJSON("http://api.timezonedb.com/v2.1/get-time-zone?key=$timezoneApiKey&format=json&by=position&lat=$lat&lng=$lon");

    if   ($jsonResult['error']==0) {
        //gmtOffset in sec
        $timezone = $jsonResult['json']['zoneName'];
    }
    return $timezone;
};



function updateSpaceDatabase ($source,$sourcekey,$name ='',$lastcurlerror=0,$lat=0,$lon=0) {
	$db = MysqliDb::getInstance();

    $db->where('source', $source);
    $db->where("sourcekey" , $sourcekey);
    // $result = $db->get("mapspace");
    $result = $db->getOne("mapspace");
	if ($db->getLastErrno() !== 0) {
		echo "GetOne  $source. Error: ". $db->getLastError();
	}

    $errorcount = 0;
    if ($db->count >0 ) {    
        $errorcount = $result["curlerrorcount"];
    }

    if ($lastcurlerror!=0) {
        $errorcount++;
    } else {
        $errorcount = 0;
    }

    $data = array(
        "source" => $source,
        "sourcekey" => $sourcekey,
        // "lastdataupdated" => time(), //$db->now(),
        "lastdataupdated" => $db->now(),
        "name" => $name,
        "lon" => $lon,
        "lat" => $lat,
        "lastcurlerror" => $lastcurlerror,
        "curlerrorcount" => $errorcount, 
    );

    if ($result == null ) {
        $db->insert("mapspace",$data);
        if ($db->getLastErrno() !== 0) {
            echo "Insert $source. Error: ". $db->getLastError();
        }

    } else {
        $db->where('source', $source);
        $db->where("sourcekey" , $sourcekey);
        $db->update("mapspace",$data);
        if ($db->getLastErrno() !== 0) {
            echo "Update $source. Error: ". $db->getLastError();
        }

    }
};

function getFablabJson() {
    $array_geo = array ("type"=> "FeatureCollection");

    $getFablabJsonResult = getJSON('https://api.fablabs.io/0/labs.json');

    message("## Update fablab json file",5);

    foreach ($getFablabJsonResult['json'] as $fablab ) {
        //echo "Updating ".$fablab['name'].PHP_EOL;

        if ( $fablab['activity_status'] !='closed' && (isset($fablab['latitude']) || isset($fablab['longitude'])) ) { 
            $id = $fablab['id'];

            $icon = '/image/fablab.png';

            $fullname = $fablab['name'];

            $nice_name = $fablab['slug'];

            if (isset($fablab['latitude']) || isset($fablab['longitude'])) {
                $lat = $fablab['latitude'];
                $lon = $fablab['longitude'];
            } else {
                $lat=0;
                $lon = 0;    
            }; 

            $address = (isset($fablab['address_1'])) ? trim($fablab['address_1']) : '';
            $address .= (isset($fablab['address_2'])) ? trim($fablab['address_2']) : '';

            $zip = (isset($fablab['postal_code'] )) ? trim($fablab['postal_code']) : '' ;
            $city = (isset($fablab['city'])) ? trim($fablab['city']) : '' ;

            $email = (isset($fablab['email'] )) ? $fablab['email'] : '' ;
            $phone = (isset($fablab['phone'] )) ? $fablab['phone'] : '' ;

            $source = 'https://fablabs.io/labs/'.$fablab['slug'];

            $url = getFablabSite($fablab['links'],$fablab['slug']);

            addspace( $array_geo, $fullname , $lon,$lat, $address, $zip, $city, $url, $email, $phone, $icon, $source, 'F');

            updateSpaceDatabase('F',$source,$fullname,0,$lon,$lat);

            message("Updating ".$fablab['name']);

       } else {
            message("Skip ".$fablab['name'].' not active.',5);
       }; 
   };
   saveGeoJSON('fablab',$array_geo);
};

function getFablabSite($links,$slug = '') {
    //check on social media site to exclude
    $socialmedia = array('wikifactory.com','plus.google.com','twitter.com','github.com','instagram.com','facebook.com','linkedin.com');
    foreach ($links as $link ) {
        //get host part of url
        $site = parse_url($link['url'], PHP_URL_HOST);
        //remove www part if needed
        if (substr($site, 0,4)=='www.') {
            $site = substr($site,4,strlen($site));
        };
        //store facebook site if found
        if ($site == 'facebook.com') {
            $facebook = $link['url'];
        };

        //copy site if not social media
        if (array_search($site,$socialmedia) == false) {
            $url = $link['url'];
        };
    };

    //set url to facebook if no other was found 
    if (!isset($url) && isset($facebook)) {
        $url = $facebook;
    };

    //if everything fails
    if (!isset($url) && isset($links[0]['url'])) {
        $url = $links[0]['url'];
    };

    if (!isset($url)) {
       $url = 'https://fablabs.io/labs/'.$slug;
    };

    return $url;
};

function getHackerspacesOrgJson() {
	$db = MysqliDb::getInstance();

    $array_geo = array ("type"=> "FeatureCollection");
    $req_results = 50;
    $req_page = 0;
    $now = date_create(date('Y-m-d\TH:i:s'));
            
    message('#### JSON from wiki.hackerspace.org');

    $result = getPageHackerspacesOrg($req_results,$req_page);

    while (isset($result) && count($result)>0) {
        foreach ($result as $space) {

            $fullname = $space['fulltext'];

            $lat =  $space['printouts']['Location'][0]['lat'];
            $lon =  $space['printouts']['Location'][0]['lon'];

            $icon = '/image/hs_black.png';

            $city =  (isset($space['printouts']['City'][0]['fulltext'])) ? $space['printouts']['City'][0]['fulltext'] : '';
           
            $email = $space['printouts']['Email'];
            $phone = $space['printouts']['Phone'];

            $url = (isset($space['printouts']['Website'][0])) ? $space['printouts']['Website'][0] : null;

            // $member = (isset($space['printouts']['Number of members'][0])) ? $space['printouts']['Number of members'][0] : null;
            // if ( $member != null ) {
            //     message($fullname.' Number of members are ['.$member.'] type '.gettype($member),5);
            // };

            $spaceapi = (isset($space['printouts']['SpaceAPI'][0])) ?  $space['printouts']['SpaceAPI'][0]  : '';
            $source = $space['fullurl'];
            $lastupdate = date_create(date("Y-m-d\TH:i:s", $space['printouts']['Modification date'][0]['timestamp']));
            $now = date_create(date('Y-m-d\TH:i:s'));

            $interval = date_diff($now, $lastupdate)->format('%a'); 

            //if space not added to map with api add it here.
            $db->where("source","A");
            $db->where("sourcekey" , cleanUrl($spaceapi));
            $db->where("lastcurlerror",0);
            $foundSpaceApi = $db->has("mapspace");

            if ($foundSpaceApi) {
               message('SKIP '.$fullname).' foundapi:'. $spaceapi;     
            } else {

                //check for results previos run
                $db->where("source","W");
                $db->where("sourcekey", $source);
                $wiki_curlerror = $db->getOne("mapspace");

                if (isset($wiki_curlerror["lastcurlerror"])) {
                    if ($wiki_curlerror["lastcurlerror"]==0) {
                        //sit is up previous run
                        addspace( $array_geo, $fullname , $lon,$lat, '', '', $city, $url, $email, $phone, $icon, $source,'W');
                        message('Checked already, add to map '.$fullname);
                    } elseif($wiki_curlerror["lastcurlerror"]!=0) {
                        message('Checked already, site down '.$fullname);
                    }
                } else {
                    $getSiteStatus = getJSON($url);
      
                    if($getSiteStatus['error']==0 or $getSiteStatus['error']==1000) {
                        addspace( $array_geo, $fullname , $lon,$lat, '', '', $city, $url, $email, $phone, $icon, $source,'W');
                        updateSpaceDatabase('W',$source,$fullname,0,$lon,$lat);
                        message( 'Update '.$fullname); 
                    } else {
                        updateSpaceDatabase('W',$source,$fullname,$getSiteStatus['error'],$lon,$lat);
                        message('Skip -site down - '.$fullname.' Error '.$getSiteStatus['error'].' Last wiki update was '.$interval.' days / '.(float) round( ($interval/365),2).' years',5);
                    };
                };
            };
        };

        //testing
        // if ($req_page>2) {
        //     return;
        // }

        if (count($result) == $req_results ) {
            $req_page++;
            $result = getPageHackerspacesOrg($req_results,$req_page);
        } else {
            $result = null;
        }
    };

    if ($req_page !=0) {
       saveGeoJSON('wiki',$array_geo);        
    } else {
        message('No wiki spaces found, nothing written');
    }
};



function getPageHackerspacesOrg($req_results,$req_page) {
    $offset = $req_page*$req_results;
    //Original 9 march 2020
    //$url = "https://wiki.hackerspaces.org/Special:Ask/format=json/limit=$req_results/link=all/headers=show/searchlabel=JSON/class=sortable-20wikitable-20smwtable/sort=Modification-20date/order=desc/offset=$offset/-5B-5BCategory:Hackerspace-5D-5D-20-5B-5BHackerspace-20status::active-5D-5D-20-5B-5BHas-20coordinates::+-5D-5D-20-5B-5BNumber-20of-20members::+-5D-5D/-3F-23/-3FModification-20date/-3FEmail/-3FWebsite/-3FCity/-3FPhone/-3FNumber-20of-20members/-3FSpaceAPI/-3FLocation/mainlabel=/prettyprint=true/unescape=true";

    //For testing with extra selection (country)
    //$country =
    //$url = "https://wiki.hackerspaces.org/Special:Ask/format=json/limit=$req_results/link=all/headers=show/searchlabel=JSON/class=sortable-20wikitable-20smwtable/sort=Modification-20date/order=desc/offset=$offset/-5B-5BCategory:Hackerspace-5D-5D-20-5B-5BHackerspace-20status::active-5D-5D-20-5B-5BHas-20coordinates::+-5D-5D-5B-5BCountry::Spain-5D-5D/-3F-23/-3FModification-20date/-3FEmail/-3FWebsite/-3FCity/-3FPhone/-3FNumber-20of-20members/-3FSpaceAPI/-3FLocation/mainlabel=/prettyprint=true/unescape=true";

    //Live
    $url = "https://wiki.hackerspaces.org/Special:Ask/format=json/limit=$req_results/link=all/headers=show/searchlabel=JSON/class=sortable-20wikitable-20smwtable/sort=Modification-20date/order=desc/offset=$offset/-5B-5BCategory:Hackerspace-5D-5D-20-5B-5BHackerspace-20status::active-5D-5D-20-5B-5BHas-20coordinates::+-5D-5D/-3F-23/-3FModification-20date/-3FEmail/-3FWebsite/-3FCity/-3FPhone/-3FNumber-20of-20members/-3FSpaceAPI/-3FLocation/mainlabel=/prettyprint=true/unescape=true";

    $getWikiJsonResult = getJSON($url);

    if ($getWikiJsonResult['error']!=0){
        message(' Error while get wiki json '.$getWikiJsonResult['error']);
        return null;
    }

    return $getWikiJsonResult['json']['results'];
};


function getFablabQuebecJson() {
    $array_geo = array ("type"=> "FeatureCollection");
    $req_results = 50;
    $req_page = 0;
            
    message('#### JSON from Fablab Quebec');

    $result = getPageFablabQuebec($req_results,$req_page);

    while (isset($result) && count($result)>0) {
        foreach ($result as $space) {

            $fullname = $space['fulltext'];
            message('FablabQ :'.$fullname);

            $lat =  $space['printouts']['A les coordonnées géographiques'][0]['lat'];
            $lon =  $space['printouts']['A les coordonnées géographiques'][0]['lon'];

            $address = (isset($space['printouts']['A l adresse physique'][0])) ? $space['printouts']['A l adresse physique'][0] : '';
            $city = (isset($space['printouts']['Est situé dans la localité'][0]))? $space['printouts']['Est situé dans la localité'][0]:'';

            $icon = '/image/fablab.png';

            $url = (isset($space['printouts']['A l adresse web'][0])) ? $space['printouts']['A l adresse web'][0] : null;
            $source = $space['fullurl'];

            updateSpaceDatabase('Q',$source,$fullname,0,$lon,$lat);

            addspace( $array_geo, $fullname , $lon,$lat, $address, '', $city, $url, '', '', $icon, $source,'Q');

        };

        if (count($result) == $req_results ) {
            $req_page++;
            $result = getPageFablabQuebec($req_results,$req_page);    
        } else {
            $result = null;
        }
    };

    if ($req_page !=0) {
       saveGeoJSON('fablabq',$array_geo);        
    } else {
        message('No wiki spaces found, nothing written');
    }
};

function getPageFablabQuebec($req_results,$req_page) {
    $offset = $req_page*$req_results;

    $url = "https://wiki.fablabs.quebec//index.php/Spécial:Requêter/format=json/link=all/headers=show/searchlabel=JSON/class=sortable-20wikitable-20smwtable/offset=$offset/limit=$req_results/-5B-5BCatégorie:Fab-20Lab-20au-20Québec-5D-5D-20-5B-5BA-20les-20coordonnées-20géographiques::+-5D-5D/-3FA-20les-20coordonnées-20géographiques/-3FA-20l-20adresse-20web//-3F-20Est-20situé-20dans-20la-20localité/-3F-20A-20l-20adresse-20physique/mainlabel=/prettyprint=true/unescape=true";

    $getWikiJsonResult = getJSON($url);

    if ($getWikiJsonResult['error']!=0){
        message(' Error while get wiki json '.$getWikiJsonResult['error']);
        return null;
    }

    return $getWikiJsonResult['json']['results'];

}


function compareDistance() {
	$db = MysqliDb::getInstance();

    $db->where('lastcurlerror',0);
    $db->orderBy("lat","asc");
    $db->orderBy("lon","asc");
    $results = $db->get('mapspace');

    message('Found to compare records '.count($results));

    $runfirst = true;
    $found = 0;
    foreach ($results as $space) {
        if ($runfirst) {
            $space_b = $space['name'];
            $spacesource_b = $space['source'];
            $sourcekey_b = $space['sourcekey'];
            $lon_b = floatval($space['lon']);
            $lat_b = floatval($space['lat']); 
            $lastcurlerror_b = $space['lastcurlerror'];
            $runfirst=false;
        }
        $space_a = $space['name'];
        $spacesource_a = $space['source'];
        $sourcekey_a = $space['sourcekey'];
        $lastcurlerror_a = $space['lastcurlerror'];
        $lon_a = floatval($space['lon']);
        $lat_a = floatval($space['lat']);

        $distance = round(distance($lat_a,$lon_a,$lat_b,$lon_b,'K')*1000); //KM to meter
        $namelike = similar_text($space_a,$space_b,$namelike_perc);

        if ($distance <=200 && $namelike_perc>45 && !$runfirst && ($spacesource_a=='W' or $spacesource_b=='W' or $spacesource_a=='Q' or $spacesource_b=='Q')) {
            $found++;
            message( "within 200m ( $distance m ) %=".(int)$namelike_perc.' #='.$namelike,5);
            message( '  1)'.$space_a.' ['.$spacesource_a.'] key ['.$sourcekey_a.']',5);
            message( '  2)'.$space_b.' ['.$spacesource_b.'] key ['.$sourcekey_b.']',5);
            if ($spacesource_a=='W' or $spacesource_a=='Q') {
                $db->where("source",$spacesource_a);
                $db->where("sourcekey" , $sourcekey_a);
                $db->update("mapspace",array("lastcurlerror" =>1001));
                message('  1 removed ');
            } elseif($spacesource_b=='W' or $spacesource_b=='Q') {
                $db->where("source",$spacesource_b);
                $db->where("sourcekey" , $sourcekey_b);
                $db->update("mapspace",array("lastcurlerror" =>1001));
               
                message('  2 removed ');
            } else {
                message('** nothing updated.');
            };
            
        };

        $space_b = $space_a;
        $spacesource_b = $spacesource_a;
        $sourcekey_b = $sourcekey_a;
        $lastcurlerror_b = $lastcurlerror_a;
        $lon_b = $lon_a;
        $lat_b = $lat_a; 
    };
};


function distance($lat1, $lon1, $lat2, $lon2, $unit) {
  if (($lat1 == $lat2) && ($lon1 == $lon2)) {
    return 0;
  }
  else {
    $theta = $lon1 - $lon2;
    $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
    $dist = acos($dist);
    $dist = rad2deg($dist);
    $miles = $dist * 60 * 1.1515;
    $unit = strtoupper($unit);

    if ($unit == "K") {
      return ($miles * 1.609344);
    } else if ($unit == "N") {
      return ($miles * 0.8684);
    } else {
      return $miles;
    }
  }
};

function addspace(&$array_geo, $name, $lat, $lon, $address = "", $zip = "", $city= "", $url="", $email = "", $phone= "", $icon='/hsmap/hs.png',$source='',$sourcetype='A') 
{
        $array_geo['features'][] = array(
        "type"=> "Feature",
        "geometry" => array (
            "type" => "Point",
            "coordinates" => Array(
                $lat,
                $lon
            ),
        ),
        "properties" => Array(
            "marker-symbol" => $icon,
            "name" => $name,
            "url" => $url,
            "address" => $address,
            "zip" => $zip,
            "city" => $city,
            "email" => $email,
            "phone" => $phone,
            "source" => $source, 
            "sourcetype" => $sourcetype,          
        )
    );
};

function message($message,$lineloglevel=0) {
    global $loglevel;
    global $loglevelfile;
    global $geojson_path;

    if ($lineloglevel >= $loglevel) {
        echo $message.PHP_EOL;
    };

    if ($lineloglevel >= $loglevelfile) {
        //
        if(!file_exists ( $geojson_path.'errorlog.txt' )) {
            $message = "Map all spaces error log, see also FAQ\nError 0-99 Curl\nError 100-999 http\nError 1000 no valid json\nError 1001 dupe\n\n".$message;
        }

        $fp = fopen($geojson_path.'errorlog.txt', 'a');
        fwrite($fp,$message.PHP_EOL);
        fclose($fp);
    };
}

function saveGeoJSON($file, $array_geo) {
    global $geojson_path;
    $json_geo = json_encode($array_geo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);

    $fp = fopen($geojson_path.$file.'.json', 'w');
    fwrite($fp,$json_geo);
    fclose($fp);
};

function cleanUrl($url) {
    //remove http or https from url
    return preg_replace("(^https?://)", "", $url );
}


message('End '.date("h:i:sa"),5);

function validateSpaceApi() {
    echo PHP_EOL . "## Validate Space api json file ". date('Y-m-d H:i').PHP_EOL;

    $dateToOld = strtotime("-3 months");
    echo 'Date to old : ' . date('Y-m-d H:i', $dateToOld).PHP_EOL;

    $adminMessages = '';
    $apiStatus = array("ok"=>0,"fail"=>0,"send"=>0);

    //Live
    $getApiDirResult = getJSON('https://raw.githubusercontent.com/SpaceApi/directory/master/directory.json');
    $hs_array = $getApiDirResult['json'];

    //Local test
    // $getApiDirResult['error'] =0;
    // $hs_array = json_decode(file_get_contents('spaceapilocal.json'),true) ;

    if ($getApiDirResult['error'] != 0) {
        echo 'Space api dir not found, curl error  ', $getApiDirResult['error'];
    } else {

        //loop all hackerspaces
        foreach ($hs_array as $space => $url) {

            // echo "-------------------------" . PHP_EOL;
            // echo 'Space ' . $space.' url: '.$url.PHP_EOL;

            $emailMessage = '';
            $email = '';

            if (parse_url($url, PHP_URL_SCHEME) == 'http') {
                $httpsurl = preg_replace("/^http:/i", "https:", $url);
                $getApiResult = getJSON($httpsurl);

                if (isset($getApiHTTPResult['json']) and $getApiResult['error'] == 0) {
                    $emailMessage .= "- Spaceapi via https works, update this in spaceapi directory." . PHP_EOL;
                    $getApiResult = $getApiResult;
                } else {
                    $emailMessage .= "- Spaceapi via https failed, consider enable https." . PHP_EOL;
                    //fallback to normal json
                    $getApiResult = getJSON($url, null, 20);
                };

            } else {
                $getApiResult = getJSON($url, null, 20);
            };
            // Error 0-99 Curl
            // Error 100-999 http
            // Error 1000 no valid json
            // Error 1001 dupe
            // ssl >2000

            // Explain the error classes
            if  ($getApiResult['error'] >= 2000) {
                $emailMessage .= '- SSL error ' . $getApiResult['error'] - 2000 . PHP_EOL;
            } elseif ($getApiResult['error'] > 1 and $getApiResult['error'] < 100) {
                $emailMessage .= '- Curl error ' . $getApiResult['error'] . PHP_EOL;
            } elseif ($getApiResult['error'] >= 100 and $getApiResult['error'] <= 999) {
                $emailMessage .= '- HTTP error ' . $getApiResult['error'] . PHP_EOL;
            } elseif ($getApiResult['error'] >= 1000 and $getApiResult['error'] < 2000) {
                $emailMessage .= '- JSON decode error ' . PHP_EOL;
            };
            
            if (isset($getApiResult['json']) && $getApiResult['error'] == 0) {

                if($getApiResult['cors'] == false) {
                    $emailMessage .= "- CORS not enabled" . PHP_EOL;
                };

                $apiJson = $getApiResult['json'];

                if (isset($apiJson['api']) ) {
                    $api = $apiJson['api'];
                } elseif (isset($apiJson['api_compatibility'][0])) {
                    $api = $apiJson['api_compatibility'][0];
                } else {
                    $emailMessage .= '- no api version found'.PHP_EOL;
                };

                if ($api < 0.13) {
                    $emailMessage .= '- Please upgrade spaceapi to latest version.' . PHP_EOL;
                    print "API = ".$api;
                };

                if (isset($apiJson['location']['lon']) && isset($apiJson['location']['lat'])) {
                    $lon = $apiJson['location']['lon'];
                    $lat = $apiJson['location']['lat'];
                } elseif (isset($apiJson['lon']) && isset($apiJson['lat'])) {
                    //<v12 api
                    $lon = $apiJson['lon'];
                    $lat = $apiJson['lat'];
                };

                if( !isset($apiJson['state']['open']) && !isset($apiJson['open'])) {
                    print "Geen open Status!!";
                }


                if (
                    $lon < -180 or $lon > 180 or $lat < -90 or $lat > 90
                ) {
                    $emailMessage .= '- Wrong lat\lon is : [ lat ' . number_format($lat,4) . '/ lon ' . number_format($lon,4).PHP_EOL;
                }

                $lastchange = $apiJson['state']['lastchange'] ?? null; //date in epoch

                if (isset($lastchange)) {
                    if ($lastchange - $dateToOld < 0) {
                        $emailMessage .= "- Date lastchange longer then 6 months ago. (". date('Y-m-d H:i', (int)$lastchange) .")". PHP_EOL;
                    };
                };

                $email = $apiJson['contact']['email'] ?? '';

                //echo 'email : ' . $email. PHP_EOL;

                if (isset($apiJson['issue_report_channels'][0])){
                    switch ($apiJson['issue_report_channels'][0]) {
                        case 'issue_mail':
                            $email = $apiJson['contact']['issue_mail'];
                            //if not a valid email assume its base64 encoded
                            if (filter_var($email, FILTER_VALIDATE_EMAIL) == false) {
                                $email = base64_decode($email);
                            };
                            break;
                        case 'ml':
                            $email = $apiJson['contact']['ml']; 
                            break;
                        case 'email':
                            $email = $apiJson['contact']['email'];
                            break;
                        case 'twitter':
                            echo "Issue via Twitter! $space".PHP_EOL;
                            $email = $apiJson['contact']['twitter'];
                            break;                            
                        default: //email
                            //$email = $apiJson['contact']['email']; 
                            echo 'Case not found???'.PHP_EOL;
                            break;
                    };
                } elseif(isset($apiJson['contact']['issue_mail'])) {
                    $email = $apiJson['contact']['issue_mail'];
                };
                //echo 'issue email :' . $email . PHP_EOL;
            };
            // else {
            //     $emailMessage .= '- No valid spaceapi json file found.';
            // };

            if ($emailMessage) {
                //echo "Send email to : " . $email . PHP_EOL;
                //echo "Message :" . PHP_EOL . $emailMessage . PHP_EOL;

                echo "-------------------------" . PHP_EOL;
                echo 'Space ' . $space . ' url: ' . $url . PHP_EOL;

                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    //$email = 'spaceapi@mapall.space';
                    $headers = 'From: spaceapi@mapall.space' . "\r\n" .
                        'Reply-To: spaceapi@mapall.space' . "\r\n";
                    $emailMessage =
                        "Dear Maker/Hacker,\r\n\r\nWe (volunteers of spaceapi.io) found some issues with your spaceapi url/json on $url. " .
                    "\r\n\r\nWe found the following issues : " . PHP_EOL .
                        $emailMessage . PHP_EOL .
                    "Please fix this issues so that other sites can enjoy your live data. To check your spaceapi manual you can use the online validator ( https://spaceapi.io/validator/ ).\r\n\r\nRegards,\r\n\r\nDave";
                    if (mail($email, "Your $space spaceapi", $emailMessage,$headers)) {
                        $apiStatus['send']++;
                    } else {
                        $apiStatus['fail']++;
                        echo "Sending mail to $space failed!".PHP_EOL;
                        echo "Found errors : $emailMessage" . PHP_EOL;
                    }
                } else {
                    $adminMessages .= $space. " Email: " . $email . PHP_EOL . $emailMessage . PHP_EOL . '******' . PHP_EOL;
                    echo "ERROR Sendmail : Email $email not valid for $space".PHP_EOL;
                    echo "Found errors : $emailMessage".PHP_EOL;
                    $apiStatus['fail']++;
                };
            } else {
                $apiStatus['ok']++;
            };
        };
        echo '****************' . PHP_EOL . $adminMessages;
    };
    echo "Checked ".$apiStatus['ok']." Failed ". $apiStatus['fail']." Send : ". $apiStatus['send'].PHP_EOL;
};

