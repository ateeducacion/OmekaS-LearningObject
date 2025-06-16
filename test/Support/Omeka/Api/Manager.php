<?php
declare(strict_types=1);

namespace Omeka\Api;

class Manager
{
    public function create($resource, array $data = [], array $fileData = [], array $options = [])
    {
        return new \stdClass();
    }
    
    public function read($resource, $id, array $data = [], array $options = [])
    {
        return new \stdClass();
    }
    
    public function update($resource, $id, array $data = [], array $fileData = [], array $options = [])
    {
        return new \stdClass();
    }
    
    public function delete($resource, $id, array $data = [], array $options = [])
    {
        return new \stdClass();
    }
    
    public function search($resource, array $data = [], array $options = [])
    {
        return new \stdClass();
    }
}
