<?php
require 'CommentApi.php';

$commentApi = new CommentApi();
$commentApi->apiRequest->setGetVar($_GET);
$post = file_get_contents("php://input");
$commentApi->apiRequest->setRawPostVar($post);
$commentApi->readRequest();
$commentApi->sendResponse();
