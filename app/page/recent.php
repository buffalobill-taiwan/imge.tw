<?php

function recent(){
	global $PDO, $RENDER, $REQ;

	$IMGE = new imge($PDO);
	$recent = $IMGE->recent(100);

	$RENDER->resJSON(util::galleryItems($recent));
}
