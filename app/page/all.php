<?php

function all(){
	global $PDO, $RENDER, $REQ;

	header('Cache-Control: private, no-store');
	if(!(new allowip($PDO))->isAllowed(util::getRemoteIP())){
		http_response_code(403);
		$RENDER->showError("Your IP is not allowed.");
	}
	if($_SERVER['REQUEST_METHOD'] !== 'GET'){
		header('Allow: GET');
		http_response_code(405);
		$RENDER->showError("Method Not Allowed");
	}
	if($REQ !== ['all']){
		http_response_code(404);
		$RENDER->showError("Not Found");
	}

	$before = null;
	if(isset($_GET['before'])){
		$before = filter_var($_GET['before'], FILTER_VALIDATE_INT, [
			'options' => ['min_range' => 1, 'max_range' => 2147483647],
		]);
		if($before === false){
			http_response_code(400);
			$RENDER->showError("Invalid Cursor");
		}
	}

	$rows = (new imge($PDO))->browse($before);
	$next = null;
	if(count($rows) > 100){
		array_pop($rows);
		$next = (int)$rows[99]['id'];
	}
	$RENDER->resJSON(['items' => util::galleryItems($rows), 'next' => $next]);
}
