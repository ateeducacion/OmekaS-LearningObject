<?php
declare(strict_types=1);

namespace LearningObjectAdapter\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;

class ConfigForm extends Form
{
    /**
     * Initialize the form elements.
     */
    public function init(): void
    {
        $this->add([
            'name' => 'activate_LearningObjectAdapter_cb',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Activate Learning Object Adapter',
                'use_hidden_element' => true,
                'checked_value' => '1',
                'unchecked_value' => '0',
            ],
            'attributes' => [
                'required' => false,
                'id' => 'activate_LearningObjectAdapter_cb'
            ],
        ]);
    }
}
