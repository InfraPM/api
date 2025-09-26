<?php
require __DIR__ . '/../Api.php';
class LayerInfoApi extends Api
{
    public function __construct()
    {
        parent::__construct();

        $this->apiResponse->setFormat("application/json");
    }
    /**
     * Perform API logic based on the API Request
     */
    public function processRequest(): void
    {
        $postData = json_decode($this->apiRequest->postVar, TRUE);

        $layerName = null;

        if (isset($this->apiRequest->getVar['layerName'])) {
            $layerName = $this->apiRequest->getVar['layerName'];
        }

        if (isset($postData['token'])) {
            $token = $postData['token'];
            $this->user->setToken($token);
            $this->user->getUserFromToken();
            $this->user->checkToken();
        } else {
            //to? maybe this is ok if you accessing a public layer
            $this->apiResponse->setHttpCode(401);
            $this->apiResponse->setBody('{"error":"You do not have access to the requested data"}');
            return;
        }

        //forward to app api

        $url = $_ENV['baseAppAPIURL'] . "mfp/api/rrac/resources/" . urlencode($layerName) . "/info";
        $opts = array(
            'http' =>
            array(
                'method'  => 'POST',
                'header'  => array(
                    'Content-Type: application/json',
                ),
                'content' => $this->apiRequest->postVar
            )

        );
        //var_dump($wfsURL);
        //var_dump($opts);
        //die();
        $context  = stream_context_create($opts);
        $response = file_get_contents($url, false, $context);
        //return $response;
        $this->apiResponse->setHttpCode(200);
        $this->apiResponse->setBody($response);
    }
}
