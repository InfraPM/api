<?php
require 'WmsApi.php';
$wmsApi = new WmsApi();
$wmsApi->apiRequest->setGetVar(array_change_key_case($_GET, CASE_LOWER));
$wmsApi->readRequest();
$wmsApi->generateResponse();
$wmsApi->sendResponse();
