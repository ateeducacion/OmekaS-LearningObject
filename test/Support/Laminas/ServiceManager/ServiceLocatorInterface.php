<?php
declare(strict_types=1);

namespace Laminas\ServiceManager;

interface ServiceLocatorInterface
{
    public function get($name);
    public function has($name);
}
