<?php
declare(strict_types=1);

namespace Omeka\File\Store;

interface StoreInterface
{
    public function getLocalPath(string $prefix): string;
    public function put(string $source, string $storagePath): void;
    public function delete(string $storagePath): void;
    public function getUri(string $storagePath): string;
}
