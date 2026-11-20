<?php

require_once dirname(__DIR__) . '/app/bootstrap.php';

if(count($REQ) <= 0){
    header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found", true, 404);
    exit();
}

if(empty($REQ[0])){
	header("Location: " . URL);
	exit();
}

$parts = explode(".", $REQ[0], 2);
$bid = "";
$ext = "";
if(isset($parts[0])){$bid = $parts[0];}
if(isset($parts[1])){$ext = $parts[1];}

if(!preg_match('/^[a-zA-Z0-9]{1,6}$/D', $bid)){
    header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found", true, 404);
    exit();
}

$id = util::bid2id($bid);
if($id < 1 || $id > 2147483647 || util::id2bid($id) !== $bid){
    http_response_code(404);
    exit();
}

$IMGE = new imge($PDO);
$info = $IMGE->queryID($id);
if($info === false){
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found", true, 404);
	exit();
}

$ext = $info["ext"];
$path = util::id2ThumbPath($id);
if(!is_file($path)){
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found", true, 404);
	exit();
}

header("Content-Type: image/png");
header("X-Accel-Redirect: /" . implode("/", array_map("rawurlencode", explode("/", $path))));
exit();
