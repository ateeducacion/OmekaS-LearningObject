<?php
declare(strict_types=1);

namespace LearningObjectAdapterTest\Media\Ingester;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use LearningObjectAdapter\Media\Ingester\LearningObject;
use LearningObjectAdapter\Service\ScormPackageManager;
use Omeka\File\Uploader;
use Omeka\Api\Manager;
use Omeka\Job\Dispatcher;
use Omeka\Entity\Media;
use Omeka\Api\Request;
use Omeka\Stdlib\ErrorStore;
use Omeka\File\TempFile;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Exception\ValidationException;

class LearningObjectTest extends TestCase
{
    private LearningObject $ingester;
    private MockObject $uploader;
    private MockObject $apiManager;
    private MockObject $dispatcher;
    private MockObject $scormPackageManager;
    private MockObject $media;
    private MockObject $request;
    private ErrorStore $errorStore;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->uploader = $this->createMock(Uploader::class);
        $this->apiManager = $this->createMock(Manager::class);
        $this->dispatcher = $this->createMock(Dispatcher::class);
        $this->scormPackageManager = $this->createMock(ScormPackageManager::class);
        
        $this->ingester = new LearningObject(
            $this->uploader,
            $this->apiManager,
            $this->dispatcher,
            $this->scormPackageManager
        );
        
        $this->media = $this->createMock(Media::class);
        $this->request = $this->createMock(Request::class);
        $this->errorStore = new ErrorStore();
        
        // Get system temp directory and ensure it's writable
        $systemTempDir = sys_get_temp_dir();
        if (!is_writable($systemTempDir)) {
            $this->markTestSkipped('System temp directory is not writable');
            return;
        }
        
        // Create our test directory
        $this->tempDir = $systemTempDir . DIRECTORY_SEPARATOR . 'learning_object_ingester_test_' . uniqid();
        if (!mkdir($this->tempDir, 0777, true)) {
            $this->markTestSkipped('Could not create temp directory');
            return;
        }
        chmod($this->tempDir, 0777);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function testGetLabel(): void
    {
        $label = $this->ingester->getLabel();
        $this->assertEquals('Learning Object (Zip)', $label);
    }

    public function testGetDescription(): void
    {
        $description = $this->ingester->getDescription();
        $this->assertEquals('Ingest a learning object zip file by uploading it.', $description);
    }

    public function testGetFileExtensions(): void
    {
        $extensions = $this->ingester->getFileExtensions();
        $this->assertEquals(['zip', 'elp'], $extensions);
    }

    public function testGetRenderer(): void
    {
        $renderer = $this->ingester->getRenderer();
        $this->assertEquals('LearningObject', $renderer);
    }

    public function testGetMediaTypes(): void
    {
        $mediaTypes = $this->ingester->getMediaTypes();
        $expected = ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'];
        $this->assertEquals($expected, $mediaTypes);
    }

    public function testIngestWithNoFiles(): void
    {
        $this->request->method('getContent')->willReturn([]);
        $this->request->method('getFileData')->willReturn([]);

        $this->ingester->ingest($this->media, $this->request, $this->errorStore);

        $this->assertTrue($this->errorStore->hasErrors());
        $errors = $this->errorStore->getErrors();
        $this->assertArrayHasKey('error', $errors);
    }

    public function testIngestWithNoFileIndex(): void
    {
        $this->request->method('getContent')->willReturn([]);
        $this->request->method('getFileData')->willReturn(['file' => ['test.zip']]);

        $this->ingester->ingest($this->media, $this->request, $this->errorStore);

        $this->assertTrue($this->errorStore->hasErrors());
        $errors = $this->errorStore->getErrors();
        $this->assertArrayHasKey('error', $errors);
    }

    public function testIngestWithInvalidFileIndex(): void
    {
        $this->request->method('getContent')->willReturn(['file_index' => 0]);
        $this->request->method('getFileData')->willReturn(['file' => []]);

        $this->ingester->ingest($this->media, $this->request, $this->errorStore);

        $this->assertTrue($this->errorStore->hasErrors());
        $errors = $this->errorStore->getErrors();
        $this->assertArrayHasKey('error', $errors);
    }

    public function testIngestWithInvalidMediaType(): void
    {
        // Create a test file with invalid media type
        $testFile = $this->tempDir . '/test.txt';
        file_put_contents($testFile, 'not a zip file');
        clearstatcache(true, $testFile); // Clear cache before getting filesize
        $fileSize = filesize($testFile);

        $fileData = [
            'file' => [
                0 => [
                    'name' => 'test.zip',
                    'tmp_name' => $testFile,
                    'type' => 'text/plain',
                    'size' => $fileSize,
                    'error' => UPLOAD_ERR_OK
                ]
            ]
        ];

        $this->request->method('getContent')->willReturn(['file_index' => 0]);
        $this->request->method('getFileData')->willReturn($fileData);

        $this->expectException(ValidationException::class);
        $this->ingester->ingest($this->media, $this->request, $this->errorStore);
    }

    public function testIngestWithValidScormPackage(): void
    {
        // Create a valid zip file
        $zipPath = $this->tempDir . '/valid_scorm.zip';
        $this->createValidScormZip($zipPath);
        
        $fileData = [
            'file' => [
                0 => [
                    'name' => 'valid_scorm.zip',
                    'tmp_name' => $zipPath,
                    'type' => 'application/zip',
                    'size' => filesize($zipPath),
                    'error' => UPLOAD_ERR_OK
                ]
            ]
        ];

        // Create a TempFile mock that will be returned by the upload method
        $tempFile = $this->createMock(TempFile::class);
        $tempFile->method('getTempPath')->willReturn($zipPath);
        $tempFile->method('setSourceName')->willReturnSelf();
        $tempFile->method('mediaIngestFile')->willReturn(null);

        // Configure the mocks
        $this->request->method('getContent')->willReturn(['file_index' => 0]);
        $this->request->method('getFileData')->willReturn($fileData);

        // The upload method should return the TempFile mock
        $this->uploader->method('upload')
            ->willReturn($tempFile);

        $this->scormPackageManager->method('isValidScormPackage')
            ->with($tempFile)
            ->willReturn(true);
            
        $this->scormPackageManager->method('extractScormPackage')
            ->willReturn($this->tempDir . '/extracted');
            
        $this->scormPackageManager->method('getScormInfo')
            ->willReturn([
                'title' => 'Test SCORM',
                'entry_point' => 'index.html'
            ]);

        $this->media->expects($this->once())
            ->method('setData')
            ->with($this->callback(function ($data) {
                return isset($data['learning_object'])
                    && isset($data['learning_object_data']);
            }));

        $this->media->expects($this->once())
            ->method('setSource')
            ->with('valid_scorm.zip');

        $this->ingester->ingest($this->media, $this->request, $this->errorStore);

        $this->assertFalse($this->errorStore->hasErrors());
    }
    public function testIngestWithValidElpFile(): void
    {
        // Create a valid eXeLearning file
        $elpPath = $this->tempDir . '/valid_exe.elp';
        $this->createValidElpZip($elpPath);
        
        // Ensure the file exists before getting its size
        if (!file_exists($elpPath)) {
            $this->markTestSkipped('Could not create test elp file');
        }
        
        clearstatcache(true, $elpPath); // Clear cache before getting filesize
        $fileSize = filesize($elpPath);

        $fileData = [
            'file' => [
                0 => [
                    'name' => 'valid_exe.elp',
                    'tmp_name' => $elpPath,
                    'type' => 'application/zip',
                    'size' => $fileSize,
                    'error' => UPLOAD_ERR_OK
                ]
            ]
        ];

        $tempFile = $this->createMock(TempFile::class);
        $tempFile->method('getTempPath')->willReturn($elpPath);

        $this->request->method('getContent')->willReturn(['file_index' => 0]);
        $this->request->method('getFileData')->willReturn($fileData);

        $this->uploader->method('upload')->willReturn($tempFile);
        $this->scormPackageManager->method('extractScormPackage')->willReturn($this->tempDir . '/extracted');
        $this->scormPackageManager->method('getScormInfo')->willReturn([
            'title' => 'Test eXeLearning',
            'entry_point' => 'index.html'
        ]);

        $this->media->expects($this->once())->method('setData');
        $this->media->expects($this->once())->method('setSource');
        $tempFile->expects($this->once())->method('setSourceName');
        $tempFile->expects($this->once())->method('mediaIngestFile');

        $this->ingester->ingest($this->media, $this->request, $this->errorStore);

        $this->assertFalse($this->errorStore->hasErrors());
    }

    public function testIngestWithInvalidElpFile(): void
    {
        // Create an invalid eXeLearning file (old version)
        $elpPath = $this->tempDir . '/invalid_exe.elp';
        $this->createInvalidElpZip($elpPath);
        
        // Ensure the file exists before getting its size
        if (!file_exists($elpPath)) {
            $this->markTestSkipped('Could not create test elp file');
        }
        
        clearstatcache(true, $elpPath); // Clear cache before getting filesize
        $fileSize = filesize($elpPath);

        $fileData = [
            'file' => [
                0 => [
                    'name' => 'invalid_exe.elp',
                    'tmp_name' => $elpPath,
                    'type' => 'application/zip',
                    'size' => $fileSize,
                    'error' => UPLOAD_ERR_OK
                ]
            ]
        ];

        $tempFile = $this->createMock(TempFile::class);
        $tempFile->method('getTempPath')->willReturn($elpPath);

        $this->request->method('getContent')->willReturn(['file_index' => 0]);
        $this->request->method('getFileData')->willReturn($fileData);

        $this->uploader->method('upload')->willReturn($tempFile);

        $this->ingester->ingest($this->media, $this->request, $this->errorStore);

        $this->assertTrue($this->errorStore->hasErrors());
        $errors = $this->errorStore->getErrors();
        $this->assertArrayHasKey('elp', $errors);
    }

    public function testForm(): void
    {
        $view = $this->createMock(PhpRenderer::class);
        $view->method('formRow')->willReturn('<input type="file">');

        $formHtml = $this->ingester->form($view);

        $this->assertIsString($formHtml);
        $this->assertStringContainsString('<input type="file">', $formHtml);
    }

    private function createValidScormZip(string $path): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE) !== true) {
            $this->markTestSkipped('Could not create zip archive');
            return;
        }
        $zip->addFromString('imsmanifest.xml', $this->getValidManifestContent());
        $zip->addFromString('index.html', '<html><body>SCORM Content</body></html>');
        $zip->close();
        
        // Verify the file was created
        if (!file_exists($path)) {
            $this->markTestSkipped('Failed to create zip file at: ' . $path);
        }
    }

    private function createValidElpZip(string $path): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE) !== true) {
            $this->markTestSkipped('Could not create zip archive');
            return;
        }
        $zip->addFromString('content.xml', '<content></content>');
        $zip->addFromString('index.html', '<html><body>eXeLearning Content</body></html>');
        $zip->close();
        
        // Verify the file was created
        if (!file_exists($path)) {
            $this->markTestSkipped('Failed to create elp file at: ' . $path);
        }
    }

    private function createInvalidElpZip(string $path): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE) !== true) {
            $this->markTestSkipped('Could not create zip archive');
            return;
        }
        $zip->addFromString('contentv3.xml', '<content></content>');
        // No index.html - makes it invalid for eXeLearning 3.x
        $zip->close();
        
        // Verify the file was created
        if (!file_exists($path)) {
            $this->markTestSkipped('Failed to create elp file at: ' . $path);
        }
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
        </resource>
    </resources>
</manifest>';
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
