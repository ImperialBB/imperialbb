<?php
define("IN_IBB", 1);

$root_path = "./";
require_once($root_path . "includes/common.php");

$language->add_file("pm");
$language->add_file("view_topic");
Template::addNamespace("L", $lang);

if($user['user_id'] <= 0) {
	showMessage(ERR_CODE_REQUIRE_LOGIN, "login.php");
}

if(!isset($_GET['func'])) $_GET['func'] = "";

if($_GET['func'] == "send")
{
	$language->add_file("posting");
	Template::addNamespace("L", $lang);

	if(isset($_POST['Submit'])) {
		CSRF::validate();

		$error = "";
		if(strlen($_POST['username']) < 1 ) {
			$error .= sprintf($lang['No_x_content'], strtolower($lang['Username'])) ."<br />";
		}
		
		if(strlen($_POST['title']) < 1 ) {
			$error .= sprintf($lang['No_x_content'], strtolower($lang['Title'])) ."<br />";
		}
		
		if(strlen($_POST['body']) < 1) {
			$error .= $lang['No_Post_Content'] . "<br />";
		}
		
		if(!isset($_POST['action']) || strlen($_POST['action']) < 1) {
			$error .= $lang['Select_An_Action_PM'] . "<br />";
		}
		
		if(strlen($error) > 0) {
			$page_master = new Template("send_pm.tpl");

			$page_master->setVars(array(
				"ACTION" => $lang['Send_PM'],
				"TITLE" => $_POST['title'],
				"BODY" => $_POST['body'],
				"CSRF_TOKEN" => CSRF::getHTML()
			));

			$page_master->addToBlock("username", array(
				"USERNAME" => $_POST['username'],
				"PM_SELECTED" => (!isset($_POST['action']) || $_POST['action'] == "pm" || $_POST['action'] == "") ? "CHECKED" : "",
				"EMAIL_SELECTED" => (isset($_POST['action']) && $_POST['action'] == "email") ? "CHECKED" : "",
			));

			if($config['bbcode_enabled'] == true) {
				$page_master->setVar("BBCODE_EDITOR",
					renderBBCodeEditor());
			}
			
			if($config['smilies_enabled'] == true) {
				$page_master->setVar("SMILIE_PICKER",
					renderSmiliePicker());
			}

			$page_master->addToBlock("error", array(
				"ERRORS" => $error
			));

			outputPage($page_master);
			exit();
		} else {
			$sql = $db2->query("SELECT *
				FROM `_PREFIX_users`
				WHERE `username` = :username",
				array(
					":username" => $_POST['username']
				));
				
			if($result = $sql->fetch()) {
				if($_POST['action'] == "pm") {
					$db2->query("INSERT INTO `_PREFIX_pm`
						VALUES (
						'',
						:title,
						:body,
						:receiver,
						:sender,
						'1',
						'1',
						:pm_time
						)",
						array(
							":title" => $_POST['title'],
							":body" => $_POST['body'],
							":receiver" => $result['user_id'],
							":sender" => $user['user_id'],
							":pm_time" => time()
						));

                    $get_config = "SELECT * FROM `_PREFIX_config`
                               WHERE `config_name` = :use_smtp";
                    $db2->query($get_config, array(":use_smtp" => "use_smtp"));
                    $answer = $db2->fetchAll();

                    $use_smtp = $answer[0]['config_value'];

                    if ($use_smtp != 0) {

                        $pm_id = $db2->lastInsertId();
                        if ($result['user_email_on_pm'] == "1") {
                            email($lang['Email_PM_Recieved_Subject'], "pm_recieved", array(
                                "USERNAME" => $result['username'],
                                "SITE_NAME" => $config['site_name'],
                                "DOMAIN" => $config['url'],
                                "PM_ID" => $pm_id), $result['user_email']);
                        }
                    }
					
					showMessage(ERR_CODE_PM_SENT, "pm.php");
				} else if($_POST['action'] == "email") {
					email($_POST['title'], "user_email", array(
						"SITE_NAME" => $config['site_name'],
						"AUTHOR_USERNAME" => $user['username'],
						"USERNAME" => $result['username'],
						"MESSAGE" => $_POST['body']), $result['user_email'], $user['user_email']);
					showMessage(ERR_CODE_EMAIL_SENT, "pm.php");	
				}
                else {
					showMessage(ERR_CODE_INVALID_ACTION);
				}
			} else {
				showMessage(ERR_CODE_USER_NOT_FOUND);
			}
		}
	} else {
		$page_master = new Template("send_pm.tpl");

		$page_master->setVars(array(
			"ACTION" => $lang['Send_PM'],
			"TITLE" => "",
			"BODY" => "",
			"CSRF_TOKEN" => CSRF::getHTML()
		));

		$page_master->addToBlock("username", array(
			"USERNAME" => (isset($_GET['username'])) ? $_GET['username'] : "",
			"PM_SELECTED" => (isset($_GET['action']) && $_GET['action'] == "email") ? "" : "CHECKED",
			"EMAIL_SELECTED" => (isset($_GET['action']) && $_GET['action'] == "email") ? "CHECKED" : "",
		));

		if($config['bbcode_enabled'] == true) {
			$page_master->setVar("BBCODE_EDITOR",
				renderBBCodeEditor()
			);
		}
		
		if($config['smilies_enabled'] == true) {
			$page_master->setVar("SMILIE_PICKER",
				renderSmiliePicker()
			);
		}

		outputPage($page_master);
		exit();
	}
}
else if($_GET['func'] == "delete")
{
	if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
		showMessage(ERR_CODE_INVALID_PM_ID);
	}
	
	$sql = $db2->query("SELECT *
		FROM `_PREFIX_pm`
		WHERE `pm_id` = :id
			AND (`pm_send_to` = :as_receiver || `pm_sent_from` = :as_sender)",
		array(":id" => $_GET['id'],
			":as_receiver" => $user['user_id'],
			":as_sender" => $user['user_id']));
			
	if($result = $sql->fetch()) {
		if($result['pm_type'] == "1") {
			if($result['pm_send_to'] == $user['user_id'] 
				&& $result['pm_sent_from'] == $user['user_id'])
			{
				$db2->query("DELETE FROM `_PREFIX_pm`
					WHERE `pm_id` = :pm_id",
					array(":pm_id" => $_GET['id']));
				
				showMessage(ERR_CODE_PM_DELETED, "pm.php");
			} else if($result['pm_send_to'] == $user['user_id']) {
				$db2->query("UPDATE `_PREFIX_pm`
					SET `pm_type` = '3'
					WHERE `pm_id` = :pm_id",
					array(":pm_id" => $_GET['id']));
					
				showMessage(ERR_CODE_PM_DELETED, "pm.php");
			} else if($result['pm_sent_from'] == $user['user_id']) {
				$db2->query("UPDATE `_PREFIX_pm`
					SET `pm_type` = '2'
					WHERE `pm_id` = :pm_id",
					array(":pm_id" => intval($_GET['id'])));
					
				showMessage(ERR_CODE_PM_DELETED, "pm.php");
			} else {
				showMessage(ERR_CODE_INVALID_PM_ID, "pm.php");
			}
		} else if($result['pm_type'] == "2") {
			if($result['pm_send_to'] == $user['user_id']) {
				$db2->query("DELETE FROM `_PREFIX_pm`
					WHERE `pm_id` = :pm_id",
					array(":pm_id" => $_GET['id']));
				
				showMessage(ERR_CODE_PM_DELETED, "pm.php");
			} else {
				showMessage(ERR_CODE_INVALID_PM_ID, "pm.php");
 			}
		} else if($result['pm_type'] == "3") {
			if($result['pm_sent_from'] == $user['user_id']) {
				$db2->query("DELETE FROM `_PREFIX_pm`
					WHERE `pm_id` = :pm_id",
					array(":pm_id" => $_GET['id']));
				
				showMessage(ERR_CODE_PM_DELETED, "pm.php");
			} else {
				showMessage(ERR_CODE_INVALID_PM_ID, "pm.php");
			}
		}
	} else {
		showMessage(ERR_CODE_INVALID_PM_ID, "pm.php");
	}
}
else if($_GET['func'] == "edit")
{
	$sql = $db2->query("SELECT *
		FROM `_PREFIX_pm`
		WHERE `pm_id` = :pm_id AND
			`pm_sent_from` = :user_id AND
			`pm_type` = '1'",
		array(":pm_id" => $_GET['id'],
			":user_id" => $user['user_id']));
			
	if($result = $sql->fetch()) {
		$language->add_file("posting");
		Template::addNamespace("L", $lang);

       	if(!isset($_GET['id'])) {
			showMessage(ERR_CODE_INVALID_PM_ID, "pm.php");
		}
		
       	if(isset($_POST['Submit'])) {
			CSRF::validate();

       		$error = "";
       		if(strlen($_POST['title']) < 1 ) {
       			$error .= sprintf($lang['No_x_content'], strtolower($lang['Title'])) . "<br />";
       		}
       		
       		if(strlen($_POST['body']) < 1) {
       			$error .= $lang['No_Post_Content'] . "<br />";
       		}
       		
       		if(strlen($error) > 0) {
				$page_master = new Template("send_pm.tpl");

				$page_master->setVars(array(
       				"ACTION" => $lang['Edit_PM'],
       				"TITLE" => $_POST['title'],
       				"BODY" => $_POST['body'],
					"CSRF_TOKEN" => CSRF::getHTML()
       			));

				if($config['bbcode_enabled'] == true) {
					$page_master->setVar("BBCODE_EDITOR",
						renderBBCodeEditor());
				}
				
				if($config['smilies_enabled'] == true) {
					$page_master->setVar("SMILIE_PICKER",
						renderSmiliePicker());
				}
				
				$page_master->addToBlock("error", array(
					"ERRORS" => $error));

				outputPage($page_master);
				exit();
       		} else {
       			$db2->query("UPDATE `_PREFIX_pm`
					SET `pm_title` = :title,
					`pm_body` = :body
					WHERE `pm_id` = :pm_id",
					array(
						":title" => $_POST['title'],
						":body" => $_POST['body'],
						":pm_id" => $_GET['id']));
						
				showMessage(ERR_CODE_PM_EDITED, "pm.php");
       		}
       	} else {
       		$sql = $db2->query("SELECT *
				FROM `_PREFIX_pm`
				WHERE `pm_id` = :pm_id &&
					`pm_sent_from` = :sender &&
					`pm_type` = '1'",
				array(
					":pm_id" => $_GET['id'],
					":sender" => $user['user_id']));
					
       		if($result = $sql->fetch()) {
				$page_master = new Template("send_pm.tpl");

				$page_master->setVars(array(
       				"ACTION" => $lang['Edit_PM'],
       				"TITLE" => $result['pm_title'],
       				"BODY" => $result['pm_body'],
					"CSRF_TOKEN" => CSRF::getHTML()
       			));

				if($config['bbcode_enabled'] == true) {
					$page_master->setVar("BBCODE_EDITOR",
						renderBBCodeEditor());
				}
				
				if($config['smilies_enabled'] == true) {
					$page_master->setVar("SMILIE_PICKER",
						renderSmiliePicker());
				}

				outputPage($page_master);
				exit();
       		} else {
				showMessage(ERR_CODE_INVALID_PM_ID, "pm.php");
       		}
		}
	} else {
		showMessage(ERR_CODE_INVALID_PM_ID, "pm.php");
	}
}
else if(isset($_GET['id']) && $_GET['id'] > 0)
{
	$sql = $db2->query("SELECT pm.*,
		u.`user_id`,
		u.`username`,
		u.`user_avatar_type`,
		u.`user_avatar_location`,
		u.`user_rank`,
		u.`user_date_joined`,
		u.`user_signature`,
		u.`user_posts`,
		u.`user_location`,
		r.`rank_name`,
		r.`rank_image`
		FROM ((`_PREFIX_pm` pm
		LEFT JOIN `_PREFIX_users` u ON u.`user_id` = pm.`pm_sent_from`)
		LEFT JOIN `_PREFIX_ranks` r ON r.`rank_id` = u.`user_rank`)
		WHERE `pm_id` = :pm_id && (
			(`pm_send_to` = :as_receiver && (`pm_type`='1' || `pm_type`='2')) ||
			(`pm_sent_from` = :as_sender && (`pm_type`='1' || `pm_type`='3'))
		)
		LIMIT 1",
		array(
			":pm_id" => $_GET['id'],
			":as_receiver" => $user['user_id'],
			":as_sender" => $user['user_id']
		)
	);

	if($result = $sql->fetch()) {
		$page_master = new Template("view_pm.tpl");

		if(!empty($result['user_signature'])) {
			$result['user_signature'] = "<br /><br />\n----------<br />\n".format_text($result['user_signature']);
		}

		$page_master->setVars(array(
			"AUTHOR_USERNAME" => $result['username'],
			"AUTHOR_RANK" => ($result['user_id'] > 0) ? $result['rank_name'] : "",
			"AUTHOR_SIGNATURE" => $result['user_signature'],
			"TITLE" => $result['pm_title'],
			"BODY" => format_text($result['pm_body']),
			"DATE" => create_date("D d M Y g:i a", $result['pm_date'])
		));

		if($result['user_id'] > 0) {
			$page_master->addToBlock("author_standard", array(
				"AUTHOR_JOINED" => create_date("D d M Y", $result['user_date_joined']),
				"AUTHOR_POSTS" => $result['user_posts']
			));

			if(!empty($result['user_location'])) {
				$page_master->addToBlock("author_location", array(
					"AUTHOR_LOCATION" => $result['user_location']
				));
			}
		}

		if(!empty($result['rank_image'])) {
			$page_master->addToBlock("rank_image", array(
				"AUTHOR_RANK" => $result['rank_name'],
				"AUTHOR_RANK_IMG" => $result['rank_image']
			));
		}

		if($result['user_avatar_type'] == UPLOADED_AVATAR || $result['user_avatar_type'] == REMOTE_AVATAR) {
			if($result['user_avatar_type'] == UPLOADED_AVATAR) {
				$result['user_avatar_location'] = $root_path . $config['avatar_upload_dir'] . "/" . $result['user_avatar_location'];
			}

			$page_master->addToBlock("avatar", array(
				"AUTHOR_AVATAR_LOCATION" => $result['user_avatar_location']
			));
		}

		if($result['pm_unread'] == "1") {
			if(!($result['pm_send_to'] != $user['user_id'] && $result['pm_sent_from'] == $user['user_id'])) {
				$db2->query("UPDATE `_PREFIX_pm`
					SET `pm_unread` = '0'
					WHERE `pm_id` = :pm_id",
					array(
						":pm_id" => $_GET['id']
					)
				);
			}
		}

		outputPage($page_master);
		exit();
	} else {
		showMessage(ERR_CODE_INVALID_PM_ID, "pm.php");
	}
}
else
{
	$page_master = new Template("manage_pm.tpl");

	if($_GET['func'] == "sentbox") {
		$where_query = "WHERE `pm_sent_from` = :user_id && (`pm_type` = '1' || `pm_type` = '3') && `pm_unread` = '1'";
	} else if($_GET['func'] == "outbox") {
		$where_query = "WHERE `pm_sent_from` = :user_id && (`pm_type` = '1' || `pm_type` = '3') && `pm_unread` = '0'";
	} else {
		$where_query = "WHERE `pm_send_to` = :user_id && (`pm_type` = '1' || `pm_type` = '2')";
	}

	$count_sql = $db2->query("SELECT count(`pm_id`) AS `pm_count`
		FROM `_PREFIX_pm` ".$where_query."",
		array(":user_id" => $user['user_id']));
		
	$count_array = $count_sql->fetch();
	$pagination = $pp->paginate($count_array['pm_count'], $config['pm_per_page']);
	
	$page_master->setVar("PAGINATION", $pagination);

	$pm_query = $db2->query("SELECT pm.*,
		u.`username`
		FROM (`_PREFIX_pm` pm
			LEFT JOIN `_PREFIX_users` u ON u.`user_id` = pm.`pm_sent_from`)
		$where_query
		ORDER BY pm.`pm_date` DESC
		LIMIT ".$pp->limit."",
		array(
			":user_id" => $user['user_id']
		)
	);

	$pm_rows = "";
	$pm_count = 0;
	while($pm = $pm_query->fetch()) {
		$read_indicator = "";
		if($pm['pm_unread'] == 1) {
			$read_indicator = $page_master->renderBlock("unread_pm", array());
		} else {
			$read_indicator = $page_master->renderBlock("read_pm", array());
		}

		$edit_button = "";
		if($_GET['func'] == "outbox") {
			$edit_button = $page_master->renderBlock("edit_pm", array(
				"ID" => $pm['pm_id']
			));
		}

		$pm_rows .= $page_master->renderBlock("pm_row", array(
			"ID" => $pm['pm_id'],
			"NAME" => $pm['pm_title'],
			"AUTHOR" => $pm['username'],
			"DATE" => create_date("D d M Y", $pm['pm_date']),
			"READ_INDICATOR"  => $read_indicator,
			"EDIT_BUTTON" => $edit_button
		));
		$pm_count++;
	}

	if($_GET['func'] == "sentbox") {
		$location = strtolower($lang['Sent_Box']);
		$page_master->setVar("LOCATION", $lang['Sent_Box']);
	} else if($_GET['func'] == "outbox") {
		$location = strtolower($lang['Outbox']);
		$page_master->setVar("LOCATION", $lang['Outbox']);
	} else {
		$location = strtolower($lang['Inbox']);
		$page_master->setVar("LOCATION", $lang['Inbox']);
	}
	
	if($pm_count > 0) {
		$page_master->setVar("PM_PANEL_CONTENT",
			$page_master->renderBlock("pms_table", array(
				"PM_ROWS" => $pm_rows
			)));
	} else {
		$page_master->setVar("PM_PANEL_CONTENT",
			$page_master->renderBlock("no_pms", array(
				"NO_PM" => sprintf($lang['You_currently_have_no_PMs_in_your'], $location)
			)));
	}

	outputPage($page_master);
	exit();
}
?>
