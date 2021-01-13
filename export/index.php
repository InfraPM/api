<?php
require '../../support/pass.php';
require '../../support/dbcon.php';
require '../../support/user.php';
$originUrl = $baseURL . "regionalroads.com";
$dbCon = new dbcon($host, $port, $db, $dbuser, $dbpassword);
$rawPost=file_get_contents("php://input");
$postData = json_decode($rawPost, TRUE);
if (isset($postData['token'])){
    $token =  $postData['token'];
}
else{
    if (isset($_GET['token'])){
        $token = $_GET['token'];
    }
    else{
        http_response_code(400);
        echo '{"error": "Invalid parameters"}';
        die();
    }
}
if (isset($_GET['data'])){
    $datasets = $_GET['data'];
}
else{
    http_response_code(400);
    echo '{"error": "Invalid parameters"}';
    die();
}
$layerArray = explode(",", $datasets);
$user = new User();
$user->setDbCon($dbCon);
$user->token = $token;
$user->getUserFromToken();
$dataList = $user->getDataList(FALSE, "read");
$dataArray = array($dataset);
header('Access-Control-Allow-Origin: ' . $originUrl); 
header("Access-Control-Allow-Credentials: true");
header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
header('Access-Control-Max-Age: 1000');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token , Authorization');
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Type: text/html');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Type: csv');
$csvSql = "";
$layerCount = 0;
if ($user->dataAccess($dataList, $layerArray)!=TRUE){
    http_response_code(401);
    echo '{"error": "You do not have access to the requested data"}';
    die();
}
foreach($layerArray as $key=>$value){  
    $curTableName = getTableNameFromData($dataList, $value);
    if ($layerCount>0){
        $csvSql.=" UNION ALL ";
    }
    $csvSql.="SELECT * FROM " . $curTableName;
    $layerCount+=1;
    $dataName = formatFileName($value);
}

header('Content-Disposition: attachment; filename="'.$dataName.'.csv"');
$dbCon->query($csvSql);
$result = $dbCon->result;
$out = fopen('php://output', 'w');
$rowCount = 0;
$resultArray = pg_fetch_assoc($result,0);
//var_dump($resultArray);
while ($row = pg_fetch_assoc($result)){
    if ($rowCount==0){
        fputcsv($out,array_keys($resultArray));
    }
    $row['Shape']='';
    //var_dump($row);
    fputcsv($out, $row);
    $rowCount+=1;
}
fclose($out);

function dataAccess($dataList, $requestedData){
    $array= json_decode($dataList,true);
    $trueCount=0;
    foreach($requestedData as $data){
        foreach($array as $json){
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
function getTableNameFromData($dataList, $requestedData){
    $array= json_decode($dataList,true);
    foreach($array as $json){
        $fullDataName = $json['name'];
        if ($fullDataName==$requestedData){
            return '"' . $json['schemaname'] . '"' . "." . '"' . $json['tablename'] . '"';
        }
    }
}
function formatFileName($fileName){
    $removeString = array("_dev", "_Line", "_Point", "_Polygon", "_view");
    foreach($removeString as $i){
        $fileName = str_replace($i,"",$fileName);
    }
    return $fileName;
}
?>
