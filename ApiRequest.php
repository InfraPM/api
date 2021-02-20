<?php
class ApiRequest
{
    private $url;
    private $getVar;
    private $postVar;
    private $type;

    public function __construct()
    {
    }
    /**
     * Return the requested property
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
     * Set the request URL
     * 
     * @param string $url The request URL
     */
    public function setUrl(string $url): void
    {
        $this->url = $url;
    }
    /**
     * Set the request $_GET variable
     * 
     * @param array $getVar The request $_GET variable
     */
    public function setGetVar(array $getVar): void
    {
        $this->getVar = $getVar;
    }
    /**
     * Set the request php://input variable
     * @param array $postVar The request $_POST variable
     */
    public function setRawPostVar(string $postVar): void
    {
        $this->postVar = $postVar;
    }
    /**
     * Set the request $_POST variable
     * @param array $postVar The request $_POST variable
     */
    public function setPostVar(array $postVar): void
    {
        $this->postVar = $postVar;
    }
    /**
     * Set the request type (GET, POST, etc.)
     * 
     * @param string $type The request type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }
}
