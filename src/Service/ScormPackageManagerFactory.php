<?php
namespace LearningObjectAdapter\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ScormPackageManagerFactory implements FactoryInterface
{
    /**
     * Create the ScormPackageManager ingester.
     *
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return ScormPackageManager
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new ScormPackageManager(
            $container->get('Omeka\File\Store')
        );
    }
}