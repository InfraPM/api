<?php
require __DIR__ . '/../Api.php';
class ToGeoJsonApi extends Api
{
    private $requestUrl;
    private $apiKey;
    private $mode;

    public function __construct()
    {
        parent::__construct();
        #$this->apiKey = $_ENV['rttiApiKey'];
        $this->apiKey = "jsFcbRMdZM5pZZ2ZINdx";
    }
    public function readRequest(): void
    {
        if (isset($this->apiRequest->getVar['mode'])) {
            $this->mode = $this->apiRequest->getVar['mode'];
            if ($this->mode == "rtti") {
                if (isset($this->apiRequest->getVar['url'])) {
                    $this->requestUrl = $this->apiRequest->getVar['url'] . "apikey=" . $this->apiKey;
                }
            }
        }
    }
    public function generateResponse(): void
    {
        $arrContextOptions = array(
            "http" => array(
                "method" => "GET",
                "header" => "Accept: application/JSON\r\n"
            ),
            "ssl" => array(
                "verify_peer" => false,
                "verify_peer_name" => false,
            ),
        );
        $this->apiResponse->setHttpCode(200);
        $this->apiResponse->setFormat("application/json");
        $this->apiResponse->setBody("");
        $response = file_get_contents($this->requestUrl, false, stream_context_create($arrContextOptions));
        $jsonResponse = json_decode($response, TRUE);
        $featureCollection = array();
        $featureCollection['type'] = "FeatureCollection";
        $featureCollection['features'] = array();
        foreach ($jsonResponse as $key => $value) {
            $curBusFeature = array();
            $curBusFeature['type'] = "Feature";
            $curBusFeature['geometry']['type'] = "Point";
            $curBusFeature['geometry']['coordinates'] = array();
            $curLong = "";
            $curLat = "";
            foreach ($value as $busKey => $busValue) {
                if ($busKey == "Latitude") {
                    $curLat = $busValue;
                } else if ($busKey == "Longitude") {
                    $curLong = $busValue;
                } else {
                    $curBusFeature['properties'][$busKey] = $busValue;
                }
            }
            array_push($curBusFeature['geometry']['coordinates'], $curLong);
            array_push($curBusFeature['geometry']['coordinates'], $curLat);
            array_push($featureCollection['features'], $curBusFeature);
        }
        $featureCollectionJson = json_encode($featureCollection);
        echo $featureCollectionJson;
    }
}
