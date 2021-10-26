<!DOCTYPE html>
<? include '../../private/init.php'; ?>
<HTML>

<HEAD>
	<link rel="stylesheet" type="text/css" href="/css/style.css">
</HEAD>

<BODY>
	<div id="header">
		<? include $PRIVATE . '/layout/navigate.php' ?>
	</div>

	<?php
	//$mysqli = new mysqli('localhost', 'spaceapi', 'spaceapi', 'spaceapi');

	$id = md5($_GET['id']);
	$table = 'data_' . $id;

	$sql = "SELECT sa, url, name, sum(get_ok) * 100 / sum(get_total) as q, timezone, timezone_long FROM spaces WHERE `key`='$id'";
	$result = $mysqli->query($sql);
	$row = $result->fetch_assoc();
	$j = json_decode($row['sa'], TRUE);

	//Timezone calculations
	$their_tz = $row['timezone'];

	if ($their_tz == null or $their_tz == '')
		$their_tz = 'Europe/Amsterdam';

	$time = new \DateTime('now', new DateTimeZone($their_tz));
	$timezoneOffset = $time->format('P');

	$tz_j = json_decode($row['timezone_long'], TRUE);
	$country = $tz_j['countryName'];

	?><h1><? if ($j['space'] == '') print $row['name'];
			else print $j['space']; ?></h1><?

											?>
	<!-- <? print $row['url']; ?> --><?

										?><p>
	<ul>
		<li><a href="<? print $j['url']; ?>"><? print $j['url']; ?></a><br>
		<li><? print $j['location']['address']; ?> (<? print $country; ?>)<br>
		<li><a href="/index.php?menu=home&lat=<? print $j['location']['lat']; ?>&lon=<? print $j['location']['lon']; ?>" target="_blank">latitude: <? print $j['location']['lat']; ?> / longitude: <? print $j['location']['lon']; ?></a><br>
		<li>timezone: <? print $their_tz; ?>
		<?php
		//debugging timezone
		if ($_GET['debug']) {
			echo '<div id="debug"><p>';
			echo '<p>Timezone offset=' . $timezoneOffset.'<p>';

			$sql = 'SELECT DATE(ts) as datum, TIME(ts) as tijd, CONVERT_TZ(ts, "SYSTEM", "' . $timezoneOffset . '") AS tztijd, open FROM ' . $table . ' WHERE ts >=  DATE_SUB(NOW(),INTERVAL 4 WEEK) ORDER BY ts';
			$result = $mysqli->query($sql);

			while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
				if ($lastrow['open'] !== $row['open']) {
					echo $row['datum'] . ' ' . $row['tijd'] . ' TZ -' . $row['tztijd'] . ' ' . $row['open'] . '<p>';
				};
				$lastrow = $row;
			}
			$result->free_result();
			echo '</div>';
		}
		?> 
	</ul>
	<?
	if ($j['state']['open'] == 'true' || $j['open'] == 'true') {
		$s = "open";
		$icon = $j['state']['icon']['open'];
	} else {
		$s = "closed";
		$icon = $j['state']['icon']['closed'];
	}

	if ($icon != '') {
	?><img border=1 src="<? print $icon; ?>" width=64> &nbsp; <?
															} else {
																$logo = $j['logo'];
																?><img border=1 src="<? print $logo; ?>" width=64> &nbsp; <?
																														}

																														print "state: $s";

																														$lastchange = 0;
																														if ($j['state']['lastchange'] != '')
																															$lastchange = $j['state']['lastchange'];
																														else if ($j['lastchange'] != '')
																															$lastchange = $j['lastchange'];

																														if ($lastchange != 0) {
																															?>
		<p>Last state change: <? print date('F d Y H:i:s', $lastchange); ?></p><?
																														} ?>

	<p>Data quality: <? print sprintf('%.2f%%', $row['q']); ?> (100% is all space-api calls succeeded)</p>

	<?
	flush();

	$sql = "SELECT sum(open) * 100 / count(*) as openp FROM $table";
	$result = $mysqli->query($sql);
	$row = $result->fetch_assoc();
	?>
	<p>Percentage open: <? print sprintf('%.2f%%', $row['openp']); ?></p>

	<? require('colors.inc.php'); ?>

	<H2>last week</H2><?
						doit('SELECT DAYNAME(ts) AS dayofweek, HOUR(ts) AS hour, avg(factor) as open FROM (select CONVERT_TZ(ts, "SYSTEM", "' . $timezoneOffset . '") AS ts, sum(open) / count(*) as factor from ' . $table . ' WHERE ts >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK) group by date(ts), hour(ts)) as der GROUP BY dayofweek, hour ORDER BY WEEKDAY(ts)');

						?><A NAME="maand"></A>
	<H2>last month</H2><?
						doit('SELECT DAYNAME(ts) AS dayofweek, HOUR(ts) AS hour, avg(factor) as open FROM (select CONVERT_TZ(ts, "SYSTEM", "' . $timezoneOffset . '") AS 	ts, sum(open) / count(*) as factor from ' . $table . ' WHERE ts >= DATE_SUB(CURDATE(), INTERVAL 31 DAY) group by date(ts), hour(ts)) as der GROUP BY dayofweek, hour ORDER BY WEEKDAY(ts)');

						?><A NAME="jaar"></A>
	<H2>this year</H2><?
						doit('SELECT DAYNAME(ts) AS dayofweek, HOUR(ts) AS hour, avg(factor) as open FROM (select CONVERT_TZ(ts, "SYSTEM", "' . $timezoneOffset . '") AS ts, sum(open) / count(*) as factor from ' . $table . ' WHERE ts >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR) group by date(ts), hour(ts)) as der GROUP BY dayofweek, hour ORDER BY WEEKDAY(ts)');

						?><A NAME="alles"></A>
	<H2>everything</H2><?
						doit('SELECT DAYNAME(ts) AS dayofweek, HOUR(ts) AS hour, avg(factor) as open FROM (select CONVERT_TZ(ts, "SYSTEM", "' . $timezoneOffset . '") AS ts, sum(open) / count(*) as factor from ' . $table . ' group by date(ts), hour(ts)) as der GROUP BY dayofweek, hour ORDER BY WEEKDAY(ts)');

						function doit($query)
						{
							#print "$query<br>\n";
							global $mysqli;

							for ($i = 0; $i < 24; $i++)
								$avgs[$i] = $avgsn[$i] = 0;

							$results = $mysqli->query($query);

							while ($row = $results->fetch_assoc()) {
								$h = $row['hour'];

								$counts[$row['dayofweek']][$h] = $row['open'];

								$avgs[$h] += $row['open'];
								$avgsn[$h]++;
							}

							for ($i = 0; $i < 24; $i++) {
								$h = $i;
								if ($avgsn[$i] != 0) {
									$counts['avg'][$h] = $avgs[$i] / $avgsn[$i];
								} else {
									$counts['avg'][$h] = 0;
								}
							}

							foreach ($counts as $d => $v) {
								$t = 0;
								for ($i = 0; $i < 24; $i++)
									$t += $counts[$d][$i];
								$counts[$d][24] = $t / 24;
							}

						?><TABLE class="heatmap"><?
													print "\n";
													?><TR>
				<TH></TH><?
							for ($h = 0; $h < 24; $h++) {
							?><TH><? printf('%02d', $h); ?></TH><?
															}
																?><TH>avg</TH><?

																				$rood = rgbToHsl(255, 40, 40);
																				$groen = rgbToHsl(40, 255, 40);

																				foreach ($counts as $d => $v) {
																				?>
			</TR>
			<TH><? print $d; ?></TH><?

																					for ($h = 0; $h < 25; $h++) {
																						$c = $counts[$d][$h];

																						$H = ($groen[0] - $rood[0]) * $c + $rood[0];
																						$L = ($groen[1] - $rood[1]) * $c + $rood[1];
																						$S = ($groen[2] - $rood[2]) * $c + $rood[2];

																						$rgb = hslToRgb($H, $L, $S);

																						$r = $rgb[0];
																						$g = $rgb[1];
																						$b = $rgb[2];

									?><TD WIDTH=30 BGCOLOR="#<? printf('%02x%02x%02x', $r, $g, $b); ?>"><? printf('%.0f', $c * 100.0); ?></TD><?
																																			}

																																				?></TR><?
																																					}
																																						?>
		</TABLE><?

							flush();
						}
				?>

	<br>
	<br>
	<p><a href="json.php?id=<? print urlencode($_GET['id']); ?>"><img src="opendata-logo.jpg" width=128></a></p>
	<br>
	<br>

	<p>(C) 2014-2020 by <a href="https://www.vanheusden.com/">www.vanheusden.com</a> &amp; 2021-<?php echo date('Y') ?> by <a href="https://daveborghuis.nl">Dave Borghuis</a></p>

</body>

</html>