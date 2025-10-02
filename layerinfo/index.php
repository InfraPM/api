<?php
require '../../support/pass.php';
require 'LayerInfoApi.php';
$rawPost = file_get_contents("php://input");
$layerInfoApi = new LayerInfoApi();
$layerInfoApi->apiRequest->setRawPostVar($rawPost);
$layerInfoApi->apiRequest->setGetVar($_GET);
$layerInfoApi->processRequest();
$layerInfoApi->sendResponse();
