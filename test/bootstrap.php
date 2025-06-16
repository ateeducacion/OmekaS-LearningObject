<?php

declare(strict_types=1);

namespace LearningObjectAdapterTest;

require dirname(__DIR__) . '/vendor/autoload.php';

// Load mock Omeka classes for testing
require_once __DIR__ . '/Support/Omeka/File/Store/StoreInterface.php';
require_once __DIR__ . '/Support/Omeka/File/Uploader.php';
require_once __DIR__ . '/Support/Omeka/File/TempFile.php';
require_once __DIR__ . '/Support/Omeka/Module/AbstractModule.php';
require_once __DIR__ . '/Support/Omeka/Mvc/Controller/Plugin/Messenger.php';
require_once __DIR__ . '/Support/Omeka/Stdlib/Message.php';
require_once __DIR__ . '/Support/Omeka/Api/Manager.php';
require_once __DIR__ . '/Support/Omeka/Api/Response.php';
require_once __DIR__ . '/Support/Omeka/Api/Request.php';
require_once __DIR__ . '/Support/Omeka/Api/Exception/ValidationException.php';
require_once __DIR__ . '/Support/Omeka/Entity/Media.php';
require_once __DIR__ . '/Support/Omeka/Job/Dispatcher.php';
require_once __DIR__ . '/Support/Omeka/Media/Ingester/IngesterInterface.php';
require_once __DIR__ . '/Support/Omeka/Stdlib/ErrorStore.php';

// Load mock Laminas classes for testing
require_once __DIR__ . '/Support/Laminas/EventManager/SharedEventManagerInterface.php';
require_once __DIR__ . '/Support/Laminas/EventManager/Event.php';
require_once __DIR__ . '/Support/Laminas/ServiceManager/ServiceLocatorInterface.php';
require_once __DIR__ . '/Support/Laminas/Mvc/Controller/AbstractController.php';
require_once __DIR__ . '/Support/Laminas/View/Renderer/PhpRenderer.php';

// Load mock ZipArchive if not available
if (!class_exists('ZipArchive')) {
    require_once __DIR__ . '/Support/MockZipArchive.php';
}
