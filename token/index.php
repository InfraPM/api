<?php
require 'TokenApi.php';

$tokenApi = new TokenApi();
$tokenApi->apiRequest->setGetVar($_GET);
$post = file_get_contents("php://input");
$tokenApi->apiRequest->setRawPostVar($post);
$tokenApi->readRequest();
$tokenApi->sendResponse();
