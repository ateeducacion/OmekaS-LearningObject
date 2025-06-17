<?php
declare(strict_types=1);

namespace LearningObjectAdapterTest;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use LearningObjectAdapter\Module;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Entity\Media;
use Omeka\Api\Response;
use Omeka\Api\Request;
use Omeka\File\Store\StoreInterface;
use LearningObjectAdapter\Service\ScormPackageManager;

class ModuleTest extends TestCase
{
    private Module $module;
    private MockObject $sharedEventManager;
    private MockObject $serviceLocator;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->module = new Module();
        $this->sharedEventManager = $this->createMock(SharedEventManagerInterface::class);
        $this->serviceLocator = $this->createMock(ServiceLocatorInterface::class);
        
        // Create a temporary directory for testing
        $this->tempDir = sys_get_temp_dir() . '/learning_object_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        
        // Set the service locator on the module
        $this->module->setServiceLocator($this->serviceLocator);
    }

    protected function tearDown(): void
    {
        // Clean up temporary directory
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function testGetConfig(): void
    {
        $config = $this->module->getConfig();
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('media_ingesters', $config);
        $this->assertArrayHasKey('media_renderers', $config);
        $this->assertArrayHasKey('service_manager', $config);
    }

    public function testAttachListeners(): void
    {
        $this->sharedEventManager
            ->expects($this->once())
            ->method('attach')
            ->with(
                'Omeka\Api\Adapter\MediaAdapter',
                'api.delete.post',
                [$this->module, 'handleMediaDeletion']
            );

        $this->module->attachListeners($this->sharedEventManager);
    }

    public function testHandleMediaDeletionWithLearningObjectMedia(): void
    {
        // Create test directory structure
        $extractionPath = 'scorm_test_123';
        $fullPath = $this->tempDir . '/original/' . $extractionPath;
        mkdir($fullPath, 0755, true);
        file_put_contents($fullPath . '/test_file.txt', 'test content');

        // Mock media with learning object data
        $media = $this->createMock(Media::class);
        $media->method('getData')->willReturn([
            'learning_object' => true,
            'learning_object_data' => [
                'extraction_path' => $extractionPath
            ]
        ]);

        // Mock response
        $response = $this->createMock(Response::class);
        $response->method('getContent')->willReturn($media);

        // Mock request
        $request = $this->createMock(Request::class);

        // Mock store
        $store = $this->createMock(StoreInterface::class);
        $store->method('getLocalPath')->with('zips')->willReturn($this->tempDir);

        // Mock ScormPackageManager
        $scormPackageManager = $this->createMock(ScormPackageManager::class);

        // Configure service locator
        $this->serviceLocator->method('get')->willReturnMap([
            [ScormPackageManager::class, $scormPackageManager],
            ['Omeka\File\Store', $store]
        ]);

        // Create event
        $event = new Event();
        $event->setParam('request', $request);
        $event->setParam('response', $response);

        // Verify directory exists before deletion
        $this->assertTrue(is_dir($fullPath));
        $this->assertTrue(file_exists($fullPath . '/test_file.txt'));

        // Handle media deletion
        $this->module->handleMediaDeletion($event);

        // Verify directory was deleted
        $this->assertFalse(is_dir($fullPath));
    }

    public function testHandleMediaDeletionWithNonLearningObjectMedia(): void
    {
        // Create test directory structure
        $extractionPath = 'scorm_test_456';
        $fullPath = $this->tempDir . '/original/' . $extractionPath;
        mkdir($fullPath, 0755, true);
        file_put_contents($fullPath . '/test_file.txt', 'test content');

        // Mock media without learning object data
        $media = $this->createMock(Media::class);
        $media->method('getData')->willReturn([
            'some_other_data' => true
        ]);

        // Mock response
        $response = $this->createMock(Response::class);
        $response->method('getContent')->willReturn($media);

        // Mock request
        $request = $this->createMock(Request::class);

        // Create event
        $event = new Event();
        $event->setParam('request', $request);
        $event->setParam('response', $response);

        // Handle media deletion
        $this->module->handleMediaDeletion($event);

        // Verify directory still exists (should not be deleted)
        $this->assertTrue(is_dir($fullPath));
        $this->assertTrue(file_exists($fullPath . '/test_file.txt'));
    }

    public function testHandleMediaDeletionWithMissingExtractionPath(): void
    {
        // Mock media with learning object data but missing extraction path
        $media = $this->createMock(Media::class);
        $media->method('getData')->willReturn([
            'learning_object' => true,
            'learning_object_data' => [
                'type' => 'SCORM'
                // Missing extraction_path
            ]
        ]);

        // Mock response
        $response = $this->createMock(Response::class);
        $response->method('getContent')->willReturn($media);

        // Mock request
        $request = $this->createMock(Request::class);

        // Create event
        $event = new Event();
        $event->setParam('request', $request);
        $event->setParam('response', $response);

        // This should not throw an exception
        $this->module->handleMediaDeletion($event);
        
        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    public function testHandleMediaDeletionWithNonExistentDirectory(): void
    {
        // Mock media with learning object data pointing to non-existent directory
        $media = $this->createMock(Media::class);
        $media->method('getData')->willReturn([
            'learning_object' => true,
            'learning_object_data' => [
                'extraction_path' => 'non_existent_directory'
            ]
        ]);

        // Mock response
        $response = $this->createMock(Response::class);
        $response->method('getContent')->willReturn($media);

        // Mock request
        $request = $this->createMock(Request::class);

        // Mock store
        $store = $this->createMock(StoreInterface::class);
        $store->method('getLocalPath')->with('zips')->willReturn($this->tempDir);

        // Mock ScormPackageManager
        $scormPackageManager = $this->createMock(ScormPackageManager::class);

        // Configure service locator
        $this->serviceLocator->method('get')->willReturnMap([
            [ScormPackageManager::class, $scormPackageManager],
            ['Omeka\File\Store', $store]
        ]);

        // Create event
        $event = new Event();
        $event->setParam('request', $request);
        $event->setParam('response', $response);

        // This should not throw an exception
        $this->module->handleMediaDeletion($event);
        
        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    public function testRemoveDirectoryRecursively(): void
    {
        // Create nested directory structure
        $testDir = $this->tempDir . '/test_removal';
        $subDir = $testDir . '/subdir';
        mkdir($subDir, 0755, true);
        
        file_put_contents($testDir . '/file1.txt', 'content1');
        file_put_contents($subDir . '/file2.txt', 'content2');
        file_put_contents($subDir . '/file3.txt', 'content3');

        // Verify structure exists
        $this->assertTrue(is_dir($testDir));
        $this->assertTrue(is_dir($subDir));
        $this->assertTrue(file_exists($testDir . '/file1.txt'));
        $this->assertTrue(file_exists($subDir . '/file2.txt'));
        $this->assertTrue(file_exists($subDir . '/file3.txt'));

        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->module);
        $method = $reflection->getMethod('removeDirectory');
        $method->setAccessible(true);

        // Remove directory
        $method->invoke($this->module, $testDir);

        // Verify everything was removed
        $this->assertFalse(is_dir($testDir));
        $this->assertFalse(is_dir($subDir));
        $this->assertFalse(file_exists($testDir . '/file1.txt'));
        $this->assertFalse(file_exists($subDir . '/file2.txt'));
        $this->assertFalse(file_exists($subDir . '/file3.txt'));
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
