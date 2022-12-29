<?php


$db_host = 'localhost';
 $db_user = 'user';
 $db_password = '4amiga';
 $db_db = 'mapall';
 $db_port = 8889;

 $mysqli = new mysqli(
 $db_host,
 $db_user,
 $db_password,
 $db_db
 );





echo date('d-m-Y H:i:s') . PHP_EOL;


$result = $mysqli->query('SELECT * FROM spaces where name="TkkrLab"');
$row = $result->fetch_assoc();

 if ($mysqli->connect_error) {
 echo 'Errno: '.$mysqli->connect_errno;
 echo '<br>';
 echo 'Error: '.$mysqli->connect_error;
 exit();
 }

 echo 'Success: A proper connection to MySQL was made.';
 echo '<br>';
 echo 'Host information: '.$mysqli->host_info;
 echo '<br>';
 echo 'Protocol version: '.$mysqli->protocol_version;

 var_dump($row);

 $mysqli->close();

// $bla = getImageNodes()

//  /**
//  * Get all image nodes.
//  *
//  * @param \DOMNode     $node       The \DOMDocument instance
//  * @param boolean      $strict     If the document has to be valid
//  *
//  * @return \DOMNode
//  */
//  public function getImageNodes(\DOMNode $node, $strict = true): \DOMNode
//  {
//      // ...
//  }


 ?>