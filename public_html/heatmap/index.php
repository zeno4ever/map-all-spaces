<!DOCTYPE html>
<?php 
require('colors.inc.php'); 
include '../../private/init.php'; 

$color[0] = '#ff6060'; //dicht = rood
$color[1] = '#60ff60'; //open = groen
$color[3] = '#999999'; //niet meer actief	

?>
<html>

<HEAD>
	<LINK HREF="sakura.css" REL="stylesheet" TYPE="text/css">
	<LINK HREF="index.css" REL="stylesheet" TYPE="text/css">
	<link rel="stylesheet" type="text/css" href="/css/style.css">
</HEAD>

<body>
	<div id="header">
		<?php include $PRIVATE . '/layout/navigate.php' ?>
	</div>
	<div style="margin-top: 70px;">
		<h1>SpaceAPI heatmap</h1>
		<div class="container">
			<p>This website retrieves the open/closed status of all hackerspaces registered at <a href="https://spaceapi.io/">spaceapi.io</a>.<br>
				This data is then displayed in a heatmap.<br>
				Each space is polled 6 times per hour (every 10 minutes).</p>
			<p>The heatmaps was original created by <a href="https://www.vanheusden.com/">www.vanheusden.com</a> and moved in 20 Sept. 2021 to mapall.space.
			</p>

			<p>Data quality: <?php
								$sql = 'select sum(get_ok) * 100 / sum(get_total) as q, sum(lns) * 100 / count(*) as openp from heatmspaces';
								$result = $db->rawQuery ($sql);
								if ($db->getLastErrno() !== 0) {
									echo "Error: ". $db->getLastError();
								}
								print sprintf('%.2f%%', $result[0]['q']);
								?> (100% is all space-api calls succeeded)</p>
			<p>Percentage of the spaces that are open: <?php print sprintf('%.2f%%', $result[0]['openp']); ?></p>
			<p>Click on a hackerspace name to open the heatmap-view.</p>

			<p><?php 
				$sql = "SELECT distinct lower(substr(name, 1, 1)) as l FROM heatmspaces ORDER BY l";
				$result = $db->rawQuery ($sql);
				if ($db->getLastErrno() !== 0) {
					echo "Error: ". $db->getLastError();
				}
				$col = 1;
				foreach ($result as $letters ) {
					?><a href="#<?php print $letters['l']; ?>" <?php
														if ($col) {
														?>style="background-color: #606060;" <?php
																							} else {
																								?>style="background-color: #808080;" <?php 
																																	}
																																	$col = 1 - $col;
																																		?>><?php print $letters['l']; ?></a><?php 
																																										}
																																											?></p>

			<?php
			$sql = "SELECT `key`, name, logo, lns, lastupdated FROM heatmspaces ORDER BY name";
			$result = $db->rawQuery ($sql);
			if ($db->getLastErrno() !== 0) {
				echo "Error: ". $db->getLastError();
			}

			$p = 'a';
			print '<p>';

			// while ($space = $result->fetch_assoc()) {
			foreach ($result as $space ) {
					$name = $space['name'];
				$first = strtolower(substr($name, 0, 1));
				if ($first != $p) {
					$p = $first;
					print "\n</p><br><a name='$first'></a><p class=\"letters\"><span class=\"letter\">$first </span><br>";
				}

				$lns = $space['lns'];

				//meer dan 6 maanden geen update/wijziging open status
				if (isset($space['lastupdated']) && strtotime($space['lastupdated']) < strtotime("-6 Month") ) {
					$lns = 3; 
				};


			?><a href="show.php?id=<?php print urlencode($space['name']); ?>" style="background-color: <?php print $color[$lns] ?>;"><?php print $name; ?></a> 
			<?php } ?>
			</p>
		</div>
		Color meanings : <span style="background-color: <?php print $color[1] ?>" > Now open </span>&nbsp;<span style="background-color: <?php print $color[0] ?>" >  Closed  </span>&nbsp;<span style="background-color: <?php print $color[3] ?>" > Inactive (longer 6 months) </span>
		<br>
		<p>See <a href="open.php">this</a> page to see a global open/closed percentage for all spaces.</p>
		<br>
		<br>

		<p>(C) 2014-2020 by <a href="https://www.vanheusden.com/">www.vanheusden.com</a> &amp; 2021-<?php echo date('Y') ?> by <a href="https://daveborghuis.nl">Dave Borghuis</a></p>
</body>

</html>