<?php
declare(strict_types=1);
namespace Omeka\File;

/**
 * Mock ZipArchive class for testing when the zip extension is not available
 */
class ZipArchive
{
    const ER_OK = 0;
    const ER_MULTIDISK = 1;
    const ER_RENAME = 2;
    const ER_CLOSE = 3;
    const ER_SEEK = 4;
    const ER_READ = 5;
    const ER_WRITE = 6;
    const ER_CRC = 7;
    const ER_ZIPCLOSED = 8;
    const ER_NOENT = 9;
    const ER_EXISTS = 10;
    const ER_OPEN = 11;
    const ER_TMPOPEN = 12;
    const ER_ZLIB = 13;
    const ER_MEMORY = 14;
    const ER_CHANGED = 15;
    const ER_COMPNOTSUPP = 16;
    const ER_EOF = 17;
    const ER_INVAL = 18;
    const ER_NOZIP = 19;
    const ER_INTERNAL = 20;
    const ER_INCONS = 21;
    const ER_REMOVE = 22;
    const ER_DELETED = 23;
    
    // Additional constants
    const CREATE = 1;
    const EXCL = 2;
    const CHECKCONS = 4;
    const OVERWRITE = 8;
    const RDONLY = 16;
    
    public $numFiles = 0;
    
    public function open($filename, $flags = null)
    {
        return self::ER_OK;
    }
    
    public function close()
    {
        return true;
    }
    
    public function extractTo($destination, $entries = null)
    {
        return true;
    }
    
    public function getNameIndex($index)
    {
        if ($index === 0) {
            return 'imsmanifest.xml';
        }
        return false;
    }
    
    public function getFromName($name)
    {
        if ($name === 'imsmanifest.xml') {
            return '<?xml version="1.0"?><manifest></manifest>';
        }
        return false;
    }
    
    public function statIndex($index)
    {
        return ['name' => 'test.txt', 'size' => 100];
    }
    
    public function addFromString($localname, $contents)
    {
        return true;
    }
}
