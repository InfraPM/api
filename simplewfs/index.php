<?php
require 'SimpleWfsApi.php';
$simpleWfsApi = new SimpleWfsApi();
if ((file_get_contents("php://input")) !== FALSE) {
    $rawPost = file_get_contents("php://input");
} else {
    $rawPost = '';
}
$simpleWfsApi->apiRequest->setGetVar(array_change_key_case($_GET, CASE_LOWER));
$simpleWfsApi->apiRequest->setRawPostVar($rawPost);
$simpleWfsApi->readRequest();
$simpleWfsApi->sendResponse();
