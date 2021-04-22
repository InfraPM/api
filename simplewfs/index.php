<?php
require 'SimpleWfsApi.php';
$simpleWfsApi = new SimpleWfsApi();
if ((file_get_contents("php://input")) !== FALSE) {
    $rawPost = file_get_contents("php://input");
} else {
    $rawPost = '';
}
$simpleWfsApi->apiRequest->setGetVar($_GET);
$simpleWfsApi->apiRequest->setRawPostVar($rawPost);
$simpleWfsApi->readRequest();
$simpleWfsApi->sendResponse();
