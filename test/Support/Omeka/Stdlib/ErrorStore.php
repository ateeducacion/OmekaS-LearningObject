<?php
declare(strict_types=1);

namespace Omeka\Stdlib;

class ErrorStore
{
    protected $errors = [];
    
    public function addError($key, $message)
    {
        $this->errors[$key][] = $message;
    }
    
    public function getErrors()
    {
        return $this->errors;
    }
    
    public function hasErrors()
    {
        return !empty($this->errors);
    }
}
