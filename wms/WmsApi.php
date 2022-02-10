<?php
require __DIR__ . '/../Api.php';
class WmsApi extends Api
{
    private $request;
    private $layers;
    private $format;
    private $token;
    private $public;
    private $workspace;
    private $parameters;
    private $dataList;

    public function __construct()
    {
        parent::__construct();
        //$this->apiResponse->setFormat("application/json");
    }
    /**
     * Perform API logic based on the API Request
     */
    public function readRequest(): void
    {
        if (isset($this->apiRequest->getVar['format'])) {
            $this->format = $this->apiRequest->getVar['format'];
        } else if (isset($_GET['FORMAT'])) {
            $this->format = $this->apiRequest->getVar['FORMAT'];
        }
        if (isset($this->apiRequest->getVar['request'])) {
            $this->request = $this->apiRequest->getVar['request'];
        } else if (isset($this->apiRequest->getVar['REQUEST'])) {
            $this->request = $this->apiRequest->getVar['REQUEST'];
        }
        if (isset($this->apiRequest->getVar['FORMAT'])) {
            $this->format = $this->apiRequest->getVar['FORMAT'];
        } else if (isset($this->apiRequest->getVar['format'])) {
            $this->format = $this->apiRequest->getVar['format'];
        }
        if (isset($this->apiRequest->getVar['token'])) {
            if ($this->apiRequest->getVar['token'] == 'public' or $this->apiRequest->getVar['token'] == '') {
                $this->public = TRUE;
            } else {
                $this->public = FALSE;
            }
            $this->token = $this->apiRequest->getVar['token'];
        } elseif (isset($this->apiRequest->getVar['TOKEN'])) {
            if ($this->apiRequest->getVar['TOKEN'] == 'public' or $this->apiRequest->getVar['TOKEN'] == '') {
                $this->public = TRUE;
            } else {
                $this->public = FALSE;
            }
            $this->token = $this->apiRequest->getVar['TOKEN'];
        } else {
            $this->public = TRUE;
        }
        if (isset($this->apiRequest->getVar['query_layers'])) {
            $this->queryLayers = $this->apiRequest->getVar['query_layers'];
        } elseif (isset($this->apiRequest->getVar['QUERY_LAYERS'])) {
            $this->queryLayers = $this->apiRequest->getVar['QUERY_LAYERS'];
        } else {
            $this->queryLayers = NULL;
        }
        if (isset($this->apiRequest->getVar['layers'])) {
            $this->layers = $this->apiRequest->getVar['layers'];
        } elseif (isset($this->apiRequest->getVar['LAYERS'])) {
            $this->layers = $this->apiRequest->getVar['LAYERS'];
        } else {
            if (isset($this->apiRequest->getVar['layer'])) {
                $this->layers = $this->apiRequest->getVar['layer'];
            } elseif (isset($this->apiRequest->getVar['LAYER'])) {
                $this->layers = $this->apiRequest->getVar['LAYER'];
            } else {
                $this->layers = NULL;
            }
        }
    }
    /**
     * Generate API Response
     */
    public function generateResponse(): void
    {
        if ($this->public == FALSE) {
            $this->user->setToken($this->token);
            $this->user->getUserFromToken();
            //$this->user->checkToken();
            $this->dataList = $this->user->getDataList();
        } else {
            $this->user->setToken($this->token);
            $this->user->getUserFromToken();
            $this->dataList = $this->user->getDataList($this->public);
        }

        $commaPos = strpos($this->layers, ",");
        $commaPosQuery = strpos($this->queryLayers, ",");

        if ($commaPos != FALSE) {
            $requestedDataArray = explode(",", $this->layers);
        } else {
            $requestedDataArray = array(0 => $this->layers);
        }
        if ($commaPosQuery != FALSE) {
            $requestedQueryDataArray = explode(",", $this->queryLayers);
        } else {
            $requestedQueryDataArray = array(0 => $this->queryLayers);
        }

        $finalRequestedDataArray = array();
        foreach ($requestedDataArray as $data) {
            $colonPos = strpos($data, ":");
            if ($colonPos != FALSE) {
                $this->workspace = $_ENV['geoserverWorkspacePrefix'] . substr($data, 0, $colonPos);
                array_push($finalRequestedDataArray, $data);
            } else {
                $this->workspace = $this->user->getWorkspace($this->dataList, substr($data, $colonPos), $_ENV['geoserverWorkspacePrefix']);
                $formattedString = $this->workspace . ":" . substr($data, $colonPos);
                array_push($finalRequestedDataArray, $formattedString);
            }
        }
        $finalRequestedQueryDataArray = array();
        if (strtolower($this->request) == "getfeatureinfo") {
            foreach ($requestedQueryDataArray as $queryData) {
                $colonPosQuery = strpos($queryData, ":");
                if ($colonPosQuery != FALSE) {
                    $workspaceQuery = $_ENV['geoserverWorkspacePrefix'] . substr($queryData, 0, $colonPosQuery);
                    array_push($finalRequestedQueryDataArray, $queryData);
                } else {
                    $workspaceQuery = $this->user->getWorkspace($this->dataList, substr($queryData, $colonPosQuery), $_ENV['geoserverWorkspacePrefix']);
                    $formattedQueryString = $workspaceQuery . ":" . substr($queryData, $colonPosQuery);
                    array_push($finalRequestedQueryDataArray, $formattedQueryString);
                }
            }
        } else {
            $finalRequestedQueryDataArray = NULL;
        }
        if (count($this->apiRequest->getVar) > 0) {
            $this->parameters = "";
            $count = 0;
            foreach ($this->apiRequest->getVar as $key => $value) {
                if ($count > 0) {
                    $this->parameters .= "&";
                }
                if (strtolower($key) == 'layers' or strtolower($key) == 'layer') {
                    $commaCount = 0;
                    $valueEdit = "";
                    foreach ($finalRequestedDataArray as $finalData) {
                        if ($commaCount == 0) {
                            $valueEdit .= $key . "=";
                        } elseif ($commaCount > 0) {
                            $valueEdit .= ",";
                        }
                        $valueEdit .= $finalData;
                        $commaCount += 1;
                    }
                    $this->parameters .= $valueEdit;
                } elseif (strtolower($key) == 'query_layers') {
                    $commaCountQuery = 0;
                    $valueEditQuery = "";
                    foreach ($finalRequestedQueryDataArray as $finalQueryData) {
                        if ($commaCountQuery == 0) {
                            $valueEditQuery .= $key . "=";
                        } elseif ($commaCountQuery > 0) {
                            $valueEditQuery .= ",";
                        }
                        $valueEditQuery .= $finalQueryData;
                        $commaCountQuery += 1;
                    }
                    $this->parameters .= $valueEditQuery;
                } else {
                    $this->parameters .= $key . "=" . urlencode($value);
                }
                $count += 1;
            }
        }
        $requestURL = "http://regionalroads.com:8080/geoserver/wms?";
        if (strtolower($this->request) == 'getcapabilities') {
            $requestURL = $requestURL . $this->parameters;
            $response = file_get_contents($requestURL);
            global $baseGeoserverURL, $baseAPIURL;
            $finalResponse = str_replace($baseGeoserverURL, $baseAPIURL, $response);
            if ($this->public == FALSE) {
                $replaceString = "/ows?token=" . $this->token . "&amp;";
                $finalResponse = str_replace("/ows?", $replaceString, $finalResponse);
            }
            $xml = simplexml_load_string($finalResponse);
            $a = $xml->Capability->Layer->Layer;
            $elementCount = 0;
            $toDelete = array();
            foreach ($xml->Capability->Layer->Layer as $key1 => $value1) {
                $dataArray = array($value1->Name);
                if ($this->user->dataAccess($this->dataList, $dataArray, $_ENV['geoserverWorkspacePrefix'], "wms") == FALSE) {
                    array_push($toDelete, $xml->Capability->Layer->Layer[$elementCount]);
                }
                $elementCount += 1;
            }
            foreach ($toDelete as $del) {
                $dom = dom_import_simplexml($del);
                $dom->parentNode->removeChild($dom);
            }
            $this->apiResponse->setFormat("application/xml");
            $this->apiResponse->setHttpCode(200);
            $this->apiRepsonse->setBody($xml->asXML());
        } else {
            $this->user->checkToken();
            if (
                $this->user->dataAccess($this->dataList, $finalRequestedDataArray, $_ENV['geoserverWorkspacePrefix'], "wms")
                and $this->user->tokenExpired == FALSE
            ) {
                $requestURL = $requestURL . $this->parameters;
                $response = file_get_contents($requestURL);
                $finalHeader = array_merge($http_response_header, $this->apiResponse->headers);
                /*foreach ($http_response_header as $key => $value) {
                    header($value);
                }*/
                $this->apiResponse->setHeaders($finalHeader);
                $this->apiResponse->setHttpCode(200);
                if (strtolower($this->request) == "getfeatureinfo") {
                    $this->apiResponse->setFormat("text/html");
                } else {
                    $this->apiResponse->setFormat($this->format);
                }
                $this->apiResponse->setBody($response);
            } else {
                $this->apiResponse->setHttpCode(401);
                $this->apiResponse->setFormat("application/json");
                $this->apiResponse->setBody('{"error": "You do not have access to the requested data"}');
            }
        }
    }
    /*function truncateRequestedData($requestedData)
        {
            $truncatedString = substr($requestedData, -4);
            if ($truncatedString == "_dev") {
                return substr($requestedData, 0, strlen($requestedData) - 4);
            } else {
                return $requestedData;
            }
        }

    private function subForeignKeyValues($foreignKeyJSON, $wmsResponse)
    {
        $startTag = "<table>";
        $endTag = "</table>";
        $startTagLength = strlen($startTag);
        $endTagLength = strlen($endTag);
        $wmsResponseLength = strlen($wmsResponse);
        $startTagPos = strpos($wmsResponse, $startTag);
        $endTagPos = strpos($wmsResponse, $endTag);
        $finalPos =  $endTagPos - $startTagPos + $endTagLength;
        $startResponse = substr($wmsResponse, 0, $startTagPos);
        $coreResponse = substr($wmsResponse, $startTagPos, $finalPos);
        $endResponse = substr($wmsResponse, $finalPos, $wmsResponseLength);
        $xml = simplexml_load_string($coreResponse);
        foreach ($xml->tr as $tr) {
            $subValue = FALSE;
            foreach ($tr as $td) {
                if ($subValue == TRUE) {
                    $subvalue = FALSE;
                }
                if (strpos($td, ":") !== FALSE) {
                }
                echo $td;
            }
        }
    }*/
}
