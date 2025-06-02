<?php
namespace LearningObjectAdapter\Media\Ingester;

use Omeka\Media\Ingester\IngesterInterface;
use Omeka\File\Uploader;
use Omeka\Api\Manager;
use Omeka\Job\Dispatcher;
use Omeka\Entity\Media;
use Laminas\Form\Element\File;
use Laminas\Form\Element\Hidden;
use Omeka\Api\Request;
use Omeka\Stdlib\ErrorStore;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\Form\Element\Text;
use LearningObjectAdapter\Service\ScormPackageManager;

class LearningObject implements IngesterInterface
{
    /**
     * @var uploader
     */
    protected $uploader;

    /**
     * @var ScormPackageManager
     */
    protected $ScormPackageManager;

    /**
     * @var ApiManager
     */
    protected $apiManager;

    /**
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * Constructor.
     *
     * @param uploader $uploader
     * @param ApiManager $apiManager
     * @param Dispatcher $dispatcher
     */
    public function __construct(Uploader $uploader, Manager $apiManager, Dispatcher $dispatcher, ScormPackageManager $ScormPackageManager)
    {
        $this->uploader = $uploader;
        $this->apiManager = $apiManager;
        $this->dispatcher = $dispatcher;
        $this->ScormPackageManager=$ScormPackageManager;
    }

    /**
     * Get the ingester's label.
     *
     * @return string
     */
    public function getLabel()
    {
        return 'Learning Object (Zip)';
    }

    /**
     * Get the ingester's description.
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Ingest a learning object zip file by uploading it.';
    }

    /**
     * Get the ingester's allowed file extensions.
     *
     * @return array
     */
    public function getFileExtensions()
    {
        return ['zip'];
    }
    /**
     * Get the ingester's allowed media types.
     *
     * @return array
     */
    public function getRenderer()
    {
        return 'LearningObject';
    }
    /**
     * Get the ingester's allowed media types.
     *
     * @return array
     */
    public function getMediaTypes()
    {
        return ['application/zip'];
    }

    /**
     * Ingest media from the given data.
     *
     * @param Media $media
     * @param array $data
     * @param array $options
     * @return void
     */
    public function ingest(Media $media, Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();
        $fileData = $request->getFileData();

        if (!isset($fileData['file'])) {
            $errorStore->addError('error', 'No files were uploaded');
            return;
        }

        if (!isset($data['file_index'])) {
            $errorStore->addError('error', 'No file index was specified');
            return;
        }

        $index = $data['file_index'];
        if (!isset($fileData['file'][$index])) {
            $errorStore->addError('error', 'No file uploaded for the specified index');
            return;
        }

         // Validate that it's a zip file
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mediaType = $finfo->file($fileData['file'][$index]['tmp_name']);
        if ($mediaType !== 'application/zip' && $mediaType !== 'application/x-zip-compressed') {
            throw new \Omeka\Api\Exception\ValidationException('Invalid file format. Only zip files are allowed.');
        }



        $tempFile = $this->uploader->upload($fileData['file'][$index], $errorStore);
        if (!$tempFile) {
            return;
        }

        // Validate SCORM package
        if (!$this->ScormPackageManager->isValidScormPackage($tempFile, $errorStore)) {
            return;
        }

        // Generate unique directory name for extraction
        $extractionDir = 'scorm_' . uniqid();
        
        // Extract SCORM package
        $extractedPath = $this->ScormPackageManager->extractScormPackage($tempFile, $extractionDir, $errorStore);
        if (!$extractedPath) {
            return;
        }

        // Get SCORM information
        $scormInfo = $this->ScormPackageManager->getScormInfo($extractedPath);


        $media->setData([
            'learning_object' => true,
            'learning_object_data' => [
                'type' => 'SCORM',
                'extraction_path' => $extractionDir,
                'scorm_info' => $scormInfo ?: [],
            ],
        ]);


        $tempFile->setSourceName($fileData['file'][$index]['name']);
        if (!array_key_exists('o:source', $data)) {
            $media->setSource($fileData['file'][$index]['name']);
        }

        $tempFile->mediaIngestFile($media, $request, $errorStore);
    }

    /**
     * Get the form element for the ingester.
     *
     * @return
     */
    public function form(PhpRenderer $view, array $options = [])
    {
        $infoTemplate = '
        <div class="media-file-info">
            <div class="media-file-thumbnail"></div>
            <div class="media-file-size">
        </div>';

        $fileInput = new File('file[__index__]');
        $fileInput->setOptions([
            'label' => 'Learning Object Zip File', // @translate
            'info' => 'Upload a learning object zip file. Only ZIP files are allowed.', // @translate
        ])
        ->setAttributes([
            'enctype' => 'multipart/form-data',
            'required' => true,
            'accept' => 'application/zip',
            'class' => 'media-file-input',
            'data-info-template' => $infoTemplate,
            
        ]);

        // Create the hidden input
        $hiddenInput = new Hidden('o:media[__index__][file_index]');
        $hiddenInput->setValue('__index__')
        ->setAttributes([
            'id' => 'hidden-field-__index__'
        ]);
        
        return  $view->formRow($fileInput) .
                $view->formRow($hiddenInput);
    }
}
