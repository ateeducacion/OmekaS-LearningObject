<?php
declare(strict_types=1);

namespace LearningObjectAdapter;

use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Module\AbstractModule;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Message;
use LearningObjectAdapter\Form\ConfigForm;

/**
 * Main class for the IsoltatedSites module.
 */
class Module extends AbstractModule
{
    /**
     * Retrieve the configuration array.
     *
     * @return array
     */
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    /**
     * Execute logic when the module is installed.
     *
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $messenger = new Messenger();
        $message = new Message("Learning Object Adapter module installed.");
        $messenger->addSuccess($message);
    }
    /**
     * Execute logic when the module is uninstalled.
     *
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $messenger = new Messenger();
        $message = new Message("Learning Object Adapter module uninstalled.");
        $messenger->addWarning($message);
    }
    
    /**
     * Register the file validator service and renderers.
     *
     * @param SharedEventManagerInterface $sharedEventManager
     */
    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        // Listen for media deletion events to clean up extracted directories
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\MediaAdapter',
            'api.delete.post',
            [$this, 'handleMediaDeletion']
        );
    }
    
    /**
     * Handle media deletion event to clean up extracted learning object directories
     *
     * @param Event $event
     */
    public function handleMediaDeletion(Event $event)
    {
        $request = $event->getParam('request');
        $response = $event->getParam('response');
        $media = $response->getContent();
        
        // Check if this is a learning object media
        $mediaData = $media->getData();
        if (!isset($mediaData['learning_object']) || !$mediaData['learning_object']) {
            return;
        }
        
        // Get the extraction path
        if (!isset($mediaData['learning_object_data']['extraction_path'])) {
            return;
        }
        
        $extractionPath = $mediaData['learning_object_data']['extraction_path'];
        
        // Get the ScormPackageManager service
        $services = $this->getServiceLocator();
        $scormPackageManager = $services->get(\LearningObjectAdapter\Service\ScormPackageManager::class);
        
        // Get the store to build the full path
        $store = $services->get('Omeka\File\Store');
        $basePath = $store->getLocalPath('zips');
        $fullExtractionPath = $basePath . '/original/' . $extractionPath;
        
        // Remove the extracted directory if it exists
        if (is_dir($fullExtractionPath)) {
            $this->removeDirectory($fullExtractionPath);
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
     * Get the configuration form for this module.
     *
     * @param PhpRenderer $renderer
     * @return string
     */
    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');
        
        $form = new ConfigForm;
        $form->init();
        
        $form->setData([
            'activate_LearningObjectAdapter_cb' => $settings->get('activate_LearningObjectAdapter', 1),
        ]);
        
        return $renderer->formCollection($form, false);
    }
    
    /**
     * Handle the configuration form submission.
     *
     * @param AbstractController $controller
     */
    public function handleConfigForm(AbstractController $controller)
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        
        $config = $controller->params()->fromPost();

        $value = isset($config['activate_LearningObjectAdapter_cb']) ? $config['activate_LearningObjectAdapter_cb'] : 0;

        // Save configuration settings in omeka settings database
        $settings->set('activate_LearningObjectAdapter', $value);
    }
}
