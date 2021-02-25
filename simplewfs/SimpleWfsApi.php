<?php
require '../Api.php';
class SimpleWfsApi extends Api
{
    private $service;
    private $request;
    private $typeNames;
    private $featureId;
    private $outputFormat;
    private $spatialData;
    private $token;
    private $download;
    private $event;
    private $dataList;
    private $workspace;

    public function __construct()
    {
        parent::__construct();
    }
    /**
     * Structure APIRequest object
     */
    public function readRequest(): void
    {
        $error = FALSE;
        $this->outputFormat = "application/xml";
        $postData = new SimpleXMLElement($this->apiRequest->postVar);
        if (isset($postData->token)) {
            $this->token = $postData->token;
        } else if (isset($postData[0])) {
            $this->token = $postData[0];
        } else {
            $this->apiResponse->setHttpCode(401);
            $this->apiResponse->setFormat($this->outputFormat);
            $this->apiResponse->setBody('<?xml version="1.0" encoding="UTF-8"?>
       <WFSerror>
       <error>You do not have access to the specified data</error>
       </WFSerror>');
            $error = TRUE;
            $this->event = "WFS Request Error";
        }
        $this->user->setToken($this->token);
        $this->user->getUserFromToken();
        if (count($this->apiRequest->getVar) > 0) {
            if (isset($this->apiRequest->getVar['outputFormat'])) {
                $this->outputFormat = $_GET['outputFormat'];
            }
            if (isset($this->apiRequest->getVar['token'])) {
                $this->token = $this->apiRequest->getVar['token'];
            }
            if (isset($this->apiRequest->getVar['download'])) {
                if ($this->apiRequest->getVar['download'] == "true") {
                    $this->download = TRUE;
                } else {
                    $this->download = FALSE;
                }
            } else {
                $this->download = FALSE;
            }
            if (isset($_GET['typeNames'])) {
                $this->typeNames = $_GET['typeNames'];
            } else if (isset($_GET['typeName'])) {
                $this->typeNames = $_GET['typeName'];
            }
            if (isset($_GET['request'])) {
                $this->request = $_GET['request'];
            } else if (isset($_GET['REQUEST'])) {
                $this->request = $_GET['REQUEST'];
            } else {
                $this->request = NULL;
            }
            if ($this->request == 'DescribeFeatureType') {
                $this->dataList = $this->user->getDataList(FALSE, "read");
                $this->event = "WFS Describe Feature Request";
            } else if ($this->request == 'GetFeature') {
                $this->dataList = $this->user->getDataList(FALSE, "read");
                $this->event = "WFS Get Feature Request";
                $_SERVER['QUERY_STRING'] = str_replace('typeNames', 'typeName', $_SERVER['QUERY_STRING']);
            }
            $errorRequestBody = $_SERVER['REQUEST_URI'];
        } else {
            $this->typeNames = $this->getDataset();
            if (strpos($this->apiRequest->postVar, "wfs:Update") != FALSE) {
                $this->dataList = $this->user->getDataList(FALSE, "modify");
                $this->event = "WFS Update Feature Request";
            } else if (strpos($this->apiRequest->postVar, "wfs:Insert") != FALSE) {
                echo "insert";
                $this->dataList = $this->user->getDataList(FALSE, "insert");
                $this->event = "WFS Insert Feature Request";
            } else if (strpos($this->apiRequest->postVar, "wfs:Delete") != FALSE) {
                echo "delete";
                $this->dataList = $this->user->getDataList(FALSE, "delete");
                $this->event = "WFS Delete Feature Request";
            }
            $errorRequestBody = $this->apiRequest->postVar;
        }
        $this->workspace = $this->user->getWorkspace($this->dataList, $this->typeNames, $_ENV['geoserverWorkspacePrefix']);
        if ($this->user->dataAccess($this->dataList, array($this->typeNames)) == FALSE) {
            $this->apiResponse->setHttpCode(401);
            $this->apiResponse->setFormat($this->outputFormat);
            $this->apiResponse->setBody('<?xml version="1.0" encoding="UTF-8"?><WFSerror><error>You do not have access to the specified data</error></WFSerror>');
            $this->event = "WFS Request Error";
            $error = TRUE;
        }
        if ($error == FALSE) {
            $this->apiResponse->setFormat($this->outputFormat);
            $this->generateResponse();
        } else {
            //ar_dump($this->user);
            $this->user->logEvent($this->event, $errorRequestBody);
        }
    }
    /**
     * Generate API Response
     */
    public function generateResponse(): void
    {
        $wfsURL = "http://regionalroads.com:8080/geoserver/" . $this->workspace . "/wfs?";
        $user = $_ENV['wfsUser'];
        $password = $_ENV['wfsPassword'];
        $encoded = base64_encode($user . ":" . $password);
        if (count($_GET) == 0) {
            $opts = array(
                'http' =>
                array(
                    'method'  => 'POST',
                    'header'  => array(
                        'Content-Type: application/xml',
                        'Authorization: Basic ' . $encoded
                    ),
                    'content' => $this->apiRequest->postVar
                )
            );
            $context  = stream_context_create($opts);
            $response = file_get_contents($wfsURL, false, $context);
            $requestBody = $this->apiRequest->postVar;
        } else {
            $opts = array(
                'http' =>
                array(
                    'method'  => 'GET',
                    'header'  => array(
                        'Content-Type: application/xml',
                        'Authorization: Basic ' . $encoded
                    )
                )
            );
            $context  = stream_context_create($opts);
            $fullURL = $wfsURL . $this->removeToken($_SERVER['QUERY_STRING']);
            $response = file_get_contents($fullURL, false, $context);
            $requestBody = $_SERVER['REQUEST_URI'];
        }
        $headerArray = array();
        foreach ($http_response_header as $key => $value) {
            $curHeader = explode(": ", $value);
            foreach ($curHeader as $k => $v) {
                $headerArray[$k] = $v;
            }
        }
        $headerArray = array_merge($this->apiResponse->headers, $headerArray);
        $this->apiResponse->setHeaders(array());
        $this->apiResponse->setHeaders($headerArray);
        if ($this->outputFormat == "application/json" && $this->download == TRUE) {
            header('Content-Disposition: attachment; filename=' . $this->typeNames . '.json');
        }
        $this->apiResponse->setHttpCode(200);
        $this->apiResponse->setBody($response);
        $this->user->logEvent($this->event, $requestBody);
    }
    /**
     * Return the dataset name from the current WFST POST request
     */
    private function getDataset(): string
    {
        $mode = "Insert";
        $startTag = "<wfs:Insert>";
        $endTag = "</wfs:Insert>";
        $endTagLen = strlen($endTag);
        $startIndex = strpos($this->apiRequest->postVar, $startTag);
        $endIndex = strpos($this->apiRequest->postVar, $endTag) + $endTagLen;
        if ($startIndex == FALSE) {
            $mode = "Update";
            $startTag = "<wfs:Update";
            $endTag = "</wfs:Update>";
            $endTagLen = strlen($endTag);
            $startIndex = strpos($this->apiRequest->postVar, $startTag);
            $endIndex = strpos($this->apiRequest->postVar, $endTag) + $endTagLen;
        }
        if ($startIndex == FALSE) {
            $mode = "Delete";
            $startTag = "<wfs:Delete";
            $endTag = "</wfs:Delete>";
            $endTagLen = strlen($endTag);
            $startIndex = strpos($this->apiRequest->postVar, $startTag);
            $endIndex = strpos($this->apiRequest->postVar, $endTag) + $endTagLen;
        }
        $length = strlen($this->apiRequest->postVar) - $startIndex - (strlen($this->apiRequest->postVar) - $endIndex);
        $subString = substr($this->apiRequest->postVar, $startIndex, $length);
        $xml = simplexml_load_string($subString);
        $json = json_encode($xml);
        $array = json_decode($json, TRUE);
        if ($mode == "Insert") {
            reset($array);
            $dataset = key($array);
            $datasetName = trim($dataset, '<>');
        } else if ($mode == "Update") {
            $datasetName = $array["@attributes"]["typeName"];
        } else if ($mode == "Delete") {
            $datasetName = $array["@attributes"]["typeName"];
        }
        return $datasetName;
    }
    /**
     * Remove the token parameter from the URL
     * 
     * @param string $queryString The URL
     */
    private function removeToken(string $queryString): string
    {
        $explode = explode("&", $queryString);
        $returnQueryString = array();
        foreach ($explode as $key => $value) {
            if (strpos($value, "token") === FALSE) {
                array_push($returnQueryString, $value);
            }
        }
        return implode("&", $returnQueryString);
    }
}
