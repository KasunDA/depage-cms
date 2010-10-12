<?php 

require_once ('inputClass.php');
require_once ('select.php');
require_once ('checkbox.php');
require_once ('radio.php');

abstract class checkboxClass extends inputClass {
    protected $optionList = array();

    public function __construct($name, $parameters, $formName) {
        parent::__construct($name, $parameters, $formName);
        $this->value = (isset($parameters['value'])) ? $parameters['value'] : array();
        $this->optionList = (isset($parameters['optionList'])) ? $parameters['optionList'] : array();
    }
    
    public function __toString() {
        $options = '';
        foreach($this->optionList as $index => $option) {
            $selected = (in_array($index, $this->value)) ? ' checked=\"yes\"' : '';
            $options .= "<span><label><input type=\"$this->type\" name=\"$this->name[]\" value=\"$index\"$selected><span>$option</span></label></span>";
        }

        $classes = $this->getClasses();
        return "<p id=\"$this->formName-$this->name\" class=\"$classes\"><span class=\"label\">$this->label</span><span>$options</span></p>";
    }
}
