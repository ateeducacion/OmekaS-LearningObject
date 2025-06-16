<?php
declare(strict_types=1);

namespace LearningObjectAdapterTest\Service;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use LearningObjectAdapter\Service\ScormPackageManager;
use Omeka\File\TempFile;
use Omeka\File\Store\StoreInterface;
use Omeka\Stdlib\ErrorStore;
use ZipArchive;

class ScormPackageManagerTest extends TestCase
{
    private ScormPackageManager $scormPackageManager;
    private MockObject $store;
    private string $tempDir;
    private string $testZipPath;

    protected function setUp(): void
    {
        $this->store = $this->createMock(StoreInterface::class);
        
        // Get system temp directory and ensure it's writable
        $systemTempDir = sys_get_temp_dir();
        if (!is_writable($systemTempDir)) {
            $this->markTestSkipped('System temp directory is not writable');
            return;
        }
        
        // Create our test directory
        $this->tempDir = $systemTempDir . DIRECTORY_SEPARATOR . 'scorm_test_' . uniqid();
        if (!mkdir($this->tempDir, 0777, true)) {
            $this->markTestSkipped('Could not create temp directory');
            return;
        }
        chmod($this->tempDir, 0777);
        
        // Create subdirectories needed for extraction
        $originalDir = $this->tempDir . DIRECTORY_SEPARATOR . 'original';
        if (!mkdir($originalDir, 0777, true)) {
            $this->markTestSkipped('Could not create original directory');
            return;
        }
        chmod($originalDir, 0777);
        
        $this->store->method('getLocalPath')->with('zips')->willReturn($this->tempDir);
        
        $this->scormPackageManager = new ScormPackageManager($this->store);
        
        // Create a test zip file
        $this->testZipPath = $this->tempDir . DIRECTORY_SEPARATOR . 'test_scorm.zip';
        $this->createTestScormZip();
        
        // Verify the test zip was created
        if (!file_exists($this->testZipPath)) {
            $this->markTestSkipped('Could not create test SCORM zip file');
            return;
        }
        
        // Ensure the zip file is writable
        chmod($this->testZipPath, 0666);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function testIsValidScormPackageWithValidPackage(): void
    {
        $tempFile = $this->createMockTempFile($this->testZipPath);
        $errorStore = new ErrorStore();

        $result = $this->scormPackageManager->isValidScormPackage($tempFile, $errorStore);

        $this->assertTrue($result);
        $this->assertFalse($errorStore->hasErrors());
    }

    public function testIsValidScormPackageWithInvalidPackage(): void
    {
        // Create invalid zip without imsmanifest.xml
        $invalidZipPath = $this->tempDir . '/invalid.zip';
        $zip = new ZipArchive();
        $zip->open($invalidZipPath, ZipArchive::CREATE);
        $zip->addFromString('some_file.txt', 'content');
        $zip->close();

        $tempFile = $this->createMockTempFile($invalidZipPath);
        $errorStore = new ErrorStore();

        $result = $this->scormPackageManager->isValidScormPackage($tempFile, $errorStore);

        $this->assertFalse($result);
        $this->assertTrue($errorStore->hasErrors());
    }

    public function testIsValidScormPackageWithNonExistentFile(): void
    {
        $tempFile = $this->createMockTempFile('/non/existent/file.zip');
        $errorStore = new ErrorStore();

        $result = $this->scormPackageManager->isValidScormPackage($tempFile, $errorStore);

        $this->assertFalse($result);
        $this->assertTrue($errorStore->hasErrors());
    }

    public function testExtractScormPackageSuccess(): void
    {
        $tempFile = $this->createMockTempFile($this->testZipPath);
        $errorStore = new ErrorStore();
        $extractionDir = 'test_extraction_' . uniqid();

        $result = $this->scormPackageManager->extractScormPackage($tempFile, $extractionDir, $errorStore);

        $this->assertNotFalse($result);
        $this->assertFalse($errorStore->hasErrors());
        $this->assertTrue(is_dir($result));
        $this->assertTrue(file_exists($result . '/imsmanifest.xml'));
        $this->assertTrue(file_exists($result . '/index.html'));
    }

    public function testExtractScormPackageWithNonExistentFile(): void
    {
        $tempFile = $this->createMockTempFile('/non/existent/file.zip');
        $errorStore = new ErrorStore();
        $extractionDir = 'test_extraction_' . uniqid();

        $result = $this->scormPackageManager->extractScormPackage($tempFile, $extractionDir, $errorStore);

        $this->assertFalse($result);
        $this->assertTrue($errorStore->hasErrors());
    }

    public function testExtractScormPackageWithDirectoryTraversalAttempt(): void
    {
        // Create malicious zip with directory traversal
        $maliciousZipPath = $this->tempDir . '/malicious.zip';
        $zip = new ZipArchive();
        $zip->open($maliciousZipPath, ZipArchive::CREATE);
        $zip->addFromString('imsmanifest.xml', $this->getValidManifestContent());
        $zip->addFromString('../../../malicious.txt', 'malicious content');
        $zip->close();

        $tempFile = $this->createMockTempFile($maliciousZipPath);
        $errorStore = new ErrorStore();
        $extractionDir = 'test_extraction_' . uniqid();

        $result = $this->scormPackageManager->extractScormPackage($tempFile, $extractionDir, $errorStore);

        $this->assertFalse($result);
        $this->assertTrue($errorStore->hasErrors());
    }

    public function testGetScormInfoSuccess(): void
    {
        // Extract the test package first
        $tempFile = $this->createMockTempFile($this->testZipPath);
        $errorStore = new ErrorStore();
        $extractionDir = 'test_extraction_' . uniqid();
        
        $extractedPath = $this->scormPackageManager->extractScormPackage($tempFile, $extractionDir, $errorStore);
        $this->assertNotFalse($extractedPath);

        $scormInfo = $this->scormPackageManager->getScormInfo($extractedPath);

        $this->assertIsArray($scormInfo);
        $this->assertArrayHasKey('title', $scormInfo);
        $this->assertArrayHasKey('description', $scormInfo);
        $this->assertArrayHasKey('entry_point', $scormInfo);
        $this->assertEquals('Test SCORM Package', $scormInfo['title']);
        $this->assertEquals('index.html', $scormInfo['entry_point']);
    }

    public function testGetScormInfoWithNonExistentManifest(): void
    {
        $nonExistentPath = $this->tempDir . '/non_existent';
        
        $result = $this->scormPackageManager->getScormInfo($nonExistentPath);

        $this->assertFalse($result);
    }

    public function testGetScormInfoWithInvalidXml(): void
    {
        // Create directory with invalid manifest
        $testDir = $this->tempDir . '/invalid_manifest';
        mkdir($testDir, 0755, true);
        file_put_contents($testDir . '/imsmanifest.xml', 'invalid xml content');

        $result = $this->scormPackageManager->getScormInfo($testDir);

        $this->assertFalse($result);
    }

    private function createTestScormZip(): void
    {
        $zip = new ZipArchive();
        if ($zip->open($this->testZipPath, ZipArchive::CREATE) !== true) {
            $this->markTestSkipped('Could not create zip archive');
            return;
        }
        
        // Add manifest
        $zip->addFromString('imsmanifest.xml', $this->getValidManifestContent());
        
        // Add entry point
        $zip->addFromString('index.html', '<html><body>Test SCORM Content</body></html>');
        
        // Add some additional files
        $zip->addFromString('style.css', 'body { font-family: Arial; }');
        $zip->addFromString('script.js', 'console.log("SCORM loaded");');
        
        $zip->close();
        
        // Verify the file was created
        if (!file_exists($this->testZipPath)) {
            $this->markTestSkipped('Failed to create zip file at: ' . $this->testZipPath);
        }
    }

    private function getValidManifestContent(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<manifest identifier="test_scorm" version="1.0" 
          xmlns="http://www.imsglobal.org/xsd/imscp_v1p1"
          xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_v1p3"
          xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xsi:schemaLocation="http://www.imsglobal.org/xsd/imscp_v1p1 imscp_v1p1.xsd
                              http://www.adlnet.org/xsd/adlcp_v1p3 adlcp_v1p3.xsd">
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
            <file href="script.js"/>
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
