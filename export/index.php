<?php
require __DIR__ . '/ExportApi.php';
$originUrl = $baseURL . "regionalroads.com";
$dbCon = new dbcon($_ENV['host'], $_ENV['port'], $_ENV['db'], $_ENV['dbuser'], $_ENV['dbpassword']);
$rawPost = file_get_contents("php://input");
$postData = json_decode($rawPost, TRUE);
$exportApi = new ExportApi();
$exportApi->apiRequest->setGetVar($_GET);
$exportApi->apiRequest->setRawPostVar($rawPost);
$exportApi->readRequest();
$exportApi->sendErrorResponse();
