<?php

function info(){
	global $PDO, $RENDER, $REQ;

	$info = util::info($PDO);
	$RENDER->resJSON($info);
}
