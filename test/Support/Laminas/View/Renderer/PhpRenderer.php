<?php
declare(strict_types=1);

namespace Laminas\View\Renderer;

class PhpRenderer
{
    public function render($nameOrModel, $values = null)
    {
        return '<form></form>';
    }
    
    public function formCollection($form, $wrap = true)
    {
        return '<form></form>';
    }
    
    public function formRow($element, $options = [])
    {
        return '<div class="form-row">' . $element->getName() . '</div>';
    }
    
    public function __invoke($name = null)
    {
        return $this;
    }
}
