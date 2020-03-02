<?php
include 'settings.php';

require  './src/Medoo.php';
use Medoo\Medoo;

//open database
$database = new Medoo([
    'database_type' => 'sqlite',
    'database_file' => $databasefile
]);

// Enable Error Reporting and Display:
error_reporting(~0);
ini_set('display_errors', 1);
//system settings
set_time_limit(0);// in secs, 0 for infinite
date_default_timezone_set('Europe/Amsterdam');

$loglevel = 0; //all
$loglevelfile = 5; //log to logfile



$cliOptions = getopt('',['all','wiki','api','fablab','log::','initdb']);

if ($cliOptions == null) {
echo "Usage update.php [options] \n --all   Process all options\n --wiki   Update data from wiki\
 --fablab Update data from fablab.io\n --log=1  define loglevel\n --initdb delete all records";
 exit;
};

message('Start update '.date("h:i:sa"),5);

if (isset($cliOptions['log'])) {
  $loglevel =  $cliOptions['log'];
  echo 'make log file level'.$cliOptions['log'].PHP_EOL;
};

if (isset($cliOptions['initdb']) or array_search('all', $argv)) {
    $database->delete('space',Medoo::Raw('WHERE true'));
    message('!! Database empty !!',4);
};

if (isset($cliOptions['api']) or isset($cliOptions['all'])) {
    getSpaceApi();
};
if (isset($cliOptions['fablab']) or isset($cliOptions['all'])) {
  getFablabJson();

};
if (isset($cliOptions['wiki']) or isset($cliOptions['all'])) {
  getHackerspacesOrgJson();
};

if (isset($cliOptions['comp']) or isset($cliOptions['log'])) {
    compareDistance();
};

//dupes are removed, generate wiki geojson again.
if (isset($cliOptions['log'])) {
  getHackerspacesOrgJson();
};







// if (array_search('initdb', $argv) or array_search('all', $argv)) {
//     $database->delete('space',['true']);
//     var_dump($database);
//     message('!! Database empty !!');
//     //deled from database (refresh all)
//     //
//     // if (array_search('initdb', $argv)) {
//     //     //$deleted = $database->delete('space',[true]);
//     //     $deleted = $database->delete('space','');
//     //     echo 'Records deleted - '.$deleted->rowCount();
//     //     echo 'Records left  - '.$database->count('space');
//     //     //exit;
//     // }
// };

// if (array_search('api', $argv) or array_search('all', $argv)) {
//     getSpaceApi();
// };
// if (array_search('fablab', $argv) or array_search('all', $argv)) {
//   getFablabJson();

// };
// if (array_search('wiki', $argv) or array_search('all', $argv)) {
//   getHackerspacesOrgJson();
// };

// if (array_search('comp', $argv) or array_search('all', $argv)) {
//     compareDistance();
// };

// //dupes are removed, generate wiki geojson again.
// if ( array_search('all', $argv)) {
//   getHackerspacesOrgJson();
// };



message('End '.date("h:i:sa"),5);


function getSpaceApi() {
    global $database;

    $array_geo = array ("type"=> "FeatureCollection");

    message("## Update Space api json file",5);

    $getApiDirResult = getCurl('https://raw.githubusercontent.com/SpaceApi/directory/master/directory.json');
    $hs_array = $getApiDirResult['json'];

    if ($getApiDirResult['error']!=0) {
        message('Space api dir not found, curl error  ',$getApiDirResult['error'],4);
    } else {

        //loop hackerspaces
        foreach ($hs_array as $space => $url) {

            message('Space '.$space);

            $foundError = $database->has("space", ["source"=>"A","sourcekey" => cleanUrl($url),"curlerrors[>]" =>0]);
            
            if ($foundError) {
                message('SKIP (in database with error) for '.$space,4);
            } else {
                $getApiResult = getCurl($url,5);

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
                        if ($apiJson['state']['open']) {
                            $icon = '/image/hs_open.png';
                        } else {
                            $icon = '/image/hs_closed.png';
                        };
                    } else {
                        $icon = '/image/hs.png';
                    };

                    //translate spaceapi array to geojson array
                    $full_address = explode(',',(isset($apiJson['location']['address'] )) ? $apiJson['location']['address'] : '' );

                    $address = (isset($full_address[0])) ? trim($full_address[0]) : '';
                    $zip = (isset($full_address[1] )) ? trim($full_address[1]) : '' ;
                    $city = (isset($full_address[2])) ? trim($full_address[2]) : '' ;

                    $email = (isset($apiJson['contact']['email'] )) ? $apiJson['contact']['email'] : '' ;
                    $phone = (isset($apiJson['contact']['phone'] )) ? $apiJson['contact']['phone'] : '' ;

                    addspace( $array_geo, $apiJson['space'] , $lon,$lat, $address, $zip, $city, $apiJson['url'], $email, $phone, $icon, $url,'A');

                    updateSpaceDatabase('A',cleanUrl($url),$space,0,$lon,$lat);
                    //message("Updating $space ");

                } else {
                    message("Skip $space - error ".$getApiResult['error'],5);
                    updateSpaceDatabase('A',cleanurl($url),$space,$getApiResult['error'],$lon,$lat);
                };
            };

        };
        saveGeoJSON('api.geojson',$array_geo);
    };
};

function updateSpaceDatabase ($source,$sourcekey,$name ='',$curlerrors=0,$lat=0,$lon=0) {
    global $database;

    if (($lat< -90 or $lat > 90) or ($lon < -180 or $lon > 180 )) {
        message('longitude or latitude wrong for '.$name,5);
    };


    $found = $database->has("space", ["source" =>$source,"sourcekey" => $sourcekey]);


    if (!$found) {
        //echo 'database Add'.PHP_EOL;
        $database->insert("space", [
            "source" => $source,
            "sourcekey" => $sourcekey,
            "lastdataupdated" => time(),

            "name" => $name,
            "lon" => $lon,
            "lat" => $lat,
            "curlerrors" => $curlerrors,
        ]);
    } else {
        //echo 'database Update'.PHP_EOL;
        $database->update("space", [
            "source" => $source,
            "sourcekey" => $sourcekey,
            "lastdataupdated" => time(),

            "name" => $name,
            "lon" => $lon,
            "lat" => $lat,
            "curlerrors" => $curlerrors,
        ],
          ["source" =>$source,"sourcekey" => $sourcekey]
    );
    }

    $errorlog = $database->error();
    if ($errorlog[1] != 0) {
        message('SqLite Error '.$errorlog[1]);
        //var_dump($errorlog);
    };
};

function getFablabJson() {
    $array_geo = array ("type"=> "FeatureCollection");

    // $hs_array = getCurl_old('https://api.fablabs.io/0/labs.json');

    $getFablabJsonResult = getCurl('https://api.fablabs.io/0/labs.json');
    // $getFablabJsonResult['json']
    // $getFablabJsonResult['error']

    message("## Update fablab json file",5);

    //setup for json later
    //$json_geo ='';

    //loop hackerspaces
    //foreach ($hs_array as $fablab ) {
    foreach ($getFablabJsonResult['json'] as $fablab ) {
        //echo "Updating ".$fablab['name'].PHP_EOL;

        if ( $fablab['activity_status'] =='active') { //isset($fablab) && 
            $id = $fablab['id'];

            $icon = '/image/fablab.png';

            $fullname = $fablab['name'];

            $nice_name = $fablab['slug'];

            if (isset($fablab['latitude']) && isset($fablab['longitude'])) {
                $lat = $fablab['latitude'];
                $lon = $fablab['longitude'];
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
   saveGeoJSON('fablab.geojson',$array_geo);
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
    global $database;

    $array_geo = array ("type"=> "FeatureCollection");
    $req_results = 50;
    $req_page = 0;
    $now = date_create(date('Y-m-d\TH:i:s'));
            
    message('#### JSON from wiki.hackerspace.org');

    $result = getPageHackerspacesOrg($req_results,$req_page);

    while (isset($result) && count($result)>0) {
        //echo ' count = '.count($result).PHP_EOL;
        foreach ($result as $space) {

            $fullname = $space['fulltext'];

            $lat =  $space['printouts']['Location'][0]['lat'];
            $lon =  $space['printouts']['Location'][0]['lon'];

            $icon = '/image/hs_black.png';

            $city =  (isset($space['printouts']['City'][0]['fulltext'])) ? $space['printouts']['City'][0]['fulltext'] : '';
           
            $email = $space['printouts']['Email'];
            $phone = $space['printouts']['Phone'];

            $url = (isset($space['printouts']['Website'][0])) ? $space['printouts']['Website'][0] : null;

            $spaceapi = (isset($space['printouts']['SpaceAPI'][0])) ?  $space['printouts']['SpaceAPI'][0]  : '';
            $source = $space['fullurl'];
            $lastupdate = date_create(date("Y-m-d\TH:i:s", $space['printouts']['Modification date'][0]['timestamp']));
            $interval = date_diff($now, $lastupdate)->format('%a'); 

            //if space not added to map with api add it here.
            $foundSpaceApi = $database->has("space", ["source"=>"A","sourcekey" => cleanUrl($spaceapi),"curlerrors" =>0]);

            if ($foundSpaceApi) {
               message('SKIP '.$fullname).' foundapi:'. $spaceapi;     
            } else {

                //check for results previos run
                $wiki_curlerror = $database->get("space",["curlerrors"], ["source"=>"W","sourcekey"  => $source]);
                if (isset($wiki_curlerror["curlerrors"])) {
                    if ($wiki_curlerror["curlerrors"]==0) {
                        //sit is up previous run
                        addspace( $array_geo, $fullname , $lon,$lat, '', '', $city, $url, $email, $phone, $icon, $source,'W');
                        message('Checked already, add to map '.$fullname);
                    } elseif($wiki_curlerror["curlerrors"]!=0) {
                        message('Checked already, site down '.$fullname);
                    }
                } else {
                    $getSiteStatus = getCurl($url);
      
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

        $req_page++;
        $result = getPageHackerspacesOrg($req_results,$req_page);
    };
    if ($req_page !=0) {
       saveGeoJSON('wiki.geojson',$array_geo);        
    } else {
        message('No wiki spaces found, nothing written');
    }
};


function getPageHackerspacesOrg($req_results,$req_page) {
    $offset = $req_page*$req_results;
    $url = "https://wiki.hackerspaces.org/Special:Ask/format=json/limit=$req_results/link=all/headers=show/searchlabel=JSON/class=sortable-20wikitable-20smwtable/sort=Modification-20date/order=desc/offset=$offset/-5B-5BCategory:Hackerspace-5D-5D-20-5B-5BHackerspace-20status::active-5D-5D-20-5B-5BHas-20coordinates::+-5D-5D-20-5B-5BNumber-20of-20members::+-5D-5D/-3F-23/-3FModification-20date/-3FEmail/-3FWebsite/-3FCity/-3FPhone/-3FNumber-20of-20members/-3FSpaceAPI/-3FLocation/mainlabel=/prettyprint=true/unescape=true";

    //$result = getCurl_old($url);
    //return $result['results'];


    $getWikiJsonResult = getCurl($url);

    if ($getWikiJsonResult['error']!=0){
        message(' Error while get wiki json '.$getWikiJsonResult['error']);
        return null;
    }

    return $getWikiJsonResult['json']['results'];
};


function compareDistance() {
    global $database;
    $results = $database->select('space',['source','sourcekey','name','lat','lon','curlerrors'],['curlerrors'=>0,"ORDER" => ['lat','lon'],]);

    $runfirst = true;
    $found = 0;
    foreach ($results as $space) {
        if ($runfirst) {
            $space_b = $space['name'];
            $spacesource_b = $space['source'];
            $sourcekey_b = $space['sourcekey'];
            //$curlerrors_b = $space['curlerrors'];
            $lon_b = floatval($space['lon']);
            $lat_b = floatval($space['lat']); 
            $runfirst=false;
        }

        $space_a = $space['name'];
        $spacesource_a = $space['source'];
        $sourcekey_a = $space['sourcekey'];
        $curlerrors_a = $space['curlerrors'];

        $lon_a = floatval($space['lon']);
        $lat_a = floatval($space['lat']);

        $distance = distance($lat_a,$lon_a,$lat_b,$lon_b,'K')*1000; //KM to meter
        $namelike = similar_text($space_a,$space_b,$namelike_perc);

        if ($distance <=200 && $namelike_perc>45 && !$runfirst && ($spacesource_a=='W' or $spacesource_b=='W')) {
            $found++;
            //echo 'A lat='.$lat_a.' lon='.$lon_a.PHP_EOL;
            //echo 'B lat='.$lat_b.' lon='.$lon_b.PHP_EOL;
            echo "within $distance m %=".(int)$namelike_perc.' #='.$namelike.PHP_EOL;
            echo '  1)'.$space_a.' ['.$spacesource_a.'] key ['.$sourcekey_a.']'.PHP_EOL;
            echo '  2)'.$space_b.' ['.$spacesource_b.'] key ['.$sourcekey_b.']'.PHP_EOL;
            if ($spacesource_a=='W') {
                //set space to not found 
                $database->update("space",["curlerrors" =>1001], ["source"=>$spacesource_a,"sourcekey" => $sourcekey_a]);
                message('  1 Updated ');
            } elseif($spacesource_b=='W') {
                $database->update("space", ["curlerrors" =>1001],["source"=>$spacesource_b,"sourcekey" => $sourcekey_b]);
                message('  2 Updated ');
            } else {
                message('** nothing updated.');
            };
        };

        $space_b = $space_a;
        $spacesource_b = $spacesource_a;
        $sourcekey_b = $sourcekey_a;
        //$curlerrors_b = $curlerrors_a;
        $lon_b = $lon_a;
        $lat_b = $lat_a; 

    };
    echo 'Found '.$found.PHP_EOL;
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

///////

function addspace(&$array_geo, $name, $lat, $lon, $address='', $zip='', $city='', $url, $email = '', $phone= '', $icon='/hsmap/hs.png',$source='',$sourcetype='A') 
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

function getCurl($url,$timeout=240) {
    global $messages;
    $curlSession = curl_init();
    curl_setopt($curlSession, CURLOPT_BINARYTRANSFER, true);
    curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($curlSession, CURLOPT_USERAGENT, "mapall.space");
    //for redirect
    curl_setopt($curlSession, CURLOPT_FOLLOWLOCATION, true);
    
    //no ssl verification
    //curl_setopt($curlSession, CURLOPT_SSL_VERIFYPEER, false);
    //curl_setopt($curlSession, CURLOPT_SSL_VERIFYHOST, false);
    
    //timeout in secs
    curl_setopt($curlSession, CURLOPT_TIMEOUT,$timeout); 

    //get file
    curl_setopt($curlSession, CURLOPT_URL, $url);
    $space_api_json = curl_exec($curlSession);

    $curl_error = curl_errno($curlSession);
    $curl_info = curl_getinfo($curlSession,CURLINFO_HTTP_CODE);

    curl_close($curlSession);

    if ( $curl_error == 0 && $curl_info == 200 ) {
        $json = json_decode($space_api_json, true);
        if ($json != null ){
            return array('json'=>$json,'error'=>0 );
        } else {
            //couldn't convert to json
            return array('json'=>null,'error'=>1000 );
        };
    } else {
        //message( '--Error on url '.$url.' CURL-ERROR:'.$curl_error.' INFO:'.$curl_info.'');
        $error = ($curl_error!=0) ? $curl_error : $curl_info;  
        return array('json'=>null,'error'=>$error);
    };
};

function message($message,$lineloglevel=0) {
    global $loglevel;
    global $loglevelfile;

    if ($lineloglevel > $loglevel) {
        echo $message.PHP_EOL;
    };

    if ($lineloglevel > $loglevelfile) {
        $fp = fopen($error_logfile, 'a');
        fwrite($fp,$message);
        fclose($fp);
    };
}

function saveGeoJSON($file, $array_geo) {
    global $geojson_path;
    $json_geo = json_encode($array_geo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);

    $fp = fopen($geojson_path.$file, 'w');
    fwrite($fp,$json_geo);
    fclose($fp);
};

function cleanUrl($url) {
    //remove http or https from url
    return preg_replace("(^https?://)", "", $url );
}



