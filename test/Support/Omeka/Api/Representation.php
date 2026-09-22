<?php

namespace Omeka\Api\Representation;

class MediaRepresentation
{
    public $data = [];
    public $ingester = 'LearningObject';
    public $thumbnails = false;

    public function mediaData()
    {
        return $this->data;
    }

    public function ingester()
    {
        return $this->ingester;
    }

    public function hasThumbnails()
    {
        return $this->thumbnails;
    }

    public function displayTitle()
    {
        return '<Course>';
    }
}
