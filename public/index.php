<?php

require_once dirname(__DIR__) . '/app/bootstrap.php';

$page = $REQ[0];
if(empty($page)){$page = "index";}

$target = "app/page/$page.php";
if(!in_array($page, ["index", "info", "rank", "recent", "ticket", "today", "upload"], true) || !is_readable($target)){
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found", true, 404);
	exit();
}

include($target);
$func = $page;
if(!function_exists($func)){
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found", true, 404);
	exit();
}
call_user_func($func);
