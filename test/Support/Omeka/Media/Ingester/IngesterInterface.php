<?php
declare(strict_types=1);

namespace Omeka\Media\Ingester;

interface IngesterInterface
{
    public function getLabel();
    public function getDescription();
    public function getFileExtensions();
    public function getRenderer();
    public function getMediaTypes();
    public function ingest(\Omeka\Entity\Media $media, \Omeka\Api\Request $request, \Omeka\Stdlib\ErrorStore $errorStore);
    public function form(\Laminas\View\Renderer\PhpRenderer $view, array $options = []);
}
