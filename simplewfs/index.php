<?php
require 'SimpleWfsApi.php';
$simpleWfsApi = new SimpleWfsApi();
$rawPost = file_get_contents("php://input");
$simpleWfsApi->apiRequest->setGetVar($_GET);
$simpleWfsApi->apiRequest->setRawPostVar($rawPost);
$simpleWfsApi->readRequest();
$simpleWfsApi->sendResponse();
