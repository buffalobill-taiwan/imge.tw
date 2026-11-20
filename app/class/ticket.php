<?php


class ticket{
	private $_pdo;
	function __construct($pdo){
		$this->_pdo = $pdo;
	}
	function getTicket($seed){
		$this->expireTicket();
		$ticket = bin2hex(random_bytes(32));
		$sql = "INSERT INTO `ticket` (`seed`, `ticket`, `expire`, `renew`)
			VALUES (:seed, :ticket, NOW() + INTERVAL 5 MINUTE, NOW() + INTERVAL 3 MINUTE)";
		$this->_pdo->prepare($sql)->execute(['seed' => $seed, 'ticket' => $ticket]);
		$sql = "SELECT `ticket`, UNIX_TIMESTAMP(`expire`) `expire`,
			UNIX_TIMESTAMP(`renew`) `renew` FROM `ticket` WHERE `ticket` = ?";
		$stmt = $this->_pdo->prepare($sql);
		$stmt->execute([$ticket]);
		return $stmt->fetch();
	}
	function checkTicket($seed, $ticket){
		$sql = "SELECT `ticket`
			FROM `ticket`
			WHERE (NOW() < `expire`)
			AND (`status` = 0)
			AND (`seed` = :seed)
			AND (`ticket` = :ticket) FOR UPDATE";
		$stmt = $this->_pdo->prepare($sql);
		$stmt->bindValue(":seed", $seed);
		$stmt->bindValue(":ticket", $ticket);
		$stmt->execute();
		$rows = $stmt->fetchAll();
		if(empty($rows)) return false;
		return $rows[0];
	}
	function expireTicket(){
		$sql = "UPDATE `ticket`
			SET `status` = -2, `comment` = 'expired'
			WHERE (`status` = 0)
			AND ( NOW() > `expire` );
		";
		$stmt = $this->_pdo->prepare($sql);
		$stmt->execute();
	}
	function completeTicket($ticket, $imge_id){
		$sql = "UPDATE `ticket` SET `status` = 1, `comment` = :imge_id WHERE (`ticket` = :ticket)";
		$stmt = $this->_pdo->prepare($sql);
		$stmt->bindValue(":imge_id", $imge_id);
		$stmt->bindValue(":ticket", $ticket);
		$stmt->execute();
	}
};
