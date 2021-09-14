<?php

//require_once('../init.php');

function getJSON($url,$fields=null,$timeout=240) {
	$result = getCurl($url,$fields,$timeout);

    if ( $result['error'] == 0 ) {
        $json = json_decode($result['result'], true);
        if ($json != null ){
            return array('json'=>$json,'error'=>0, 'cors' => $result['cors'] );
        } else {
            return array('json'=>null,'error'=>1000, 'cors' => $result['cors'] );
		};
    } else {
        return array('json'=>null,'error'=>$result['error'], 'cors'=>$result['cors']);
    };

}

function getCurl($url,$postFields=null,$timeout=240) {
    global $PRIVATE;
    global $httpHeaders;

	$httpHeaderLastModified=null;
    $httpHeaderCORSEnabled=false;

	$httpHeaders =[];

    $curlSession = curl_init();

    //curl_setopt($curlSession, CURLOPT_BINARYTRANSFER, true);
    curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curlSession, CURLOPT_USERAGENT, "http://mapall.space");

    //curl_setopt($curlSession, CURLOPT_HTTPHEADER, true);
    //curl_setopt($curlSession, CURLOPT_, false);


    //curl_setopt($curlSession, CURLOPT_NOBODY,false);
    
    //for redirect
    curl_setopt($curlSession, CURLOPT_FOLLOWLOCATION, true);
    
    //timeout in secs
    curl_setopt($curlSession, CURLOPT_TIMEOUT,$timeout); 

    //get file
    curl_setopt($curlSession, CURLOPT_URL, $url);

    //set post options if needed
    if (is_array($postFields)) {
    	curl_setopt( $curlSession, CURLOPT_POST, true );
    	curl_setopt( $curlSession, CURLOPT_POSTFIELDS, http_build_query( $postFields ) );
    };

    curl_setopt($curlSession, CURLOPT_COOKIEJAR, $PRIVATE."cookie.txt");
	curl_setopt($curlSession, CURLOPT_COOKIEFILE, $PRIVATE."cookie.txt");

    curl_setopt($curlSession, CURLOPT_HEADERFUNCTION, "CurlHeader");


    $result = curl_exec($curlSession);
    $curl_error = curl_errno($curlSession);
    $curl_info = curl_getinfo($curlSession,CURLINFO_HTTP_CODE);
    $curl_ssl  = curl_getinfo($curlSession, CURLINFO_SSL_VERIFYRESULT);

    if ($curl_ssl!=0) {
		message('SSL verify error '.$curl_ssl,5);
    }

    curl_close($curlSession);

    foreach ($httpHeaders as $line) {
    	if (substr($line,0,13)=='Last-Modified') {
    		$httpHeaderLastModified = date("Y-m-d H:i",strtotime(trim(substr($line,14),"\x0A..\x0D")));
    	};
        if (strtolower(substr($line,0,30)) == 'access-control-allow-origin: *') {
            $httpHeaderCORSEnabled = true;
        };
    }

    if ( $curl_error == 0 && $curl_info == 200 && $curl_ssl==0) {
    	return array('result'=>$result,'error'=>0,'lastmodified'=>$httpHeaderLastModified,'cors'=> $httpHeaderCORSEnabled);
    } else {
        //$curl_ssl
        if ($curl_error!=0) {
            $error = $curl_error;
        } elseif($curl_info!=0) {
            $error = $curl_info;
        } else {
            $error = $curl_ssl+2000;
        }
        // echo '** SSL Error :' . $curl_ssl . PHP_EOL;
        // echo '** CURL  Error :' . $curl_error . PHP_EOL;
        // echo '** HTTP Error :' . $curl_info . PHP_EOL;
        

        //$error = ($curl_error!=0) ? $curl_error : $curl_info;  
        return array('result'=>null,'error'=>$error,'lastmodified'=>null, 'cors' => $httpHeaderCORSEnabled);
    };
};

function CurlHeader( $curl, $header_line ) {
	global $httpHeaders;
	$httpHeaders[] = $header_line;
    //echo "HeaderLine :" . $header_line . PHP_EOL;
    return strlen($header_line);
};

?>