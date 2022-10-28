<?php
require __DIR__ . '/../Api.php';
class PermissionsApi extends Api
{
    private $mode;

    public function __construct()

    {
        parent::__construct();
        $this->apiRequest->setType("POST");
        $this->apiResponse->setFormat("application/json");
    }
    /**
     * Read request variables (GET, POST) and perform
     * logic to return appropriate response
     */
    public function readRequest(): void
    {
        $postData = json_decode($this->apiRequest->postVar, TRUE);
        if (isset($postData['token']) == FALSE) {
            $this->apiResponse->setHttpCode(400);
            $this->apiResponse->setBody('{"error":"Invalid Parameters"}');
        } else {
            if (isset($this->apiRequest->getVar['mode'])) {
                $this->mode = $this->apiRequest->getVar['mode'];
                if (!in_array($this->mode, ['app','data','all'])) {
                    $this->mode = 'data';
                }
            } else {
                $this->mode = "data";
            }
            
            if (isset($postData['agencyid'])){
                $this->getPermissions($postData['token'], $postData['agencyid']);
            }else{
                $this->getPermissions($postData['token'], null);
            }
        }
    }
    /**
     * Retrieve all permissions from the database for the given user
     */
    public function getPermissions(string $token, string $agencyid = null): void
    {
        //$token = $postData['token'];
        $this->user->setToken($token);
        $this->user->getUserFromToken();
        $this->user->checkToken();
        if ($this->user->tokenExpired) {
            $this->apiResponse->setHttpCode(401);
            $this->apiResponse->setBody('{"error":"Unathorized"}');
            return;
        }
        if( ($this->mode == 'all')) {
            $permissions = $this->user->getPermList(FALSE, null, $agencyid);
            $userInfo = array('user_id' => $this->user->userId, 'username' => $this->user->userName);
            $returnString = json_encode( array('user'=>$userInfo, 'perms'=>$permissions) );
        } else {
            if ($this->mode == 'app') {
                $dataList = $this->user->getAppList(FALSE, "read");
            } else {
                $dataList = $this->user->getDataList(FALSE, "read");
            }
            $jsonDataList = json_decode($dataList, TRUE);
            $returnString = "{";
            $returnString .= '"read": [';
            $count = 0;
            foreach ($jsonDataList as $i) {
                if ($count > 0) {
                    $returnString .= ",";
                }

                $returnString .= '"' . $i['name'] . '"';
                $count += 1;
            }
            $returnString .= "],";
            if ($this->mode == 'app') {
                $modifyDataList = $this->user->getAppList(FALSE, "modify");
            } else {
                $modifyDataList = $this->user->getDataList(FALSE, "modify");
            }
            $jsonModifyDataList = json_decode($modifyDataList, TRUE);
            $returnString .= '"modify": [';
            $count = 0;
            foreach ($jsonModifyDataList as $j) {
                if ($count > 0) {
                    $returnString .= ",";
                }
                $returnString .= '"' . $j['name'] . '"';
                $count += 1;
            }
            $returnString .= "],";
            if ($this->mode == 'app') {
                $deleteDataList = $this->user->getAppList(FALSE, "delete");
            } else {
                $deleteDataList = $this->user->getDataList(FALSE, "delete");
            }
            $jsonDeleteDataList = json_decode($deleteDataList, TRUE);
            $returnString .= '"delete": [';
            $count = 0;
            foreach ($jsonDeleteDataList as $k) {
                if ($count > 0) {
                    $returnString .= ",";
                }
                $returnString .= '"' . $k['name'] . '"';
                $count += 1;
            }
            $returnString .= "],";
            if ($this->mode == 'app') {
                $insertDataList = $this->user->getAppList(FALSE, "insert");
            } else {
                $insertDataList = $this->user->getDataList(FALSE, "insert");
            }
            $jsonInsertDataList = json_decode($insertDataList, TRUE);
            $returnString .= '"insert": [';
            $count = 0;
            foreach ($jsonInsertDataList as $l) {
                if ($count > 0) {
                    $returnString .= ",";
                }
                $returnString .= '"' . $l['name'] . '"';
                $count += 1;
            }
            $returnString .= "],";
            if ($this->mode == 'app') {
                $commentDataList = $this->user->getAppList(FALSE, "comment");
            } else {
                $commentDataList = $this->user->getDataList(FALSE, "comment");
            }
            $jsonCommentDataList = json_decode($commentDataList, TRUE);
            $returnString .= '"comment": [';
            $count = 0;
            foreach ($jsonCommentDataList as $m) {
                if ($count > 0) {
                    $returnString .= ",";
                }
                $returnString .= '"' . $m['name'] . '"';
                $count += 1;
            }
            $returnString .= "]}";
        }
        $this->apiResponse->setBody($returnString);
        $this->apiResponse->setHttpCode(200);
    }
}
