<?php
namespace LearningObjectAdapter\Media\Ingester;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LearningObjectAdapter\Service\ScormPackageManager;

class LearningObjectFactory implements FactoryInterface
{
    /**
     * Create the LearningObject ingester.
     *
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return LearningObject
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    { 
        return new LearningObject(
            $container->get('Omeka\File\Uploader'),
            $container->get('Omeka\ApiManager'),
            $container->get('Omeka\Job\Dispatcher'),
            $container->get(ScormPackageManager::class)
        );
    }
}
