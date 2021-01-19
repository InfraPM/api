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
header('Content-type: application/json');
$rawPost=file_get_contents("php://input");
$postData = json_decode($rawPost, TRUE);
if(isset($postData['token'])  && isset($_GET['table'])){
    $token = $postData['token'];
    $data = $_GET['table'];
    $dbCon0 = new dbcon($host, $port, $db, $dbuser, $dbpassword);
    $user = new User();
    $user->setDbCon($dbCon0);
    $user->token = $token;
    $user->getUserFromToken();
    $dataList = $user->getDataList();
    $requestedData = array($data);
    if(dataAccess($dataList, $requestedData)==FALSE){
        http_response_code(401);
        echo '{"error": "You do not have access to the requested data"}';
        die();
        }
}
else{
    http_response_code(400);
    echo '{"error": "Parameters not set"}';
    die();
}
if (isset($_GET['table'])){
    $tableName = $_GET['table'];
    $tableName = getTableNameFromData($dataList, $data);
    $dbCon = new dbcon($host, $port, $db, $dbuser, $dbpassword);
    $sql = "SELECT DISTINCT tc.table_schema, tc.constraint_name, tc.table_name, kcu.column_name, ccu.table_schema AS foreign_table_schema, ccu.table_name AS foreign_table_name, ccu.column_name AS foreign_column_name FROM information_schema.table_constraints AS tc JOIN information_schema.key_column_usage AS kcu ON tc.constraint_name = kcu.constraint_name AND tc.table_schema = kcu.table_schema JOIN information_schema.constraint_column_usage AS ccu ON ccu.constraint_name = tc.constraint_name AND ccu.table_schema = tc.table_schema WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_name = $1";
    $parameters = array($tableName);
    $dbCon->query($sql, $parameters);
    $result = $dbCon->result;
    $jsonText = "[";
    $rowCount = 0;
    while($row=pg_fetch_assoc($result)){
        if ($rowCount>0){
            $jsonText.=",";
        }
        $jsonText.="{";
        $jsonText.='"primaryTableSchema":"'.$row['table_schema'].'",';
        $jsonText.='"primaryTableName":"'.$row['table_name'].'",';
        $jsonText.='"primaryColumnName":"'.$row['column_name'].'",';
        $jsonText.='"foreignTableSchema":"'.$row['foreign_table_schema'].'",';
        $jsonText.='"foreignTableName":"'.$row['foreign_table_name'].'",';
        $jsonText.='"foreignColumnName":"'.$row['foreign_column_name'].'",';
        $dbCon2 = new dbcon($host, $port, $db, $dbuser, $dbpassword);
        $dbCon3 = new dbcon($host, $port, $db, $dbuser, $dbpassword);
        $sql3 = "SELECT column_name FROM information_schema.columns WHERE table_schema = '".$row['foreign_table_schema']."' AND table_name = '".$row['foreign_table_name']."' AND column_name != '".$row['foreign_column_name']."'";
        $dbCon3->query($sql3);
        $result3 = $dbCon3->result;
        $resultRows3 = pg_fetch_assoc($result3);
        if (!empty($resultRows3)){
            $distinctList = $resultRows3['column_name'];
        }
        $sql2 = 'SELECT DISTINCT "'. $distinctList . '", "' .$row['foreign_column_name'] . '" FROM "' .$row['foreign_table_schema'] . '"."' . $row['foreign_table_name'] . '" ORDER BY "'. $distinctList . '"';
        $dbCon2->query($sql2);
        $result2 = $dbCon2->result;
        $foreignTableValueString = '"values": [';
        #will be dependent on only having two columns in each lookup table (id and value) and the
        $rCount = 0;
        while ($rw = pg_fetch_assoc($result2)){
            if ($rCount>0){
                $foreignTableValueString.=",";
            }
            $foreignTableValueString.="{";
            $rowCount2 = 0;
            foreach($rw as $key=>$value){
                if ($rowCount2>0){
                    $foreignTableValueString.=",";
                }
                if ($key==$row['foreign_column_name']){
                  $foreignTableValueString.='"id":'.$value;
                }
                else{
                $valueType = gettype($value);
                $foreignTableValueString.='"value":"'.$value.'",';
                $foreignTableValueString.='"valueType":"'.gettype($value).'"';
                }
                $rowCount2+=1;
            }
            $foreignTableValueString.="}";
            $rCount+=1;
        }
        $foreignTableValueString.="]";
        $rowCount+=1;
        $jsonText.=$foreignTableValueString;
        $jsonText.="}";
    }
    $jsonText.="]";
    echo $jsonText;
}
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
            return $json['tablename'];
        }
    }
}
?>
