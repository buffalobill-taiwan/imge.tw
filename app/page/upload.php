<?php

function upload(){
	global $PDO, $RENDER, $REQ;
	$ip = util::getRemoteIP();
	if(!(new allowip($PDO))->isAllowed($ip)){
		$RENDER->showError("Your IP is not allowed.");
	}

	$file = $_FILES['file'] ?? [];
	$error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
	if($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE){
		$RENDER->showError("Size Too Big");
	}
	if($error !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])){
		$RENDER->showError("No Valid File Uploaded");
	}
	if($file['size'] > 8388608){
		$RENDER->showError("Size Too Big");
	}

	$tmp = $file['tmp_name'];
	$info = @getimagesize($tmp);
	if(!$info || !$info[0] || !$info[1] || $info[0] * $info[1] > 40000000){
		$RENDER->showError("Invalid Image or Dimensions Too Large");
	}
	$ext = image_type_to_extension($info[2], false);
	if($ext === false || !ctype_alnum($ext)){
		$RENDER->showError("Unsupported Image Format");
	}

	$token = $REQ[1] ?? '';
	if(!preg_match('/^[a-f0-9]{64}$/D', $token)){
		$RENDER->showError("Ticket Not Valid");
	}
	$digest = hash_file('sha256', $tmp);
	try{
		$result = storeUpload($PDO, $ip, $token, $digest, $file, $info, $ext);
	}catch(Throwable $e){
		error_log("Image upload failed: " . $e->getMessage());
		$RENDER->showError("Unable to Save Image");
	}
	$RENDER->resJSON($result);
}

function storeUpload($pdo, $ip, $token, $digest, $file, $info, $ext){
	$lock = $pdo->prepare('SELECT GET_LOCK(?, 10)');
	$lock->execute([$digest]);
	if((int)$lock->fetchColumn() !== 1){
		return ['error' => 'Image Upload Busy'];
	}

	$paths = [];
	try{
		$pdo->beginTransaction();
		$tickets = new ticket($pdo);
		if(!$tickets->checkTicket($ip, $token)){
			return ['error' => 'Ticket Not Valid'];
		}

		$images = new imge($pdo);
		$existing = $images->existDigest($digest);
		$duplicate = false;
		if($existing){
			$id = (int)$existing['id'];
			$ext = $existing['ext'];
			$duplicate = (int)$existing['status'] === 0
				&& is_file(util::id2ImgePath($id, $ext))
				&& is_file(util::id2ThumbPath($id));
			if(!$duplicate && !in_array((int)$existing['status'], [0, -1], true)){
				return ['error' => 'Image Unavailable'];
			}
		}

		if(!$duplicate){
			$thumb = new Imagick();
			$thumb->setBackgroundColor(new ImagickPixel('transparent'));
			$thumb->readImage($file['tmp_name'] . '[0]');
			$thumb->thumbnailImage(128, 128, true, true);
			$thumb->setImageFormat('png32');

			$record = [
				'ticket' => $token, 'seed' => $ip, 'digest' => $digest,
				'upload_path' => $file['name'], 'ext' => $ext,
				'upload_type' => $info['mime'], 'upload_size' => $file['size'],
				'mime_type' => $info['mime'], 'width' => $info[0], 'height' => $info[1],
			];
			$id = $existing ? $images->repair($id, $record) : $images->newImge($record);
			$original = util::id2ImgePath($id, $ext);
			$thumbnail = util::id2ThumbPath($id);
			foreach([$original, $thumbnail] as $path){
				$dir = dirname($path);
				if(!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)){
					throw new RuntimeException('Unable to create image directory');
				}
			}

			// Keep any surviving file from an existing record intact.
			if(!$existing || !is_file($thumbnail)){
				publishUploadFile($thumbnail, fn($temporary) => $thumb->writeImage($temporary));
				$paths[] = $thumbnail;
			}
			if(!$existing || !is_file($original)){
				publishUploadFile($original, fn($temporary) => move_uploaded_file($file['tmp_name'], $temporary));
				$paths[] = $original;
			}
		}

		$tickets->completeTicket($token, $id);
		$result = ['url' => IURL . '/' . util::id2bid($id) . '.' . rawurlencode($ext)];
		if($duplicate){
			$result['duplicate'] = true;
		}
		$pdo->commit();
		return $result;
	}catch(Throwable $e){
		foreach($paths as $path){
			if(is_file($path) && !unlink($path)){
				error_log('Unable to remove partial image file: ' . $path);
			}
		}
		throw $e;
	}finally{
		try{
			if($pdo->inTransaction()){
				$pdo->rollBack();
			}
		}finally{
			$release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
			$release->execute([$digest]);
		}
	}
}

function publishUploadFile($path, callable $write){
	$directory = realpath(dirname($path));
	if($directory === false){
		throw new RuntimeException('Upload directory does not exist');
	}

	$temporary = tempnam($directory, '.upload-');
	if($temporary === false){
		throw new RuntimeException('Unable to create temporary upload file');
	}
	try{
		if(dirname($temporary) !== $directory || !$write($temporary)
			|| !chmod($temporary, 0644) || !rename($temporary, $path)){
			throw new RuntimeException('Unable to publish image file: ' . $path);
		}
	}finally{
		if(is_file($temporary) && !unlink($temporary)){
			error_log('Unable to remove temporary upload file: ' . $temporary);
		}
	}
}
