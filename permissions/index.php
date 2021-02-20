<?php
require 'PermissionsApi.php';
$rawPost = file_get_contents('php://input');
$permissionsApi = new PermissionsApi();
if (!empty($rawPost)) {
    $permissionsApi->apiRequest->setRawPostVar($rawPost);
    $permissionsApi->apiRequest->setGetVar($_GET);
    $permissionsApi->readRequest();
} else {
    $permissionsApi->apiResponse->setHttpCode(401);
    $permissionsApi->apiResponse->setBody('{"error": "Access Denied"}');
}
$permissionsApi->sendResponse();
