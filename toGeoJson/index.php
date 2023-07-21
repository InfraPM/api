<?php
require 'ToGeoJsonApi.php';

$toGeoJsonApi = new ToGeoJsonApi();
$toGeoJsonApi->apiRequest->setGetVar($_GET);
$toGeoJsonApi->readRequest();
$toGeoJsonApi->generateResponse();
$toGeoJsonApi->sendResponse();
