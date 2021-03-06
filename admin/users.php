<?php

define("IN_IBB", 1);
define("IN_ADMIN", 1);

$root_path = "../";
require_once($root_path."includes/common.php");
require_once($root_path."models/user.php");
Template::setBasePath($root_path . "templates/original/admin/");

$language->add_file("admin/users");
Template::addNamespace("L", $lang);

if(!isset($_GET['func'])) $_GET['func'] = "";

// Create template instance. 
$tplUsers = new Template("users.tpl");

/**
 * Search a user
 */
if($_GET['func'] == "search") {
	// Get the first 30 users ids (id + username)
	$lstUsersIds = User::findUsersIds(30);
	
	// Process the search sub-view. Add items to users list.
	$tplUsersSearch = new Template("users_search.tpl");
	foreach($lstUsersIds as $id => $username) {
		if($id == -1) {
			continue;
		}
		
		$tplUsersSearch->addToBlock("userlist_item", array("USERNAME" => $username));
	}
	$tplUsersSearch->setVar("CSRF_TOKEN", CSRF::getHTML());
	// Add usersearch sub-view to main user view.
	$tplUsers->addToTag("users_page", $tplUsersSearch);
	
} elseif($_GET['func'] == "edit") {
	CSRF::validate();
	/**
	 * EDIT
	 */
	// If username wasn't sent or empty !
	if(!isset($_POST['username']) || trim($_POST['username']) == "") {
		$_SESSION["return_url"] = "users.php?func=search";
		header("Location: error.php?code=".ERR_CODE_USERNAME_NOT_SET);
		exit();
	}
	
	// Fetch user
	$oUser = User::findUser(trim($_POST['username']));
	
	if(is_null($oUser)) {
		$_SESSION["return_url"] = "users.php?func=search";
		header("Location: error.php?code=".ERR_CODE_USER_NOT_FOUND);
		exit();
	}
	
	$_SESSION['user_edit_id'] = $oUser->getId();
	$tplUserEdit = new Template("users_edit.tpl");
	
	// Parse birthday
	$birthday = parseBirthday($oUser->getBirthday());
	// Get ims 
	$ims = $oUser->getMessengers();

	// Add non-conditional vars.
	$tplUserEdit->setVars(array("USERNAME" => $oUser->getUsername(),
		"USER_ID" => $oUser->getId(),
		"WEBSITE" => $oUser->getWebsite(),
		"SIGNATURE" => $oUser->getSignature(),
		"LOCATION" => $oUser->getLocation(),
		"EMAIL" => $oUser->getEmail(),
		"BDAY_MONTH" => $birthday["month"],
		"BDAY_DAY" => $birthday["day"],
		"BDAY_YEAR" => $birthday["year"],
		"AIM" => $ims["aim"],
		"ICQ" => $ims["icq"],
		"MSN" => $ims["msn"],
		"YAHOO" => $ims["yahoo"]
		));
		
	// Fetch usergroups.
	$db2->query("SELECT * FROM _PREFIX_usergroups");
	while($ug_result = $db2->fetch()) {
		$tplUserEdit->addToBlock("usergroupslist_item", array(
			"UG_ID" => $ug_result['id'],
			"UG_NAME" => $ug_result['name'],
			"UG_SELECTED" => ($ug_result['id'] == $oUser->getUsergroupId()) ? "selected=\"SELECTED\"" : ""
			));
	}
	
	// Fetch ranks.
	$db2->query("SELECT * FROM `_PREFIX_ranks`");
	while($rank_result = $db2->fetch()) {
		$tplUserEdit->addToBlock("rankslist_item", array(
			"RANK_ID" => $rank_result['rank_id'],
			"RANK_NAME" => $rank_result['rank_name'],
			"RANK_SELECTED" => ($rank_result['rank_id'] == $oUser->getRankId()) ? "selected=\"SELECTED\"" : ""
			));
	}
	
	// Fetch user levels. TODO: Move somewhere
	$user_levels = array($lang['Administrator'] => "5", $lang['Moderator'] => "4", $lang['Registered'] => "3", $lang['Validating'] => "2", $lang['Guest'] => "1", $lang['Banned'] => "0");
	foreach($user_levels as $ul_name => $ul_id)	{
		$tplUserEdit->addToBlock("levelslist_item", array(
			"UL_ID" => $ul_id,
			"UL_NAME" => $ul_name,
			"UL_SELECTED" => ($ul_id == $oUser->getLevel()) ? "selected=\"selected\"" : ""
			));
	}
	
	// Add conditional vars.
	if($oUser->getEmailOnPm()) {
		$tplUserEdit->addToBlock("email_on_pm_true", array());
	} else {
		$tplUserEdit->addToBlock("email_on_pm_false", array());
	}
	$tplUserEdit->setVar("CSRF_TOKEN", CSRF::getHTML());
	// Add subview.
	$tplUsers->addToTag("users_page", $tplUserEdit);
} elseif($_GET['func'] == "save") {
	/** 
	 * SAVE
	 */
	 CSRF::validate();
	 
	 // Check if any data is even sent.
	if(!isset($_SESSION['user_edit_id']) || $_SESSION['user_edit_id'] < 0) {
		$_SESSION["return_url"] = "users.php?func=search";
		header("Location: error.php?code=".ERR_CODE_USERNAME_NOT_SET);
		exit();
	}
	
	// Verify if user exists.
	$oUser = User::findUser($_SESSION['user_edit_id']);
	if(is_null($oUser)) {
		$_SESSION["return_url"] = "users.php?func=search";
		header("Location: error.php?code=".ERR_CODE_USER_NOT_FOUND);
		exit();
	}
	// Unset var.
	unset($_SESSION['user_edit_id']);
	
	$oUser->setUsername($_POST['username']); 
	//$oUser->setMail($_POST['email']);
	$oUser->setEmailOnPm($_POST['emailonpm'] == "true" ? true : false);
	$oUser->setSignature($_POST['signature']);
	$oUser->setUsergroupId($_POST['usergroupslist']);
	$oUser->setRankId($_POST['rankslist']);
	$oUser->setLevel($_POST['levelslist']); 
	$oUser->setWebsite($_POST['website']);
	$oUser->setLocation($_POST['location']);
	$oUser->setSignature($_POST['signature']);
			
	$pass_ok = true;
	if(strlen($_POST['new_password']) > 0) {
		if($_POST['new_password'] != $_POST['new_password2']) {
			$pass_ok = false;
		} else {
			$oUser->setPassword($_POST['new_password']); 
			$oUser->updatePassword();
		}
	}
	
	$ok = $oUser->update();
	
	if(!$ok) {
		$_SESSION["return_url"] = "users.php?func=search";
		header("Location: error.php?code=".ERR_CODE_USER_CANT_UPDATE);
		exit();
	} else if(!$pass_ok) {
		$_SESSION["return_url"] = "users.php?func=search";
		header("Location: error.php?code=".ERR_CODE_USER_PASS_MISMATCH);
		exit();
	} else { 
		$_SESSION["return_url"] = "users.php?func=search";
		header("Location: error.php?code=".ERR_CODE_USER_UPDATE_SUCCESS);
		exit();
	}
} else if($_GET['func'] == "delete") {
	/** 
	 * DELETE
	 */
	if(!isset($_POST['username']))
	{
		// Get the first 30 users ids (id + username)
		$lstUsersIds = User::findUsersIds(30);
		$tplUserDelete = new Template("users_delete.tpl");

		foreach($lstUsersIds as $id => $username) {
			if($id == -1) {
				continue;
			}
		
			$tplUserDelete->addToBlock("userlist_item", array("USERNAME" => $username));
		}
		
		$tplUserDelete->setVar("CSRF_TOKEN", CSRF::getHTML());
		// Add subview.
		$tplUsers->addToTag("users_page", $tplUserDelete);
	} else {
		CSRF::validate();
		$ok = User::delete($_POST['username']);

		if(!$ok) { 
			$_SESSION["return_url"] = "users.php?func=delete";
			header("Location: error.php?code=".ERR_CODE_USER_CANT_DELETE);
			exit();
		} else {
			$_SESSION["return_url"] = "users.php?func=delete";
			header("Location: error.php?code=".ERR_CODE_USER_DELETE_SUCCESS);
			exit();
		}
	}
}

outputPage($tplUsers);
?>
