<!DOCTYPE html>
<?php 
	include '../../private/init.php'; 
?>
<HTML>
<HEAD>
	<link rel="stylesheet" type="text/css" href="/css/style.css">
</HEAD>
<BODY>
	<div id="header">
		<?php include $PRIVATE . '/layout/navigate.php' ?>
	</div>
	<?php
	$id = md5($_GET['id']);
	$table = 'data_' . $id;

	$sql = "SELECT sa, url, name, get_ok * 100 / get_total as q, timezone, timezone_long FROM heatmspaces WHERE `key`='$id'";

	$result = $db->rawQuery ($sql);
	if ($db->getLastErrno() !== 0) {
		echo "Error: ". $db->getLastError();
	}
	$row = $result[0];
	$j = json_decode($row['sa'], TRUE);	

	//Timezone calculations
	$their_tz = $row['timezone'];

	if ($their_tz == null or $their_tz == '') {
		$their_tz = 'Europe/Amsterdam';
	}

	$country ='';
	if (isset($row['timezone_long'])) {
		$tz_j = json_decode($row['timezone_long'], TRUE);
		$country = $tz_j['countryName'];	
	}; 

	$time = new \DateTime('now', new DateTimeZone($their_tz));
	$timezoneOffset = $time->format('P');

	?>
	<h1>
	<?php print $j['space']??$row['name']; ?>
	</h1><p>
	<ul>
		<?php	 if (isset($j)) { ?>
			<li><a href="<?php print $j['url']; ?>"><?php print $j['url']; ?></a><br>
			<li><?php print $j['location']['address']; ?> (<?php print $country; ?>)<br>
			<li><a href="/index.php?menu=home&lat=<?php print $j['location']['lat']; ?>&lon=<?php print $j['location']['lon']; ?>" target="_blank">latitude: <?php print $j['location']['lat']; ?> / longitude: <?php print $j['location']['lon']; ?></a><br>
		<?php }	?>
		<li>timezone: <?php print $their_tz; ?>
		<?php
		//debugging timezone
		if ( array_key_exists( 'debug', $_GET)) {
			echo '<div id="debug"><p>';
			echo '<p>Timezone offset=' . $timezoneOffset.'<p>';

			$sql = 'SELECT DATE(ts) as datum, TIME(ts) as tijd, CONVERT_TZ(ts, "SYSTEM", "' . $timezoneOffset . '") AS tztijd, open FROM ' . $table . ' WHERE ts >=  DATE_SUB(NOW(),INTERVAL 1 MONTH) ORDER BY ts';
			// $result = $mysqli->query($sql);


			$result = $db->rawQuery ( $sql);
			if ($db->getLastErrno() !== 0) {
				echo "Error  ". $db->getLastError().'  '.__LINE__;
			}

			$lastrow=null;
			foreach ($result as $row ) {
					if ($lastrow['open'] !== $row['open']) {
					echo $row['datum'] . ' ' . $row['tijd'] . ' TZ -' . $row['tztijd'] . ' ' . $row['open'] . '<p>';
				};
				$lastrow = $row;
			}
			echo 'Last entry : '. $lastrow['datum'] . ' ' . $lastrow['tijd'] . ' TZ -' . $lastrow['tztijd'] . ' ' . $lastrow['open'] . '<p>';
			echo '</div>';
		}
		?> 
	</ul>
	<?php
	$icon = $j['logo']??'';
	$s='';
	if (isset($j)) {
		if ((isset($j['state']['open']) && $j['state']['open'] == 'true') || (isset($j['open']) && $j['open'] == 'true')) {
			$s = "open";
			$icon = $j['state']['icon']['open']??'';
		} else {
			$s = "closed";
			$icon = $j['state']['icon']['closed']??'';
		}
	}
	?><img border=1 src="<?php print $icon; ?>" width=64> &nbsp; <?php
	print "state: ".$s;
	$lastchange = 0;
	if (isset($j)) {
		if (isset($j['state']['lastchange']))
			$lastchange = $j['state']['lastchange'];
		else if (isset($j['lastchange']))
			$lastchange = $j['lastchange'];
	}
	if ($lastchange != 0) {
		?>
		<p>Last state change: <?php print date('F d Y H:i:s', (int)$lastchange); ?></p><?php
	} ?>

	<p>Data quality: <?php print sprintf('%.2f%%', $row['q']); ?> (100% is all space-api calls succeeded)</p>

	<?php
	flush();

	$sql = "SELECT sum(open) * 100 / count(*) as openp FROM $table";
	// $result = $mysqli->query($sql);

	$result = $db->rawQuery ( $sql);
	if ($db->getLastErrno() !== 0) {
		echo "Error  ". $db->getLastError().'  '.__LINE__;
	}
	$row = $result[0];
	// $row = $result->fetch_assoc();
	?>
	<p>Percentage open: <?php print sprintf('%.2f%%', $row['openp']); ?></p>
	<?php require('colors.inc.php'); ?>
	<H2>last week (7 days) </H2><?php
		doit('SELECT DAYOFWEEK(tts) as dayofweek, HOUR(tts) as hour, SUM(open) / COUNT(*) AS open FROM (SELECT CONVERT_TZ(ts, "SYSTEM", "' . $timezoneOffset . '") AS tts, open FROM ' . $table . ') AS i WHERE tts >= DATE_SUB(CURRENT_TIMESTAMP(), INTERVAL 7 DAY) GROUP BY dayofweek, hour');
		?><A NAME="maand"></A>
	<H2>last month (31 days)</H2><?php
		doit('SELECT DAYOFWEEK(tts) as dayofweek, HOUR(tts) as hour, SUM(open) / COUNT(*) AS open FROM (SELECT CONVERT_TZ(ts, "SYSTEM", "' . $timezoneOffset . '") AS tts, open FROM ' . $table . ') AS i WHERE tts >= DATE_SUB(CURRENT_TIMESTAMP(), INTERVAL 31 DAY) GROUP BY dayofweek, hour');
		?><A NAME="jaar"></A>
	<H2>this year (356 days)</H2><?php
		doit('SELECT DAYOFWEEK(tts) as dayofweek, HOUR(tts) as hour, SUM(open) / COUNT(*) AS open FROM (SELECT CONVERT_TZ(ts, "SYSTEM", "' . $timezoneOffset . '") AS tts, open FROM ' . $table . ') AS i WHERE tts >= DATE_SUB(CURRENT_TIMESTAMP(), INTERVAL 365 DAY) GROUP BY dayofweek, hour');
		?><A NAME="alles"></A>
	<H2>everything</H2><?php
		doit('SELECT DAYOFWEEK(tts) as dayofweek, HOUR(tts) as hour, SUM(open) / COUNT(*) AS open FROM (SELECT CONVERT_TZ(ts, "SYSTEM", "' . $timezoneOffset . '") AS tts, open FROM ' . $table . ') AS i GROUP BY dayofweek, hour');
	
	function doit($query)
	{
		$db = MysqliDb::getInstance();		

		for ($i = 0; $i < 24; $i++)
			$avgs[$i] = $avgsn[$i] = 0;
		
		$results = $db->rawQuery ( $query);
        if ($db->getLastErrno() !== 0) {
            echo "Error: ". $db->getLastError().'  '.__LINE__;
			exit;
        };

		foreach ($results as $row ) {
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
			for ($i = 0; $i < 24; $i++) {
				if (!array_key_exists($i, $counts[$d])) {
					$counts[$d][$i] = 0;
				}
				$t += $counts[$d][$i];
			}
			$counts[$d][24] = $t / 24;
		}

	?><TABLE class="heatmap"><?php
		print "\n";
	?><TR><TH></TH><?php
		for ($h = 0; $h < 24; $h++) {
		?><TH><?php printf('%02d', $h); ?></TH><?php
			}
		?><TH>avg</TH><?php

			$rood = rgbToHsl(255, 40, 40);
			$groen = rgbToHsl(40, 255, 40);

			foreach ($counts as $d => $v) {
			?>
	</TR>
	<TH><?php print $d; ?></TH><?php
	for ($h = 0; $h < 25; $h++) {
		$c = $counts[$d][$h];

		$H = ($groen[0] - $rood[0]) * $c + $rood[0];
		$L = ($groen[1] - $rood[1]) * $c + $rood[1];
		$S = ($groen[2] - $rood[2]) * $c + $rood[2];

		$rgb = hslToRgb($H, $L, $S);

		$r = $rgb[0];
		$g = $rgb[1];
		$b = $rgb[2];
	?><TD WIDTH=30 BGCOLOR="#<?php printf('%02x%02x%02x', $r, $g, $b); ?>"><?php printf('%.0f', $c * 100.0); ?></TD><?php
	}
	?></TR><?php
		}
			?>
	</TABLE>
	<?php
				flush();
			}
	?>

	<br>
	<br>
	<p><a href="json.php?id=<?php print urlencode($_GET['id']); ?>"><img src="opendata-logo.jpg" width=128></a></p>
	<br>
	<br>

	<p>(C) 2014-2020 by <a href="https://www.vanheusden.com/">www.vanheusden.com</a> &amp; 2021-<?php echo date('Y') ?> by <a href="https://daveborghuis.nl">Dave Borghuis</a></p>

</body>

</html>
