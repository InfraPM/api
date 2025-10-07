<?php

require __DIR__ . '/../ows/OwsApi.php';

class WmsApi extends OwsApi
{
    private $layers;
    private $workspace;
    private $parameters;
    private $cqlfilter;
    private $queryLayers;

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
        parent::readRequest();

        if (isset($this->apiRequest->getVar['query_layers'])) {
            $this->queryLayers = $this->apiRequest->getVar['query_layers'];
        } else {
            $this->queryLayers = NULL;
        }
        if (isset($this->apiRequest->getVar['layers'])) {
            $this->layers = $this->apiRequest->getVar['layers'];
        } else {
            if (isset($this->apiRequest->getVar['layer'])) {
                $this->layers = $this->apiRequest->getVar['layer'];
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
        if (empty($this->request)) {
            $this->error(400, "Missing required parameter: request");
            return;
        }

        if ($this->public == FALSE) {
            $this->user->setToken($this->token);
            $this->user->getUserFromToken();
            $this->dataList = $this->user->getDataList();
        } else {
            $this->dataList = $this->user->getDataList($this->public);
        }

        $requestURL = $_ENV['baseGeoserverURL'] . "/wms?";
        $arrContextOptions = array(
            "http" => array(
                "ignore_errors" => true,
            ),
            "ssl" => array(
                "verify_peer" => false,
                "verify_peer_name" => false,
            ),
        );

        if (strtolower($this->request) == 'getcapabilities') {
            $this->handleGetCapabilities($requestURL, $arrContextOptions);
        } else {
            if (empty($this->layers)) {
                $this->error(400, "Missing required parameter: layers");
                return;
            }

            $commaPos = strpos($this->layers, ",");
            $commaPosQuery = $this->queryLayers != null ? strpos($this->queryLayers, ",") : FALSE;

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
                    } elseif (strtolower($key) == 'cql_filter') {
                        $this->cqlfilter = $value;
                    } else {
                        $this->parameters .= $key . "=" . urlencode($value);
                    }
                    $count += 1;
                }
            }

            $this->user->checkToken();
            if (
                $this->user->dataAccess($this->dataList, $finalRequestedDataArray, $_ENV['geoserverWorkspacePrefix'], "wms")
                && ($this->user->tokenExpired == FALSE || $this->public == TRUE)
            ) {

                if (!$this->updateCqlFilter($this->dataList, $requestedDataArray)) {
                    return;
                }
                if ($this->cqlfilter) {
                    $requestURL = $requestURL . $this->parameters . "&cql_filter=" . urlencode($this->cqlfilter);
                } else {
                    $requestURL = $requestURL . $this->parameters;
                }

                $response = file_get_contents($requestURL, false, stream_context_create($arrContextOptions));
                $finalHeader = array_merge($this->parseHeaders(http_get_last_response_headers()), $this->apiResponse->headers);
                $this->apiResponse->setHeaders($finalHeader);
                $this->apiResponse->setHttpCode(200);
                $this->apiResponse->setBody($response);
            } else {
                $this->error(401, "You do not have access to the requested data");
                return;
            }
        }
    }


    function updateCqlFilter($dataList, $requestDataArray)
    {

        //determine how many of the requested layers require agency filters
        $array = json_decode($dataList, TRUE);
        $agencycnt = 0;
        foreach ($requestDataArray as $data) {
            foreach ($array as $json) {
                if ($json['name'] == $data) {
                    if ($json['is_agency_secure'] == "t") {
                        $agencycnt += 1;
                    }
                }
            }
        }

        if ($agencycnt == 0) return true;
        if ($agencycnt != count($requestDataArray)) {
            //some layers require agency filter others don't
            //we don't support this
            $this->error(401, "Cannot combine layers that require agency validation with those that do not.");
            return false;
        }

        //add agency id to cql filter
        $ids = null;
        $hasall = false;
        foreach ($requestDataArray as $data) {
            $agencies = $this->user->getAgencies($data, "read");
            if ($hasall == true && $agencies != null) {
                $this->error(401, "Cannot combine layers that have difference agency access.");
                return;
            }
            if ($agencies == null) {
                $hasall = true;
            }

            if ($ids == null) {
                $ids = $agencies;
            } else if ($ids !== $agencies) {
                $this->error(401, "Cannot combine layers that have difference agency access.");
                return;
            }
        }

        if ($ids == null) {
            //has access to all data
            return true;
        }

        if ($this->cqlfilter == "") {
            $this->cqlfilter = "agency_id in (" . implode(",", $ids) . ")";
        } else {
            $this->cqlfilter = "(" . $this->cqlfilter . ") and agency_id in (" . implode(",", $ids) . ")";
        }
        return true;
    }

    private function handleGetCapabilities($requestURL, $arrContextOptions)
    {
        $requestURL = $requestURL . 'request=getcapabilities';
        $response = file_get_contents($requestURL, FALSE, stream_context_create($arrContextOptions));
        global $baseGeoserverURL, $baseAPIURL;

        $finalResponse = str_replace($baseGeoserverURL, $baseAPIURL, $response);
        if ($this->public == FALSE) {
            $replaceString = "/ows?token=" . $this->token . "&amp;";
            $finalResponse = str_replace("/ows?", $replaceString, $finalResponse);
        }
        $xml = simplexml_load_string($finalResponse);
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
        $this->apiResponse->setBody($xml->asXML());
    }

    function error($code, $message)
    {
        $this->apiResponse->setHttpCode($code);
        $this->apiResponse->setFormat("application/json");
        $this->apiResponse->setBody('{"error": "' . $message . '"}');
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
