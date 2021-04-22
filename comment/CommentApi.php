<?php
require __DIR__ . '/../Api.php';

class CommentApi extends Api
{
    private $commentSchema;
    public function __construct()
    {
        parent::__construct();
        $this->commentSchema = "geoserver_dev";
        $this->apiRequest->setType("POST");
        $this->apiResponse->setFormat("application/json");
    }
    /**
     * Perform API logic based on the API Request
     */
    public function readRequest(): void
    {
        if (!empty($this->apiRequest->getVar['m']) or !empty($this->apiRequest->postVar)) {
            $mode = $this->apiRequest->getVar['m'];
            if ($mode == "read") {
                $this->readComments();
            } else if ($mode == "add") {
                $this->addComments();
            } else if ($mode == "update") {
                $this->updateComments();
            } else {
                $this->apiResponse->setHttpCode(400);
                $this->apiResponse->setBody('{"error":"Invalid Parameters"}');
            }
        } else {
            $this->apiResponse->setHttpCode(400);
            $this->apiResponse->setBody('{"error":"Invalid Parameters"}');
        }
    }
    /**
     * Read comment(s) from the database based on given
     * POST parameters:
     * [{"data":"foo",
     * "token":"bar",
     * "featureId":"baz",
     * "featureIdField":"biz"}]
     */
    public function readComments(): void
    {
        $postData = json_decode($this->apiRequest->postVar, TRUE);
        $token = $postData[0]['token'];
        $dataset = $postData[0]['data'];
        $featureId = $postData[0]['featureId'];
        $featureIdField = $postData[0]['featureIdField'];
        if (isset($postData[0]['commentId'])) {
            $commentId = $postData[0]['commentId'];
        } else {
            $commentId = NULL;
        }
        $this->user->setToken($token);
        $this->user->getUserFromToken();
        $this->user->checkToken();
        $dataList = $this->user->getDataList(FALSE, "read");
        $dataArray = array($dataset);
        if ($this->user->dataAccess($dataList, $dataArray, "", "wfs") != TRUE or $this->user->tokenExpired) {
            $this->apiResponse->setBody('{"error": "You do not have access to the requested data"}');
            $this->apiResponse->setHttpCode(401);
            $this->sendResponse();
        } else {
            $dataJson = $this->user->getDataListElement($dataList, $dataset);
            $tableName = $dataJson["tablename"];
            $schemaName = $dataJson["schemaname"];
            $this->user->setSpatialDataSchemaName($schemaName);
            $sqlStatement = <<<EOD
SELECT "Comments"."CommentId", "CommentIndex"."ReplyId" AS "ReplyId", "$tableName"."$featureIdField" AS "OBJECTID", "users"."username" AS "UserName", "Comment", "Timestamp", "ModifiedTimestamp", "CommentStatus"."CommentStatus", "CommentType"."CommentType" FROM "$schemaName"."$tableName" INNER JOIN "$schemaName"."CommentIndex" ON
"$tableName"."$featureIdField"="CommentIndex"."FeatureId" INNER JOIN "$schemaName"."Comments" ON
"Comments"."CommentId" = "CommentIndex"."CommentId" INNER JOIN "$schemaName"."CommentStatus" ON
"CommentStatus"."Id" = "Comments"."Status" INNER JOIN "gm"."users" ON "users"."id" = "Comments"."User" 
INNER JOIN $schemaName."CommentType" ON "CommentType"."Id" = "Comments"."Type" 
WHERE "CommentIndex"."FeatureId"=$1 AND "CommentStatus"."CommentStatus"=$2
EOD;
            if ($commentId != NULL) {
                $sqlStatement .= ' AND "Comments"."CommentId" = $3';
                $parameters = array($featureId, 'Active', $commentId);
            } else {
                $parameters = array($featureId, 'Active');
            }
            $this->dbCon->query($sqlStatement, $parameters);
            $result = $this->dbCon->result;
            $jsonString = "[";
            $commaCount = 0;
            while ($row = pg_fetch_assoc($result)) {
                if ($commaCount > 0) {
                    $jsonString .= ",";
                }
                $jsonString .= "{";
                $jsonString .= '"CommentId":' . $row["CommentId"] . ',';
                if ($row["ReplyId"] === NULL) {
                    $curReplyId = "null";
                } else {
                    $curReplyId = $row["ReplyId"];
                }
                $jsonString .= '"ReplyId":' . $curReplyId . ',';
                $jsonString .= '"OBJECTID":' . $row["OBJECTID"] . ',';
                $jsonString .= '"UserName":"' . $row["UserName"] . '",';
                $curComment = json_encode($row["Comment"]);
                $jsonString .= '"Comment":' . $curComment . ',';
                $jsonString .= '"Timestamp":"' . $row["Timestamp"] . '",';
                $jsonString .= '"ModifiedTimestamp":"' . $row["ModifiedTimestamp"] . '",';
                if ($this->user->ownsComment($row['CommentId'])) {
                    $jsonString .= '"RequesterOwnsComment": true,';
                } else {
                    $jsonString .= '"RequesterOwnsComment": false,';
                }
                $jsonString .= '"CommentType":"' . $row["CommentType"] . '",';
                $jsonString .= '"CommentStatus":"' . $row["CommentStatus"] . '"';
                $jsonString .= "}";
                $commaCount += 1;
            }
            $jsonString .= "]";
            $this->apiResponse->setBody($jsonString);
            $this->apiResponse->setHttpCode(200);
        }
    }
    /**
     * Add comment(s) based on POST parameters
     * 
     * [{"data":"foo",
     * "token":"bar",
     * "featureId":"baz",
     * "comment":"biz",
     * "commentStatus":"buz",
     * "commentType":"boz",
     * "replyId": "bez"}]
     */
    public function addComments(): void
    {
        $successCount = 0;
        //$dbCon = new dbcon($host, $port, $db, $dbuser, $dbpassword);
        $postData = json_decode($this->apiRequest->postVar, TRUE);
        foreach ($postData as $i) {
            //$curUser = new User();
            $this->user->setDbCon($this->dbCon);
            $curToken = $i['token'];
            $this->user->setToken($curToken);
            $this->user->getUserFromToken();
            $this->user->checkToken();
            $curDataset = $i['data'];
            $dataArray = array($curDataset);
            $dataList = $this->user->getDataList(FALSE, "comment");
            $curDataJson = $this->user->getDatalistElement($dataList, $curDataset);
            if ($this->user->dataAccess($dataList, $dataArray, "", "wfs") != TRUE or $this->user->tokenExpired) {
                $this->apiResponse->setHttpCode(401);
                $this->apiRepsonse->setBody('{"error": "You do not have access to the requested data"}');
                $this->sendResponse();
            }
            $curUserName = $this->user->userName;
            $curTableName = $curDataJson["tablename"];
            $curSchemaName = $curDataJson["schemaname"];
            $curFeatureId = $i['featureId'];
            $curComment = $i['comment'];
            $curCommentStatus = $i['commentStatus'];
            $curCommentType = $i['commentType'];
            $sql0 = "BEGIN";
            $sql1 = <<<EOD
INSERT INTO "$curSchemaName"."Comments" ("Comment", "User", "Status", "Type") VALUES ($1, (SELECT "id" FROM "gm"."users" WHERE "token"=$2), (SELECT "Id" FROM "$curSchemaName"."CommentStatus" WHERE "CommentStatus" = $3), (SELECT "Id" FROM "$curSchemaName"."CommentType" WHERE "CommentType" = $4)) RETURNING "Comments"."CommentId" AS "CommentId"
EOD;
            $parameters1 = array($curComment, $curToken, $curCommentStatus, $curCommentType);
            $this->dbCon->query($sql0);
            $res0 = $this->dbCon->result;
            $this->dbCon->query($sql1, $parameters1);
            $res1 = $this->dbCon->result;
            $result1Success = $this->dbCon->success();
            $row = pg_fetch_assoc($res1);
            $insertedCommentId = $row['CommentId'];
            if (is_null($i['replyId']) == FALSE) {
                $curReplyId = $i['replyId'];
                $sql2 =  <<<EOD
INSERT INTO "$curSchemaName"."CommentIndex" ("CommentId", "DatasetId", "FeatureId", "ReplyId") VALUES ($1, (SELECT id FROM gm.spatialdata WHERE name=$2 AND schemaname=$3 AND tablename=$4), $5, $6)
EOD;
                $parameters2 = array($insertedCommentId, $curDataset, $curSchemaName, $curTableName, $curFeatureId, $curReplyId);
            } else {
                $sql2 = <<<EOD
INSERT INTO "$curSchemaName"."CommentIndex" ("CommentId", "DatasetId", "FeatureId") VALUES ($1, (SELECT id FROM gm.spatialdata WHERE name=$2 AND schemaname=$3 AND tablename=$4), $5)
EOD;
                $parameters2 = array($insertedCommentId, $curDataset, $curSchemaName, $curTableName, $curFeatureId);
            }
            $this->dbCon->query($sql2, $parameters2);
            $res2 = $this->dbCon->result;
            $result2Success = $this->dbCon->success();
            if ($result1Success and $result2Success) {
                $this->dbCon->query("COMMIT");
                $successCount++;
            } else {
                $this->dbCon->query("ROLLBACK");
            }
        }
        if ($successCount == count($postData)) {
            $this->apiResponse->setBody('{"Status": "Success", "AddedComments": ' . $successCount . ' }');
            $this->user->logEvent("Add Comment", $this->apiRequest->postVar);
        } else if ($successCount == 0) {
            $this->apiResponse->setBody('{"Status": "Failure", "AddedComments": ' . $successCount . ' }');
            $this->user->logEvent("Add Comment Error", $this->apiRequest->postVar);
        } else {
            $this->apiResponse->setBody('{"Status": "Partial Success", "AddedComments": ' . $successCount . ' }');
            $this->user->logEvent("Add Comment Error", $this->apiRequest->postVar);
        }
        $this->apiResponse->setHttpCode(200);
    }
    /**
     * Update comment(s) based on POST request
     * 
     * [{"token":"foo",
     * "commentId":"bar",
     * "comment":"baz",
     * "commentStatus":"biz",
     * "commentType":"buz"}]
     */
    public function updateComments(): void
    {
        $postData = json_decode($this->apiRequest->postVar, TRUE);
        //$dbCon = new dbcon($host, $port, $db, $dbuser, $dbpassword);
        $successArray = array();
        foreach ($postData as $i) {
            //$curUser = new User();
            $this->user->setDbCon($this->dbCon);
            $curToken = $i['token'];
            $this->user->setToken($curToken);
            $this->user->getUserFromToken();
            $this->user->checkToken();
            $curComment = $i['comment'];
            $curStatus = $i['commentStatus'];
            $curType = $i['commentType'];
            $curCommentId = $i['commentId'];
            $curDataset = $this->getDatasetFromComment($curCommentId, $this->commentSchema);
            $dataList = $this->user->getDataList(FALSE, "comment");
            $dataArray = array($curDataset);
            $dataJson = $this->user->getDataListElement($dataList, $curDataset);
            $schemaName = $dataJson["schemaname"];
            $this->user->setSpatialDataSchemaName($schemaName);
            if ($this->user->dataAccess($dataList, $dataArray, "", "wfs") != TRUE or $this->user->tokenExpired) {
                $this->apiResponse->httpCode(401);
                $this->apiResponse->body('{"error": "You do not have access to the requested data"}');
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
            if ($this->user->ownsComment($curCommentId)) {
                $this->dbCon->query($archiveSql, $archiveParameters);
                $result = $this->dbCon->result;
                while ($row = pg_fetch_assoc($result)) {
                    $curArchiveId = $row['CommentId'];
                }
                $updateArchiveParameters = array('Archived', $curArchiveId);
                $this->dbCon->query($updateArchiveSql, $updateArchiveParameters);
                $this->dbCon->query($archiveIndexSql, $archiveIndexParameters);
                while ($row = pg_fetch_assoc($this->dbCon->result)) {
                    $indexDatasetId = $row['DatasetId'];
                    $indexFeatureId = $row['FeatureId'];
                    $indexReplyId = $row['ReplyId'];
                    $indexCommentId = $row['CommentId'];
                }
                $insertArchiveIndexParameters = array($curArchiveId, $indexDatasetId, $indexFeatureId, $indexReplyId, $indexCommentId);
                $this->dbCon->query($insertArchiveIndexSql, $insertArchiveIndexParameters);
                $this->dbCon->query($updateCommentSql, $updateCommentParameters);
                while ($row = pg_fetch_assoc($this->dbCon->result)) {
                    array_push($successArray, $row);
                }
            }
        }
        if (count($successArray) == count($postData)) {
            $this->apiResponse->setBody('{"Status": "Success", "UpdatedFeatures": ' . count($successArray) . ' }');
            $this->user->logEvent("Update Comment", $this->apiRequest->postVar);
        } else if (count($successArray) == 0) {
            $this->apiResponse->setBody('{"Status": "Failure", "UpdatedFeatures": ' . count($successArray) . ' }');
            $this->user->logEvent("Update Comment Error", $this->apiRequest->postVar);
        } else {
            $this->apiResponse->setBody('{"Status": "Partial Success", "UpdatedFeatures": ' . count($successArray) . ' }');
            $this->user->logEvent("Update Comment Error", $this->apiRequest->postVar);
        }
        $this->apiResponse->setHttpCode(200);
    }
    /**
     * Return the dataset that the given commmentId
     * is associated with.
     *
     * @param int $commentId The id of the comment
     *
     * @return string The dataset name
     */
    private function getDatasetFromComment($commentId): string
    {
        $sql = <<<EOD
SELECT spatialdata.name AS name FROM gm.spatialdata INNER JOIN $this->commentSchema."CommentIndex" ON spatialdata.id = "CommentIndex"."DatasetId" WHERE "CommentIndex"."CommentId" = $1
EOD;
        $parameters = array($commentId);
        $this->dbCon->query($sql, $parameters);
        $result = $this->dbCon->result;
        while ($row = pg_fetch_assoc($result)) {
            $datasetName = $row['name'];
        }
        return $datasetName;
    }
}
