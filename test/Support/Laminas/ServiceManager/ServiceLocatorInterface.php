<?php
declare(strict_types=1);

namespace Laminas\ServiceManager;

interface ServiceLocatorInterface extends \Interop\Container\ContainerInterface
{
    public function get($name);
    public function has($name);
}
