<?php
namespace ExternalModules;

require_once '../../classes/ExternalModules.php';

header('Content-type: application/json');

$searchTerms = $_GET['parameters'];

$matchingProjects = ExternalModules::getAdditionalFieldChoices(["type" => "project-id"], $searchTerms);

echo json_encode(["results" => $matchingProjects["choices"],"more" => false]);