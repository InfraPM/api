<?php
require __DIR__ . '/../Api.php';
class MapOptionsApi extends Api
{
    private $schemaName;
    private $tableName;

    public function __construct()
    {
        parent::__construct();
        $this->schemaName = "gm";
        $this->tableName = "mapreference";
        $this->apiRequest->setType("POST");
        $this->apiResponse->setFormat("text/html");
    }
    /**
     * Perform API logic based on the API Request
     */
    public function readRequest(): void
    {
        $postData = json_decode($this->apiRequest->postVar, TRUE);
        $referenceCriteria = "";
        $mapNameCriteria = "";
        $parameterCount = 0;
        $criteriaArray = array();
        $error = FALSE;
        if (isset($this->apiRequest->getVar['referenceId'])) {
            $parameterCount += 1;
            $referenceId = $this->apiRequest->getVar['referenceId'];
            $referenceIdCriteria = <<<EOD
"id" = $parameterCount
EOD;
            $criteriaArray[$referenceId] = $referenceIdCriteria;
        } else if (isset($this->apiRequest->getVar['mapName'])) {
            $parameterCount += 1;
            $mapName = $this->apiRequest->getVar['mapName'];
            $mapNameCriteria = <<<EOD
"name"= $$parameterCount
EOD;
            $criteriaArray[$mapName] = $mapNameCriteria;
        } else {
            $this->apiResponse->setHttpCode(401);
            $this->apiResponse->setBody('{"error":"Invalid parameters"}');
            $error = TRUE;
        }
        $publicCriteria = "";
        if (isset($postData['token'])) {
            $parameterCount += 1;
            $token = $postData['token'];
            $this->user->setToken($token);
            $this->user->getUserFromToken();
            $this->user->checkToken();
            //$this->user->checkPassword();
        } else {
            $this->apiResponse->setHttpCode(401);
            $this->apiResponse->setBody('{"error":"You do not have access to the requested data"}');
            $error = TRUE;
        }
        if ($error == FALSE) {
            $this->getMapOptions($criteriaArray);
        }
    }
    /**
     * Format the API Response based on the API Request
     */
    private function getMapOptions(array $criteriaArray): void
    {
        $sql = <<<EOD
SELECT "options", "displayname" FROM "$this->schemaName"."$this->tableName"
EOD;
        $criteriaCount = 0;
        $parameters = array();
        foreach ($criteriaArray as $key => $value) {
            if ($criteriaCount == 0) {
                $sql .= " WHERE ";
            } else if ($criteriaCount > 0) {
                $sql .= " AND ";
            }
            $sql .= $value;
            array_push($parameters, $key);
            $criteriaCount += 1;
        }
        $this->dbCon->query($sql, $parameters);
        $result = $this->dbCon->result;
        while ($row = pg_fetch_assoc($result)) {
            $returnJson = $row['options'];
        }
        if ($returnJson != NULL and $this->user->tokenExpired == FALSE) {
            $this->apiResponse->setHttpCode(200);
            $this->apiResponse->setBody($returnJson);
        } else {
            $this->apiResponse->setHttpCode(400);
            $this->apiResponse->setBody('{"error":"Invalid parameters"}');
        }
    }
}
