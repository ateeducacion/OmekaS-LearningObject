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
        return ['zip', 'elp'];
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
        return ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'];
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

        $filename = $fileData['file'][$index]['name'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mediaType = $finfo->file($fileData['file'][$index]['tmp_name']);
        if (!in_array($mediaType, ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'])) {
            throw new \Omeka\Api\Exception\ValidationException('Formato de archivo inválido. Solo se permiten archivos zip o elp.');
        }

        $tempFile = $this->uploader->upload($fileData['file'][$index], $errorStore);
        if (!$tempFile) {
            return;
        }

        $isExeLearning3 = false;
        // Si es .elp, analizar su contenido
        if ($extension === 'elp') {
            $zipPath = $tempFile->getTempPath();
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) === true) {
                $hasContentXml = false;
                $hasIndexHtml = false;
                $hasContentV3Xml = false;
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);
                    if ($name === 'content.xml') $hasContentXml = true;
                    if ($name === 'index.html') $hasIndexHtml = true;
                    if ($name === 'contentv3.xml') $hasContentV3Xml = true;
                }
                $zip->close();
                if ($hasContentXml && $hasIndexHtml) {
                    // eXe 3.x: tratar como SCORM-like
                    $isExeLearning3 = true;
                } elseif ($hasContentV3Xml && !$hasIndexHtml) {
                    $errorStore->addError('elp', 'El archivo eXeLearning (.elp) es de una versión antigua (2.x) y no puede visualizarse directamente.\nAbra el paquete con eXeLearning 3.x y expórtelo de nuevo para actualizarlo.');
                    return;
                } else {
                    $errorStore->addError('elp', 'El archivo .elp no tiene la estructura esperada para eXeLearning 3.x.');
                    return;
                }
            } else {
                $errorStore->addError('elp', 'No se pudo abrir el archivo .elp como ZIP.');
                return;
            }
        }

        // Validar SCORM o eXe 3.x
        if (!$isExeLearning3 && !$this->ScormPackageManager->isValidScormPackage($tempFile, $errorStore)) {
            return;
        }

        $extractionDir = 'scorm_' . uniqid();
        $extractedPath = $this->ScormPackageManager->extractScormPackage($tempFile, $extractionDir, $errorStore);
        if (!$extractedPath) {
            return;
        }

        $scormInfo = $this->ScormPackageManager->getScormInfo($extractedPath);
        // Si es eXeLearning 3.x, forzar el entry_point a index.html
        if ($isExeLearning3) {
            $scormInfo['entry_point'] = 'index.html';
            $scormInfo['title'] = $scormInfo['title'] ?: 'eXeLearning 3.x';
        }

        $media->setData([
            'learning_object' => true,
            'learning_object_data' => [
                'type' => ($extension === 'elp') ? 'eXeLearning' : 'SCORM',
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
