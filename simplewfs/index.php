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
    $rawPost=file_get_contents("php://input");

if (strlen($rawPost)>0){
    //$postData = json_decode($rawPost, TRUE);
    $postData = new SimpleXMLElement($rawPost);
    if(isset($postData->token)){
        $token = $postData->token;
    }
    else if (isset($postData[0])){
        $token = $postData[0];
    }   
    else{
        echo '<?xml version="1.0" encoding="UTF-8"?>
   <WFSerror>
   <error>You do not have access to the specified data</error>
   </WFSerror>';
        die();
    }
}
else{
    if (isset($_GET['token'])){
        $token = $_GET['token'];
    }   
    else{
        echo '<?xml version="1.0" encoding="UTF-8"?>
   <WFSerror>
   <error>You do not have access to the specified data</error>
   </WFSerror>';
        die();
    }
}
if (isset($_GET['outputFormat'])){
    $outputFormat = $_GET['outputFormat'];
}
if (isset($_GET['download'])){
    if ($_GET['download']=="true"){
        $download=TRUE;
    }
    else{
        $download=FALSE;
    }
}
else{
    $download=FALSE;
}
$dbCon = new dbcon($host, $port, $db, $dbuser, $dbpassword);
$user = new User();
$user->setDbCon($dbCon);
$user->token = $token;
$user->getUserFromToken();
//if ($_SERVER['REQUEST_METHOD']=="GET"){
if (count($_GET)>0){    
    if (isset($_GET['typeNames'])){
        $data = $_GET['typeNames'];
    }
    else if (isset($_GET['typeName'])){
        $data = $_GET['typeName'];
    }
    if (isset($_GET['request'])){
        $request=$_GET['request'];
    }
    else if (isset($_GET['REQUEST'])){
        $request = $_GET['REQUEST'];
    }
    else{
        $request = NULL;
    }
    if ($request == 'DescribeFeatureType'){
        $dataList = $user->getDataList(FALSE, "read");
        $event = "WFS Describe Feature Request";
    }
    else if ($request =='GetFeature'){
        $dataList = $user->getDataList(FALSE, "read");
        $event = "WFS Get Feature Request";
        $_SERVER['QUERY_STRING'] = str_replace('typeNames', 'typeName', $_SERVER['QUERY_STRING']);
    }
}
//else if ($_SERVER['REQUEST_METHOD']=="POST"){
else{
	//$rawPost = file_get_contents("php://input");
	$data = getDataset($rawPost);
	$fId = getFid($rawPost);
	if (strpos($rawPost, "wfs:Update")!=FALSE){
	    $dataList = $user->getDataList(FALSE, "modify");
	    $event = "WFS Update Feature Request";
	}
	else if (strpos($rawPost,"wfs:Insert")!=FALSE){
	    $dataList = $user->getDataList(FALSE, "insert");
	    $event = "WFS Insert Feature Request";
	}
	else if (strpos($rawPost, "wfs:Delete")!=FALSE){
	    $dataList = $user->getDataList(FALSE, "delete");
	    $event = "WFS Delete Feature Request";
	}    
}
$requestedData = array($data);
$workspace = getWorkspace($dataList, $requestedData);
$workspace = $geoserverWorkspacePrefix . $workspace;
if(dataAccess($dataList, $requestedData)==FALSE){
	echo '<?xml version="1.0" encoding="UTF-8"?>
<WFSerror>
   <error>You do not have access to the specified data</error>
</WFSerror>';
	die();
}
$wfsURL = "http://regionalroads.com:8080/geoserver/".$workspace."/wfs?";
$user = $wfsUser;
$password = $wfsPassword;
$encoded = base64_encode($user .":".$password);
if (count($_GET)==0){
	$opts = array('http' =>
                  array(
                      'method'  => 'POST',
                      'header'  => array('Content-Type: application/xml',
                                         'Authorization: Basic ' . $encoded),
                      'content' => $rawPost
                  )
	);
	$context  = stream_context_create($opts);
	$response = file_get_contents($wfsURL, false, $context);
}
else{
	$opts = array('http' =>
                  array(
                      'method'  => 'GET',
                      'header'  => array('Content-Type: application/xml',
                                         'Authorization: Basic ' . $encoded)
                  )
	);
	$context  = stream_context_create($opts);
	$response = file_get_contents($wfsURL.$_SERVER['QUERY_STRING'],false,$context);
}
foreach($http_response_header as $key=>$value){
	header($value);
}
if ($outputFormat=="application/json" && $download==TRUE){
    header('Content-Disposition: attachment; filename=' . $data . '.json');
}
echo $response;

function dataAccess($dataList, $requestedData){
	#add support for workspace:dataset naming????
	$array= json_decode($dataList,true);
	#print_r($array);
	$trueCount=0;
	foreach($requestedData as $data){
	    foreach($array as $json){
            #$fullDataName = $json['workspace'] . ":" . $json['name'];
            $fullDataName = $json['name'];
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
function getWorkspace($dataList, $requestedData){
	#requestedData is converted to single value (first in array)
	$array= json_decode($dataList,true);
	foreach($array as $key=>$json){
	    foreach($json as $key=>$value){
            if($key=="name"){
                if ($value==$requestedData[0]){
                    return $json['workspace'];
                }
            }
	    }
	}
	return NULL;
}
function getDataset($rawPost){
	$mode="Insert";
	$startTag = "<wfs:Insert>";
	$endTag = "</wfs:Insert>";
	$endTagLen = strlen($endTag);
	$startIndex = strpos($rawPost, $startTag);
	$endIndex = strpos($rawPost, $endTag) + $endTagLen;
	if ($startIndex==FALSE){
	    $mode="Update";
	    $startTag = "<wfs:Update";
	    $endTag = "</wfs:Update>";
	    $endTagLen = strlen($endTag);
	    $startIndex = strpos($rawPost, $startTag);
	    $endIndex = strpos($rawPost, $endTag) + $endTagLen;
	}
	if ($startIndex==FALSE){
	    $mode="Delete";
	    $startTag = "<wfs:Delete";
	    $endTag = "</wfs:Delete>";
	    $endTagLen = strlen($endTag);
	    $startIndex = strpos($rawPost, $startTag);
	    $endIndex = strpos($rawPost, $endTag) + $endTagLen;
	}
	$length = strlen($rawPost)- $startIndex - (strlen($rawPost) - $endIndex);
	$subString = substr($rawPost, $startIndex, $length);    
	$xml = simplexml_load_string($subString);
	$json = json_encode($xml);
	$array = json_decode($json,TRUE);
	if ($mode=="Insert"){
	    reset($array);
	    $dataset = key($array);    
	    $datasetName = trim($dataset, '<>');
	}
	else if ($mode=="Update"){
	    $datasetName = $array["@attributes"]["typeName"];
	}
	else if ($mode=="Delete"){	
	    $datasetName = $array["@attributes"]["typeName"];
	}
	return $datasetName;
}
function getFid($rawPost){
	$startIndex = strpos($rawPost, '<FeatureId');
	$length = strlen($rawPost) - $startIndex;
	$subString = substr($rawPost, $startIndex, $length);
	$xml = simplexml_load_string($subString);
	$json = json_encode($xml);
	$array = json_decode($json,TRUE);   
}
?>
