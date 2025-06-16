<?php
declare(strict_types=1);

namespace Laminas\EventManager;

class Event
{
    protected $params = [];
    protected $name;
    protected $target;
    
    public function __construct($name = null, $target = null, $params = null)
    {
        $this->name = $name;
        $this->target = $target;
        $this->params = $params ?: [];
    }
    
    public function getParam($name, $default = null)
    {
        return isset($this->params[$name]) ? $this->params[$name] : $default;
    }
    
    public function setParam($name, $value)
    {
        $this->params[$name] = $value;
        return $this;
    }
    
    public function getParams()
    {
        return $this->params;
    }
    
    public function setParams(array $params)
    {
        $this->params = $params;
        return $this;
    }
    
    public function getName()
    {
        return $this->name;
    }
    
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }
    
    public function getTarget()
    {
        return $this->target;
    }
    
    public function setTarget($target)
    {
        $this->target = $target;
        return $this;
    }
}
