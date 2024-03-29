<?php
require __DIR__ . '/../Api.php';
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
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            return;
        }
        $error = FALSE;
        $this->outputFormat = "application/xml";
        if ($this->apiRequest->postVar != '') {
            $postData = new SimpleXMLElement($this->apiRequest->postVar);
            if (isset($postData->token)) {
                $this->token = $postData->token;
            } else if (isset($postData[0])) {
                $this->token = $postData[0];
            } else {
                $this->apiResponse->setHttpCode(400);
                $this->apiResponse->setFormat($this->outputFormat);
                $this->apiResponse->setBody('<?xml version="1.0" encoding="UTF-8"?>
       <WFSerror>
       <error>You do not have access to the specified data</error>
       </WFSerror>');
                $error = TRUE;
                $this->event = "WFS Request Error";
            }
        }
        if ($this->token != NULL) {
            $this->user->setToken($this->token);
            $this->user->getUserFromToken();
            $this->user->checkToken();
        }
        if (count($this->apiRequest->getVar) > 0) {
            if (isset($this->apiRequest->getVar['outputFormat'])) {
                $this->outputFormat = $this->apiRequest->getVar['outputFormat'];
            }
            if (isset($this->apiRequest->getVar['token'])) {
                $this->token = $this->apiRequest->getVar['token'];
                $this->user->setToken($this->token);
                $this->user->getUserFromToken();
                $this->user->checkToken();
            } //else {
            //$this->token = '';
            //}
            if (isset($this->apiRequest->getVar['download'])) {
                if ($this->apiRequest->getVar['download'] == "true") {
                    $this->download = TRUE;
                } else {
                    $this->download = FALSE;
                }
            } else {
                $this->download = FALSE;
            }
            if (isset($this->apiRequest->getVar['typeNames'])) {
                $this->typeNames = $this->apiRequest->getVar['typeNames'];
            } else if (isset($this->apiRequest->getVar['typeName'])) {
                $this->typeNames = $this->apiRequest->getVar['typeName'];
            }
            if (isset($this->apiRequest->getVar['request'])) {
                $this->request = $this->apiRequest->getVar['request'];
            } else if (isset($this->apiRequest->getVar['REQUEST'])) {
                $this->request = $this->apiRequest->getVar['REQUEST'];
            } else {
                $this->request = NULL;
            }
            if ($this->token == NULL) {
                $this->token = '';
            }
            $this->user->setToken($this->token);
            $this->user->getUserFromToken();
            $this->user->checkToken();
            if ($this->user->tokenExpired) {
                $this->apiResponse->setHttpCode(400);
                $this->apiResponse->setFormat($this->outputFormat);
                $this->apiResponse->setBody('<?xml version="1.0" encoding="UTF-8"?>
       <WFSerror>
       <error>You do not have access to the specified data</error>
       </WFSerror>');
                $error = TRUE;
                $this->event = "WFS Request Error";
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
                $this->dataList = $this->user->getDataList(FALSE, "insert");
                $this->event = "WFS Insert Feature Request";
            } else if (strpos($this->apiRequest->postVar, "wfs:Delete") != FALSE) {
                $this->dataList = $this->user->getDataList(FALSE, "delete");
                $this->event = "WFS Delete Feature Request";
            }
            $errorRequestBody = $this->apiRequest->postVar;
        }
        $this->workspace = $this->user->getWorkspace($this->dataList, $this->typeNames, $_ENV['geoserverWorkspacePrefix']);
        if ($this->user->dataAccess($this->dataList, array($this->typeNames), "", "wfs") == FALSE) {
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
            $this->apiResponse->setHttpCode(401);
            $this->apiResponse->setFormat($this->outputFormat);
            $this->apiResponse->setBody('<?xml version="1.0" encoding="UTF-8"?><WFSerror><error>You do not have access to the specified data</error></WFSerror>');
            $this->event = "WFS Request Error";
            $this->user->logEvent($this->event, $errorRequestBody);
        }
    }
    /**
     * Generate API Response
     */
    public function generateResponse(): void
    {
        $wfsURL = $_ENV['baseGeoserverURL'] . "/" . $this->workspace . "/wfs?";
        $user = $_ENV['wfsUser'];
        $password = $_ENV['wfsPassword'];
        $encoded = base64_encode($user . ":" . $password);
        if (count($this->apiRequest->getVar) == 0) {
            //update post body filter to validate
            //for agencies if required
            $error = $this->addAgencyFiltersToPostBody($this->dataList, $this->typeNames);
            if ($error){
                $this->apiResponse->setHttpCode(401);
                $this->apiResponse->setFormat($this->outputFormat);
                $this->apiResponse->setBody('<?xml version="1.0" encoding="UTF-8"?><WFSerror><error>' . $error . '</error></WFSerror>');
                //$this->event = "WFS Request Error";
                //$this->user->logEvent($this->event, $error);
                return;
            }

            $opts = array(
                'http' =>
                array(
                    'method'  => 'POST',
                    'header'  => array(
                        'Content-Type: application/xml',
                        'Authorization: Basic ' . $encoded
                    ),
                    'content' => $this->apiRequest->postVar
                ),
                "ssl" => array(
                    "verify_peer" => false,
                    "verify_peer_name" => false,
                )

            );
            //var_dump($wfsURL);
            //var_dump($this->apiRequest->postVar);
            //die();
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
                ),
                "ssl" => array(
                    "verify_peer" => false,
                    "verify_peer_name" => false,
                ),
            );

            $querystr = $this->removeToken($_SERVER['QUERY_STRING']);
            //currently typeNames is a string and doesn't support multiple type names
            $querystr = $this->updateCqlFilter($querystr, $this->dataList, $this->typeNames);
            $context  = stream_context_create($opts);
            $fullURL = $wfsURL . $querystr;    
            
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
        } else if ($this->outputFormat == "application/vnd.google-earth.kml xml" && $this->download == TRUE) {
            header('Content-Disposition: attachment; filename=' . $this->typeNames . '.kml');
        } else if ($this->outputFormat == "shape-zip" && $this->download == TRUE) {
            header('Content-Disposition: attachment; filename=' . $this->typeNames . '.zip');
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
        $xml = simplexml_load_string($subString, SimpleXMLElement::class, LIBXML_NOWARNING | LIBXML_NOERROR );
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
     * Function parses a WFS POST body for Insert, Update, Delete
     * actions and either validates the user has access to the 
     * agency_id for the Insert features, and adds an agency_id
     * filter to Update and Delete actions so users can only
     * update agencies they have permission to access.
     */
    private function addAgencyFiltersToPostBody(string $dataList, string $typeName) 
    {
        //agency ids
        $xml = new DOMDocument();
        $xml->loadXML($this->apiRequest->postVar);

        //determine version as this affects the 
        //filter V1.X requires PropertyName for filter
        //V2.X requires ValueReference
        $wfsversion = "1.0.0";
        if ($xml->firstChild->hasAttribute("version")){
            $wfsversion = $xml->firstChild->getAttribute("version");
        }
        
        if (count($xml->getElementsByTagName('Replace')) > 0){
            return "Replace not supported.";
        }

        $actioncnt = count($xml->getElementsByTagName('Insert'))  +
                    count($xml->getElementsByTagName('Delete')) +
                    count($xml->getElementsByTagName('Update')) ;
        if ($actioncnt > 1){
            return "Multiple actions are not supported in a single transaction";
        }

        //INSERT
        //first find agencies the user is allowed to insert into
        $ids = $this->getAllowedAgencies($dataList, $typeName, 'insert');        
        if ($ids != null){
            //validate insert statements
            //they require an agency and access to agency
            foreach($xml->getElementsByTagName('Insert') as $insertRequest) {
                
                $hasagency = false;
                foreach($insertRequest->childNodes as $insert){
                    foreach($insert->childNodes as $attribute){
                        if ($attribute->nodeName == "agency_id"){
                            if ($hasagency){
                                return "Mulitple agency_id's specified for feature.";
                            }
                            $hasagency = true;
                            if (!in_array($attribute->textContent, $ids)){
                                return "You do not have permissions to add data to given agency.";
                            }
                        }
                    }
                }
                if (!$hasagency){               
                    return "No agency_id specified for feature.";
                }
            }
        }
        
        //DELETE AND UPDATE
        //For these we add to the filter to include
        //the valid agency_ids. This will prevent
        //updating of features it shouldn't update
        $ops = array("Delete", "Update");        
        foreach($ops as $op){
            $items = $xml->getElementsByTagName($op);
            if (count($items) == 0) continue;
            
            $perm = 'delete';
            if ($op == "Update") $perm='modify';

            $ids = $this->getAllowedAgencies($dataList, $typeName, $perm);
            if ($ids != null){
                foreach($items as $deleteRequest) {
                    $filters = $deleteRequest->getElementsByTagName('Filter');
                    if (count($filters) == 0){
                        //Just don't allow this for now and it's not necessary.
                        //If we do need to support this we need to create Filter 
                        //tag with proper namespace references
                        return "Update/Delete not supported without a filter";
                    }else{
                        //update filter
                        foreach($filters as $filter){
                            $currentFilter = $deleteRequest->removeChild($filter);
                            $and = $this->createElement($xml, $filter, "And");
                            while ($currentFilter->hasChildNodes()) {
                                $domNode = $currentFilter->removeChild($currentFilter->firstChild);
                                $and->appendChild($domNode);
                            }

                            //ValueReference = v2.0
                            //PropertyName=v1
                            if (count($ids) === 1){
                                $and->appendChild($this->createAgencyFilter($xml, $filter, $ids[0], $wfsversion));
                            }else{
                                $or = $this->createElement($xml, $filter, "Or"); 
                                foreach($ids as $id){
                                    $or->appendChild($this->createAgencyFilter($xml, $filter, $id, $wfsversion));
                                }
                                $and->appendChild($or);
                            }                
                            $currentFilter->appendChild($and);
                            $deleteRequest->appendChild($currentFilter);
                        }
                    }
                }
            }
        }
        //var_dump($xml->saveXml());
        //die();
        $this->apiRequest->setRawPostVar($xml->saveXml());
        return null;
    }

    /**
     * creates an xml agency filter for post update/delete requests
     * @param xml -xml document
     * @param filter  filter node to grab namespace from
     * @param agencyid  agency_id to filter
     */
    private function createAgencyFilter($xml, $filter, $agencyid, $wfsversion){
        $pet = $this->createElement($xml, $filter, "PropertyIsEqualTo"); 
        if ($wfsversion == "1.0.0" || $wfsversion == "1.1.0"){
            $pn = $this->createElement($xml, $filter, "PropertyName");
        }else{
            $pn = $this->createElement($xml, $filter, "ValueReference");
        }
        
        $pn->nodeValue = "agency_id";

        $lit = $this->createElement($xml, $filter, "Literal");
        $lit->nodeValue = $agencyid;

        $pet->appendChild($pn);
        $pet->appendChild($lit);
        return $pet;
    }
    /**
     * creates an xml element with same namespace as parent
     * @param xml xml document
     * @param parentNode parent node to get namespace from
     * @param element element name to create
     */
    private function createElement($xml, $parentNode, $element){
        if ($parentNode != null && $parentNode->prefix != null && $parentNode->prefix != ''){
            return $xml->createElement("$parentNode->prefix:$element");
        }else{
            return $xml->createElement($element);
        }
        
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

    /**
     * For GET requests updates the cql filter (or replaces it with a FILTER)
     * to prevent access to non agency rescources
     */
    private function updateCqlFilter(string $queryString, string $dataList, string $typeName): string
    {
        $ids = $this->getAllowedAgencies($dataList, $typeName, 'read');
        //nothing to update
        if ($ids == null) return $queryString;

        //parse query string
        //if it has a cql filter than update the cql filter to include agency ids
        //if it has a featureid filter then convert that to <Filter> and add
        //agency_id if it has neither add cql filter that includes agency ids
        $explode = explode("&", $queryString);
        $returnQueryString = array();
        
        $hasfilter = false;
        foreach ($explode as $key => $value) {
            if (strpos(strtolower($value), "cql_filter=") !== FALSE) {
                $part = urldecode(substr($value, strlen("cql_filter=")));

                $afilter = "agency_id in (" . implode(",", $ids) . ")";

                $newpart = "(" . $part . ") and " . $afilter;
                array_push($returnQueryString, "cql_filter=" . urlencode($newpart));
                $hasfilter = true;
            }elseif (strpos(strtolower($value), "featureid=") !== FALSE){
                $part = urldecode(substr($value, strlen("featureid=")));
                 
                //Convert to <Filter> 
                $newpart = "<Filter><And>";

                $fids = explode(",", $part);
                if (count($fids) == 1){
                    $newpart .= "<FeatureId fid=\"" . $fids[0] . "\"/>";
                }else{
                    $newpart .= "<Or>";
                    foreach($fids as $fid){
                        $newpart .= "<FeatureId fid=\"" . $fid. "\"/>";
                    }
                    $newpart .= "</Or>";
                }
                
                if (count($ids) == 1){
                    $newpart .= "<PropertyIsEqualTo><PropertyName>agency_id</PropertyName><Literal>" . $ids[0] . "</Literal></PropertyIsEqualTo>";
                }else{
                    $newpart .= "<Or>";
                    foreach($ids as $id){
                        $newpart .= "<PropertyIsEqualTo><PropertyName>agency_id</PropertyName><Literal>" . $id . "</Literal></PropertyIsEqualTo>";
                    }

                    $newpart .= "</Or>";
                }
                $newpart .= "</And></Filter>";
                array_push($returnQueryString, "Filter=" . urlencode($newpart));
                $hasfilter = true;
            }else{
                array_push($returnQueryString, $value);
            }
        }

        if (!$hasfilter){
            $afilter = "agency_id in (" . implode(",", $ids) . ")";
            array_push($returnQueryString, "cql_filter=" . urlencode($afilter));
        }

        return implode("&", $returnQueryString);
    }

    function getAllowedAgencies(string $dataList, string $typeName, string $mode){
        //only single value is supported for typeName at this time = not an array
        $array = json_decode($dataList, TRUE);
        $agencycnt = 0;
        
        $needsagency = false;
        foreach ($array as $json) {
            if ($json['name'] == $typeName) {
                if ($json['is_agency_secure'] == "t"){
                    $needsagency = true;
                    break;
                }
            }
        }

        if (!$needsagency){
            //have access to everything
            return null;
        } 

        //otherwise find the agency
        $ids = $this->user->getAgencies($typeName, $mode);
        if ($ids == null){
            //have access to everything
            return null;
        }else{
            return $ids;
            
        }
        
    }

}
