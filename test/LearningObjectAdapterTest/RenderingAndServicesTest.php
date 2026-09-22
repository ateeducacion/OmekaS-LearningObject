<?php

declare(strict_types=1);

namespace LearningObjectAdapterTest;

use LearningObjectAdapter\Media\Renderer\LearningObject;
use LearningObjectAdapter\View\Helper\FormatFileSize;
use LearningObjectAdapter\View\Helper\MediaThumbnail;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\MediaRepresentation;
use PHPUnit\Framework\TestCase;

class RenderingAndServicesTest extends TestCase
{
    private function view()
    {
        return new class extends PhpRenderer {
            public $partialArguments;

            public function partial($name, array $arguments)
            {
                $this->partialArguments = $arguments;
                TestCase::assertSame('common/scorm-package', $name);
                return '<iframe></iframe>';
            }

            public function basePath()
            {
                return '/omeka';
            }

            public function escapeHtml($value)
            {
                return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }

            public function escapeHtmlAttr($value)
            {
                return $this->escapeHtml($value);
            }
        };
    }

    public function testRendererHandlesPackagesContentAndEmptyData(): void
    {
        $renderer = new LearningObject();
        $view = $this->view();
        $media = new MediaRepresentation();
        $this->assertStringContainsString('No learning object data available.', $renderer->render($view, $media));
        $media->data = ['learning_object_data' => [
            'scorm_info' => ['title' => '<Title>'], 'description' => '<Description>', 'content' => '<p>Body</p>',
        ]];
        $html = $renderer->render($view, $media);
        $this->assertStringContainsString('&lt;Title&gt;', $html);
        $this->assertStringContainsString('&lt;Description&gt;', $html);
        $this->assertStringContainsString('<p>Body</p>', $html);
        foreach (['SCORM', 'eXeLearning'] as $type) {
            $media->data = ['learning_object_data' => ['type' => $type]];
            $this->assertStringContainsString('<iframe></iframe>', $renderer->render($view, $media));
            $this->assertSame($media, $view->partialArguments['media']);
        }
    }

    public function testRendererThumbnailUsesDefaultAndCustomImages(): void
    {
        $renderer = new LearningObject();
        $view = $this->view();
        $media = new MediaRepresentation();
        $html = $renderer->thumbnail($view, $media, ['width' => 320, 'height' => 200]);
        $this->assertStringContainsString('learning-object-thumb.png', $html);
        $this->assertStringContainsString('width="320"', $html);
        $this->assertStringContainsString('height="200"', $html);
        $this->assertStringContainsString('&lt;Course&gt;', $html);
        if (!defined('OMEKA_PATH')) {
            define('OMEKA_PATH', sys_get_temp_dir() . '/learning_object_renderer_' . uniqid());
        }
        $media->data = ['learning_object_data' => ['type' => 'SCORM', 'extraction_path' => 'missing']];
        $this->assertStringContainsString('learning-object-thumb.png', $renderer->thumbnail($view, $media));
        if (!defined('OMEKA_PATH')) {
            define('OMEKA_PATH', sys_get_temp_dir() . '/learning_object_renderer_' . uniqid());
        }
        $dir = OMEKA_PATH . '/files/original/thumbnail-test';
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/thumbnail.png', 'image');
        try {
            $media->data['learning_object_data']['extraction_path'] = 'thumbnail-test';
            $this->assertStringContainsString('/thumbnail-test/thumbnail.png', $renderer->thumbnail($view, $media));
        } finally {
            unlink($dir . '/thumbnail.png');
            rmdir($dir);
            rmdir(dirname($dir));
            rmdir(dirname($dir, 2));
            rmdir(OMEKA_PATH);
        }
    }

    public function testThumbnailHelperFallsBackAndEscapesAttributes(): void
    {
        $helper = new MediaThumbnail();
        $helper->setView($this->view());
        $media = new MediaRepresentation();
        $this->assertSame('default thumbnail', $helper(new \stdClass()));
        $media->ingester = 'upload';
        $this->assertSame('default thumbnail', $helper($media));
        $media->ingester = 'LearningObject';
        $media->thumbnails = true;
        $this->assertSame('default thumbnail', $helper($media));
        $media->thumbnails = false;
        $this->assertStringContainsString('learning-object-thumbnail', $helper($media));
        $html = $helper($media, 'medium', ['class' => 'custom']);
        $this->assertStringContainsString('custom learning-object-thumbnail', $html);
        $this->assertStringContainsString('&lt;Course&gt;', $html);
    }

    public function testFileSizeFormatting(): void
    {
        $helper = new FormatFileSize();
        foreach ([0 => '0 bytes', 1 => '1 byte', 2 => '2 bytes', 1024 => '1.00 KB',
            1048576 => '1.00 MB', 1073741824 => '1.00 GB'] as $bytes => $expected) {
            $this->assertSame($expected, $helper($bytes));
        }
    }

    public function testFactoriesWireTheirServices(): void
    {
        $store = $this->createMock(\Omeka\File\Store\StoreInterface::class);
        $store->method('getLocalPath')->with('zips')->willReturn('/tmp/scorm');
        $manager = new \LearningObjectAdapter\Service\ScormPackageManager($store);
        $container = $this->createMock(\Interop\Container\ContainerInterface::class);
        $container->method('get')->willReturnMap([
            ['Omeka\File\Store', $store],
            ['Omeka\File\Uploader', new \Omeka\File\Uploader()],
            ['Omeka\ApiManager', new \Omeka\Api\Manager()],
            ['Omeka\Job\Dispatcher', new \Omeka\Job\Dispatcher()],
            [\LearningObjectAdapter\Service\ScormPackageManager::class, $manager],
        ]);
        $factory = new \LearningObjectAdapter\Service\ScormPackageManagerFactory();
        $this->assertInstanceOf(get_class($manager), $factory($container, get_class($manager)));
        $factory = new \LearningObjectAdapter\Media\Ingester\LearningObjectFactory();
        $this->assertInstanceOf(\LearningObjectAdapter\Media\Ingester\LearningObject::class, $factory($container, ''));
        $factory = new \LearningObjectAdapter\Service\ViewHelper\MediaThumbnailFactory();
        $this->assertInstanceOf(MediaThumbnail::class, $factory($container, MediaThumbnail::class));
    }
}
