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
header('Content-Type: application/json');
$commentSchema = "geoserver_dev";
if (isset($_GET['m'])){
    $mode = $_GET['m'];
    $rawPost = file_get_contents("php://input");
    $postData = json_decode($rawPost, TRUE);
    if ($mode=='read'){
        $dbCon = new dbcon($host, $port, $db, $dbuser, $dbpassword);
        $token = $postData[0]['token'];
        $dataset = $postData[0]['data'];
        $featureId = $postData[0]['featureId'];
        $featureIdField = $postData[0]['featureIdField'];
        if (isset($postData[0]['commentId'])){
            $commentId = $postData[0]['commentId'];
        }
        else{
            $commentId = NULL;
        }
        $user = new User();
        $user->setDbCon($dbCon);
        $user->token = $token;
        $user->getUserFromToken();
        $dataList = $user->getDataList(FALSE, "read");
        $dataArray = array($dataset);
        if ($user->dataAccess($dataList, $dataArray)!=TRUE){
            echo '{"error": "You do not have access to the requested data"}';
            die();
        }
        $dataJson = $user->getDataListElement($dataList, $dataset);
        $tableName = $dataJson["tablename"];
        $schemaName = $dataJson["schemaname"];
        $user->spatialDataSchemaName = $schemaName;
        $sqlStatement = <<<EOD
SELECT "Comments"."CommentId", "CommentIndex"."ReplyId" AS "ReplyId", "$tableName"."$featureIdField" AS "OBJECTID", "users"."username" AS "UserName", "Comment", "Timestamp", "ModifiedTimestamp", "CommentStatus"."CommentStatus", "CommentType"."CommentType" FROM "$schemaName"."$tableName" INNER JOIN "$schemaName"."CommentIndex" ON
"$tableName"."$featureIdField"="CommentIndex"."FeatureId" INNER JOIN "$schemaName"."Comments" ON
"Comments"."CommentId" = "CommentIndex"."CommentId" INNER JOIN "$schemaName"."CommentStatus" ON
"CommentStatus"."Id" = "Comments"."Status" INNER JOIN "gm"."users" ON "users"."id" = "Comments"."User" 
INNER JOIN $schemaName."CommentType" ON "CommentType"."Id" = "Comments"."Type" 
WHERE "CommentIndex"."FeatureId"=$1 AND "CommentStatus"."CommentStatus"=$2
EOD;
        if ($commentId!=NULL){
            $sqlStatement .= ' AND "Comments"."CommentId" = $3';
            $parameters = array($featureId,'Active', $commentId);
        }
        else{
            $parameters = array($featureId,'Active');
        }
        $dbCon->query($sqlStatement, $parameters);
        $result = $dbCon->result;
        $jsonString = "[";
        $commaCount = 0;
        while ($row = pg_fetch_assoc($result)) {
            if ($commaCount>0){
                $jsonString.=",";
            }
            $jsonString.="{";
            $jsonString.='"CommentId":'.$row["CommentId"].',';          
            if ($row["ReplyId"]===NULL){
                $curReplyId = "null";
            }
            else{
                $curReplyId = $row["ReplyId"];
            }
            $jsonString.='"ReplyId":'.$curReplyId.',';
            $jsonString.='"OBJECTID":'.$row["OBJECTID"].',';
            $jsonString.='"UserName":"'.$row["UserName"].'",';
            $curComment = json_encode($row["Comment"]);
            //$curComment = $row["Comment"];
            $jsonString.='"Comment":'.$curComment.',';
            $jsonString.='"Timestamp":"'.$row["Timestamp"].'",';
            $jsonString.='"ModifiedTimestamp":"'.$row["ModifiedTimestamp"].'",';
            if ($user->ownsComment($row['CommentId'])){
                $jsonString.='"RequesterOwnsComment": true,';
            }
            else{
                $jsonString.='"RequesterOwnsComment": false,';
            }
            $jsonString.='"CommentType":"'.$row["CommentType"].'",';
            $jsonString.='"CommentStatus":"'.$row["CommentStatus"].'"';
            $jsonString.="}";
            $commaCount+=1;
            
        }
        $jsonString.="]";
        echo $jsonString;
    }
    else if ($mode == 'add'){
        $successCount = 0;
        $dbCon = new dbcon($host, $port, $db, $dbuser, $dbpassword);
        foreach ($postData as $i){            
            $curUser = new User();
            $curUser->setDbCon($dbCon);
            $curToken = $i['token'];
            $curUser->token = $curToken;
            $curUser->getUserFromToken();
            $curDataset = $i['data'];
            $dataArray = array($curDataset);
            $dataList = $curUser->getDataList(FALSE, "comment");
            $curDataJson = $curUser->getDatalistElement($dataList, $curDataset);
            if ($curUser->dataAccess($dataList, $dataArray)!=TRUE){
                echo '{"error": "You do not have access to the requested data"}';
                die();
        }
            $curUserName = $curUser->userName;
            $curTableName = $curDataJson["tablename"];
            $curSchemaName = $curDataJson["schemaname"];
            $curFeatureId = $i['featureId'];
            $curComment = $i['comment'];            
            $curCommentStatus = $i['commentStatus'];
            $curCommentType = $i['commentType'];
            $sql0 = "BEGIN";
            $sql1=<<<EOD
INSERT INTO "$curSchemaName"."Comments" ("Comment", "User", "Status", "Type") VALUES ($1, (SELECT "id" FROM "gm"."users" WHERE "token"=$2), (SELECT "Id" FROM "$curSchemaName"."CommentStatus" WHERE "CommentStatus" = $3), (SELECT "Id" FROM "$curSchemaName"."CommentType" WHERE "CommentType" = $4)) RETURNING "Comments"."CommentId" AS "CommentId"
EOD;
            $parameters1 = array($curComment, $curToken, $curCommentStatus, $curCommentType);
            $dbCon->query($sql0);
            $res0 = $dbCon->result;
            $dbCon->query($sql1,$parameters1);
            $res1 = $dbCon->result;
            $result1Success=$dbCon->success();
            $row = pg_fetch_assoc($res1);            
            $insertedCommentId = $row['CommentId'];                
            if (is_null($i['replyId'])==FALSE){
                $curReplyId = $i['replyId'];
                $sql2 =  <<<EOD
INSERT INTO "$curSchemaName"."CommentIndex" ("CommentId", "DatasetId", "FeatureId", "ReplyId") VALUES ($1, (SELECT id FROM gm.spatialdata WHERE name=$2 AND schemaname=$3 AND tablename=$4), $5, $6)
EOD;
                $parameters2 = array($insertedCommentId, $curDataset, $curSchemaName, $curTableName, $curFeatureId, $curReplyId);
            }
            else{
                $sql2 = <<<EOD
INSERT INTO "$curSchemaName"."CommentIndex" ("CommentId", "DatasetId", "FeatureId") VALUES ($1, (SELECT id FROM gm.spatialdata WHERE name=$2 AND schemaname=$3 AND tablename=$4), $5)
EOD;
                $parameters2 = array($insertedCommentId, $curDataset, $curSchemaName, $curTableName, $curFeatureId);
            }
            $dbCon->query($sql2, $parameters2);
            $res2 = $dbCon->result;            
            $result2Success = $dbCon->success();
            if ($result1Success and $result2Success){
                $dbCon->query("COMMIT");
                $successCount++;
            }
            else{
                $dbCon->query("ROLLBACK");
            }
        }
        if ($successCount==count($postData)){
            echo '{"Status": "Success", "AddedComments": '.$successCount.' }';
        }
        else if ($successCount==0){
            echo '{"Status": "Failure", "AddedComments": '.$successCount.' }';            
        }
        else{
            echo '{"Status": "Partial Success", "AddedComments": '.$successCount.' }';
        }
    }
    else if ($mode == 'update'){
        $dbCon = new dbcon($host, $port, $db, $dbuser, $dbpassword);
        $successArray = array();
        foreach($postData as $i){
            $curUser = new User();
            $curUser->setDbCon($dbCon);
            $curToken = $i['token'];
            $curUser->token = $curToken;
            $curUser->getUserFromToken();
            $curComment = $i['comment'];
            $curStatus = $i['commentStatus'];
            $curType = $i['commentType'];
            $curCommentId = $i['commentId'];
            $curDataset = getDatasetFromComment($curCommentId, $commentSchema, $dbCon);
            $dataList = $curUser->getDataList(FALSE, "comment");
            $dataArray = array($curDataset);
            $dataJson = $curUser->getDataListElement($dataList, $curDataset);
            $schemaName = $dataJson["schemaname"];
            $curUser->spatialDataSchemaName = $schemaName;
            if ($curUser->dataAccess($dataList, $dataArray)!=TRUE){
                echo '{"error": "You do not have access to the requested data"}';
                die();
            }
            //make copy of row in comments table
            $archiveSql = <<<EOD
INSERT INTO $schemaName."Comments" ("Comment", "User", "Timestamp", "Status", "Type", "ModifiedTimestamp")
SELECT "Comment", "User", "Timestamp", "Status", "Type", "ModifiedTimestamp" FROM $schemaName."Comments"
WHERE "Comments"."CommentId" = $1
RETURNING *
EOD;
            $archiveParameters = array($curCommentId);
            //update copy of row to have status of 'Archived'
            $updateArchiveSql = <<<EOD
UPDATE $schemaName."Comments" SET "Status" = (SELECT "Id" FROM $schemaName."CommentStatus" WHERE "CommentStatus" = $1) WHERE "CommentId" = $2
EOD;
            //get row in CommentIndex with current comment id
            $archiveIndexSql = <<<EOD
SELECT * FROM $schemaName."CommentIndex" WHERE "CommentId" = $1 LIMIT 1
EOD;
            $archiveIndexParameters = array($curCommentId);
            //create copy of this row with archiveId populated
            $insertArchiveIndexSql = <<<EOD
INSERT INTO $schemaName."CommentIndex" ("CommentId", "DatasetId", "FeatureId", "ReplyId", "ArchiveId") VALUES ($1, $2, $3, $4, $5)
EOD;
            //finally update the original comment record
            $updateCommentSql = <<<EOD
UPDATE $schemaName."Comments" SET "Comment" = $1, "Status" = (SELECT "Id" FROM $schemaName."CommentStatus" WHERE "CommentStatus" = $2), "Type" = (SELECT "Id" FROM $schemaName."CommentType" WHERE "CommentType" = $3), "ModifiedTimestamp" = Now() WHERE "Comments"."CommentId" = $4 RETURNING *;
EOD;
            $updateCommentParameters = array($curComment, $curStatus, $curType, $curCommentId);            
            if ($curUser->ownsComment($curCommentId)){
                $dbCon->query($archiveSql, $archiveParameters);
                $result = $dbCon->result;
                while ($row = pg_fetch_assoc($result)){
                    $curArchiveId = $row['CommentId'];
                }
                $updateArchiveParameters = array('Archived', $curArchiveId);
                $dbCon->query($updateArchiveSql, $updateArchiveParameters);
                $dbCon->query($archiveIndexSql, $archiveIndexParameters);
                while ($row = pg_fetch_assoc($dbCon->result)){
                    $indexDatasetId = $row['DatasetId'];
                    $indexFeatureId = $row['FeatureId'];
                    $indexReplyId = $row['ReplyId'];
                    $indexCommentId = $row['CommentId'];
                }
                $insertArchiveIndexParameters = array($curArchiveId, $indexDatasetId, $indexFeatureId, $indexReplyId, $indexCommentId);
                $dbCon->query($insertArchiveIndexSql, $insertArchiveIndexParameters);
                $dbCon->query($updateCommentSql, $updateCommentParameters);                         
                while ($row = pg_fetch_assoc($dbCon->result)){
                    array_push($successArray, $row);
                }
            }
        }
        if (count($successArray)==count($postData)){
            echo '{"Status": "Success", "UpdatedFeatures": '.count($successArray).' }';
        }
        else if (count($successArray)==0){
            echo '{"Status": "Failure", "UpdatedFeatures": '.count($successArray).' }';            
        }
        else{
            echo '{"Status": "Partial Success", "UpdatedFeatures": '.count($successArray).' }';
        }
    }
    else{
        echo '{"error": "Invalid Parameters"}';
        die();
    }
}
else{
    echo '{"error": "Invalid Parameters"}';
    die();
}

function getDatasetFromComment($commentId, $commentSchema, $dbCon){
    $sql = <<<EOD
SELECT spatialdata.name AS name FROM gm.spatialdata INNER JOIN $commentSchema."CommentIndex" ON spatialdata.id = "CommentIndex"."DatasetId" WHERE "CommentIndex"."CommentId" = $1
EOD;
    $parameters = array($commentId);
    $dbCon->query($sql, $parameters);
    $result = $dbCon->result;
    while($row = pg_fetch_assoc($result)){
        $datasetName = $row['name'];
    }
    return $datasetName;
}
?>
