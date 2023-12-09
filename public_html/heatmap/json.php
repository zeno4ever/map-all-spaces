<?php
include '../../private/init.php';

$id = md5($_GET['id']);
$table = 'data_' . $id;

$period='';
if (isset($_GET['period'])) {
	$period = strtoupper($_GET['period']);
}

if (!in_array($period, array('WEEK', 'MONTH', 'YEAR', 'EVERYTHING'))) {
	$period ='MONTH';
}

switch ($period) {
	case 'WEEK':
		$countdays = 7;
		break;
	case 'MONTH':
		$countdays = 31;
		break;
	case 'YEAR':
		$countdays = 365;
		break;
}

$sql = "SELECT sa, timezone FROM heatmspaces WHERE `key`='$id'";
$result = $db->rawQuery ( $sql);
if ($db->getLastErrno() !== 0) {
	echo "Error  $space. Error: ". $db->getLastError().'  '.__LINE__;
}
$row = $result[0];
$their_tz = $row['timezone'];
if ($their_tz == null or $their_tz == '')
	$their_tz = 'Europe/Amsterdam';

$time = new \DateTime('now', new DateTimeZone($their_tz));
$timezoneOffset = $time->format('P');

$j = json_decode($row['sa'], TRUE);
$s = "closed";

if (isset($j['state']['open']) && $j['state']['open']==true) {
	$s ='open';
} else if (isset($j['open'])&& $j['open']==true) {
	$s ='open';
};

if($period == 'EVERYTHING') {
	$query = 'SELECT DAYOFWEEK(tts) as dayofweek, HOUR(tts) as hour, SUM(open) / COUNT(*) AS open FROM (SELECT CONVERT_TZ(ts, "SYSTEM", "' . $timezoneOffset . '") AS tts, open FROM ' . $table . ') AS i GROUP BY dayofweek, hour';
} else {
	$query = 'SELECT DAYOFWEEK(tts) as dayofweek, HOUR(tts) as hour, SUM(open) / COUNT(*) AS open FROM (SELECT CONVERT_TZ(ts, "SYSTEM", "' . $timezoneOffset . '") AS tts, open FROM ' . $table . ') AS i WHERE tts >= DATE_SUB(CURRENT_TIMESTAMP(), INTERVAL '.$countdays.' DAY) GROUP BY dayofweek, hour';
}

for ($i = 0; $i < 24; $i++) {
	$avgs[$i] = $avgsn[$i] = 0;
}

$result = $db->rawQuery ($query);
if ($db->getLastErrno() !== 0) {
	echo "Error  $space. Error: ". $db->getLastError().'  '.__LINE__;
	exit;
}

foreach ($result as $row ) {
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
		$t += $counts[$d][$i]??0;
	$counts[$d][24] = $t / 24;
}

$result = array();
$result['copyright'] = 'v1 (C) by Folkert van Heusden <mail@vanheusden.com>';
$result['copyright'] .= ' v2 (C) by Dave Borghuis';
$result['license'] = 'This work is licensed under a Creative Commons Attribution-NoDerivatives 4.0 International License.';
$result['data'] = $counts;
$result['tz'] = $their_tz;
$result['period'] = strtolower($period);
$result['space-state'] = $s;

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
echo json_encode($result);
exit;