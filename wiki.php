<?php
/*
Author : Dave Borghuis
See also : 	https://wiki.hackerspaces.org/Hackerspace_Census_2019
*/
require 'vendor/autoload.php';
require 'settings.php'; //get secret settings
require 'mapall_functions.php';

//database
use Medoo\Medoo;

if (isset($databasefile)) {
	$database = new Medoo([
	    'database_type' => 'sqlite',
	    'database_file' => $databasefile
	]);
} else {	
	echo 'Set $databasefile in settings.php';
	exit;
};

$wikiApi  = "https://wiki.hackerspaces.org/w/api.php";


if (php_sapi_name()=='cli') {

	$cliOptions = getopt('',['test','live','log::','count::','init','help','close::']);
	if (isset($cliOptions['help'])) {
		echo "Usage update.php [options] \n --test    Testing, do all except mailind and update wiki\n --live    Real run inc. updates and sending mail\n --init   Empty database\n --log=1  Define loglevel, 0 everything, 5 only errors\n --init Delete all records and logfile\n   --close=$name\n";
		 exit;
	};

	if (isset($cliOptions['init'])) {
	    $database->delete('wikispace',Medoo::Raw('WHERE true'));
	    // if(!file_exists ( $geojson_path.'errorlog.txt' )) {
	    //     unlink($geojson_path.'errorlog.txt');
	    // }
	    echo('Init : database empty and logfile removed');
	};

	//Have or live or test option. 
	if (!(isset($cliOptions['test']) or isset($cliOptions['live']))) {
		message('Use or test of live funtion.'.php_sapi_name());
		exit;
	};

	//testing
	if (isset($cliOptions['test'])) {
		$testrun = true;
	};

	if (isset($cliOptions['count'])) {
		$maxcount =  $cliOptions['count'];
	} else {
		$maxcount=0;
	};
	message('Maxcount = '.$maxcount);



	// $url = getCurl('https://nerdbridge.de');
	// $siteUp = getCurl($url);
	// //$date = getDateSiteAlternativeLink($site['result']);

	// //is name of hacker space mentioned in html?
	// $namefound = substr_count(strtoupper($siteUp['result']),str_replace('_',' ',strtoupper($fullname)));
	// message('Found name in stite '.$namefound,0);

	// if (!empty($url) and $namefound!=0 and len($siteUp['result'])>100) {
	// 	$checkDate['altLink'] = getDateSiteAlternativeLink($siteUp['result']);
	// 	if (!empty($checkDate['altLink'])) {
	// 		message('Alternative Link (rss)'.$checkDate['altLink'],0);
	// 	};
	// };
	// exit;

	//twitter API
	$twitter = new TwitterAPIExchange($twitterSettings);

	$loglevel=0;
	$loglevelfile=0;
	$log_path = './';
	$removeOlderThen = date("Y-m-d H:i",strtotime('-2 years'));;
	$httpHeaders = [];

	// ** Login wiki **//
	//$wikiApi  = "https://test.wikipedia.org/w/api.php";
	//$wikiApi  = "https://wiki.hackerspaces.org/w/api.php";
	$login_Token = getLoginToken();
	//message('Login token ='.$login_Token);
	loginRequest( $login_Token );
	$csrf_Token = getCSRFToken();


	//Have or live or test option. 
	if ( isset($cliOptions['close']) ) {
		$space = $cliOptions['close'];
		updateOneHackerSpace($space,'update');
	} else {
		//For each hackerspace do 
		getHackerspacesOrgJson();
	};

	//all done, logout
	echo 'Logout'.PHP_EOL;
	logoutRequest($csrf_Token);


} else {
	//call from web
	$loglevel=0;
	$loglevelfile=0;
	$log_path = './';
	$removeOlderThen = date("Y-m-d H:i",strtotime('-2 years'));;
	$httpHeaders = [];

	// ** Login wiki **//
	//$wikiApi  = "https://test.wikipedia.org/w/api.php";


};


function updateOneHackerSpace($space,$action) {
	global $wikiApi,$login_Token,$csrf_Token ;

	if (empty($login_Token)) {
		$login_Token = getLoginToken();
		//message('Login token ='.$login_Token);
		loginRequest( $login_Token );
		$csrf_Token = getCSRFToken();
	}

	$wikitext = getWikiPage($space);

	if (strpos($wikitext, '|email=')>0) {
		$email = substr($wikitext, strpos($wikitext, '|email=')+7);
		$email = substr($email,0,strpos($email, '|')-1);
	} elseif(strpos($wikitext,'|residencies_contact=')>0) {
		$email = substr($wikitext, strpos($wikitext, '|residencies_contact=')+21);
		$email = substr($email,0,strpos($email, '|')-1);
	} else {
		$email = '';
	}


	switch ($action) {
		case 'close':
			sendEmail($email,$space,'https://wiki.hackerspaces.org/'.$space);
			updateHackerspaceWiki($space,'close');
			break;
		case 'update':
			//sendEmail($email,$space,'https://wiki.hackerspaces.org/'.$space);
			updateHackerspaceWiki($space,'update');
			break;
		default:
			message('ERROR : Action not defined!!',5);
			break;
	}
}


function getHackerspacesOrgJson() {
    global $database, $removeOlderThen, $testrun, $maxcount;

    $req_results = 50;
    $req_page = 0;
    $now = date_create(date('Y-m-d\TH:i:s'));
            
    $statistics = [];

    message('#### Check hackerspaces ####');

    $result = getPageHackerspacesOrg($req_results,$req_page);

    while (isset($result) && count($result)==50) {

        foreach ($result as $space) {
        	//var_dump($space);

        	//$statistics['total']+=1;

            $fullname = $space['fulltext'];
            $lastupdate = date_create(date("Y-m-d\TH:i:s", $space['printouts']['Modification date'][0]['timestamp']));
            $interval = date_diff($now, $lastupdate)->format('%a'); //in days

	        $source = $space['fullurl'];

            message('.......... ');
            message('** Space '.$fullname.' Last modified '.$lastupdate->format('Y-m-d').' days '.$interval.' **  '.$source,4);

            $url = (isset($space['printouts']['Website'][0])) ? $space['printouts']['Website'][0] : null;

            message('Url = '.$url);
            //check if fullname and url match

            //only check if no modification in last year
            if ($interval > 356 ) {
            	$statistics['total']+=1;
       		
	            $email = (isset($space['printouts']['Email'][0])) ?  $space['printouts']['Email'][0]  : '';

				$siteUp = getCurl($url,null,60); //wait long time for responce 


				//TODO : if site not defined still check other media (eg twitter etc.)
				if ($siteUp['error']==0 or empty($url)) {

					//clear all  dates
					$checkDate = array();

					if (empty($url)) {
						message('No site, check on dates.');
					} else {
						message('Site up check on dates.');

						// //is name of hacker space mentioned in html?

						// $namefound = substr_count(strtoupper($siteUp['result']),str_replace('_',' ',strtoupper($fullname)));
						// message('Found name in stite '.$namefound,0);

						// // $fullname replace _ with ' '
						// // find $fullname in $siteUp['respons'];
						// echo 'site *** ';
						// //var_dump($siteUp['result']);

						// echo ' *** end site'.PHP_EOL;

						// if (!empty($url) and $namefound!=0 and strlen($siteUp['result'])>100) {
						// 	$checkDate['altLink'] = getDateSiteAlternativeLink($siteUp['result']);
						// 	if (!empty($checkDate['altLink'])) {
						// 		message('Alternative Link (rss)'.$checkDate['altLink'],0);
						// 	};
						// };

					}

					//get from http header - disabled, to many false positives
					// if ($siteUp['lastmodified']!='') {
					// 	$checkDate['httpLastModified'] = $siteUp['lastmodified'];
					// 	message('Site Last Modified (http headers) '.$siteUp['lastmodified']);
					// }

					//check wiki
					if (isset($space['printouts']['Wiki'][0])) {
						$hackerspaceWiki = $space['printouts']['Wiki'][0];
						$checkDate['wiki'] = getDateLastWikiEdit($hackerspaceWiki);
						message('Wiki '.$checkDate['wiki'].' - '.$space['printouts']['Wiki'][0]);
					}

					//check twitter
					if (isset($space['printouts']['Twitter'][0])) {
						$checkDate['twitter'] = getDateLastTweet($space['printouts']['Twitter'][0] );
						message('Twitter '.$checkDate['twitter'].' - '.$space['printouts']['Twitter'][0]);
					}

					//check spaceAPI
					if (isset($space['printouts']['SpaceAPI'][0])) {
						$checkDate['spaceapi'] = getDataLastSpacaAPI($space['printouts']['SpaceAPI'][0]);
						message('SpaceAPI '.$checkDate['spaceapi'] );
					}

					//mailinglist
					if (isset($space['printouts']['Mailinglist'][0])) {
						$checkDate['mailman'] = getDateLastMailManPost($space['printouts']['Mailinglist'][0]);
						message('Mailinglist '.$checkDate['mailman'].' - '.$space['printouts']['Mailinglist'][0]);
					}
					
					//newsfeed, asume rss/xml athom file
					if (isset($space['printouts']['Newsfeed'][0])) {
						$checkDate['newsfeed'] = getDateNewsFeed($space['printouts']['Newsfeed'][0]);
						message('Newsfeed '.$checkDate['newsfeed'].' - '.$space['printouts']['Newsfeed'][0]);	
					}

					//calender feed
					if (isset($space['printouts']['CalendarFeed'][0])) {
						$checkDate['calender'] = getDataLastCalenderFeed($space['printouts']['CalendarFeed'][0]);
						message('Calenderfeed '.$checkDate['calender'].' - '.$space['printouts']['CalendarFeed'][0]);
					}

					//do all the check
					$lastUpdateDate = 0;
					foreach ($checkDate as $source => $date) {
						if ($date > $lastUpdateDate) {
							$lastUpdateDate = $date;
						};
					};
					message('Last Activity was on '. $lastUpdateDate,2);

					if ($lastUpdateDate==0) {
			        	$statistics['manual']+=1;

						message('No activity for space, manual check.',5);

						updateDatabase($source,$fullname,0,'manual');

					} elseif ($removeOlderThen > $lastUpdateDate) {
			        	$statistics['inactive']+=1;

						message('No activity for space, set to closed.',5);
						message('Send email to '.$email,4);

						if (!$testrun) {
							sendEmail($email,$fullname,$source);
							updateHackerspaceWiki($fullname,'close');//Step5
						}
						updateDatabase($source,$fullname,0,'inactive');

					} else {
						$statistics['active']+=1;

						message('Space still active, update wiki page ',4);
						if (!$testrun) {
							updateHackerspaceWiki($fullname,'update');//Step5
						}
						updateDatabase($source,$fullname,0,'active');
					}

				} else {
					$statistics['down']+=1;

					$count = updateDatabase($source,$fullname,$siteUp['error'],'down');
					message('Site down Error: ['.$siteUp['error'].'] Checked times :'.$count,4);

					if ($count >3) {
						message('Site down for 3 x -- Close space and send email to '.$email,5);
						if (!$testrun) {
							sendEmail($email,$fullname,$source);
							updateHackerspaceWiki($fullname,'close');
						};
					};

				};

            } else {
            	$statistics['skipped']+=1;
            };

	        if ($maxcount!=0 and $statistics['total']>=$maxcount) {
	    	    message('Processed :'.$statistics['total'].' Down :'.$statistics['down'].' Manual :'.$statistics['manual'].' Active :'.$statistics['active'].' Inactive :'.$statistics['inactive'].' Skipped :'.$statistics['skipped']);
	            return;
	        };


        };


        $req_page++;
        $result = getPageHackerspacesOrg($req_results,$req_page);
    };
    //all done.. 
    message('Processed :'.$statistics['total'].' Down :'.$statistics['down'].' Manual :'.$statistics['manual'].' Active :'.$statistics['active'].' Inactive :'.$statistics['inactive'].' Skipped :'.$statistics['skipped']);
};


function getPageHackerspacesOrg($req_results,$req_page) {
	global $testrun;

    $offset = $req_page*$req_results;

    if ($testrun && false) {
		$sorting = 'desc'; //for testing, newest first
    } else {
	    $sorting = 'asc';    	
    };

    //$sorting = 'asc';    	

    //Live
    $url = "https://wiki.hackerspaces.org/Special:Ask/format=json/limit=$req_results/link=all/headers=show/searchlabel=JSON/class=sortable-20wikitable-20smwtable/sort=Modification-20date/order=$sorting/offset=$offset/-5B-5BCategory:Hackerspace-5D-5D-20-5B-5BHackerspace-20status::active-5D-5D-20-5B-5BHas-20coordinates::+-5D-5D/-3F-23/-3FModification-20date/-3FEmail/-3FWebsite/-3FCity/-3FPhone/-3FNumber-20of-20members/-3FSpaceAPI/-3FLocation/-3FCalendarFeed/-3FFeed/-3FNewsfeed/-3FTwitter/-3FFacebook/-3FEmail/-3FMailinglist/mainlabel=/prettyprint=true/unescape=true/s-maxage=0";


    $getWikiJsonResult = getJSON($url);

    if ($getWikiJsonResult['error']!=0){
    	var_dump($getWikiJsonResult['json']);
        message(' Error while get wiki json '.$getWikiJsonResult['error']);
        return null;
    }

    return $getWikiJsonResult['json']['results'];
};


function updateDatabase($wikiurl,$name ='',$lastcurlerror=0,$status='') {
    global $database;

    $found = $database->has("wikispace", ["wikiurl" => $wikiurl]);
 
    if ($lastcurlerror!=0) {
        $errorcount = 1;
    } else {
        $errorcount = 0;
    }

    if (!$found) {
        $database->insert("wikispace", [
        	"wikiurl" =>$wikiurl,
        	"name" =>$name,
            "lastdataupdated" => time(),
            "name" => $name,
            "lastcurlerror" => $lastcurlerror,
            "curlerrorcount" => $errorcount,
            "status" => $status, 
        ]);
    } else {
        $database->update("wikispace", [
        	"wikiurl" =>$wikiurl,
        	"name" =>$name,
            "lastdataupdated" => time(),
            "lastcurlerror" => $lastcurlerror,
            "curlerrorcount[+]" =>$errorcount,
            "status" => $status, 

	        ],
	          ["wikiurl" =>$wikiurl]
		);
    }

    $errorlog = $database->error();
    if ($errorlog[1] != 0) {
        message('SqLite Error '.$errorlog[1]);
    };

    //get couter
	$count = $database->get("wikispace","curlerrorcount", ["wikiurl" =>$wikiurl]);
	return $count;


};

// Step 1: GET request to fetch login token
function getLoginToken() {
	global $wikiApi ;

	$params = [
		"action" => "query",
		"meta" => "tokens",
		"type" => "login",
		"format" => "json"
	];

	$url = $wikiApi  . "?" . http_build_query( $params );

	$result = getJSON($url);

	return $result["json"]["query"]["tokens"]["logintoken"];
}

// Step 2: POST request to log in. Use of main account for login is not
// supported. Obtain credentials via Special:BotPasswords
// (https://www.mediawiki.org/wiki/Special:BotPasswords) for lgname & lgpassword
function loginRequest( $logintoken ) {
	global $wikiApi ;
	global $botUser;
	global $botPasswd;

	$params = [
		"action" => "login",
		"lgname" => $botUser,
		"lgpassword" => $botPasswd,
		"lgtoken" => $logintoken,
		"format" => "json"
	];

	$url = $wikiApi;//  . "?" . http_build_query( $params );

	$result = getJSON($url,$params);
}

// Step 3: GET request to fetch CSRF token
function getCSRFToken() {
	global $wikiApi ;

	$params = [
		"action" => "query",
		"meta" => "tokens",
		"format" => "json"
	];

	$url = $wikiApi  . "?" . http_build_query( $params );

	$result = getJSON($url);

	return $result["json"]["query"]["tokens"]["csrftoken"];
}

function getWikiPage($spaceURLname) {
	global $wikiApi ;

	$params = [
		"action" => "parse",
		"page" => $spaceURLname,
		"prop" => "wikitext",
	    "format" => "json"
	];

	$url = $wikiApi . "?" . http_build_query( $params );

	$result = getJSON($url);
	return($result["json"]["parse"]["wikitext"]["*"]);
}


// Step 4: POST request to edit a page
function updateHackerspaceWiki( $spaceURLname , $action ) {
	global $wikiApi, $csrf_Token;

	//https://wiki.hackerspaces.org/Special:ApiSandbox#action=edit&title=TkkrLab&appendtext=%22Hello%20World%22&format=json

	//get current page
	$wikitext = getWikiPage($spaceURLname);

	//check on current status, should be active. If not give ERROR
	$currentState = substr($wikitext, strpos($wikitext, '|status=')+8,6);
	if ($currentState != 'active') {
		message('ERROR: Wiki for '.$spaceURLname.' is '.$currentState.'. No changes made ',5);
		return;
	}

	if ($action == 'close') {
		$newpage = str_replace('|status=active','|status=closed',$wikitext);
	} elseif ($action == 'update') {
		$newpage = $wikitext."\n<!-- Checked by mapall.space bot on ".date("Y-m-d H:i").'. -->';
	} else {
		message('No hs wiki action defined!!',5);
	}

	$params = [
		"action" => "edit",
		"title" => $spaceURLname,
		"text" => $newpage,
		"token" => $csrf_Token,
		"summary" => "Updates by mapall.space bot",
		"bot" => true,
		"format" => "json"
	];

	$url = $wikiApi ;// . "?" . http_build_query( $params );
	$result = getJSON($url,$params);

	if (isset($result['json']['error']) or $result['json']['edit']['result']=='Failure') {
		//var_dump( $result['json']['error']);
	}

	//solve captcha 
	if ($result['json']['edit']['result']=='Failure' and isset($result['json']['edit']['captcha']) ) {

		$captchparams = [
			"captchaid" => $result["json"]['edit']['captcha']['id'],
			"captchaword" => getCaptchaAnswer($result["json"]['edit']['captcha']['question']),
		];
		$params = array_merge($params,$captchparams);

		$url = $wikiApi ;// . "?" . http_build_query( $params );

		$result = getJSON($url,$params);

		if (isset($result['json']['error']) or $result['json']['edit']['result']=='Failure') {
			//var_dump( $result);
		};
	}

	//clear chache / purge
	//https://www.mediawiki.org/wiki/API:Purge
	$params = [
		"action" => "purge",
		"titles" => $spaceURLname,
		"format" => "json"
	];

	$url = $wikiApi ;// . "?" . http_build_query( $params );
	$result = getJSON($url,$params);

}


// Step 4: POST request to logout
function logoutRequest( $csrftoken ) {
	global $wikiApi;
	$params = [
		"action" => "logout",
		"token" => $csrftoken,
		"format" => "json",
	];


	$url = $wikiApi;//  . "?" . http_build_query( $params );

	$result = getJSON($url,$params);
}

function getCaptchaAnswer($question) {
	switch ($question) {
		case "What does the quote on the top of the List of Events page say?":
			return 'To become great, you must stand on the shoulders of giants.';
			break;
        case "Where is hackerspaces.org currently hosted at? Hint: Read the Disclaimers (bottom of page)":
            return "Nessus";
			break;
        case "What is the name of our IRC channel on freenode? Hint: Read the Communication page":
            return "#hackerspaces";
			break;
		case "This website is for whom? Hint: Read the frontpage":
			return "Anyone and Everyone";
			break;
		case "Hacker_____?":
			return "spaces";
			break;
		default:
			message("CaptchaAnswer not found for quesion :".$question,5);
	}
}

function message($message,$lineloglevel=0) {
    global $loglevel;
    global $loglevelfile;
    global $log_path;

    if ($lineloglevel >= $loglevel) {
        echo $message.PHP_EOL;
    };

    if ($lineloglevel >= $loglevelfile) {
        //
        if(!file_exists ( $log_path.'errorlog.txt' )) {
            $message = "Map all spaces error log, see also FAQ\nError 0-99 Curl\nError 100-999 http\nError 1000 no valid json\nError 1001 dupe\n\n".$message;
        }

        $fp = fopen($log_path.'errorlog.txt', 'a');
        fwrite($fp,$message.PHP_EOL);
        fclose($fp);
    };
}

function getDataLastSpacaAPI($spaceapiurl) {
		$json = getJSON($spaceapiurl);
		if (isset($json['json']['state']['lastchange']) and $json['json']['state']['lastchange']!=0) {
	        return date("Y-m-d H:i:s",$json['json']['state']['lastchange']);
		} else {
			return null;
		}
}

function getDateLastTweet($user) {
	global $twitter;

	$tuser = @end(explode('/', $user));

	$url = 'https://api.twitter.com/1.1/statuses/user_timeline.json';
	$getfield = "?screen_name=$tuser&count=1";
	$requestMethod = 'GET';

	$result = json_decode($twitter->setGetfield($getfield)
	             ->buildOauth($url, $requestMethod)
	             ->performRequest(),JSON_OBJECT_AS_ARRAY);

	if(isset($result[0]['created_at']) or $tuser='') {
		return date("Y-m-d H:i",strtotime($result[0]['created_at']));
	} else {
		message('**** twitter timeline empty?',5);
		return '';
	}

};

function getDateLastWikiEdit($wiki) {
	//do something smart with /wiki/ or /w/ 
	$result = getJSON($wiki.'/w/api.php?action=query&format=json&list=recentchanges');
	return date("Y-m-d H:i",strtotime($result['json']['query']['recentchanges'][0]['timestamp']));
}

function getDateNewsFeed($feed) {
	$result = getCurl($feed);
	if ((substr($result['result'],0,4)=='<rss') or (substr($result['result'],0,5)=='<?xml')) {
		$xml =simplexml_load_string($result['result'],'SimpleXMLElement',LIBXML_NOERROR);
		return date("Y-m-d H:i",strtotime($xml->channel->lastBuildDate));
	} else {
		message('Newsfeed no RSS feed '.$feed);
		return null;
	}
}

function getDateLastMailManPost($mailinglist){
	//if mailman
	if (strpos($mailinglist,'/mailman/listinfo/')>0) {
		$pos =strpos($mailinglist,'/mailman/listinfo/');
		$pipermail = substr($mailinglist,0,$pos).'/pipermail/'.substr($mailinglist,$pos+18);
		$result = getCurl($mailman);
		preg_match_all('/<td>(.*?)<\/td>/i', $result['result'], $matches, PREG_SET_ORDER, 0);
		$lastArchive = substr($matches[3][0],4,-6);
		$foundDate = strtotime($lastArchive);
			if ($foundDate !=0 and $result['error']==0) {
				return date("Y-m-d H:i",$foundDate);
			} else {
				return null;
			}

	} elseif(strpos($mailinglist,'groups.google.com')>0) {
		//convert to https
		if (substr($mailinglist,0,5)=='http:') {
			$mailinglist = 'https:'.substr($mailinglist,5);	
		}
		//convert url to rss feed
		if (strpos($mailinglist,'/group/')>0) {
			$googlefeed= str_replace('/group/','/forum/feed/',$mailinglist).'/msgs/rss_v2_0.xml?num=1';
		} else {
			$googlefeed= str_replace('/#!forum/','/feed/',$mailinglist).'/msgs/rss_v2_0.xml?num=1';
		}
		$result = getCurl($googlefeed);
		if (substr($result['result'],0,4)=='<rss')  {
			$xml =simplexml_load_string($result['result'],'SimpleXMLElement',LIBXML_NOERROR);
			return date("Y-m-d H:i",strtotime($xml->channel->item->pubDate));
		} else {
			return null;
		}
	} else {
		return null;
	}
}

function getDataLastCalenderFeed($ical) {
	$result = getCurl($ical);
	$file = str_getcsv($result['result'],"\n");
    foreach ($file as $line) {
    	//echo $line.PHP_EOL;
   //  	if (substr($line,0,8)=='CREATED:') {
			// $foundCreateDate = date("Y-m-d H:i",strtotime(substr($line,8)));
   //  	}
   //  	if ($foundCreateDate > $lastCreateDate) {
   //  		$lastCreateDate = $foundCreateDate; 
   //  	}

    	//if calender dont have CREATED entrys
    	if (substr($line,0,6)=='DTEND:') {
			$foundEndDate = date("Y-m-d H:i",strtotime(substr($line,6)));
    	}
    	if ($foundEndDate > $lastEndDate) {
    		$lastEndDate = $foundEndDate; 
    	}

    	if (substr($line,0,8)=='DTSTAMP:') {
			$foundStampDate = date("Y-m-d H:i",strtotime(substr($line,8)));
    	}
    	if ($foundStampDate > $lastStampDate) {
    		$lastStampDate = $foundStampDate; 
    	}


    }
    //message('Create'.$lastCreateDate.'  DTEND:'.$lastEndDate.'DTSTAMP:'.$lastStampDate);

    if (isset($lastStampDate)) {
	    return $lastStampDate;

    } else {
    	return $lastEndDate;
    }
}


function getDateSiteAlternativeLink($site) {
	//$url = getCurl($url,null,20);
	//$checkDate = array();
					
	libxml_use_internal_errors(true);
	$DOMfile = new DomDocument();
	$DOMfile->loadHTML($site);
	$xml = simplexml_import_dom($DOMfile);
	//var_dump($xml->head->link);
	foreach ( $xml->head->link as $link ) {
		message('Link found'.print_r($link));
	    if ( $link['rel'] == 'alternate' ) {
	    	$type = (string)$link['type'];
	        $self_link = (string)$link['href'];
	        message(' Found '.$self_link.' type ='.$type,0);
	        if ($type == 'application/rss+xml' or $type == 'application/atom+xml') {
	        	$founddate = getDateNewsFeed($self_link);
    	      	message('Date = '.$founddate.' Found '.$self_link.' type ='.$type,	0);
	        	if ($founddate > $checkDate) {
	        		$checkDate = $founddate;
	        	}
	        	//$foundnum +=1;
            	//$checkDate[$foundnum] = getDateNewsFeed($self_link);
	        } 
	    }
    	return $checkDate;
	}

	//do all the check
	// $lastUpdateDate = 0;
	// foreach ($checkDate as $source => $date) {
	// 	if ($date > $lastUpdateDate) {
	// 		$lastUpdateDate = $date;
	// 	};
	// };

	// return $lastUpdateDate;

};


function sendEmail($email,$fullname,$url) {
		if ($testrun) {
			$email = 'dave@daveborghuis.nl';
		}
		if (empty($email)) {
			return false;
		};
        $headers = "From:Dave Borghuis <webmaster@mapall.space>\r\nMIME-Version: 1.0\r\nContent-type: text/html; charset=iso-8859-1";
        $mailmessage = "Hello,<br>Wiki entry for <a href=\"$url\">$fullname</a> has been changed. We tryed to acces your site several times but got http errors. We asume that your hacerspace is no longer active. If this is not the case go to the wiki and change the status and add additional information if possible.<br>More information about this proces can be found on <a href=\"https:\\\\mapall.space\\hswikilist.php\">Mapall site</a><br>Regards,<br>Dave Borghuis";
        $mailsend = mail( 
            $email,
            'Hackerspaces.org entry for '.$fullname ,
            $mailmessage,
            $headers
        );

        if ($mailsend) {
            echo 'Send email to '.$email.PHP_EOL;
            return true;
        } else {
            echo 'Email not send!! '.$email.PHP_EOL;
            return false;
        }
};


?>