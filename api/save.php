<?php
define("IBB_WEB_SERVICE", 1);
require_once("imperial_service.php");
header("Content-type: application/json");

ValidateAccess();

if($_GET['act'] == "profile") {
	$lstErrors = array();
	$keys = array(
		"id" => "", "signature" => "", "email" => "",
		"location" => "", "website" => "");
	
	foreach($keys as $key => $value) {
		if(!isset($_POST[$key])) {
			$lstErrors[] = "Property `".$key."` was not received.";
		} else {
			$keys[$key] = $_POST[$key];
		}
	}

	$ok = ImperialService::setUserInfo($keys);
	
	$error = array();
		
	if($ok) {
		echo "SUCCESS";
		exit();
	} else {
		$error = array(
			"error" => "USER_COULDNT_UPDATE",
			"error_level" => "FATAL",
			"error_msg" => "Database is either busy, down, or Jim has died.");
	}
	
	$json = json_encode($error);
	echo $json;
	exit();	
}

if($_GET['act'] == "post") {
	if(!isset($_POST['user_id']) || !isset($_POST['topic_id']) || !isset("post_body")) {
		$error = array(
			"error" => "POST_DATA_NOT_SET",
			"error_level" => "FATAL",
			"error_msg" => "There's a parameter missing, somewhere.");
		$json = json_encode($error);
		echo $json;
		exit();
	}
	
	$ok = ImperialService::sendPostToTopicId($_POST['post_body'], 
		$_POST['user_id'], $_POST['topic_id']);
		
	$error = array();
		
	if($ok) {
		echo "SUCCESS";
		exit();
	} else {
		$error = array(
			"error" => "POST_CANT_POST",
			"error_level" => "FATAL",
			"error_msg" => "Database is either busy, down, or Jim has died.");
	}
	
	$json = json_encode($error);
	echo $json;
	exit();	
}

?>
