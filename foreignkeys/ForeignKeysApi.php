<?php
require '../Api.php';
class ForeignKeysApi extends Api
{
    public function __construct()
    {
        parent::__construct();
        $this->apiRequest->setType("POST");
        $this->apiResponse->setFormat("application/json");
    }
    public function readRequest(): void
    {
        if (!empty($this->apiRequest->postVar) or !empty($this->apiRequest->getVar['table'])) {
            $this->getForeignKeys();
        } else {
            $this->apiResponse->setHttpCode(400);
            $this->apiResponse->setBody('{"error":"Invalid Parameters"}');
        }
    }
    public function getForeignKeys(): void
    {
        $postData = json_decode($this->apiRequest->postVar, TRUE);
        $token = $postData['token'];
        $table = $_GET['table'];
        $this->user->setToken($token);
        $this->user->getUserFromToken();
        $this->user->checkToken();
        $dataList = $this->user->getDataList();
        $requestedData = array($table);
        if ($this->user->dataAccess($dataList, $requestedData, "", "wfs") and $this->user->tokenExpired == FALSE) {
            $tableName = $this->getTableNameFromData($dataList, $table);
            $sql = "SELECT DISTINCT tc.table_schema, tc.constraint_name, tc.table_name, kcu.column_name, ccu.table_schema AS foreign_table_schema, ccu.table_name AS foreign_table_name, ccu.column_name AS foreign_column_name FROM information_schema.table_constraints AS tc JOIN information_schema.key_column_usage AS kcu ON tc.constraint_name = kcu.constraint_name AND tc.table_schema = kcu.table_schema JOIN information_schema.constraint_column_usage AS ccu ON ccu.constraint_name = tc.constraint_name AND ccu.table_schema = tc.table_schema WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_name = $1";
            $parameters = array($tableName);
            $this->dbCon->query($sql, $parameters);
            $result = $this->dbCon->result;
            $jsonText = "[";
            $rowCount = 0;
            while ($row = pg_fetch_assoc($result)) {
                if ($rowCount > 0) {
                    $jsonText .= ",";
                }
                $jsonText .= "{";
                $jsonText .= '"primaryTableSchema":"' . $row['table_schema'] . '",';
                $jsonText .= '"primaryTableName":"' . $row['table_name'] . '",';
                $jsonText .= '"primaryColumnName":"' . $row['column_name'] . '",';
                $jsonText .= '"foreignTableSchema":"' . $row['foreign_table_schema'] . '",';
                $jsonText .= '"foreignTableName":"' . $row['foreign_table_name'] . '",';
                $jsonText .= '"foreignColumnName":"' . $row['foreign_column_name'] . '",';
                //$dbCon2 = new dbcon($host, $port, $db, $dbuser, $dbpassword);
                //$dbCon3 = new dbcon($host, $port, $db, $dbuser, $dbpassword);
                $sql3 = "SELECT column_name FROM information_schema.columns WHERE table_schema = '" . $row['foreign_table_schema'] . "' AND table_name = '" . $row['foreign_table_name'] . "' AND column_name != '" . $row['foreign_column_name'] . "'";
                $this->dbCon->query($sql3);
                $result3 = $this->dbCon->result;
                $resultRows3 = pg_fetch_assoc($result3);
                if (!empty($resultRows3)) {
                    $distinctList = $resultRows3['column_name'];
                }
                //currently SortOrder field is mandatory -> could make this optional
                $sql2 = 'SELECT DISTINCT "' . $distinctList . '", "' . $row['foreign_column_name'] . '", "SortOrder" AS "SortOrder" FROM "' . $row['foreign_table_schema'] . '"."' . $row['foreign_table_name'] . '" ORDER BY "' . $row['foreign_table_name'] . '"."SortOrder"';
                $this->dbCon->query($sql2);
                $result2 = $this->dbCon->result;
                $foreignTableValueString = '"values": [';
                #will be dependent on only having two columns in each lookup table (id and value) and the
                $rCount = 0;
                while ($rw = pg_fetch_assoc($result2)) {
                    if ($rCount > 0) {
                        $foreignTableValueString .= ",";
                    }
                    $foreignTableValueString .= "{";
                    $rowCount2 = 0;
                    foreach ($rw as $key => $value) {
                        if ($key != "SortOrder") {
                            if ($rowCount2 > 0) {
                                $foreignTableValueString .= ",";
                            }
                            if ($key == $row['foreign_column_name']) {
                                $foreignTableValueString .= '"id":' . $value;
                            } else {
                                $valueType = gettype($value);
                                $foreignTableValueString .= '"value":"' . $value . '",';
                                $foreignTableValueString .= '"valueType":"' . gettype($value) . '"';
                            }
                            $rowCount2 += 1;
                        }
                    }
                    $foreignTableValueString .= "}";
                    $rCount += 1;
                }
                $foreignTableValueString .= "]";
                $rowCount += 1;
                $jsonText .= $foreignTableValueString;
                $jsonText .= "}";
            }
            $jsonText .= "]";
            $this->apiResponse->setHttpCode(200);
            $this->apiResponse->setBody($jsonText);
        } else {
            $this->apiResponse->setHttpCode(401);
            $this->apiResponse->setBody('{"error": "You do not have access to the requested data"}');
        }
    }
    private function getTableNameFromData($dataList, $requestedData)
    {
        $array = json_decode($dataList, true);
        foreach ($array as $json) {
            $fullDataName = $json['name'];
            if ($fullDataName == $requestedData) {
                return $json['tablename'];
            }
        }
    }
}
