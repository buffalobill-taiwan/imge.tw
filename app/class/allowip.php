<?php

class allowip{
    private $_pdo;
    function __construct($pdo){
        $this->_pdo = $pdo;
    }
	function isAllowed($ip){
		if(UPLOAD_MODE === 'local'){return true;}
		$ipl = ip2long($ip);
		if($ipl === false){return false;}
		$sql = "SELECT `id`
			FROM `allowip`
			WHERE (`start` <= :ipl)
			AND (`end` >= :ipl2)
			AND (`status` = 0)
		";
		$stmt = $this->_pdo->prepare($sql);
		$stmt->bindValue(":ipl", $ipl, PDO::PARAM_INT);
		$stmt->bindValue(":ipl2", $ipl, PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll();
		return !(empty($rows));
	}
};
