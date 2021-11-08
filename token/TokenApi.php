<?php
require __DIR__ . '/../Api.php';
class TokenApi extends Api
{
    private $userName;
    private $password;
    private $token;


    public function __construct()
    {
        parent::__construct();
        $this->apiRequest->setType("POST");
        $this->apiResponse->setFormat("application/json");
    }

    public function readRequest(): void
    {
        $postData = json_decode($this->apiRequest->postVar, TRUE);
        if (isset($postData['userName'])) {
            $this->userName = $postData['userName'];
            if (isset($postData['password'])) {
                $this->password = $postData['password'];
                $this->user->setUserName($this->userName);
                $this->user->setPassword($this->password);
                $this->user->checkPassword();
                if ($this->user->isValid()) {
                    $this->user->getToken_db();
                    $this->user->getUserFromToken();
                    $this->user->checkToken();
                    $this->token = $this->user->refreshToken();
                    $this->apiResponse->setHttpCode(200);
                    $this->apiResponse->setBody($this->token);
                } else {
                    $this->apiResponse->setHttpCode(401);
                    $this->apiResponse->setBody('{"error": "Unauthorized"}');
                }
            } else {
                $this->apiResponse->setHttpCode(400);
                $this->apiResponse->setBody('{"error": "Invalid Parameters"}');
            }
        } else {
            $this->apiResponse->setHttpCode(400);
            $this->apiResponse->setBody('{"error": "Invalid Parameters"}');
        }
    }
}
