<?php
// src/View/Helper/MediaThumbnail.php
namespace LearningObjectAdapter\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\View\Helper\Thumbnail;

class MediaThumbnail extends Thumbnail
{
    public function __invoke($media, $type = 'medium', array $attribs = [])
    {
        //
        // Check if this is a learning object media
        if ($media instanceof MediaRepresentation) {
            if ($media->ingester() === 'LearningObject') {
                return $this->renderLearningObjectThumbnail($media, $type, $attribs);
            }
        }
        
        // Fall back to default thumbnail rendering*/
        return parent::__invoke($media, $type, $attribs);
    }

    protected function renderLearningObjectThumbnail(MediaRepresentation $media, $type, array $attribs)
    {
        $view = $this->getView();
        
        // Check if media has thumbnails
        if ($media->hasThumbnails()) {
            return parent::__invoke($media, $type, $attribs);
        }
        
        // Use default SCORM icon
        $defaultIcon = $view->basePath() . '/modules/LearningObjectAdapter/asset/img/learning-object-thumb.png';
        
        $attribs['src'] = $defaultIcon;
        $attribs['alt'] = $media->displayTitle();
        $attribs['class'] = isset($attribs['class']) ? $attribs['class'] . ' learning-object-thumbnail' : 'learning-object-thumbnail';
        return '<img ' . $this->htmlAttribs($attribs) . ' />';
    }
}
