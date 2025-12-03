<?php
require __DIR__ . '/../Api.php';
class ExportAPI extends API
{
    private $token;
    private $headers;
    private $dataList;
    private $error;
    private $dataArray;
    private $outputCsvContent;
    private $outputCsvFile;

    public function __construct()
    {
        parent::__construct();
        $this->error = FALSE;
        //$this->apiResponse->setFormat("application/json");
    }
    public function readRequest(): void
    {
        $this->error = FALSE;
        if (isset($this->apiRequest->getVar['token'])) {
            $this->token = $this->apiRequest->getVar['token'];
        } else {
            $this->apiResponse->setHttpCode(400);
            $this->apiResponse->setFormat("application/json");
            $this->apiResponse->setBody('{"error": "Invalid Parameters"}');
            $this->error = TRUE;
        }
        if (isset($this->apiRequest->getVar['data'])) {
            $this->dataArray = explode(",", $this->apiRequest->getVar['data']);
        } else {
            $this->apiResponse->setHttpCode(400);
            $this->apiResponse->setFormat("application/json");
            $this->apiResponse->setBody('{"error": "Invalid Parameters"}');
            $this->error = TRUE;
        }
        $this->generateResponse();
    }
    public function generateResponse(): void
    {
        if ($this->error == FALSE) {
            $this->user->setToken($this->token);
            $this->user->getUserFromToken();
            $this->user->checkToken();
            if ($this->user->tokenExpired == FALSE) {
                $this->dataList = $this->user->getDataList(PermType::USER, "read");
                $originUrl = $_ENV['baseURL'] . "regionalroads.com";
                $this->headers = array(
                    'Access-Control-Allow-Origin' => $originUrl,
                    "Access-Control-Allow-Credentials'=>'true",
                    'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
                    'Access-Control-Max-Age' => '1000',
                    'Access-Control-Allow-Headers' => 'Origin, Content-Type, X-Auth-Token , Authorization',
                    'Content-Description' => 'File Transfer',
                    'Content-Type' => 'application/octet-stream',
                    'Content-Type' => 'text/html',
                    'Expires' => '0',
                    'Cache-Control' => 'must-revalidate',
                    'Pragma' => 'public',
                    'Content-Type' => 'csv'
                );
                $csvSql = "";
                //$dataArray = explode(",", $this->data);
                if ($this->user->dataAccess($this->dataList, $this->dataArray) != TRUE) {
                    $this->error = TRUE;
                    $this->apiResponse->setHttpCode(401);
                    $this->apiResponse->setFormat("application/json");
                    $this->apiResponse->setBody('{"error": "You do not have access to the requested data"}');
                } else {
                    $layerCount = 0;
                    //var_dump($this->dataArray);
                    foreach ($this->dataArray as $key => $value) {
                        $curTableName = $this->getTableNameFromData($value);
                        if ($layerCount > 0) {
                            $csvSql .= " UNION ALL ";
                        }
                        $csvSql .= "SELECT * FROM " . $curTableName;
                        $layerCount += 1;
                        $dataName = $this->formatFileName($value);
                    }
                    $this->headers['Content-Disposition'] = 'attachment; filename="' . $dataName . '.csv"';
                    foreach ($this->headers as $key => $value) {
                        header($key . ": " . $value);
                    }
                    $this->dbCon->query($csvSql);
                    $result = $this->dbCon->result;
                    $out = fopen('php://output', 'w');
                    $mem = fopen('php://memory', 'r+');
                    $rowCount = 0;
                    $resultArray = pg_fetch_assoc($result, 0);
                    while ($row = pg_fetch_assoc($this->dbCon->result)) {
                        if ($rowCount == 0) {
                            fputcsv($out, array_keys($resultArray));
                            fputcsv($mem, array_keys($resultArray));
                        }
                        $row['Shape'] = '';
                        fputcsv($out, $row);
                        fputcsv($mem, $row);
                        $rowCount += 1;
                    }
                    rewind($mem);
                    $this->outputCsvContent = stream_get_contents($mem);
                    $this->apiResponse->setHttpCode(200);
                    http_response_code($this->apiResponse->httpCode);
                    fclose($out);
                }
            } else {
                $this->error = TRUE;
                $this->apiResponse->setHttpCode(400);
                $this->apiResponse->setFormat("application/json");
                $this->apiResponse->setBody('{"error": "You do not have access to the requested dataset"}');
            }
        }
    }
    public function sendErrorResponse(): void
    {
        if ($this->error) {
            $this->sendResponse();
        }
    }
    private function getTableNameFromData($requestedData)
    {
        foreach ($this->dataList as $item) {
            $fullDataName = $item['name'];
            if ($fullDataName == $requestedData) {
                return '"' . $item['schemaname'] . '"' . "." . '"' . $item['tablename'] . '"';
            }
        }
    }
    private function formatFileName($fileName)
    {
        $removeString = array("_dev", "_Line", "_Point", "_Polygon", "_view");
        foreach ($removeString as $i) {
            $fileName = str_replace($i, "", $fileName);
        }
        return $fileName;
    }
}
