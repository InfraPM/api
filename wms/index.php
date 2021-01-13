<?php
require '../../support/pass.php';
require '../../support/dbcon.php';
require '../../support/user.php';
$originUrl = $baseURL . "regionalroads.com";
header('Access-Control-Allow-Origin: ' . $originUrl);
header("Access-Control-Allow-Credentials: true");
header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
header('Access-Control-Max-Age: 1000');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token , Authorization');
$parameters = "";
$rawPost=file_get_contents("php://input");
$postData = json_decode($rawPost, TRUE);
if (isset($_POST['format'])){
    header('Content-Type: text/html');
}
elseif (isset($_GET['format'])){
    header('Content-Type: text/html');
}
elseif (isset($_POST['FORMAT'])){
    header('Content-Type: text/html');
}
elseif (isset($_GET['FORMAT'])){
    header('Content-Type: text/html');
}
if (isset($_POST['request'])){
    $request = $_POST['request'];
}
elseif (isset($_GET['request'])){
    $request = $_GET['request'];
}
elseif (isset($_POST['REQUEST'])){
    $request = $_POST['REQUEST'];
}
elseif (isset($_GET['REQUEST'])){
    $request = $_GET['REQUEST'];
}
if (strtolower($request)=='getlegendgraphic'){
    if (isset($_GET['FORMAT'])){
        $format = $_GET['FORMAT'];
    }
    else if (isset($_GET['format'])){
        $format=$_GET['format'];
    }
    $headerString = 'Content-Type: ' . $format;
    header($headerString);
}
if (isset($postData['token'])){
    $token = $postData['token'];
    $public = FALSE;
}
elseif (isset($_GET['token'])){
    $token = $_GET['token'];
    $public = FALSE;
    }
elseif (isset($postData['TOKEN'])){
    $token = $postData['TOKEN'];
    $public = FALSE;
}
elseif (isset($_GET['TOKEN'])){
    $token = $_GET['TOKEN'];
    $public = FALSE;
}
else{
    $public = TRUE;
}
if (isset($_POST['query_layers'])){
    $queryLayers = $_POST['query_layers'];
}
elseif (isset($_GET['query_layers'])){
    $queryLayers = $_GET['query_layers'];    
}
elseif (isset($_GET['QUERY_LAYERS'])){
    $queryLayers = $_GET['QUERY_LAYERS'];
}
elseif (isset($_POST['QUERY_LAYERS'])){
    $queryLayers = $_POST['QUERY_LAYERS'];
}
else{
    $queryLayers = NULL;

}
if (isset($_POST['layers'])){
    $requestedData = $_POST['layers'];
}
elseif (isset($_GET['layers'])){
    $requestedData = $_GET['layers'];    
}
elseif (isset($_GET['LAYERS'])){
    $requestedData = $_GET['LAYERS'];
}
elseif (isset($_POST['LAYERS'])){
    $requestedData = $_POST['LAYERS'];
}
else{
    if (isset($_GET['layer'])){
        $requestedData = $_GET['layer'];
    }
    elseif(isset($_GET['LAYER'])){
        $requestedData = $_GET['LAYER'];
    }
    elseif (isset($_POST['layer'])){
        $requestedData = $_POST['layer'];
    }
    elseif(isset($_POST['LAYER'])){
        $requestedData = $_POST['LAYER'];
    }
    else{
        $requestedData = NULL;
    }
}

$dbCon = new dbcon($host, $port, $db, $dbuser, $dbpassword);
$user = new User();
$user->setDbCon($dbCon);
if ($public==FALSE){
    $user->token = $token;
    $user->getUserFromToken();
    $dataList = $user->getDataList();
}
else{
    $dataList = $user->getDataList($public);
}

$commaPos = strpos($requestedData, ",");
$commaPosQuery = strpos($queryLayers, ",");

if ($commaPos!=FALSE){
    $requestedDataArray = explode(",", $requestedData);
}
else{
    $requestedDataArray = array(0=>$requestedData);
}
if ($commaPosQuery!=FALSE){
    $requestedQueryDataArray = explode(",", $queryLayers);
}
else{
    $requestedQueryDataArray = array(0=>$queryLayers);
}

$finalRequestedDataArray = array();
foreach ($requestedDataArray as $data){
    $colonPos = strpos($data, ":");
    if ($colonPos!=FALSE){
        $workspace= $geoserverWorkspacePrefix . substr($data,0,$colonPos);
        array_push($finalRequestedDataArray, $data);
    }
    else{
        $workspace = getWorkspace($dataList, substr($data,$colonPos), $geoserverWorkspacePrefix);        
        $formattedString = $workspace.":".substr($data,$colonPos);
        array_push($finalRequestedDataArray, $formattedString);
    }
}
$finalRequestedQueryDataArray = array();
if (strtolower($request)=="getfeatureinfo"){
    foreach ($requestedQueryDataArray as $queryData){
        $colonPosQuery = strpos($queryData, ":");
        if ($colonPosQuery!=FALSE){
            $workspaceQuery = $geoserverWorkspacePrefix . substr($queryData,0,$colonPosQuery);
            array_push($finalRequestedQueryDataArray, $queryData);
        }
        else{
            $workspaceQuery = getWorkspace($dataList, substr($queryData,$coloPosQuery), $geoserverWorkspacePrefix);
            $formattedQueryString = $workspaceQuery.":".substr($queryData,$colonPosQuery);
            array_push($finalRequestedQueryDataArray, $formattedQueryString);
        }
    }
}
else{
    $finalRequestedQueryDataArray=NULL;
}

if (isset($_GET)){
    $count = 0;
    foreach($_GET as $key=>$value){
        if ($count>0){
            $parameters.="&";
        }
        if (strtolower($key)=='layers' OR strtolower($key)=='layer'){
            $commaCount = 0;
            $valueEdit = "";
            foreach($finalRequestedDataArray as $finalData){
                if($commaCount==0){
                    $valueEdit.=$key."=";
                }
                elseif($commaCount>0){
                    $valueEdit.=",";
                }
                $valueEdit.= $finalData;
                $commaCount+=1;
            }
            $parameters.=$valueEdit;
        }
        elseif (strtolower($key)=='query_layers'){            
            $commaCountQuery = 0;
            $valueEditQuery = "";
            foreach($finalRequestedQueryDataArray as $finalQueryData){
                if($commaCountQuery==0){
                    $valueEditQuery.=$key."=";
                }
                elseif($commaCountQuery>0){
                    $valueEditQuery.=",";
                }
                $valueEditQuery.= $finalQueryData;
                $commaCountQuery+=1;
            }
            $parameters.=$valueEditQuery;
        }
        else{
            $parameters.=$key."=".urlencode($value);
        }
        $count+=1;
    }
}
$requestURL = "http://regionalroads.com:8080/geoserver/wms?";
if (strtolower($request)=='getcapabilities'){
    $requestURL= $requestURL . $parameters;
    $response = file_get_contents($requestURL);
    global $baseGeoserverURL, $baseAPIURL;
    $finalResponse = str_replace($baseGeoserverURL,$baseAPIURL,$response);
    if ($public==FALSE){
        $replaceString = "/ows?token=".$token."&amp;";
        $finalResponse = str_replace("/ows?", $replaceString, $finalResponse);
    }
    $xml=simplexml_load_string($finalResponse);
    $a = $xml->Capability->Layer->Layer;
    $elementCount = 0;
    $toDelete = array();
    foreach ($xml->Capability->Layer->Layer as $key1=>$value1){
        $dataArray = array($value1->Name);
        if (dataAccess($dataList, $dataArray, $geoserverWorkspacePrefix)==FALSE){
            array_push($toDelete, $xml->Capability->Layer->Layer[$elementCount]);
        }
        $elementCount+=1;
    }
    foreach ($toDelete as $del){
        $dom=dom_import_simplexml($del);
        $dom->parentNode->removeChild($dom);        
    } 
    echo $xml->asXML();
    die();
}
else{
        if (dataAccess($dataList, $finalRequestedDataArray, $geoserverWorkspacePrefix)){
        $requestURL= $requestURL . $parameters;
        $response = file_get_contents($requestURL);
        foreach($http_response_header as $key=>$value){
            header($value);
        }
        echo $response;


    }
    else{
        http_response_code(401);
        header("Content-Type: application/json");
        echo '{"error": "You do not have access to the requested data}';
    }
}

function truncateRequestedData($requestedData){
    $truncatedString = substr($requestedData, -4);    
    if ($truncatedString=="_dev"){
        return substr($requestedData, 0, strlen($requestedData)-4);
    }
    else{
        return $requestedData;
    }
}

function subForeignKeyValues($foreignKeyJSON, $wmsResponse){
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
    foreach($xml->tr as $tr){
        $subValue = FALSE;
        foreach($tr as $td){
            if ($subValue==TRUE){
                $subvalue=FALSE;
            }
            if (strpos(td, ":")!== FALSE){
                
            }
            echo $td;
        }
                
    }
}

    function dataAccess($dataList, $requestedData, $geoserverWorkspacePrefix=""){
    $array= json_decode($dataList,true);
    $trueCount=0;
    foreach($requestedData as $data){
        foreach($array as $json){
            $fullDataName = $geoserverWorkspacePrefix . $json['workspace'] . ":" . $json['name'];
            if ($fullDataName==$data){
                $trueCount+=1;
            }
        }
    }
    if($trueCount==count($requestedData)){
        return TRUE;
    }
    else{
        return FALSE;
    }
}

        function getWorkspace($dataList, $requestedData, $geoserverWorkspacePrefix=""){
        $array= json_decode($dataList,true);
        foreach($array as $key=>$json){
            foreach($json as $key=>$value){
            if($key=="name"){
                if ($value==$requestedData){                         
                    return $geoserverWorkspacePrefix . $json['workspace'];
                }
            }
        }
    }
    return NULL;
}
?>
