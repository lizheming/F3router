<?php
$f3 = require("f3router.php");

$f3->route('GET /', function() {
	echo 'hello world!';
});

$f3->run();
?>