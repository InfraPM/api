<?php

require __DIR__ . '/../Api.php';

class OwsApi extends Api
{
    protected $request = NULL;
    protected $token = NULL;
    protected $public = TRUE;
    protected $dataList = '';

    public function __construct()
    {
        parent::__construct();
    }

    public function readRequest(): void
    {
        if (isset($this->apiRequest->getVar['request'])) {
            $this->request = $this->apiRequest->getVar['request'];
        }
        if (isset($this->apiRequest->getVar['token'])) {
            $this->token = $this->apiRequest->getVar['token'];
        } else {
            $this->token = 'public';
        }
        if ($this->token == 'public' || $this->token == '' || $this->token == NULL) {
            $this->public = TRUE;
        } else {
            $this->public = FALSE;
        }
    }
}
