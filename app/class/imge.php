<?php

class imge{
	private $_pdo;
	function __construct($pdo){
		$this->_pdo = $pdo;
	}
	function newImge($arg){
		$sql = "INSERT INTO `imge`
			(`ticket`, `seed`, `digest`, `upload_path`, `ext`, `upload_type`, `upload_size`, `mime_type`, `width`, `height`)
			VALUES (:ticket, :seed, :digest, :upload_path, :ext, :upload_type,  :upload_size, :mime_type, :width, :height)
			;";
		$stmt = $this->_pdo->prepare($sql);
		foreach($arg as $key => $val){
			$stmt->bindValue(":$key", $val);
		}
		$stmt->execute();

		$imge_id = $this->_pdo->lastInsertId();
		return $imge_id;
	}
	function repair($id, $record){
		$sql = "UPDATE `imge` SET `ticket`=:ticket, `seed`=:seed, `digest`=:digest,
			`upload_path`=:upload_path, `ext`=:ext, `upload_type`=:upload_type,
			`upload_size`=:upload_size, `mime_type`=:mime_type, `width`=:width,
			`height`=:height, `status`=0, `comment`=NULL WHERE `id`=:id";
		$record['id'] = $id;
		$this->_pdo->prepare($sql)->execute($record);
		return $id;
	}
	function queryID($id){
		$sql = "SELECT `id`, `ext`, `upload_size`, `mime_type`
			FROM `imge`
			WHERE (`id` LIKE :id)
			AND (`status` = 0)
		";
		$stmt = $this->_pdo->prepare($sql);
		$stmt->bindValue(":id", $id);
		$stmt->execute();
		$rows = $stmt->fetchAll();
		if(empty($rows)) return false;
		return $rows[0];
	}
	function existDigest($digest){
		$sql = "SELECT `id`, `ext`, `status`
			FROM `imge`
			WHERE `digest` = :digest
		";
		$stmt = $this->_pdo->prepare($sql);
		$stmt->bindValue(":digest", $digest);
		$stmt->execute();

		$rows = $stmt->fetchAll();
		if(empty($rows)) return false;
		return $rows[0];
	}
	function recent($n = 100){
		$sql = "SELECT `id`, `ext`
			FROM `imge`
			WHERE (`status` = 0)
			ORDER by `timestamp` DESC
			LIMIT $n
		";
		$stmt = $this->_pdo->prepare($sql);
		$stmt->execute();
		$rows = $stmt->fetchAll();
		return $rows;
	}
	function total(){
		$sql = "SELECT count(`id`) `total`
			FROM `imge`
			WHERE (`status` = 0)
			";
		$stmt = $this->_pdo->prepare($sql);
		$stmt->execute();
		$rows = $stmt->fetchAll();
		if(empty($rows)) return 0;
		return $rows[0]['total'];
	}
	function space(){
		$sql = "SELECT COALESCE(SUM(`upload_size`), 0) `space`
			FROM `imge` WHERE `status` = 0
			";
		$stmt = $this->_pdo->prepare($sql);
		$stmt->execute();
		$rows = $stmt->fetchAll();
		if(empty($rows)) return 0;
		return $rows[0]['space'];
	}
};
