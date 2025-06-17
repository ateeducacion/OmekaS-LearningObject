<?php
namespace LearningObjectAdapter\Media\Renderer;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\Renderer\RendererInterface;

class LearningObject implements RendererInterface
{
    public function render(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        $data = $media->mediaData();
        $learningObjectData = isset($data['learning_object_data']) ? $data['learning_object_data'] : [];

        $html = '<div class="learning-object-container">';
        
        if (!empty($learningObjectData) && isset($learningObjectData['type'])
            && in_array($learningObjectData['type'], ['SCORM', 'eXeLearning'])) {
            // Use partial for SCORM packages
            $html .= $view->partial('common/scorm-package', [
                'learningObjectData' => $learningObjectData,
                'media' => $media,
                'options' => $options,
                'formatFileSize' => [$this, 'formatFileSize']
            ]);
        } else {
            // Handle other types of learning objects
            $html .= '<div class="learning-object-content">';
            
            if (!empty($learningObjectData)) {
                if (isset($learningObjectData['scorm_info']['title'])) {
                    $html .= '<h3>' . $view->escapeHtml($learningObjectData['scorm_info']['title']) . '</h3>';
                }
                
                if (isset($learningObjectData['description'])) {
                    $html .= '<div class="description">' .
                        $view->escapeHtml($learningObjectData['description']) .
                        '</div>';
                }

                if (isset($learningObjectData['content'])) {
                    $html .= '<div class="content">' .
                        $learningObjectData['content'] .
                        '</div>';
                }
            } else {
                $html .= '<p>No learning object data available.</p>';
            }
            
            $html .= '</div>';
        }

        $html .= '</div>' .$this->getInlineStyles();

        return $html;
    }
    /**
     * Get the thumbnail for learning object media
     *
     * @param PhpRenderer $view
     * @param MediaRepresentation $media
     * @param array $options
     * @return string
     */
    public function thumbnail(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        $data = $media->mediaData();
        $learningObjectData = isset($data['learning_object_data']) ? $data['learning_object_data'] : [];

        // Default thumbnail path
        $thumbnailPath = $view->basePath() . '/modules/LearningObjectAdapter/asset/img/learning-object-thumb.png';
        
        // Check if this is a SCORM package and if there's a custom thumbnail
        if (!empty($learningObjectData) && isset($learningObjectData['type'])
            && $learningObjectData['type'] === 'SCORM') {
            $extractionPath = $learningObjectData['extraction_path'] ?? '';
            
            // Look for common thumbnail files in the SCORM package
            $possibleThumbnails = ['thumbnail.png', 'thumbnail.jpg', 'icon.png',
                                'icon.jpg', 'preview.png', 'preview.jpg'];
            
            foreach ($possibleThumbnails as $thumbFile) {
                $thumbPath = OMEKA_PATH . '/files/original/' . $extractionPath . '/' . $thumbFile;
                if (file_exists($thumbPath)) {
                    $thumbnailPath = $view->basePath() . '/files/original/' . $extractionPath . '/' . $thumbFile;
                    break;
                }
            }
        }

        $html = '<img src="' . $view->escapeHtmlAttr($thumbnailPath) . '" ';
        $html .= 'alt="' . $view->escapeHtmlAttr($media->displayTitle()) . '" ';
        $html .= 'class="learning-object-thumbnail" ';
        
        // Add size attributes if specified in options
        if (isset($options['width'])) {
            $html .= 'width="' . (int) $options['width'] . '" ';
        }
        if (isset($options['height'])) {
            $html .= 'height="' . (int) $options['height'] . '" ';
        }
        
        $html .= 'style="max-width: 100%; height: auto;" />';

        return $html;
    }
    /**
     * Get inline CSS styles for the SCORM renderer
     *
     * @return string
     */
    protected function getInlineStyles()
    {
        return '
        <style>
            .scorm-package {
                text-align: center;
                padding: 20px;
                border: 1px solid #ddd;
                border-radius: 8px;
                background-color: #f9f9f9;
                margin: 15px 0;
            }

            .scorm-title {
                color: #333;
                margin-bottom: 15px;
                font-size: 1.4em;
            }

            .scorm-description {
                margin: 15px 0 25px 0;
                color: #666;
                line-height: 1.6;
                font-style: italic;
            }

            .scorm-actions {
                display: flex;
                justify-content: center;
                gap: 15px;
                margin: 25px 0;
                flex-wrap: wrap;
            }

            .scorm-launch,
            .scorm-download {
                display: inline-block;
            }

            .scorm-launch-btn,
            .scorm-download-btn {
                display: inline-block;
                padding: 12px 24px;
                text-decoration: none;
                border-radius: 6px;
                font-weight: bold;
                transition: all 0.3s ease;
                border: none;
                cursor: pointer;
                font-size: 14px;
                min-width: 180px;
            }

            .scorm-launch-btn {
                background-color: #007cba;
                color: white;
            }

            .scorm-launch-btn:hover {
                background-color: #005a87;
                color: white;
                text-decoration: none;
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0, 124, 186, 0.3);
            }

            .scorm-download-btn {
                background-color: #007cba;
                color: white;
            }

            .scorm-download-btn:hover {
                background-color: #005a87;
                color: white;
                text-decoration: none;
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(108, 117, 125, 0.3);
            }

            .scorm-launch-btn i,
            .scorm-download-btn i {
                margin-right: 8px;
            }

            .scorm-metadata {
                margin-top: 20px;
                padding-top: 15px;
                border-top: 1px solid #ddd;
            }

            .scorm-metadata > div {
                margin: 5px 0;
            }

            .scorm-metadata i {
                margin-right: 5px;
                color: #888;
            }

            .alert {
                padding: 15px;
                border-radius: 6px;
                margin: 15px 0;
                border: 1px solid transparent;
            }

            .alert-warning {
                background-color: #fff3cd;
                border-color: #ffeaa7;
                color: #856404;
            }

            .alert i {
                margin-right: 8px;
            }

            .text-muted {
                color: #6c757d;
            }

            /* Responsive design */
            @media (max-width: 768px) {
                .scorm-actions {
                    flex-direction: column;
                    align-items: center;
                }
                
                .scorm-launch-btn,
                .scorm-download-btn {
                    width: 100%;
                    max-width: 250px;
                }
            }
        </style>';
    }
}
