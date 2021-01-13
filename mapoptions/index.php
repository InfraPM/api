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
$postData = json_decode($rawPost, TRUE);
$schemaName="gm";
$tableName="mapreference";
$dbCon = new dbcon($host, $port, $db, $dbuser, $dbpassword);
$user = new User();
$user->setDbCon($dbCon);
$referenceCriteria="";
$mapNameCriteria="";
$parameterCount = 0;
$criteriaArray = array();
if (isset($_GET['referenceId'])){
    $parameterCount+=1;
    $referenceId = $_GET['referenceId'];
    $referenceIdCriteria = <<<EOD
"id" = $parameterCount
EOD;
    $criteriaArray[$referenceId]= $referenceIdCriteria;
}
else if (isset($_GET['mapName'])){
    $parameterCount+=1;
    $mapName = $_GET['mapName'];
    $mapNameCriteria = <<<EOD
"name"= $$parameterCount
EOD;
    $criteriaArray[$mapName]=$mapNameCriteria;
}
else{
    http_response_code(401);
    echo '{"error":"Invalid parameters"}';
    die();
}
$publicCriteria="";
if (isset($postData['token'])){
    $parameterCount+=1;
    $token = $postData['token'];
    $user->token = $token;
    $user->getUserFromToken();
    $user->validate();
    if ($user->exists()==FALSE){
        $publicValue = 't';
        $publicCriteria=<<<EOD
"public"=$$parameterCount;
EOD;
        $criteriaArray[$publicValue]=$publicCriteria;
    }
}
else{
    $publicValue = 't';
    $criteriaArray[$publicValue]=$publicCriteria;
}

$sql = <<<EOD
SELECT "options", "displayname" FROM "$schemaName"."$tableName"
EOD;
$criteriaCount = 0;
$parameters = array();
foreach($criteriaArray as $key=>$value){
    if ($criteriaCount==0){
        $sql.=" WHERE ";
    }
    else if ($criteriaCount>0){
        $sql.= " AND ";
    }
    $sql.=$value;
    array_push($parameters, $key);
    $criteriaCount+=1;
}
$dbCon->query($sql, $parameters);
$result = $dbCon->result;
while($row=pg_fetch_assoc($result)){
    $returnJson = $row['options'];
}
echo $returnJson;

?>
