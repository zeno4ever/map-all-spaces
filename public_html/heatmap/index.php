<!DOCTYPE html>
<? require('colors.inc.php'); ?>
<? include '../../private/init.php'; ?>
<html>

<HEAD>
	<LINK HREF="sakura.css" REL="stylesheet" TYPE="text/css">
	<LINK HREF="index.css" REL="stylesheet" TYPE="text/css">
	<link rel="stylesheet" type="text/css" href="/css/style.css">
</HEAD>

<body>
	<div id="header">
		<? include $PRIVATE . '/layout/navigate.php' ?>
	</div>
	<div style="margin-top: 70px;">
		<h1>SpaceAPI heatmap</h1>
		<div class="container">
			<p>This website retrieves the open/closed status of all hackerspaces registered at <a href="https://spaceapi.io/">spaceapi.io</a>.<br>
				This data is then displayed in a heatmap.<br>
				Each space is polled 6 times per hour (every 10 minutes).</p>
			<p>The heatmaps was original created by <a href="https://www.vanheusden.com/">www.vanheusden.com</a> and moved in 20 Sept. 2021 to mapall.space.
				</p>

			<p>Data quality: <?
								//$mysqli = new mysqli('localhost', 'spaceapi', 'spaceapi', 'spaceapi');

								$sql = 'select sum(get_ok) * 100 / sum(get_total) as q, sum(lns) * 100 / count(*) as openp from spaces';
								if (!$result = $mysqli->query($sql)) {
									echo "Sorry, the website is experiencing problems.<br>";
									echo "Errno: " . $mysqli->errno . "<br>\n";
									echo "Error: " . $mysqli->error . "<br>\n";
									exit;
								}

								$row = $result->fetch_assoc();
								print sprintf('%.2f%%', $row['q']);
								?> (100% is all space-api calls succeeded)</p>
			<p>Percentage of the spaces that are open: <? print sprintf('%.2f%%', $row['openp']); ?></p>
			<p>Click on a hackerspace name to open the heatmap-view.</p>

			<p><? $sql = "SELECT distinct lower(substr(name, 1, 1)) as l FROM spaces ORDER BY name";
				$result = $mysqli->query($sql);
				$col = 1;
				while ($letters = $result->fetch_assoc()) {
				?><a href="#<? print $letters['l']; ?>" <?
														if ($col) {
														?>style="background-color: #606060;" <?
																							} else {
																								?>style="background-color: #808080;" <?
																																	}
																																	$col = 1 - $col;
																																		?>><? print $letters['l']; ?></a><?
																																										}
																																											?></p>

			<?php
			$sql = "SELECT `key`, name, logo, lns FROM spaces ORDER BY name";
			if (!$result = $mysqli->query($sql)) {
				echo "Sorry, the website is experiencing problems.<br>";
				echo "Errno: " . $mysqli->errno . "<br>\n";
				echo "Error: " . $mysqli->error . "<br>\n";
				exit;
			}

			$p = 'a';
			print '<p>';

			while ($space = $result->fetch_assoc()) {
				$name = $space['name'];
				$first = strtolower(substr($name, 0, 1));
				if ($first != $p) {
					$p = $first;
					print "\n</p><br><a name='$first'></a><p class=\"letters\"><span class=\"letter\">$first </span><br>";
				}

				$lns = $space['lns'];

				if ($lns) {
			?><a href="show.php?id=<?php print urlencode($space['name']); ?>" style="background-color: #60ff60;"><? print $name; ?></a> <?
																																	} else {
																																		?><a href="show.php?id=<?php print urlencode($space['name']); ?>" style="background-color: #ff6060;"><? print $name; ?></a> <?
																																																																}
																																																															}
																																																																	?>
			</p>
		</div>
		<br>
		<p>See <a href="open.php">this</a> page to see a global open/closed percentage for all spaces.</p>
		<br>
		<br>

		<p>(C) 2014-2020 by <a href="https://www.vanheusden.com/">www.vanheusden.com</a></p>
</body>

</html>