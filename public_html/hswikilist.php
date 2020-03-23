<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>Map all spaces - Hackerspace Censes</title>
    <link rel="stylesheet" type="text/css" href="/css/style.css">
	<meta name="Map hackerspaces/fablabs/makerspaces " content="Dynamic map with all hackerspace, fablabs and makerspaces">

    <!-- If IE use the latest rendering engine -->
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!-- Set the page to the width of the device and set the zoon level -->
    <meta name="viewport" content="width = device-width, initial-scale = 1">

	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>    
	<script type="text/javascript" >
		function wikiupdate(hackerspace) 
		{
			$.post("wikiupdate.php",
				{
				hackerspace: hackerspace,
				status: "checked"
				}	
				)  
				.done(function() {
					//$(this).css("background","red");
				})
				.fail(function() {
					alert( "error" );
				});
			  $(event.target || event.srcElement).parents('tr').hide();
		};
	</script>
	<style>
		table {
			border: 0p;
		}
		 th, td { 
            text-align: left; 
            padding: 8px; 
        } 

	  tr:nth-child(even) {background: #DDD}
	</style>
  </head>
  <body>
  	<main id="content">
	<div id="header">
	    <nav class="menu">
	        <ul >
	         	<li><a href="/">Home</a></li>
	         	<li><a href="faq.php">FAQ</a></li>
	         	<li><a href="hswikilist.php" class="active">Hackerspace Census</a></li>
	         	<li><a href="about.html">About</a></li>
            	<li style="float:right"><a href="https://github.com/zeno4ever/map-all-spaces" target=_blank><img src="/image/github-white.png" alt="Join us on Github"></a></li>
	        </ul>
	    </nav>
	    <div style="float:right"><a href="https://github.com/zeno4ever/map-all-spaces" style=""><img src="/image/github-white.png" alt="Join us on Github"></a></div>
	</div>

	<div class="content">
		The <a href="https://wiki.hackerspaces.org/List_of_Hacker_Spaces" target:_blank>wiki</a> is a very nice tool so everyone can add their own space. The challenge is that to ensure that the entry remain up to date in order for the list to remain accurate and relevant. To solve this some people from hackerspace.org started the <a href="https://wiki.hackerspaces.org/Hackerspace_Census_2019">Hackerspace Census</a> to check all entry's and set them to 'closed' when hackerspace doesn't exist any more.
	<p>
	I created a script that tries to automate this. For every entry it wil:
	<ul>
		<li>Check if site is still up. After 3 failed tests the entry wil be set to 'closed' and a email send.</li>
		<li>Check if there is recent activity on one of the following places : Main site, Twitter, Mailinglist, Wiki, SpaceAPI, Newsfeed, Calenderfeed. If this is the case the entry on wiki is updated with remark that this is checked. If the activity is to long ago (> 2 years) entry will be set to status 'closed'.</li>
		<li>Left over are the entrys that have to be checked manual.</li>
	</ul>
	Below you will find a list of all the hackerspace entries that have to be checked manual. If you want to help pick an entry, check what the status is of the hackerspace. If its still exist add (hidden) tekst to the wiki like '&lt!-- Checked by person $yourname --&gt' or set the status to 'closed'. 	<p>
<?php
	require "../settings.php"; //get secret settings

	//twitter feed
	require '../vendor/autoload.php';

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

	$result = $database->select("wikispace", '*' ,["status" => 'manual']);

	shuffle($result);

	echo '<h4>To check '.count($result).' hackerspaces.</h4>';
	if ($result) {
		echo '<table><tr><th>Hackerspace Name</th><th>Status</th><th></th></tr>';
		foreach ($result as $space) {
			echo '<tr>';
			echo '<td><a href="'.$space['wikiurl'].'" target="_blank">'.$space['name'].'</a></td>';
			echo '<td>'.$space['status'].'</td>';
			echo '<td><button type="button" onclick="wikiupdate(`'.$space['name'].'`);">Checked</button></td>';
			echo '</tr>';
			$line+=1;
		};
		echo '</table>';
	} else {
		echo '<h1>All done, excelent!</h1>';
	}
?>
</div>
</main>
</body>
</html>
