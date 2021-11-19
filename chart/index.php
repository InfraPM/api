<?php
require 'ChartApi.php';

$chartApi = new ChartApi();
$chartApi->apiRequest->setGetVar($_GET);
$post = file_get_contents("php://input");
$chartApi->apiRequest->setRawPostVar($post);
$chartApi->readRequest();
$chartApi->sendResponse();
