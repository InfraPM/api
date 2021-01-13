<?php
require '../../support/pass.php';
require '../../support/dbcon.php';
require '../../support/user.php';
$allowHost = $baseURL . 'regionalroads.com';
$corsHeader = 'Access-Control-Allow-Origin: ' . $allowHost;
header($corsHeader); 
header("Access-Control-Allow-Credentials: true");
header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
header('Access-Control-Max-Age: 1000');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token , Authorization');
$rawPost=file_get_contents("php://input");
$postData = json_decode($rawPost, TRUE);
if (!empty($postData['token'])){
    $token = $postData['token'];
}
else{
    http_response_code(401);
    echo '{"error": "Access Denied"}';
    die();
}
$dbCon = new dbcon($host, $port, $db, $dbuser, $dbpassword);
$user = new User();
$user->setDbCon($dbCon);
$user->token = $token;
$user->getUserFromToken();
$dataList = $user->getDataList(FALSE, "read");

$jsonDataList = json_decode($dataList, TRUE);
$returnString = "{";
$returnString.='"read": [';
$count = 0;
foreach ($jsonDataList as $i){    
    if ($count>0){
        $returnString.=",";
    }
    
    $returnString.='"'.$i['name'].'"';
    $count+=1;
    
}
$returnString.="],";
$modifyDataList = $user->getDataList(FALSE, "modify");
$jsonModifyDataList = json_decode($modifyDataList, TRUE);
$returnString.='"modify": [';
$count = 0;
foreach ($jsonModifyDataList as $j){
    if ($count>0){
        $returnString.=",";
    }
    $returnString.='"'.$j['name'].'"';
    $count+=1;
    
}
$returnString.="],";
$deleteDataList = $user->getDataList(FALSE, "delete");
$jsonDeleteDataList = json_decode($deleteDataList, TRUE);
$returnString.='"delete": [';
$count = 0;
foreach ($jsonDeleteDataList as $k){
    if ($count>0){
        $returnString.=",";
    }
    $returnString.='"'.$k['name'].'"';
    $count+=1;
    
}
$returnString.="],";
$insertDataList = $user->getDataList(FALSE, "insert");
$jsonInsertDataList = json_decode($insertDataList, TRUE);
$returnString.='"insert": [';
$count = 0;
foreach ($jsonInsertDataList as $l){
    if ($count>0){
        $returnString.=",";
    }
    $returnString.='"'.$l['name'].'"';
    $count+=1;
    
}
$returnString.="],";
$commentDataList = $user->getDataList(FALSE, "comment");
$jsonCommentDataList = json_decode($commentDataList, TRUE);
$returnString.='"comment": [';
$count = 0;
foreach ($jsonCommentDataList as $m){
    if ($count>0){
        $returnString.=",";
    }
    $returnString.='"'.$m['name'].'"';
    $count+=1;
    
}
$returnString.="]}";
echo $returnString;
?>
