<?php
/**
 * Created by PhpStorm.
 * User: mcguffk
 * Date: 12/28/2016
 * Time: 11:45 AM
 */
include_once(dirname(dirname(dirname(dirname(__FILE__))))."/redcap_connect.php");

$searchTerms = $_GET['parameters'];

$sql = "SELECT p.project_id, p.app_title
		FROM redcap_projects p, redcap_user_rights u
		WHERE p.project_id = u.project_id
			AND u.username = '".db_real_escape_string(USERID)."'
			AND LOWER(p.app_title) LIKE '%".strtolower(db_real_escape_string($searchTerms))."%'";

$result = db_query($sql);

$matchingProjects = [];

while($row = db_fetch_assoc($result)) {
	$matchingProjects[] = ["id" => $row["project_id"], "text" => $row["app_title"]];
}

echo json_encode(["results" => $matchingProjects,"more" => false]);