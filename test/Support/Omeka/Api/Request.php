<?php
declare(strict_types=1);

namespace Omeka\Api;

class Request
{
    protected $resource;
    protected $operation;
    protected $id;
    protected $content = [];
    protected $fileData = [];
    
    public function __construct($resource = null, $operation = null)
    {
        $this->resource = $resource;
        $this->operation = $operation;
    }
    
    public function getResource()
    {
        return $this->resource;
    }
    
    public function setResource($resource)
    {
        $this->resource = $resource;
        return $this;
    }
    
    public function getOperation()
    {
        return $this->operation;
    }
    
    public function setOperation($operation)
    {
        $this->operation = $operation;
        return $this;
    }
    
    public function getId()
    {
        return $this->id;
    }
    
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }
    
    public function getContent()
    {
        return $this->content;
    }
    
    public function setContent(array $content)
    {
        $this->content = $content;
        return $this;
    }
    
    public function getFileData()
    {
        return $this->fileData;
    }
    
    public function setFileData(array $fileData)
    {
        $this->fileData = $fileData;
        return $this;
    }
}
