<?php header("HTTP/1.1 404 Not Found"); ?>
<h2>Failed to resolve application URI: <?php
	echo parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
?></h2>