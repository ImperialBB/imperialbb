<?php

define("IN_IBB", 1);
define("IN_ADMIN", 1);

$root_path = "../";
require_once($root_path."includes/common.php");
require_once($root_path."models/user.php");
Template::setBasePath($root_path . "templates/original/admin/");
$language->add_file("admin/error");

// If no error code, just redirect to index.
if(!isset($_GET['code']) || !is_numeric($_GET['code'])) {
	header("location: index.php");
	exit();
}

$tplError = new Template("error.tpl");

if(isset($lang['err_code'.$_GET['code']])) { 
	$tplError->setVar("ERROR_MSG", $lang['err_code'.$_GET['code']]);
}

if(isset($_SESSION['return_url'])) {
	$tplError->setVar("RETURN_URL", $_SESSION['return_url']);
}

$tplError->setVar("ERROR", $lang['error']);
$tplError->setVar("DONT_WAIT", $lang['dont_wait_redirect']);

// add to main layout
//$main_layout->addToTag("page_content", $tplError);
echo $tplError->render();
?>
