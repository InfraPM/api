<?php
require __DIR__ . '/ApiRequest.php';
require __DIR__ . '/ApiResponse.php';
require __DIR__ . '/../support/User.php';

class Api
{
    private $apiResponse;
    private $apiRequest;
    private $dbCon;
    private $user;

    public function __construct()
    {
        $apiRequest = new ApiRequest();
        $apiResponse = new ApiResponse();
        $this->setApiRequest($apiRequest);
        $this->setApiResponse($apiResponse);
        $dbCon = new DbCon($_ENV['host'], $_ENV['port'], $_ENV['db'], $_ENV['dbuser'], $_ENV['dbpassword']);
        $user = new User();
        $user->setDbCon($dbCon);
        $this->setDbCon($dbCon);
        $this->setUser($user);
        $baseURL = $_ENV['baseURL'];
        $originWhiteListStr = $_ENV['originWhiteList'];
        $originWhiteList = explode('|',$originWhiteListStr);
        $originUrl = $baseURL . "regionalroads.com";
        $allowedOrigins = array_merge($originWhiteList, [$originUrl]);
        $headers = array(
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS, HEAD',
            'Access-Control-Max-Age' => '1000',
            'Access-Control-Allow-Headers' => 'Origin, Accept, Content-Type, X-Auth-Token, Authorization, Access-Control-Request-Method, Access-Control-Request-Headers, X-Requested-With'
        );
        if (isset($_SERVER["HTTP_ORIGIN"]) && in_array($_SERVER["HTTP_ORIGIN"], $allowedOrigins)) {
            $headers['Access-Control-Allow-Origin'] = $_SERVER["HTTP_ORIGIN"];
        }

        $this->apiResponse->setHeaders($headers);
    }
    /**
     * Return the requested property of the class
     * 
     * @param string $property The property to return
     */
    public function __get(string $property)
    {
        if (isset($this->$property)) {
            return $this->$property;
        } else {
            throw new Exception('Property ' . get_class($this) . '::' . $property . ' does not exist.');
        }
    }
    /**
     * Set the ApiRequest of the ApiCall
     * 
     * @param ApiRequest $request The Request of the API Call
     */
    public function setApiRequest(ApiRequest $request): void
    {
        $this->apiRequest = $request;
    }
    /**
     * Set the API Response of the API Call
     * 
     * @param $response The API Response of the API Call
     */
    public function setApiResponse(ApiResponse $response): void
    {
        $this->apiResponse = $response;
    }
    /**
     * Send the Response of the API Call
     */
    public function sendResponse(): void
    {
        foreach ($this->apiResponse->headers as $key => $value) {
            header($key . ": " . $value);
        }
        header('Content-type: ' . $this->apiResponse->format);
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            http_response_code(200);
        } else {
            http_response_code($this->apiResponse->httpCode);
            echo $this->apiResponse->body;
        }
        die();
    }
    /**
     * Set the database connection for the API Call
     * 
     * @param dbCon $dbCon The databse connection
     */
    public function setDbCon(DbCon $dbCon)
    {
        $this->dbCon = $dbCon;
    }
    /**
     * Set the user of the API Call
     * 
     * @param User $user The user of the API Call
     */
    public function setUser(User $user): void
    {
        $this->user = $user;
    }
}
