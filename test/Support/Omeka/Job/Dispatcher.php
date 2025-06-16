<?php
declare(strict_types=1);

namespace Omeka\Job;

class Dispatcher
{
    public function dispatch($jobClass, array $args = [])
    {
        return new \stdClass();
    }
    
    public function setQueueName($queueName)
    {
        return $this;
    }
    
    public function setStrategy($strategy)
    {
        return $this;
    }
}
