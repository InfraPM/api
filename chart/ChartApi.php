<?php

require __DIR__ . '/../Api.php';

class ChartApi extends Api
{
    private $viewName;
    private $chartType;
    private $dataFormat;
    private $error;
    private $token;
    private $dataList;

    public function __construct()
    {
        parent::__construct();
        $this->apiRequest->setType("POST");
        $this->apiResponse->setFormat("application/json");
    }

    public function readRequest()
    { //add WHERE clause parameter
        if (isset($this->apiRequest->getVar['viewName']) && isset($this->apiRequest->getVar['chartType']) && isset($this->apiRequest->getVar['dataFormat']) && isset($this->apiRequest->getVar['token'])) {
            $this->viewName = $this->apiRequest->getVar['viewName'];
            $this->chartType = $this->apiRequest->getVar['chartType'];
            $this->dataFormat = $this->apiRequest->getVar['dataFormat'];
            $this->token = $this->apiRequest->getVar['token'];
            $validChartTypes = array(
                'line',
                'area',
                'bar',
                'radar',
                'histogram',
                'pie',
                'donut',
                'radialBar',
                'scatter',
                'bubble',
                'heatmap',
                'candlestick'
            );
            $validDataFormats = array(
                'singleValues',
                'pairedValues',
                'xyValues',
                'labels'
            );
            if (in_array($this->chartType, $validChartTypes) == FALSE) {
                $this->apiResponse->setHttpCode(400);
                $this->apiResponse->setBody('{"error":"Invalid Parameters"}');
                $this->error = TRUE;
            } else if (in_array($this->dataFormat, $validDataFormats) == FALSE) {
                $this->apiResponse->setHttpCode(400);
                $this->apiResponse->setBody('{"error":"Invalid Parameters"}');
                $this->error = TRUE;
            } else {
                $this->readView();
            }
        } else {
            $this->apiResponse->setHttpCode(400);
            $this->apiResponse->setBody('{"error":"Invalid Parameters"}');
            $this->error = TRUE;
        }
    }
    private function checkUserAccess()
    {
        $this->user->setToken($this->token);
        $this->user->getUserFromToken();
        $this->user->checkToken();
        $this->dataList = $this->user->getDataList(FALSE, "read");
        //var_dump($dataList);
        $dataArray = array($this->viewName);
        //var_dump($dataArray);
        if ($this->user->dataAccess($this->dataList, $dataArray, '', "wfs") != TRUE or $this->user->tokenExpired) {
            $this->apiResponse->setBody('{"error": "You do not have access to the requested data"}');
            $this->apiResponse->setHttpCode(401);
            $this->error = TRUE;
            //$this->sendResponse();
        }
    }
    public function readView()
    {
        $this->checkUserAccess();
        if ($this->error == FALSE) {
            $dataListElement = $this->user->getDataListElement($this->dataList, $this->viewName);
            $tableName = $dataListElement['tablename'];
            $schemaName = $dataListElement['schemaname'];
            $fullName = '"' . $schemaName . '"."' . $tableName . '"';
            $sqlStatement = <<<EOD
SELECT * FROM $fullName
EOD;
            $this->dbCon->query($sqlStatement);
            $result = $this->dbCon->result;
            $this->formatResult();
        }
    }
    private function formatResult()
    {
        //convention will be that first column in view is x axis, all other coulumns become series in chart
        //pull column names and use for labels?
        if ($this->dataFormat == 'singleValues') {
            $chartType = '"chart": {"type": "' . $this->chartType . '"},';
            $data = '"series": [{"data": [';
            $categories = '"xaxis": {"categories": [';
            $rowCount = 0;
            while ($row = pg_fetch_assoc($this->dbCon->result)) {
                $columnCount = 0;
                foreach ($row as $key => $value) {
                    if ($columnCount == 0) {
                        if ($rowCount > 0) {
                            $categories .= ',';
                        }
                        if (is_null($value) || $value == '') {
                            $value = 'null';
                        }
                        $categories .= '"' . $value . '"';
                    } else if ($columnCount == 1) {
                        if ($rowCount > 0) {
                            $data .= ',';
                        }
                        if (is_null($value) || $value == '') {
                            $value = 'null';
                        }
                        $data .= $value;
                    }
                    $columnCount += 1;
                }
                $rowCount += 1;
            }
            $categories .= "]}";
            $data .= "]}]";
            $response = '{' . $chartType . $data . "," . $categories . '}';
        } else if ($this->dataFormat == 'pairedValues') {
        } else if ($this->dataFormat == 'xyValues') {
        } else if ($this->dataFormat == 'labels') {
        }
        $this->apiResponse->setBody($response);
        $this->apiResponse->setHttpCode(200);
    }
    //structure response based on dataFormat parameter
}
