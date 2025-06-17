<?php
declare(strict_types=1);

namespace LearningObjectAdapterTest\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use LearningObjectAdapter\Module;
use LearningObjectAdapter\Media\Ingester\LearningObject;
use LearningObjectAdapter\Service\ScormPackageManager;
use Omeka\File\Uploader;
use Omeka\Api\Manager;
use Omeka\Job\Dispatcher;
use Omeka\Entity\Media;
use Omeka\Api\Request;
use Omeka\Api\Response;
use Omeka\Stdlib\ErrorStore;
use Omeka\File\TempFile;
use Omeka\File\Store\StoreInterface;
use Laminas\EventManager\Event;
use Laminas\ServiceManager\ServiceLocatorInterface;

/**
 * Integration test that tests the complete workflow from ingestion to deletion
 */
class LearningObjectWorkflowTest extends TestCase
{
    private Module $module;
    private LearningObject $ingester;
    private ScormPackageManager $scormPackageManager;
    private MockObject $serviceLocator;
    private MockObject $store;
    private string $tempDir;
    private string $testZipPath;

    protected function setUp(): void
    {
        // Get system temp directory and ensure it's writable
        $systemTempDir = sys_get_temp_dir();
        if (!is_writable($systemTempDir)) {
            $this->markTestSkipped('System temp directory is not writable');
            return;
        }
        
        // Create temporary directory with full permissions
        $this->tempDir = $systemTempDir . DIRECTORY_SEPARATOR . 'learning_object_workflow_test_' . uniqid();
        if (!mkdir($this->tempDir, 0777, true)) {
            $this->markTestSkipped('Could not create temporary directory');
            return;
        }
        chmod($this->tempDir, 0777); // Ensure directory is writable
        
        // Create subdirectories needed for extraction
        $originalDir = $this->tempDir . DIRECTORY_SEPARATOR . 'original';
        if (!mkdir($originalDir, 0777, true)) {
            $this->markTestSkipped('Could not create original directory');
            return;
        }
        chmod($originalDir, 0777);
        
        // Create mock store
        $this->store = $this->createMock(StoreInterface::class);
        $this->store->method('getLocalPath')->with('zips')->willReturn($this->tempDir);
        
        // Create real ScormPackageManager
        $this->scormPackageManager = new ScormPackageManager($this->store);
        
        // Create mock dependencies for ingester
        $uploader = $this->createMock(Uploader::class);
        $apiManager = $this->createMock(Manager::class);
        $dispatcher = $this->createMock(Dispatcher::class);
        
        // Create ingester
        $this->ingester = new LearningObject(
            $uploader,
            $apiManager,
            $dispatcher,
            $this->scormPackageManager
        );
        
        // Create module
        $this->module = new Module();
        
        // Create mock service locator
        $this->serviceLocator = $this->createMock(ServiceLocatorInterface::class);
        $this->serviceLocator->method('get')->willReturnMap([
            [ScormPackageManager::class, $this->scormPackageManager],
            ['Omeka\File\Store', $this->store]
        ]);
        
        $this->module->setServiceLocator($this->serviceLocator);
        
        // Create test SCORM package
        $this->testZipPath = $this->tempDir . '/test_scorm.zip';
        $this->createTestScormZip();
        
        // Verify test zip was created
        if (!file_exists($this->testZipPath)) {
            $this->markTestSkipped('Could not create test SCORM package');
            return;
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function testCompleteWorkflowFromIngestionToDeletion(): void
    {
        // Step 1: Test SCORM package validation
        $tempFile = $this->createMockTempFile($this->testZipPath);
        $errorStore = new ErrorStore();
        
        $isValid = $this->scormPackageManager->isValidScormPackage($tempFile, $errorStore);
        $this->assertTrue($isValid, 'SCORM package should be valid');
        $this->assertFalse($errorStore->hasErrors(), 'No errors should occur during validation');
        
        // Step 2: Test SCORM package extraction
        $extractionDir = 'scorm_workflow_test_' . uniqid();
        $extractedPath = $this->scormPackageManager->extractScormPackage($tempFile, $extractionDir, $errorStore);
        
        $this->assertNotFalse($extractedPath, 'Extraction should succeed');
        $this->assertTrue(is_dir($extractedPath), 'Extracted directory should exist');
        $this->assertTrue(file_exists($extractedPath . '/imsmanifest.xml'), 'Manifest should be extracted');
        $this->assertTrue(file_exists($extractedPath . '/index.html'), 'Entry point should be extracted');
        
        // Step 3: Test SCORM info extraction
        $scormInfo = $this->scormPackageManager->getScormInfo($extractedPath);
        $this->assertIsArray($scormInfo, 'SCORM info should be an array');
        $this->assertArrayHasKey('title', $scormInfo);
        $this->assertArrayHasKey('entry_point', $scormInfo);
        $this->assertEquals('Test SCORM Package', $scormInfo['title']);
        $this->assertEquals('index.html', $scormInfo['entry_point']);
        
        // Step 4: Simulate media creation with learning object data
        $media = $this->createMock(Media::class);
        $mediaData = [
            'learning_object' => true,
            'learning_object_data' => [
                'type' => 'SCORM',
                'extraction_path' => $extractionDir,
                'scorm_info' => $scormInfo
            ]
        ];
        $media->method('getData')->willReturn($mediaData);
        
        // Verify the extracted directory exists before deletion
        $this->assertTrue(is_dir($extractedPath), 'Directory should exist before deletion');
        
        // Step 5: Test media deletion event handling
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $response->method('getContent')->willReturn($media);
        
        $event = new Event();
        $event->setParam('request', $request);
        $event->setParam('response', $response);
        
        // Handle media deletion
        $this->module->handleMediaDeletion($event);
        
        // Step 6: Verify cleanup
        $this->assertFalse(is_dir($extractedPath), 'Directory should be deleted after media deletion');
        $this->assertFalse(file_exists($extractedPath . '/imsmanifest.xml'), 'Manifest should be deleted');
        $this->assertFalse(file_exists($extractedPath . '/index.html'), 'Entry point should be deleted');
    }

    public function testWorkflowWithMultipleFiles(): void
    {
        $extractionDirs = [];
        $extractedPaths = [];
        
        // Create multiple SCORM packages
        for ($i = 0; $i < 3; $i++) {
            $tempFile = $this->createMockTempFile($this->testZipPath);
            $errorStore = new ErrorStore();
            $extractionDir = 'scorm_multi_test_' . $i . '_' . uniqid();
            $extractionDirs[] = $extractionDir;
            
            $extractedPath = $this->scormPackageManager->extractScormPackage($tempFile, $extractionDir, $errorStore);
            $extractedPaths[] = $extractedPath;
            
            $this->assertNotFalse($extractedPath);
            $this->assertTrue(is_dir($extractedPath));
        }
        
        // Verify all directories exist
        foreach ($extractedPaths as $path) {
            $this->assertTrue(is_dir($path), 'All extracted directories should exist');
        }
        
        // Delete them one by one
        foreach ($extractionDirs as $index => $extractionDir) {
            $media = $this->createMock(Media::class);
            $media->method('getData')->willReturn([
                'learning_object' => true,
                'learning_object_data' => [
                    'extraction_path' => $extractionDir
                ]
            ]);
            
            $request = $this->createMock(Request::class);
            $response = $this->createMock(Response::class);
            $response->method('getContent')->willReturn($media);
            
            $event = new Event();
            $event->setParam('request', $request);
            $event->setParam('response', $response);
            
            $this->module->handleMediaDeletion($event);
            
            // Verify only this directory was deleted
            $this->assertFalse(is_dir($extractedPaths[$index]), "Directory $index should be deleted");
            
            // Verify other directories still exist
            for ($j = $index + 1; $j < count($extractedPaths); $j++) {
                $this->assertTrue(is_dir($extractedPaths[$j]), "Directory $j should still exist");
            }
        }
    }

    public function testWorkflowWithNestedDirectories(): void
    {
        // Create a SCORM package with nested structure
        $nestedZipPath = $this->tempDir . '/nested_scorm.zip';
        $this->createNestedScormZip($nestedZipPath);
        
        $tempFile = $this->createMockTempFile($nestedZipPath);
        $errorStore = new ErrorStore();
        $extractionDir = 'scorm_nested_test_' . uniqid();
        
        $extractedPath = $this->scormPackageManager->extractScormPackage($tempFile, $extractionDir, $errorStore);
        
        $this->assertNotFalse($extractedPath);
        $this->assertTrue(is_dir($extractedPath));
        $this->assertTrue(is_dir($extractedPath . '/assets'));
        $this->assertTrue(is_dir($extractedPath . '/assets/images'));
        $this->assertTrue(file_exists($extractedPath . '/assets/style.css'));
        $this->assertTrue(file_exists($extractedPath . '/assets/images/logo.png'));
        
        // Test deletion
        $media = $this->createMock(Media::class);
        $media->method('getData')->willReturn([
            'learning_object' => true,
            'learning_object_data' => [
                'extraction_path' => $extractionDir
            ]
        ]);
        
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $response->method('getContent')->willReturn($media);
        
        $event = new Event();
        $event->setParam('request', $request);
        $event->setParam('response', $response);
        
        $this->module->handleMediaDeletion($event);
        
        // Verify complete cleanup
        $this->assertFalse(is_dir($extractedPath));
        $this->assertFalse(is_dir($extractedPath . '/assets'));
        $this->assertFalse(is_dir($extractedPath . '/assets/images'));
        $this->assertFalse(file_exists($extractedPath . '/assets/style.css'));
        $this->assertFalse(file_exists($extractedPath . '/assets/images/logo.png'));
    }

    private function createTestScormZip(): void
    {
        // Ensure parent directory exists and is writable
        $parentDir = dirname($this->testZipPath);
        if (!is_dir($parentDir)) {
            if (!mkdir($parentDir, 0777, true)) {
                $this->markTestSkipped('Could not create parent directory for zip file');
                return;
            }
            chmod($parentDir, 0777);
        }
        
        $zip = new \ZipArchive();
        if ($zip->open($this->testZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $this->markTestSkipped('Could not create zip archive');
            return;
        }
        
        // Add required SCORM files
        $zip->addFromString('imsmanifest.xml', $this->getValidManifestContent());
        $zip->addFromString('index.html', '<html><body>Test SCORM Content</body></html>');
        $zip->addFromString('style.css', 'body { font-family: Arial; }');
        
        // Add optional SCORM schema files
        $zip->addFromString('adlcp_rootv1p2.xsd', '<?xml version="1.0"?><schema></schema>');
        $zip->addFromString('ims_xml.xsd', '<?xml version="1.0"?><schema></schema>');
        $zip->addFromString('imscp_rootv1p1p2.xsd', '<?xml version="1.0"?><schema></schema>');
        $zip->addFromString('imsmd_rootv1p2p1.xsd', '<?xml version="1.0"?><schema></schema>');
        
        $zip->close();
        
        // Verify the file was created
        if (!file_exists($this->testZipPath)) {
            $this->markTestSkipped('Failed to create zip file at: ' . $this->testZipPath);
            return;
        }
        
        // Ensure the zip file is writable
        chmod($this->testZipPath, 0666);
    }

    private function createNestedScormZip(string $path): void
    {
        // Ensure parent directory exists and is writable
        $parentDir = dirname($path);
        if (!is_dir($parentDir)) {
            if (!mkdir($parentDir, 0777, true)) {
                $this->markTestSkipped('Could not create parent directory for zip file');
                return;
            }
            chmod($parentDir, 0777);
        }
        
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $this->markTestSkipped('Could not create zip archive');
            return;
        }
        
        // Add required SCORM files
        $zip->addFromString('imsmanifest.xml', $this->getValidManifestContent());
        $zip->addFromString('index.html', '<html><body>Nested SCORM Content</body></html>');
        
        // Add nested structure
        $zip->addFromString('assets/style.css', 'body { font-family: Arial; }');
        $zip->addFromString('assets/script.js', 'console.log("loaded");');
        $zip->addFromString('assets/images/logo.png', 'fake png content');
        $zip->addFromString('assets/images/background.jpg', 'fake jpg content');
        
        // Add optional SCORM schema files
        $zip->addFromString('adlcp_rootv1p2.xsd', '<?xml version="1.0"?><schema></schema>');
        $zip->addFromString('ims_xml.xsd', '<?xml version="1.0"?><schema></schema>');
        $zip->addFromString('imscp_rootv1p1p2.xsd', '<?xml version="1.0"?><schema></schema>');
        $zip->addFromString('imsmd_rootv1p2p1.xsd', '<?xml version="1.0"?><schema></schema>');
        
        $zip->close();
        
        // Verify the file was created
        if (!file_exists($path)) {
            $this->markTestSkipped('Failed to create zip file at: ' . $path);
            return;
        }
        
        // Ensure the zip file is writable
        chmod($path, 0666);
    }

    private function getValidManifestContent(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<manifest identifier="test_scorm" version="1.0" 
          xmlns="http://www.imsglobal.org/xsd/imscp_v1p1"
          xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_v1p3">
    <metadata>
        <schema>ADL SCORM</schema>
        <schemaversion>2004 4th Edition</schemaversion>
    </metadata>
    <organizations default="test_org">
        <organization identifier="test_org">
            <title>Test SCORM Package</title>
            <item identifier="item_1" identifierref="resource_1">
                <title>Test Item</title>
            </item>
        </organization>
    </organizations>
    <resources>
        <resource identifier="resource_1" type="webcontent" adlcp:scormType="sco" href="index.html">
            <file href="index.html"/>
            <file href="style.css"/>
        </resource>
    </resources>
</manifest>';
    }

    private function createMockTempFile(string $path): MockObject
    {
        $tempFile = $this->createMock(TempFile::class);
        $tempFile->method('getTempPath')->willReturn($path);
        return $tempFile;
    }

    private function removeDirectory(string $dir): void
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
}
