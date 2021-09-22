<?php 

echo '
<!DOCTYPE html>
<html lang="en-US">
<head>
	<link rel="stylesheet" href="/css/style.css" />
	<meta name="Map hackerspaces/fablabs/makerspaces " content="Dynamic map with all hackerspace, fablabs and makerspaces">
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
	<!-- Global site tag (gtag.js) - Google Analytics -->
	<script async src="https://www.googletagmanager.com/gtag/js?id=G-2M9QVB70G3"></script>
	<script>
		window.dataLayer = window.dataLayer || [];

		function gtag() {
			dataLayer.push(arguments);
		}
		gtag('js', new Date());

		gtag('config', 'G-2M9QVB70G3');
	</script>
	<title>'
    echo $title;
    '</title>
</head>
';