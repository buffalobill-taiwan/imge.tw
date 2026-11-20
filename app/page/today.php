<?php

function today(){
	global $PDO, $RENDER, $REQ;
	$RENDER->showError("This Function is disabled.");
	/*

	$VIEW = new view($PDO);
	$IMGE = new imge($PDO);
	$rank = $VIEW->today(20);

	$result = array();
	foreach($rank as $r){
		$bid = util::id2bid($r["imge_id"]);
		$info = $IMGE->queryID($r["imge_id"]);
		if($info === false){continue;}
		$i = IURL . "/$bid.{$info['ext']}";
		$t = TURL . "/$bid.png";
		$result[] = array("i" => $i, "t" => $t, "count" => $r["count"]);
	}

	$RENDER->resJSON($result);
	 */
}
