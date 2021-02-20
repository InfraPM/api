<?php
require 'ForeignKeysApi.php';

$rawPost = file_get_contents("php://input");
$foreignKeysApi = new ForeignKeysApi();
$foreignKeysApi->apiRequest->setGetVar($_GET);
$foreignKeysApi->apiRequest->setRawPostVar($rawPost);
$foreignKeysApi->readRequest();
$foreignKeysApi->sendResponse();
