<?php

function recent(){
	global $PDO, $RENDER, $REQ;

	$IMGE = new imge($PDO);
	$recent = $IMGE->recent(100);

	$result = array();
	foreach($recent as $r){
		if(!is_file(util::id2ImgePath($r["id"], $r["ext"])) || !is_file(util::id2ThumbPath($r["id"]))){continue;}
		$bid = util::id2bid($r["id"]);
		$i = IURL . "/$bid.{$r["ext"]}";
		$t = TURL . "/$bid.png";
		$result[] = array("i" => $i, "t" => $t);
	}

	$RENDER->resJSON($result);
}
