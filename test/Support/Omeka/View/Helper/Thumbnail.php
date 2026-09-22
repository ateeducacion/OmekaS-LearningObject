<?php
namespace Omeka\View\Helper;

class Thumbnail extends \Laminas\View\Helper\AbstractHelper
{
    public function __invoke($media, $type = 'medium', array $attribs = [])
    {
        return 'default thumbnail';
    }
    protected function htmlAttribs(array $attribs)
    {
        $html = [];
        foreach ($attribs as $key => $value) {
            $html[] = $key . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
        }
        return implode(' ', $html);
    }
}
