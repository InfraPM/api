<?php
require '../../support/pass.php';
require 'MapOptionsApi.php';
$rawPost = file_get_contents("php://input");
$mapOptionsApi = new MapOptionsApi();
$mapOptionsApi->apiRequest->setRawPostVar($rawPost);
$mapOptionsApi->apiRequest->setGetVar($_GET);
$mapOptionsApi->readRequest();
$mapOptionsApi->sendResponse();
