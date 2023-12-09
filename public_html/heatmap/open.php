<!DOCTYPE html>
<?php
 require('colors.inc.php');
include '../../private/init.php';
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

	<div>
		<h1>SpaceAPI</h1>
		<div class="container">
			<?php

			$sql = "SELECT `key`, name, url FROM heatmspaces ORDER BY name";
			$result = $db->rawQuery ( $sql);
			if ($db->getLastErrno() !== 0) {
				echo "Error  $space. Error: ". $db->getLastError().'  '.__LINE__;
				exit;
			}

			$n = 0;
			foreach ($result as  $space ) {
				$key = $space['key'];
				$name = $space['name'];
				$url = $space['url'];

				$sql2 = 'SELECT sum(open)/count(*) * 100 as open from data_' . $key;
				$result2 = $db->rawQuery ( $sql2);
				if ($db->getLastErrno() !== 0) {
					echo "Error  $space. Error: ". $db->getLastError().'  '.__LINE__;
					exit;
				}
				$row2 = $result2[0];

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
						<td style="text-align:left"><a href="/heatmap/show.php?id=<?php print urlencode($data[$i][0]).'">'.$data[$i][0]; ?></a></td>
						<td style="text-align:right"><?php print sprintf('%.2f', $data[$i][1]); ?>%</td>
					</tr><?php
						}
							?>
			</table>

			</p>
		</div>

		<br>
		<br>

		<p>(C) 2014-2020 by <a href="https://www.vanheusden.com/">www.vanheusden.com</a> &amp; 2021-<?php echo date('Y') ?> by <a href="https://daveborghuis.nl">Dave Borghuis</a></p>
</body>

</html>