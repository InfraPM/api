<?php
require __DIR__ . '/../Api.php';

/**
 * simple proxy to forward requests for doc resources to the
 * RRAC application
 */
class LayerInfoApi extends Api
{
    public function __construct()
    {
        parent::__construct();
        $this->apiResponse->setFormat("application/json");
    }
    /**
     * forward the doc resouce request to the RRAC application
     */
    public function processRequest(): void
    {
        $layerName = null;
        if (isset($this->apiRequest->getVar['layerName'])) {
            $layerName = $this->apiRequest->getVar['layerName'];
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
        $context  = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context); //Note: @ suppresses all warning messages 
        if ($response === false) {
            $this->apiResponse->setHttpCode(500);
            $this->apiResponse->setBody("");
        } else {
            $this->apiResponse->setHttpCode(200);
            $this->apiResponse->setBody($response);
        }
    }
}
