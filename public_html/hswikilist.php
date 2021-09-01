<?php


require '../private/init.php'; //get secret settings
require $PRIVATE . '/wiki.php';
//require $PUBLIC . '../vendor/autoload.php';

if ($_COOKIE['wikipw'] == substr(sha1($wikiPasswd), 0, 20)) {
	$validUser = true;
} else {
	$validUser = false;
};

?>
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
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
	<script type="text/javascript">
		function wikiupdate(hackerspace, action) {
			$.post("wikiupdate.php", {
					hackerspace: hackerspace,
					status: "checked",
					action: action
				})
				.done(function() {
					//$(this).css("background","red");
				})
				.fail(function() {
					alert("error");
				});
			$(event.target || event.srcElement).parents('tr').hide();
		};
	</script>
</head>

<body>
	<main id="content">
		<div id="header">
			<nav class="menu">
				<ul>
					<li><a href="/">Home</a></li>
					<li><a href="faq.php">FAQ</a></li>
					<li><a href="hswikilist.php" class="active">Hackerspace Census</a></li>
					<li><a href="onespace.html">Status your space</a></li>
					<li><a href="about.html">About</a></li>
					<li style="float:right"><a href="https://github.com/zeno4ever/map-all-spaces" target=_blank><img src="/image/github-white.png" alt="Join us on Github"></a></li>
				</ul>
			</nav>
			<div style="float:right"><a href="https://github.com/zeno4ever/map-all-spaces" style=""><img src="/image/github-white.png" alt="Join us on Github"></a></div>
		</div>
		<div class="pwform">
			<?php
			if (!$validUser) {
				echo '	
			<form action="login.php" method="get">
				<input type="password" id="pwd" name="pwd" placeholder="Password">
				<input type="submit" style="position: absolute; left: -9999px">
			</form>
			';
			}
			?>
		</div>

		<div class="content">
			The <a href="https://wiki.hackerspaces.org/List_of_Hacker_Spaces" target:_blank>wiki</a> is a very nice tool so everyone can add their own space. The challenge is that to ensure that the entry remain up to date in order for the list to remain accurate and relevant. To solve this some people from hackerspace.org started the <a href="https://wiki.hackerspaces.org/Hackerspace_Census_2019">Hackerspace Census</a> to check all entry's and set them to 'closed' when hackerspace doesn't exist any more.
			<p>
				I created a script that tries to automate this. For every entry it wil:
			<ul>
				<li>Check if site is still up. After 3 failed tests the entry wil be set to 'suspected inactive' and a email will be send.</li>
				<li>Check if there is recent activity on one of the following places : Site (via rss feeds), Twitter, Mailinglist, Wiki, SpaceAPI, Newsfeed and Calenderfeed. If this is the case the entry on wiki is updated with remark that this is checked. If the activity is to long ago (> 2 years) entry will be set to status 'suspected inactive' and email will be send.</li>
				<li>Left over are the entrys that have to be checked manual.</li>
			</ul>
			Below you will find a list of all of the hackerspace entities that have to be manually checked. If you want to help - pick an entry and figure out whether the hackerspace is still open or not (social media, website, etc). If it still active, add (hidden) text to the wiki like: '&lt!-- set to $status for $reason, Checked by person $yourname on $date --&gt' or set the status to 'closed' or 'suspected inactive'. To do this you'll need to create a Hackerspaces.org wiki log in and then you can edit any pages.
			<?php

			//$result = $database->select("wikispace", '*' ,["status" => 'manual']);
			$result = $database->query("SELECT DISTINCT name,wikiurl FROM wikispace WHERE status = 'manual' ORDER BY wikiurl;")->fetchAll();

			shuffle($result);

			echo '<h4>Right now there are ' . count($result) . ' hackerspaces to be check manualy.</h4>';
			if ($result) {
				echo '<table class="wiki"><tr><th colspan="3">Hackerspace Name</th></tr>';
				foreach ($result as $space) {
					echo '<tr>';
					echo '<td><a href="' . $space['wikiurl'] . '" target="_blank">' . $space['name'] . '</a></td>';

					//if loged in 
					if ($validUser) {
						echo '<td><button type="button" onclick="wikiupdate(`' . $space['name'] . '`,`close`);">Close</button></td>';
						echo '<td><button type="button" onclick="wikiupdate(`' . $space['name'] . '`,`update`);">Update</button></td>';
						echo '<td><button type="button" onclick="wikiupdate(`' . $space['name'] . '`);">Checked</button></td>';
						echo '</tr>';
					} else {
						echo '<td><button type="button" onclick="wikiupdate(`' . $space['name'] . '`);">Checked</button></td>';
					}
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