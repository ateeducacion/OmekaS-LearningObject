<?php
declare(strict_types=1);

namespace Omeka\Entity;

class Media
{
    protected $data = [];
    protected $id;
    protected $source;
    
    public function __construct($id = 1)
    {
        $this->id = $id;
    }
    
    public function getId()
    {
        return $this->id;
    }
    
    public function getData()
    {
        return $this->data;
    }
    
    public function setData(array $data)
    {
        $this->data = $data;
        return $this;
    }
    public function setSource($source)
    {
        $this->source = $source;
        return $this;
    }
}
