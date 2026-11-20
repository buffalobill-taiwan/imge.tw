<?php

class view{
	private $_pdo;
	function __construct($pdo){
		$this->_pdo = $pdo;
	}
	function viewImge($imge_id, $ip){
		$sql = "INSERT INTO `view`
			(`date`, `imge_id`, `seed`)
			VALUES (CURRENT_DATE(), :imge_id, :ip)
			;";
		$stmt = $this->_pdo->prepare($sql);
		$stmt->bindValue(":imge_id", $imge_id);
		$stmt->bindValue(":ip", $ip);
		try{
			$stmt->execute();
		}catch(PDOException $e){
			//Do nothing.
		}
	}
	function rank($limit){
		$sql ="SELECT `imge_id`, COUNT(`imge_id`) `count`
			FROM `view`
			WHERE `date` = SUBDATE(CURDATE(),1)
			GROUP BY `imge_id`
			ORDER BY `count` DESC
			LIMIT $limit
		";
		$stmt = $this->_pdo->prepare($sql);
		$stmt->execute();
		$rows = $stmt->fetchAll();
		return $rows;
	}
	function today($limit){
		$sql ="SELECT `imge_id`, COUNT(`imge_id`) `count`
			FROM `view`
			WHERE `date` = CURDATE()
			GROUP BY `imge_id`
			ORDER BY `count` DESC
			LIMIT $limit
		";
		$stmt = $this->_pdo->prepare($sql);
		$stmt->execute();
		$rows = $stmt->fetchAll();
		return $rows;
	}
};
