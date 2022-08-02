<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');

include '../../private/init.php';

$id = md5($_GET['id']);
$table = 'data_' . $id;

$period = strtoupper($_GET['period']);
if (!in_array($period, array('WEEK', 'MONTH', 'YEAR', 'EVERYTHING'))) {
	$period ='MONTH';
}

$sql = "SELECT sa, timezone FROM spaces WHERE `key`='$id'";
$result = $mysqli->query($sql);
$row = $result->fetch_assoc();
$their_tz = $row['timezone'];
if ($their_tz == null or $their_tz == '')
	$their_tz = 'Europe/Amsterdam';

$time = new \DateTime('now', new DateTimeZone($their_tz));
$timezoneOffset = $time->format('P');

$j = json_decode($row['sa'], TRUE);
$s = "closed";
if ($j['state']['open'] == 'true' || $j['open'] == 'true')
	$s = "open";

if($period == 'EVERYTHING') {
	$query = 'SELECT DAYNAME(ts) AS dayofweek, HOUR(ts) AS hour, avg(factor) as open FROM (select CONVERT_TZ(ts, "SYSTEM", "' . $timezoneOffset . '") AS ts, sum(open) / count(*) as factor from ' . $table . ' group by date(ts), hour(ts)) as der GROUP BY dayofweek, hour ORDER BY WEEKDAY(ts)';
} else {
	$query = 'SELECT DAYNAME(ts) AS dayofweek, HOUR(ts) AS hour, avg(factor) as open FROM (select CONVERT_TZ(ts, "SYSTEM", "' . $timezoneOffset . '") AS 	ts, sum(open) / count(*) as factor from ' . $table . ' WHERE ts >= DATE_SUB(CURDATE(), INTERVAL 1 ' . $period . ') group by date(ts), hour(ts)) as der GROUP BY dayofweek, hour ORDER BY WEEKDAY(ts)';
}

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
	};
}

foreach ($counts as $d => $v) {
	$t = 0;
	for ($i = 0; $i < 24; $i++)
		$t += $counts[$d][$i];
	$counts[$d][24] = $t / 24;
}

$result = array();
$result['copyright'] = 'v1 (C) by Folkert van Heusden <mail@vanheusden.com>';
$result['license'] = 'This work is licensed under a Creative Commons Attribution-NoDerivatives 4.0 International License.';
$result['data'] = $counts;
$result['tz'] = $their_tz;
$result['period'] = strtolower($period);
$result['space-state'] = $s;

print json_encode($result);

flush();