<?php
include_once(dirname(dirname(dirname(dirname(__FILE__))))."/redcap_connect.php");

$projectId = $_GET['project_id'];

if($projectId == "") die();

$sql = "SELECT m.field_name, m.element_label
		FROM redcap_metadata m, redcap_projects p, redcap_user_rights u
		WHERE p.project_id = ".db_real_escape_string($projectId)."
			AND p.project_id = u.project_id
			AND m.project_id = p.project_id
			AND u.username = '".db_real_escape_string(USERID)."'
			AND (u.expiration IS NULL or u.expiration > ".date('Ymdhis').")";

$q = db_query($sql);

if(db_error()) {
	die();
}

$fieldList = [];
while($row = db_fetch_assoc($q)) {
	$fieldList[$row['field_name']] = ["label" => $row['element_label']];
}

echo json_encode($fieldList);