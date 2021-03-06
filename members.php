<?php
define("IN_IBB", 1);
$root_path = "./";
require_once($root_path . "includes/common.php");
$language->add_file("members");
$page_master = new Template("memberslist.tpl");

$member_count_query = $db2->query("SELECT count(`user_id`) AS 'member_count' FROM `_PREFIX_users`");

$member_count_result = $member_count_query->fetch();
$member_count = (intval($member_count_result['member_count']) - 1);

$pagination = $pp->paginate($member_count, $config['members_per_page']);

$page_master->setVars(array(
	"PAGINATION" => $pagination
));

$sql = $db2->query("SELECT *
	FROM `_PREFIX_users`
	WHERE `user_id` > '0'
	ORDER BY `user_id` ASC
	LIMIT " . $pp->limit . ""
);

while($result = $sql->fetch())  {
	if($result['user_level'] <= 1 || $result['user_rank'] <= 0) {
		continue;
	}
	
    $membername = '';
    $membername = format_membername($result['user_rank'],$result['user_id'],$result['username']);
	$page_master->addToBlock("member", array(
		"ID" => $result['user_id'],
		"USERNAME" => $membername,
		"USER" => $result['username'],
		"POSTS" => $result['user_posts'],
		"DATE_JOINED" => create_date("D d M Y", $result['user_date_joined'])
	));
}

$page_title = $config['site_name'] . " &raquo; " . $lang['Members_List'];
outputPage($page_master, $page_title);
exit();
?>
