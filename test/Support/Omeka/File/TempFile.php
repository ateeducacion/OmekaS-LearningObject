<?php
declare(strict_types=1);

namespace Omeka\File;

class TempFile
{
    protected $tempPath;
    protected $sourceName;
    
    public function __construct(string $tempPath = '')
    {
        $this->tempPath = $tempPath;
    }
    
    public function getTempPath(): string
    {
        return $this->tempPath;
    }
    
    public function setTempPath(string $tempPath): self
    {
        $this->tempPath = $tempPath;
        return $this;
    }
    
    public function getSourceName(): ?string
    {
        return $this->sourceName;
    }
    
    public function setSourceName(string $sourceName): self
    {
        $this->sourceName = $sourceName;
        return $this;
    }
    
    public function mediaIngestFile($media, $request, $errorStore)
    {
        // Mock implementation
    }
}
