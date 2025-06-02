<?php
namespace LearningObjectAdapter\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use LearningObjectAdapter\View\Helper\MediaThumbnail;
use Laminas\ServiceManager\Factory\FactoryInterface;

class MediaThumbnailFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new MediaThumbnail();
    }
}