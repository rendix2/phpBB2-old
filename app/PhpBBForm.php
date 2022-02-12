<?php
/**
 *
 * Created by PhpStorm.
 * Filename: PhpBBForm.php
 * User: Tomáš Babický
 * Date: 06.03.2021
 * Time: 15:26
 */

namespace phpBB2\App;

use Nette\ComponentModel\IContainer;

class PhpBBForm extends \Nette\Application\UI\Form
{
    /**
     * PhpBBForm constructor.
     *
     * @param IContainer|null $parent
     * @param null $name
     */
    public function __construct(IContainer $parent = null, $name = null)
    {
        parent::__construct($parent, $name);

        $this->addProtection();

        $this->onRender[] = [$this, 'onRender'];
    }

    /**
     *
     */
    public function onRender()
    {
        $renderer = $this->getRenderer();

        $renderer->wrappers['controls']['container'] = 'table class="forumline"';
        $renderer->wrappers['pair']['container'] = 'tr';
        $renderer->wrappers['pair']['.error'] = 'has-danger';
        $renderer->wrappers['control']['container'] = 'td class="row2"';
        $renderer->wrappers['label']['container'] = 'td class="row1"';
        $renderer->wrappers['control']['description'] = 'span class=form-text';
        $renderer->wrappers['control']['errorcontainer'] = 'span class=form-control-feedback';
        $renderer->wrappers['control']['.error'] = 'is-invalid';

        foreach ($this->getControls() as $control) {
            $type = $control->getOption('type');
            if ($type === 'button') {
                $control->getControlPrototype()->addClass('mainoption');
            } elseif (in_array($type, ['text', 'textarea', 'select'], true)) {
                $control->getControlPrototype()->addClass('form-control');

            } elseif ($type === 'file') {
                $control->getControlPrototype()->addClass('form-control-file');

            } elseif (in_array($type, ['checkbox', 'radio'], true)) {
                if ($control instanceof \Nette\Forms\Controls\Checkbox) {
                    //$control->getLabelPrototype()->addClass('form-check-label');
                } else {
                    //$control->getItemLabelPrototype()->addClass('form-check-label');
                }
                //$control->getControlPrototype()->addClass('form-check-input');
                //$control->getSeparatorPrototype()->setName('div')->addClass('form-check');
            }
        }
    }
}
