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
		<h1>SpaceAPI</h1>
		<div class="container">
			<?php
			//$mysqli = new mysqli('localhost', 'spaceapi', 'spaceapi', 'spaceapi');

			$sql = "SELECT `key`, name, url FROM spaces ORDER BY name";
			if (!$result = $mysqli->query($sql)) {
				echo "Sorry, the website is experiencing problems.<br>";
				echo "Errno: " . $mysqli->errno . "<br>\n";
				echo "Error: " . $mysqli->error . "<br>\n";
				exit;
			}

			$n = 0;
			while ($space = $result->fetch_assoc()) {
				$key = $space['key'];
				$name = $space['name'];
				$url = $space['url'];

				$sql2 = 'SELECT sum(open)/count(*) * 100 as open from data_' . $key;
				if (!$result2 = $mysqli->query($sql2)) {
					echo "Sorry, the website is experiencing problems.<br>";
					echo "Errno: " . $mysqli->errno . "<br>\n";
					echo "Error: " . $mysqli->error . "<br>\n";
					exit;
				}
				$row2 = $result2->fetch_assoc();

				$data[] = [$name, $row2['open']];

				$n++;
			}

			function sortByOrder($a, $b)
			{
				return $b[1] - $a[1];
			}

			usort($data, 'sortByOrder');
			?>
			<table>
				<tr>
					<th>space</th>
					<th>percentage open</th>
				</tr>
				<?php
				for ($i = 0; $i < $n; $i++) {
					if ($data[$i][1] >= 100 || $data[$i][1] <= 0.0)
						continue;

				?><tr>
						<td><?php print $data[$i][0]; ?></td>
						<td><?php print sprintf('%.2f', $data[$i][1]); ?>%</td>
					</tr><?php
						}
							?>
			</table>

			</p>
		</div>

		<br>
		<br>

		<p>(C) 2014-2020 by <a href="https://www.vanheusden.com/">www.vanheusden.com</a></p>
</body>

</html>