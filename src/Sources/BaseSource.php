<?php


namespace Sources;

abstract class BaseSource
{
    /** @var \TranslatorWorkflow */
    protected $workflow;

    protected $icon = '';

    public function __construct($workflow)
    {
        $this->workflow = $workflow;
    }

    public abstract function translate($query);

    public abstract function speech($query);

    protected function addResult($title, $subtitle, $arg = null)
    {
        $result = $this->workflow->basicResult($title, $subtitle, $arg);
        if ($this->icon) {
            $result->icon($this->icon);
        }
        return $result;
    }
}