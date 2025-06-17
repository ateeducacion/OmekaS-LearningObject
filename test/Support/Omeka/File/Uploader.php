<?php
declare(strict_types=1);

namespace Omeka\File;

use Omeka\File\TempFile;
use Omeka\Stdlib\ErrorStore;

class Uploader
{
    public function upload(array $fileData, ErrorStore $errorStore = null): ?TempFile
    {
        return null;
    }
    
    public function getErrorMessages(): array
    {
        return [];
    }
}
