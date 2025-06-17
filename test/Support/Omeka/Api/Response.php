<?php
declare(strict_types=1);

namespace Omeka\Api;

class Response
{
    protected $content;
    
    public function __construct($content = null)
    {
        $this->content = $content;
    }
    
    public function getContent()
    {
        return $this->content;
    }
    
    public function setContent($content)
    {
        $this->content = $content;
        return $this;
    }
}
