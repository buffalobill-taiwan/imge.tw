<?php

function ticket(){
	global $PDO, $RENDER, $REQ;

	$ip = util::getRemoteIP();
	$ALLOWIP = new allowip($PDO);
	if(!$ALLOWIP->isAllowed($ip)){
		$RENDER->showError("Your IP is not allowed.");
	}
	$TICKET = new ticket($PDO);
	$t = $TICKET->getTicket($ip);

	$RENDER->resJSON($t);
}
