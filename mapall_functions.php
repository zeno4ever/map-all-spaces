<?php


function getJSON($url,$fields=null,$timeout=240) {
	$result = getCurl($url,$fields,$timeout);

    if ( $result['error'] == 0 ) {
        $json = json_decode($result['result'], true);
        if ($json != null ){
            return array('json'=>$json,'error'=>0 );
        } else {
            return array('json'=>null,'error'=>1000 );
		};
    } else {
        return array('json'=>null,'error'=>$result['error']);
    };

}

function getCurl($url,$postFields=null,$timeout=240) {
    //global $messages;
	//global $httpHeaders;

	$httpHeaderLastModified=null;
	$httpHeaders =[];

    $curlSession = curl_init();
    curl_setopt($curlSession, CURLOPT_BINARYTRANSFER, true);
    curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curlSession, CURLOPT_USERAGENT, "http://mapall.space");

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

	curl_setopt($curlSession, CURLOPT_COOKIEJAR, "cookie.txt");
	curl_setopt($curlSession, CURLOPT_COOKIEFILE, "cookie.txt");

    //curl_setopt( $curlSession, CURLOPT_HEADERFUNCTION, "CurlHeader");

    $result = curl_exec($curlSession);
    $curl_error = curl_errno($curlSession);
    $curl_info = curl_getinfo($curlSession,CURLINFO_HTTP_CODE);
    $curl_ssl  = curl_getinfo($curlSession, CURLINFO_SSL_VERIFYRESULT);

    if ($curl_ssl!=0) {
		message('SSL verify error '.$curl_ssl,5);
    }

    curl_close($curlSession);

    // foreach ($httpHeaders as $line) {
    // 	if (substr($line,0,13)=='Last-Modified') {
    // 		$httpHeaderLastModified = date("Y-m-d H:i",strtotime(trim(substr($line,14),"\x0A..\x0D")));
    // 	}
    // }

    if ( $curl_error == 0 && $curl_info == 200 && $curl_ssl==0) {
    	return array('result'=>$result,'error'=>0,'lastmodified'=>$httpHeaderLastModified);
    } else {
        //$curl_ssl
        if ($curl_error!=0) {
            $error = $curl_error;
        } elseif($curl_error!=0) {
            $error = $curl_error;
        } else {
            $error = $curl_ssl+2000;
        }
        //$error = ($curl_error!=0) ? $curl_error : $curl_info;  
        return array('result'=>null,'error'=>$error,'lastmodified'=>null);
    };
};

// function CurlHeader( $curl, $header_line ) {
// 	global $httpHeaders;
// 	$httpHeaders[] = $header_line;
//     return strlen($header_line);
// }

?>