<?php
declare(strict_types=1);

namespace LearningObjectAdapter;
use LearningObjectAdapter\Service\ScormPackageManager;
use LearningObjectAdapter\Service\ScormPackageManagerFactory;


//use ThreeDViewer\Media\FileRenderer\Viewer3DRenderer;

return [
    'view_helpers' => [
        'invokables' => [
            'thumbnail' => 'mediaThumbnail', // Override the default thumbnail helper
            'formatFileSize' => \LearningObjectAdapter\View\Helper\FormatFileSize::class,
        ],
        'factories' => [
            'mediaThumbnail' => \LearningObjectAdapter\Service\ViewHelper\MediaThumbnailFactory::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\SettingsFieldset::class => Form\SettingsFieldset::class,
            Form\SiteSettingsFieldset::class => Form\SiteSettingsFieldset::class,
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'service_manager' =>[
        'factories' => [
            ScormPackageManager::class => \LearningObjectAdapter\Service\ScormPackageManagerFactory::class
        ],
    ],
    'media_ingesters' => [
        'factories' => [
            'LearningObject' => \LearningObjectAdapter\Media\Ingester\LearningObjectFactory::class,
        ],
    ],
    'media_renderers' => [
        'invokables' => [
            'LearningObject' => \LearningObjectAdapter\Media\Renderer\LearningObject::class,
        ],
    ],
    'LearningObjectAdapter' => [
        'settings' => [
            'activate_LearningObjectAdapter' => true,
        ]
    ],
];
