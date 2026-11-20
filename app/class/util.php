<?php

defined("BASE62") || define("BASE62", "mVw4nJipQFaozZ1bxRg9c6djB5UIleEKTANryX0Hh3kfuWOCsSGtD2vL8qMPY7");

class util{
	static function getRemoteIP(){
		$ip = $_SERVER['REMOTE_ADDR'];
//		if(isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP'])) { $ip = $_SERVER['HTTP_CLIENT_IP']; }
//		if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { $ip = $_SERVER['HTTP_X_FORWARDED_FOR']; }
		return $ip;
	}

	static function isMobile() {
		if(!isset($_SERVER["HTTP_USER_AGENT"])){return false;}
		return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
	}

	static function swapstr($str){
		$newstr = $str;
		for($i = 1; $i < strlen($str); $i += 2){
			$newstr[ $i ] = $str[ $i - 1];
			$newstr[ $i - 1 ] = $str[ $i];
		}
		return $newstr;
	}

	static function id2ThumbPath($id){
		$ht = floor(($id % 10000) / 100);
		$ot = $id % 100;
		$path = "storage/t/$ht/$ot";
		return "$path/$id.png";
	}

	static function id2ImgePath($id, $ext){
		$ht = floor(($id % 10000) / 100);
		$ot = $id % 100;
		$path = "storage/i/$ht/$ot";
		return "$path/$id.$ext";
	}

	static function id2bid($num, $base = BASE62){
		$num42 = $num ^ 4242;
		$b = strlen($base);
		$r = bcmod($num42, $b);
		$res = $base[$r];
		$q = floor(bcdiv($num42, $b));
		while ($q || strlen($res) < 3) {
			$r = bcmod($q, $b);
			$q = floor(bcdiv($q, $b));
			$res = $base[$r] . $res;
		}
		return self::swapstr($res);
	}

	static function bid2id($bid, $base = BASE62){
		$bid = self::swapstr($bid);
		$b = strlen($base);
		$limit = strlen($bid);
		$res = strpos($base, $bid[0]);
		for($i=1; $i<$limit; $i++) {
			$res = bcadd(bcmul($b, $res), strpos($base,$bid[$i]));
		}
		return $res ^ 4242;
	}

	static function byteConvert($bytes) {
		if ($bytes == 0)
			return "0Bytes";
		$s = array('Bytes', 'KB', 'MB', 'GB', 'TB', 'PB');
		$e = floor(log($bytes, 1024));
		return round($bytes/pow(1024, $e), 2).$s[$e];
	}

	static function galleryItems($rows){
		$result = array();
		foreach($rows as $r){
			if(!is_file(util::id2ImgePath($r["id"], $r["ext"])) || !is_file(util::id2ThumbPath($r["id"]))){continue;}
			$bid = util::id2bid($r["id"]);
			$i = IURL . "/$bid.{$r["ext"]}";
			$t = TURL . "/$bid.png";
			$result[] = array("i" => $i, "t" => $t);
		}
		return $result;
	}

	static function info($PDO){
		$IMGE = new imge($PDO);

		$total = $IMGE->total();
		$space = $IMGE->space();
		$free = disk_free_space("storage");
		$percent = floor($space * 10000 / ($free+$space)) / 100;

		return array(
			"total" => $total,
			"space" => util::byteConvert($space),
			"free" => util::byteConvert($free),
			"percent" => $percent,
			"top" => [],
		);
	}
};

if(PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__){
	for($i = 0; $i < 10; $i++){
		$r = rand();
		$r = $i;
		$b62 = util::id2bid($r);
		$b10 = util::bid2id($b62);
		print "$r: $b62 -> $b10\n";
	}
	$b10 = util::bid2id("IVx");
	print "IVx: $b10\n";
	$b1102 = util::id2bid(1102);
	print "1102: $b1102\n";
}
