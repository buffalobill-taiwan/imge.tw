<?php

function index(){
	global $PDO, $RENDER, $REQ;
	$ip = util::getRemoteIP();
	$ALLOWIP = new allowip($PDO);
	$RENDER->addVar("fromTW", $ALLOWIP->isAllowed($ip));
	$RENDER->addVar("isMobile", util::isMobile());
	$RENDER->addVar("info", util::info($PDO));
	$RENDER->addVar("ip", $ip);
	$RENDER->render('index');
}
