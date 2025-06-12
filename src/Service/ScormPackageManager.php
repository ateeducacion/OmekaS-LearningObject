<?php
namespace LearningObjectAdapter\Service;

use ZipArchive;
use Omeka\File\TempFile;
use Omeka\Stdlib\ErrorStore;
use Omeka\File\Store\StoreInterface;

class ScormPackageManager
{
    /**
     * @var string Base path for storing extracted SCORM packages
     */
    protected $basePath;

    /**
     * @var Store object to storing extracted SCORM packages
     */
    protected $store;

    /**
     * @var array Required SCORM files for validation
     */
    protected $requiredScormFiles = [
        'imsmanifest.xml'
    ];

    /**
     * @var array Optional SCORM files that indicate SCORM content
     */
    protected $optionalScormFiles = [
        'adlcp_rootv1p2.xsd',
        'ims_xml.xsd',
        'imscp_rootv1p1p2.xsd',
        'imsmd_rootv1p2p1.xsd'
    ];

    public function __construct(StoreInterface $store)
    {
        $this->store=$store;
        $this->basePath=$this->store->getLocalPath('zips');
        //$basePath = rtrim($basePath, '/');
    }

    /**
     * Check if the uploaded file is a valid SCORM package
     *
     * @param TempFile $tempFile
     * @param ErrorStore $errorStore
     * @return bool
     */
    public function isValidScormPackage(TempFile $tempFile, ErrorStore $errorStore)
    {
        $zipPath = $tempFile->getTempPath();
        
        if (!file_exists($zipPath)) {
            $errorStore->addError('scorm', 'Uploaded file does not exist.');
            return false;
        }

        $zip = new ZipArchive();
        $result = $zip->open($zipPath);

        if ($result !== true) {
            $errorStore->addError('scorm', 'Could not open zip file. Error code: ' . $result);
            return false;
        }

        // Check for required SCORM files
        $foundRequired = 0;
        $foundOptional = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            $basename = basename($filename);

            // Check for required files
            if (in_array($basename, $this->requiredScormFiles)) {
                $foundRequired++;
            }

            // Check for optional SCORM indicator files
            if (in_array($basename, $this->optionalScormFiles)) {
                $foundOptional++;
            }
        }

        $zip->close();

        // Must have imsmanifest.xml at minimum
        if ($foundRequired === 0) {
            $errorStore->addError('scorm', 'Invalid SCORM package: missing imsmanifest.xml file.');
            return false;
        }

        // Additional validation: check if imsmanifest.xml contains SCORM-specific content
        if (!$this->validateManifestContent($tempFile, $errorStore)) {
            return false;
        }

        return true;
    }

    /**
     * Validate the content of imsmanifest.xml to ensure it's SCORM
     *
     * @param TempFile $tempFile
     * @param ErrorStore $errorStore
     * @return bool
     */
    protected function validateManifestContent(TempFile $tempFile, ErrorStore $errorStore)
    {
        $zipPath = $tempFile->getTempPath();
        $zip = new ZipArchive();
        
        if ($zip->open($zipPath) !== true) {
            return false;
        }

        $manifestContent = $zip->getFromName('imsmanifest.xml');
        $zip->close();

        if ($manifestContent === false) {
            $errorStore->addError('scorm', 'Could not read imsmanifest.xml content.');
            return false;
        }

        // Check for SCORM-specific XML elements
        $scormIndicators = [
            'adlcp:',
            'schemaversion',
            'scorm',
            'sco',
            'asset'
        ];

        $foundIndicators = 0;
        foreach ($scormIndicators as $indicator) {
            if (stripos($manifestContent, $indicator) !== false) {
                $foundIndicators++;
            }
        }

        if ($foundIndicators === 0) {
            //$errorStore->addError('scorm', 'imsmanifest.xml does not appear to contain SCORM content.');
            //return false;
        }

        return true;
    }

    /**
     * Extract SCORM package contents to a directory
     *
     * @param TempFile $tempFile
     * @param string $extractionDir Directory name within files/original
     * @param ErrorStore $errorStore
     * @return string|false Path to extracted directory or false on failure
     */
    public function extractScormPackage(TempFile $tempFile, $extractionDir, ErrorStore $errorStore)
    {
        $zipPath = $tempFile->getTempPath();
        
        if (!file_exists($zipPath)) {
            $errorStore->addError('scorm', 'Source zip file does not exist.');
            return false;
        }

        // Create extraction path
        $extractPath = $this->basePath . '/original/' . $extractionDir;
        
        // Ensure the directory doesn't already exist
        if (file_exists($extractPath)) {
            $extractPath = $this->generateUniqueDirectory($extractPath);
        }

        // Create directory with proper permissions
        if (!$this->createDirectory($extractPath, $errorStore)) {
            return false;
        }

        $zip = new ZipArchive();
        $result = $zip->open($zipPath);

        if ($result !== true) {
            $errorStore->addError('scorm', 'Could not open zip file for extraction. Error code: ' . $result);
            return false;
        }

        // Extract with security checks
        if (!$this->secureExtraction($zip, $extractPath, $errorStore)) {
            $zip->close();
            $this->cleanupDirectory($extractPath);
            return false;
        }

        $zip->close();

        return $extractPath;
    }

    /**
     * Perform secure extraction to prevent directory traversal attacks
     *
     * @param ZipArchive $zip
     * @param string $extractPath
     * @param ErrorStore $errorStore
     * @return bool
     */
    protected function secureExtraction(ZipArchive $zip, $extractPath, ErrorStore $errorStore)
    {
        $extractPath = realpath($extractPath);
        
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            
            // Security check: prevent directory traversal
            if (strpos($filename, '..') !== false || strpos($filename, '/..') !== false) {
                $errorStore->addError('scorm', 'Security violation: attempted directory traversal in zip file.');
                return false;
            }

            // Skip directories and hidden files
            if (substr($filename, -1) === '/' || substr(basename($filename), 0, 1) === '.') {
                continue;
            }

            $targetPath = $extractPath . '/' . $filename;
            $targetDir = dirname($targetPath);

            // Ensure target directory exists
            if (!is_dir($targetDir)) {
                if (!mkdir($targetDir, 0755, true)) {
                    $errorStore->addError('scorm', 'Could not create directory: ' . $targetDir);
                    return false;
                }
            }

            // Verify the target path is within our extraction directory
            if (strpos(realpath($targetDir), $extractPath) !== 0) {
                $errorStore->addError('scorm', 'Security violation: attempted to extract outside target directory.');
                return false;
            }

            // Extract the file
            $fileContent = $zip->getFromIndex($i);
            if ($fileContent === false) {
                $errorStore->addError('scorm', 'Could not read file from zip: ' . $filename);
                return false;
            }

            if (file_put_contents($targetPath, $fileContent) === false) {
                $errorStore->addError('scorm', 'Could not write extracted file: ' . $targetPath);
                return false;
            }
        }

        return true;
    }

    /**
     * Create directory with proper permissions
     *
     * @param string $path
     * @param ErrorStore $errorStore
     * @return bool
     */
    protected function createDirectory($path, ErrorStore $errorStore)
    {
        if (!mkdir($path, 0755, true)) {
            $errorStore->addError('scorm', 'Could not create extraction directory: ' . $path);
            return false;
        }

        return true;
    }

    /**
     * Generate a unique directory name if the target already exists
     *
     * @param string $basePath
     * @return string
     */
    protected function generateUniqueDirectory($basePath)
    {
        $counter = 1;
        $newPath = $basePath;

        while (file_exists($newPath)) {
            $newPath = $basePath . '_' . $counter;
            $counter++;
        }

        return $newPath;
    }

    /**
     * Clean up directory in case of extraction failure
     *
     * @param string $path
     */
    protected function cleanupDirectory($path)
    {
        if (is_dir($path)) {
            $this->removeDirectory($path);
        }
    }

    /**
     * Recursively remove directory and its contents
     *
     * @param string $dir
     */
    protected function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /**
     * Get SCORM package information from extracted directory
     *
     * @param string $extractedPath
     * @return array|false
     */
    public function getScormInfo($extractedPath)
    {
        $manifestPath = $extractedPath . '/imsmanifest.xml';
        
        if (!file_exists($manifestPath)) {
            return false;
        }

        $xml = simplexml_load_file($manifestPath);

        if ($xml === false) {
            return false;
        }

        // Extract basic SCORM information
        $info = [
            'title' => '',
            'description' => '',
            'version' => '',
            'schemaversion' => '',
            'entry_point' => ''
        ];

        // Get title and description from metadata
        if (isset($xml->metadata->lom->general->title->langstring)) {
            $info['title'] = (string) $xml->metadata->lom->general->title->langstring;
        }

        if (isset($xml->organizations->organization->title)) {
            $info['title'] = (string) $xml->organizations->organization->title;
        }

        if (isset($xml->metadata->lom->general->description->langstring)) {
            $info['description'] = (string) $xml->metadata->lom->general->description->langstring;
        }

        // Get schema version
        if (isset($xml->metadata->schemaversion)) {
            $info['schemaversion'] = (string) $xml->metadata->schemaversion;
        }

        // Find entry point (launch file)
        if (isset($xml->organizations->organization->item)) {
            foreach ($xml->organizations->organization->item as $item) {
                if (isset($item['identifierref'])) {
                    $resourceId = (string) $item['identifierref'];
                    
                    // Find corresponding resource
                    if (isset($xml->resources->resource)) {
                        foreach ($xml->resources->resource as $resource) {
                            if ((string) $resource['identifier'] === $resourceId) {
                                $info['entry_point'] = (string) $resource['href'];
                                break;
                            }
                        }
                    }
                    break;
                }
            }
        }

        return $info;
    }
}
