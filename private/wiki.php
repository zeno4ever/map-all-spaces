<?php
/*
Author : Dave Borghuis
See also : 	https://wiki.hackerspaces.org/Hackerspace_Census_2019
*/

require 'init.php';

$wikiApi  = "https://wiki.hackerspaces.org/w/api.php";
$log_path = './';
$loglevel=0;
$loglevelfile=0;
$removeOlderThen = date("Y-m-d H:i",strtotime('-2 years'));
$wikiMessage ='';

if (php_sapi_name()=='cli') {

	$cliOptions = getopt('',['test','live','log::','count::','init','help','close::']);
	if (isset($cliOptions['help'])) {
		echo "Usage update.php [options] \n --test    Testing, do all except mailind and update wiki\n --live    Real run inc. updates and sending mail\n --init   Empty database\n --log=1  Define loglevel, 0 everything, 5 only errors\n --init Delete all records and logfile\n   --close=$name\n";
		 exit;
	};

	if (isset($cliOptions['init'])) {
	    $result = $db->delete('wikispace');
		if ($db->getLastErrno() !=0) {
			echo "SQL error : ".$db->getLastError();
		}
	    echo('Init : database empty');
	};

	//Have or live or test option. 
	if (!(isset($cliOptions['test']) or isset($cliOptions['live']))) {
		message('Use or test of live funtion.'.php_sapi_name());
		exit;
	};

	//testing
	if (isset($cliOptions['test'])) {
		$testrun = true;
	} else {
		$testrun = false;
	};

	if (isset($cliOptions['count'])) {
		$maxcount =  $cliOptions['count'];
	} else {
		$maxcount=0;
	};
	
	message('Maxcount = '.$maxcount);

	// ** Login wiki **//
	$login_Token = getLoginToken();
	//message('Login token ='.$login_Token);
	loginRequest( $login_Token );
	$csrf_Token = getCSRFToken();

	//Have or live or test option. 
	if ( isset($cliOptions['close']) ) {
		//test with wikiMessage text
		$wikiMessage = '[This might be the bot text]';
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
};


function updateOneHackerSpace($space,$action) {
	global $wikiApi,$login_Token,$csrf_Token,$wikiMessage;

	$date = date("h:i:sa");
	$wikiMessage .= "Updated manual on $date via http://mapall.space/hswikilist.php\n ";

	if (empty($login_Token)) {
		$login_Token = getLoginToken();
		loginRequest( $login_Token );
		$csrf_Token = getCSRFToken();
	}

	$wikitext = getWikiPage($space);

	//email
	preg_match('/^\/|email=(.*)/', $wikitext, $newmail);

	//residencies contact
	preg_match('/^\/|residencies_contact=(.*)/', $wikitext, $resmail);

	if (isset($newmail[1]) and !isset($resmail[1]))  {
		$email = $newmail[1];
	} elseif (isset($resmail[1])) {
		$email = $resmail[1];
	};

	switch ($action) {
		case 'close':
			if(isset($email)) {
				sendEmail($email,$space,'https://wiki.hackerspaces.org/'.$space);
				//echo "Send email to :".$email.PHP_EOL;
			}
			updateHackerspaceWiki($space,'inactive');
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
    global $removeOlderThen, $testrun, $maxcount, $wikiMessage;

    $req_results = 50;
    $req_page = 0;
    $now = date_create(date('Y-m-d\TH:i:s'));
            
    $statistics = array('total'=>0,'down'=>0,'manual'=>0,'active'=>0,'inactive'=>0,'skipped'=>0);

    message('#### Check hackerspaces on '.date('Y-m-d H:i').' ####');

    $result = getPageHackerspacesOrg($req_results,$req_page);

    while (isset($result) && count($result)==50) {

        foreach ($result as $space) {
			$wikiMessage = '';

            $fullname = $space['fulltext'];
            $lastupdate = date_create(date("Y-m-d\TH:i:s", $space['printouts']['Modification date'][0]['timestamp']));
            $interval = date_diff($now, $lastupdate)->format('%a'); //in days

	        $source = $space['fullurl'];

            echo '.......... '.PHP_EOL;
            message('** Space '.$fullname.' Last modified on wiki '.$lastupdate->format('Y-m-d').' - days '.$interval.' ago **  '.$source,4);

            // $url = (isset($space['printouts']['Website'][0])) ?? $space['printouts']['Website'][0] : null;
            $url = $space['printouts']['Website'][0] ?? null;

            message('Url = '.$url);

            //only check if no modification in last year
            if ($interval > 356 ) { 
            	$statistics['total']+=1;

				$email =  $space['printouts']['Email'][0]  ?? '';

				if (isset($space['printouts']['Residencies Contact'][0])) {
					$email .= $space['printouts']['Residencies Contact'][0] ??'';
					echo 'Found Residencies mail '. $space['printouts']['Residencies Contact'][0];
				};
				$email = str_replace('mailto:', '' ,$email);


				$siteUp = getCurl($url,null,60); //wait long time for responce 

				if ($siteUp['error']==0 or empty($url)) {
					//clear all  dates
					$checkDate = array();

					if (empty($url)) {
						message('No site, check on dates.');
					} else {

						message('Site up check on RSS dates.');

						$namefound = substr_count(strtoupper($siteUp['result']),str_replace('_',' ',strtoupper($fullname)));
						message('Found name on homepage '.$namefound.' times.',0);

						if ($namefound>0 && !empty($siteUp['result'])) {

							$siteFeed = getDateSiteAlternativeLink($siteUp['result'],$url);

            				//some rss feeds return current datetime, exclude these
							if (!empty($siteFeed)) {
								$siteFeedDate = date_create($siteFeed);

	            				$intervalSiteDays = date_diff($now, $siteFeedDate)->format('%a');

								if ($intervalSiteDays>1) {
									$checkDate['altLink'] = $siteFeed;
									message('Alternative Link (rss) :'.$checkDate['altLink'],0);
								};
							};
						};
					};

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

					//check twitter, not usable since Musk took over
					// if (isset($space['printouts']['Twitter'][0])) {
					// 	$checkDate['twitter'] = getDateLastTweet($space['printouts']['Twitter'][0] );
					// 	message('Twitter '.$checkDate['twitter'].' - '.$space['printouts']['Twitter'][0]);
					// }

					//check twitter via nitter
					if (isset($space['printouts']['Twitter'][0])) {
						$checkDate['twitter'] = getDateLastTweetNitter($space['printouts']['Twitter'][0] );
						message('Twitter/nitter '.$checkDate['twitter'].' - '.$space['printouts']['Twitter'][0]);
					}

					//check Fediverse/Mastadon
					if (isset($space['printouts']['Fediverse'][0])) {
						$checkDate['fediverse'] = getDateLastFediverse($space['printouts']['Fediverse'][0] );
						message('Fediverse '.$checkDate['fediverse'].' - '.$space['printouts']['Fediverse'][0]);
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

					//placeholder for facebook check

					//check if lon/lat is within normal range
					if (isset($space['printouts']['Location'][0])) {
						$location = $space['printouts']['Location'][0];
						//message('lat' . $location['lat '] . '/ lon ' . $location['lon']);
						if ($location['lon'] < -180 or $location['lon'] > 180 or $location['lat'] < -90 or $location['lat'] > 90
						) {
							message('Wrong lat\lon is : [ lat ' . $location['lat'] . '/ lon ' . $location['lon']);
							//sendErrorLonLatEmail($email, $fullname, $url, $location);
						}
					};

					//do all the check
					$lastUpdateDate = 0;
					foreach ($checkDate as $datesource => $date) {
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

						message('No activity for space, set to inactive.',5);
						message('Send email to '.$email,4);

						if (!$testrun) {
							sendEmail($email,$fullname,$source);
							updateHackerspaceWiki($fullname,'inactive');//Step5
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


						$result = updateDatabase($source,$fullname,$siteUp['error'],'down');

						if (count($result)>0) {
							$string = '';
							foreach ($result as $value) {
								$string .= $value[''].' ( Error '.$value['lastcurlerror'].'), ';
							}
							message('Site down, checked on dates : ' . $string);
							if (count($result)>3) {
								$statistics['down'] += 1;
								message('Set wiki to inactive, send email to ',$email);
								if (!$testrun) {
									sendEmail($email,$fullname,$source);
									updateHackerspaceWiki($fullname,'inactive');
								}
							} else {
								$statistics['skipped'] += 1;
							}						
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

    if ($testrun) {
		$sorting = 'desc'; //for testing, newest first
    } else {
	    $sorting = 'asc';    	
    };

    $wikiDate = date("d-20M-20Y",mktime(0, 0, 0, date("m"),   date("d")-11,   date("Y")));
    $url = "https://wiki.hackerspaces.org/w/index.php?title=Special:Ask&x=-5B-5BCategory%3AHackerspace-5D-5D-20-5B-5BHackerspace-20status%3A%3Aactive-5D-5D-20-5B-5BHas-20coordinates%3A%3A%2B-5D-5D-20-5B-5BModification-20date%3A%3A%E2%89%A4$wikiDate-5D-5D%2F-3F-23%2F-3FModification-20date%2F-3FEmail%2F-3FWebsite%2F-3FWiki%2F-3FCity%2F-3FPhone%2F-3FNumber-20of-20members%2F-3FSpaceAPI%2F-3FLocation%2F-3FCalendarFeed%2F-3FNewsfeed%2F-3FTwitter%2F-3FFediverse%2F-3FFacebook%2F-3FEmail%2F-3FMailinglist&format=json&limit=$req_results&offset=$offset&link=all&headers=show&searchlabel=JSON&class=sortable+wikitable+smwtable&sort=Modification+date&order=$sorting&mainlabel=&prettyprint=true&unescape=true";

    $getWikiJsonResult = getJSON($url);

    if ($getWikiJsonResult['error']!=0){
    	// var_dump($getWikiJsonResult['json']);
        message(' Error while get wiki json '.$getWikiJsonResult['error']);
        return null;
    }

    return $getWikiJsonResult['json']['results'];
};


function updateDatabase($wikiurl,$name ='',$lastcurlerror=0,$status='') {
	$db = MysqliDb::getInstance();

	$data = array(
    	"wikiurl" =>$wikiurl,
    	"name" =>$name,
        "lastdataupdated" => $db->now(),
        "lastcurlerror" => $lastcurlerror,
        "status" => $status, 
	);

	if ($db->where("wikiurl",$wikiurl)->getOne("wikispace"))  {
		$result = $db->update("wikispace", $data);
	} else {
		$result = $db->insert("wikispace", $data);
	}




	if ($db->getLastErrno() !== 0) {
		echo 'Update failed. Error: '. $db->getLastError();
	}

    return $db->get("wikispace", "wikiurl = $wikiurl");
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
	global $wikiApi, $csrf_Token, $wikiMessage;

	//https://wiki.hackerspaces.org/Special:ApiSandbox#action=edit&title=TkkrLab&appendtext=%22Hello%20World%22&format=json

	//get current page
	$wikitext = getWikiPage($spaceURLname);

	//check on current status, should be active. If not give ERROR
	$currentState = substr($wikitext, strpos($wikitext, '|status=')+8,6);
	if ($currentState != 'active') {
		message('ERROR: Wiki for '.$spaceURLname.' is '.$currentState.'. No changes made ',5);
		return;
	}

	if ($action == 'inactive') {
		$newpage = str_replace('|status=active','|status=suspected inactive',$wikitext);
	} elseif ($action == 'update') {
		$newpage = $wikitext;
	} else {
		message('No hs wiki action defined!!',5);
	}

	$newpage .= "\n<!--- Checked by mappall.space bot on ".	date("Y-m-d H:i:s").".\n".$wikiMessage."\n-->";

	$params = [
		"action" => "edit",
		"title" => $spaceURLname,
		"text" => $newpage,
		"token" => $csrf_Token,
		"summary" => "Update to $action by wikibot",
		"bot" => true,
		"format" => "json"
	];

	$url = $wikiApi ;// . "?" . http_build_query( $params );
	$result = getJSON($url,$params);

	// if (isset($result['json']['error']) or $result['json']['edit']['result']=='Failure') {
	// 	var_dump( $result['json']['error']);
	// }

	//solve captcha 
	if ($result['json']['edit']['result']=='Failure' and isset($result['json']['edit']['captcha']) ) {

		echo 'Solve wiki captcha';

		$captchparams = [
			"captchaid" => $result["json"]['edit']['captcha']['id'],
			"captchaword" => getCaptchaAnswer($result["json"]['edit']['captcha']['question']),
		];
		$params = array_merge($params,$captchparams);

		$url = $wikiApi ;// . "?" . http_build_query( $params );

		$result = getJSON($url,$params);

		// if (isset($result['json']['error']) or $result['json']['edit']['result']=='Failure') {
		// 	var_dump( $result);
		// };
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
		case "What is the name of our IRC channel on libera? Hint: Read the Communication page":
			return "#hackerspaces";
			break;
		case "This website is for whom? Hint: Read the frontpage":
			return "Anyone and Everyone";
			break;
		case "Hacker______?":
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
    global $wikiMessage;

    if ($lineloglevel >= $loglevel) {
        echo $message.PHP_EOL;
    };

    $wikiMessage .= $message.PHP_EOL;

    if ($lineloglevel >= $loglevelfile) {
        //
        if(!file_exists ( $log_path.'wikilog.txt' )) {
            $message = "Map all spaces error log, see also FAQ\nError 0-99 Curl\nError 100-999 http\nError 1000 no valid json\nError 1001 dupe\nError 2000 > ssl error\n\n".$message;
        }

        $fp = fopen($log_path.'wikilog.txt', 'a');
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

//no longer used since Must took over
// function getDateLastTweet($user) {
// 	global $twitter;

// 	$tuser = @end(explode('/', $user));

// 	$url = 'https://api.twitter.com/1.1/statuses/user_timeline.json';
// 	$getfield = "?screen_name=$tuser&count=1";
// 	$requestMethod = 'GET';

// 	$result = json_decode($twitter->setGetfield($getfield)
// 	             ->buildOauth($url, $requestMethod)
// 	             ->performRequest(),JSON_OBJECT_AS_ARRAY);

// 	if(isset($result[0]['created_at']) or $tuser='') {
// 		return date("Y-m-d H:i",strtotime($result[0]['created_at']));
// 	} else {
// 		return null; //timeline empty
// 	}

// };

function getDateLastTweetNitter($user) {
	global $nitterhosts,$nitterworks;

	if ($nitterworks =='') {
		foreach($nitterhosts as $host) {
			$result = getCurl($host.'tkkrlab/rss');
			if ($result['error']==0) {
				$nitterworks = $host;
				echo 'Nitter up : '.$host;
				break;
			}
		}
	}

	$tuser = substr(parse_url($user, PHP_URL_PATH),1);
	$lastdate = 0;
	$result = getCurl("$nitterworks$tuser/rss");
	if ($result['error']==0) {
		$xml = simplexml_load_string($result['result'],'SimpleXMLElement',LIBXML_NOCDATA|LIBXML_NOERROR);
		$json = json_encode($xml);
		$array = json_decode($json,TRUE);
		foreach($array['channel']['item'] as $rssItem) {
			if (isset($rssItem['pubDate'])) {
				$rssDate = strtotime($rssItem['pubDate']);
				if (empty($lastdate) or $rssDate > $lastdate) {
					$lastdate = $rssDate;
				}
			} else {
				echo "Error in feed $nitterworks$tuser\n";
			}
		}
		return date("Y-m-d H:i:s",$lastdate);
	} else {
		return null;
	}
};

function getDateLastFediverse($fediverse) {

	$result = getCurl("$fediverse.rss");
	if ($result['error']==0) {
		$xml = simplexml_load_string($result['result']);
		$json = json_encode($xml);
		$array = json_decode($json,TRUE);
		foreach($array['channel']['item'] as $rssItem) {
			$rssDate = strtotime($rssItem['pubDate']);
			if (empty($lastdate) or $rssDate > $lastdate) {
				$lastdate = $rssDate;
			}
		}
		echo "Datum voor $fediverse ".date("Y-m-d H:i:s",$lastdate)."\n";
		return date("Y-m-d H:i:s",$lastdate);
	} else {
		return null;
	}
};





function getDateLastWikiEdit($wiki) {
	message('Wiki on '.$wiki);
	$result = getCurl($wiki);
	if ($result['error'] == 0 ) {
		$wikiDomain = parse_url($wiki, PHP_URL_SCHEME).'://'.parse_url($wiki, PHP_URL_HOST);
		return getDateSiteAlternativeLink($result['result'],$wikiDomain);
	} else {
		message('Wiki not found, error '.$result['error']);
		return null;
	} 
}

function getDateNewsFeed($feed) {
	$result = getCurl($feed);

	if (!empty($result['result']) && $result['error']==0) {

		if (  (substr($result['result'],0,4)=='<rss') or (substr($result['result'],0,5)=='<?xml') or (substr($result['result'],0,5)=='<feed')  ) {
			$xml = simplexml_load_string($result['result'],'SimpleXMLElement',LIBXML_NOERROR);
	
			if (!empty($xml->channel->lastBuildDate)) {
				return date("Y-m-d H:i",strtotime($xml->channel->lastBuildDate[0]));
			} elseif (!empty($xml->channel->pubDate)) {
				return date("Y-m-d H:i",strtotime($xml->channel->pubDate[0]));
			} elseif (!empty($xml->entry->published)) {
				return date("Y-m-d H:i",strtotime($xml->entry->published[0]));	
			} else {
				return null;	
			}
		}
	} else {
		message('Newsfeed no RSS feed '.$feed.' Error :'.$result['error']);
		return null;
	}

}

function getDateLastMailManPost($mailinglist){
	//if mailman
	if (strpos($mailinglist,'/mailman/listinfo/')>0) {
		$pos =strpos($mailinglist,'/mailman/listinfo/');
		$mailman = substr($mailinglist,0,$pos).'/pipermail/'.substr($mailinglist,$pos+18);
		$result = getCurl($mailman);
		if ($result['error']==0 && $result['result']) {
			$foundDate=0;
			preg_match_all('/<td>(.*?)<\/td>/i', $result['result'], $matches, PREG_SET_ORDER, 0);
			if (isset($matches[3][0])){
				$lastArchive = substr($matches[3][0],4,-6);
				$foundDate = strtotime($lastArchive);	
			}
			if ($foundDate !=0 and $result['error']==0) {
				return date("Y-m-d H:i",$foundDate);
			} else {
				return null;
			}
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
		if ($result['error']==0 && $result['result']) {
			if (substr($result['result'],0,4)=='<rss')  {
				$xml =simplexml_load_string($result['result'],'SimpleXMLElement',LIBXML_NOERROR);
				return date("Y-m-d H:i",strtotime($xml->channel->item->pubDate));
			} else {
				return null;
			}
		}
	} else {
		return null;
	}
}

function getDataLastCalenderFeed($ical) {
	$result = getCurl($ical);
	if (!empty($result['result']) && $result['error'] == 0) {
		$file = str_getcsv($result['result'],"\n");
		$foundEndDate =	$foundStampDate = $lastStampDate= $lastEndDate = 0;
		foreach ($file as $line) {
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
	
		if (isset($lastStampDate)) {
			return $lastStampDate;
		} elseif(isset($lastEndDate)) {
			return $lastEndDate;
		} else {
			return null;
		}
	
	}
}


function getDateSiteAlternativeLink($site,$url) {
	$checkDate = array();

	return null;

	if (empty($site)) {
		return null;
	}

	libxml_use_internal_errors(true);
	$DOMfile = new DomDocument();
	$DOMfile->loadHTML($site);
	$xml = simplexml_import_dom($DOMfile);
	$generator = '';

	//find generator
	foreach ($xml->head->meta as $value) {
		$array = (array)$value;
		if (isset($array['@attributes']['name']) && $array['@attributes']['name']=='generator') {
			$generator = $array['@attributes']['content'];
		}
	}
	//find alternative links
	foreach ($xml->head->link as $value) {
		$array = (array)$value;

		if (isset($array['@attributes']['type']) && ($array['@attributes']['rel']=='alternate'  && (
				$array['@attributes']['type'] == 'application/rss+xml' or 
				$array['@attributes']['type'] == 'application/atom+xml') or
				$array['@attributes']['type'] == 'application/rsd+xml'
			)) {
			$link = $array['@attributes']['href'];
			if (substr($link,0,1)=='/') {
				$link = $url.$link;
			}
	
			if (substr($generator,0,9) == 'MediaWiki+++') {
				message('Proces MediaWiki');

				$wikiFeed = parse_url($link, PHP_URL_SCHEME).'://'.parse_url($link, PHP_URL_HOST).'/'.parse_url($link, PHP_URL_PATH);
				$wikiFeed .= '?days=9999&limit=1&action=feedrecentchanges&feedformat=atom';
				$result = getCurl($wikiFeed);
				if ($result['error']==0) {
					$feedRecentChanges = simplexml_load_string($result['result']);
					$foundDate = $feedRecentChanges->entry->updated;
					if (!empty($foundDate)) {					
						return date("Y-m-d H:i",strtotime($foundDate));	
					};
				} else {
					message('Error on '.$wikiFeed.' Error : '.$result['error']);
				}
			} elseif ($generator == 'DokuWiki') {
				message('Proces DokuWiki +++');
				$result = getCurl($link);
				if ($result['error']==0) {
					try {
						$xmlfeed = 	simplexml_load_string($result['result']);
						if (!($xmlfeed === false)) {
							$dc = $xmlfeed->item->children('http://purl.org/dc/elements/1.1/');
							$foundDate = $dc->date;
						} else {
							message('Error in XML DokuWiki ' . $link);
						}
					} catch (exception $e) {
						message('Error in XML DokuWiki ' . $link);
					}

					if (!empty($foundDate)) {
						return date("Y-m-d H:i",strtotime($dc->date));				
					} else {
						message('No date found for '.$link);
					}
				}
			} elseif (substr($generator,0,9) == 'WordPress') {
				message('Proces Wordpress');
				if (parse_url($link, PHP_URL_PATH) != '/comments/feed/' ) {
					$foundDate = getDateNewsFeed($link);
					if (!empty($foundDate)) {
						$checkDate[$link]=date("Y-m-d H:i",strtotime($foundDate));	
					} 
				}
			} else {
				//channel lastBuildDate
				$foundDate = getDateNewsFeed($link);
				if (!empty($foundDate)) {
					$checkDate[$link]=date("Y-m-d H:i",strtotime($foundDate));	
				} 
			}
		}
	}

	//do all the check
	$lastUpdateDate = 0;
	foreach ($checkDate as $datesource => $date) {
		if ($date > $lastUpdateDate) {
			$lastUpdateDate = $date;
			message('RSS Feed : '.$generator.' date : '.$date.'  source : '.$datesource);
		};
	};

	return $lastUpdateDate;

};


function sendEmail($email,$fullname,$url) {
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			message("ERROR Sendmail : Email $email not valid",5);
			return false;
		}

		$wikiMessage = "Send email to : ". $email;

        $headers = "From:Dave Borghuis <webmaster@mapall.space>\r\nMIME-Version: 1.0\r\nContent-type: text/html; charset=iso-8859-1";
        $mailmessage = "Hello,<br>Wiki entry for <a href=\"$url\">$fullname</a> has been changed. We asume that your hacerspace is no longer active. If this is not the case go to the wiki and change the status and add additional information if possible.<br>More information about this proces can be found on <a href=\"https:\\\\mapall.space\\hswikilist.php\">Mapall site</a><br>Do not reply to this mail, it wil not be read.\nLog of our checks : \n".$wikiMessage;
        $mailsend = mail( 
            $email,
            'Hackerspaces.org entry for '.$fullname ,
            $mailmessage,
            $headers
        );

        if (!$mailsend) {
            message('Error : Email not send!! '.$email,5);
            return false;
        }
};

function sendErrorLonLatEmail($email, $fullname, $url, $location)
{
	// if ($testrun) {
	// 	$email = 'dave@daveborghuis.nl';
	// }
	if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		message("ERROR Sendmail : Email $email not valid", 5);
		return false;
	}

	// $wikiMessage = "Send email to : " . $email;

	$headers = "From:Dave Borghuis <webmaster@mapall.space>\r\nMIME-Version: 1.0\r\nContent-type: text/html; charset=iso-8859-1";
	$mailmessage = "Hello,<br>Wiki entry for <a href=\"$url\">$fullname</a> has incorrect location data. Probaly the longatude and latatude may be swaped. Please check this data and change it on your wiki page accordingly.";
	$mailsend = mail(
		$email,
		'Location data on Hackerspaces.org entry for ' . $fullname,
		$mailmessage,
		$headers
	);

	if (!$mailsend) {
		message('Error : Email not send!! ' . $email, 5);
		return false;
	}
};


?>