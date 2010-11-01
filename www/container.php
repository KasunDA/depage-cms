<?php 

require_once('exceptions.php');
require_once('fieldset.php');
require_once('formClass.php');

/**
 * container
 *
 * The abstract container class contains the similatrities of the child classes
 * formClass and fieldset.
 * 
 **/
abstract class container {
    protected $name;
    protected $valid;
    protected $inputs = array();
    protected $defaults = array();

    public function __construct($name, $parameters = array()) {
        $this->checkContainerName($name);
        $this->name = $name;

        $this->setDefaults();

        foreach ($this->defaults as $parameter => $default) {
            $this->$parameter = isset($parameters[$parameter]) ? $parameters[$parameter] : $default;
        }
    }

    protected function setDefaults() {
    }

    public function __call($functionName, $functionArguments) {
        if (substr($functionName, 0, 3) === 'add') {
            $type = strtolower(str_replace('add', '', $functionName));
            $name = (isset($functionArguments[0])) ? $functionArguments[0] : '';
            $parameters = isset($functionArguments[1]) ? $functionArguments[1] : array();
            return $this->addInput($type, $name, $parameters);
        }
    }

    public function addInput($type, $name, $parameters = array()) {
        $this->_checkInputType($type);

        $newInput = new $type($name, $parameters, $this->name);

        $this->inputs[] = $newInput;

        return $newInput;
    }

    public function validate() {
        $this->valid = true;
        foreach($this->inputs as $input) {
            $input->validate();
            $this->valid = (($this->valid) && ($input->isValid()));
        }
    }

    public function isValid() {
        return $this->valid;
    }

    public function setRequired() {
        foreach ($this->inputs as $input) {
            $input->setRequired();
        }
    }

    protected function checkContainerName($name) {
        if (!is_string($name)) {
            throw new formNameNoStringException();
        }
        if (trim($name) === '') {
            throw new invalidFormNameException();
        }
    }

    private function _checkInputType($type) {
        if (!class_exists($type)) {
            throw new unknownInputTypeException();
        }
    }

    public function getName() {
        return $this->name;
    }

    public function getInputs() {
        $allInputs = array();
        foreach($this->inputs as $input) {  
            if (is_a($input, 'fieldset')) {
                $allInputs = array_merge($allInputs, $input->getInputs());
            } else {
                $allInputs[] = $input;
            }
        }
        return $allInputs;
    }
}
