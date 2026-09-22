<?php
namespace Laminas\View\Helper;

abstract class AbstractHelper
{
    protected $view;
    public function setView($view)
    {
        $this->view = $view;
    }
    public function getView()
    {
        return $this->view;
    }
}
