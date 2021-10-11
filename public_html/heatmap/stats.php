<!DOCTYPE html>
<? require('colors.inc.php'); ?>
<? include '../../private/init.php'; ?>
<html>

<HEAD>
	<LINK HREF="sakura.css" REL="stylesheet" TYPE="text/css">
	<LINK HREF="index.css" REL="stylesheet" TYPE="text/css">
</HEAD>

<body>
	<div>
		<h1>SpaceAPI statistics</h1>

		<?
		//$mysqli = new mysqli('localhost', 'spaceapi', 'spaceapi', 'spaceapi');
		$sql = 'SELECT name, `key`, sa FROM spaces ORDER BY name';
		$result = $mysqli->query($sql);
		?>

		<table>
			<tr>
				<th>space</th>
				<th>open%</th>
				<th>records</th>
				<th>data age</th>
			</tr>
			<?
			while ($row = $result->fetch_assoc()) {
				$j = json_decode($row['sa'], TRUE);
				$sql = 'SELECT sum(open) * 100 / count(*) as openp, count(*) AS n FROM data_' . $row['key'];
				$result2 = $mysqli->query($sql);
				$row2 = $result2->fetch_assoc();
			?>
				<tr>
					<td><a href="<? print $j['url']; ?>"><? print $row['name']; ?></a></td>
					<td><? print sprintf('%.2f%%', $row2['openp']); ?></td>
					<td><? print $row2['n']; ?></td>

					<? $lastchange = 0;
					if ($j['state']['lastchange'] != '')
						$lastchange = $j['state']['lastchange'];
					else if ($j['lastchange'] != '')
						$lastchange = $j['lastchange'];
					?><td><? if ($lastchange != 0) print time() - $lastchange; ?></td>
				<? } ?>
				</tr>
		</table>

		<br>
		<br>

		<p>(C) 2014-2020 by <a href="https://www.vanheusden.com/">www.vanheusden.com</a> &amp; 2021-<?php echo date('Y') ?> by <a href="https://daveborghuis.nl">Dave Borghuis</a></p>
</body>

</html>