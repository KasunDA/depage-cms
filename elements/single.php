<?php
/**
 * @file    single.php
 * @brief   single input element
 **/

namespace depage\htmlform\elements;

use depage\htmlform\abstracts;

/** 
 * @brief HTML-single-choice input type i.e. radio and select.
 **/
class single extends abstracts\input {
    /** 
     * @brief Contains list of selectable options.
     **/
    protected $list = array();

    /**
     * @brief   single class constructor
     *
     * @param   $name       (string)    element name
     * @param   $parameters (array)     element parameters, HTML attributes, validator specs etc.
     * @param   $form       (object)    parent form object
     * @return  void
     **/
    public function __construct($name, $parameters, $form) {
        parent::__construct($name, $parameters, $form);

        $this->list = (isset($parameters['list']) && is_array($parameters['list'])) ? $parameters['list'] : array();
    }

    /**
     * @brief   collects initial values across subclasses
     *
     * @return  void
     **/
    protected function setDefaults() {
        parent::setDefaults();

        // single-choice-elements have values of type string
        $this->defaults['defaultValue'] = '';
        $this->defaults['skin']         = 'radio';
    }

    /**
     * @brief   Renders HTML - option list part of select/radio single element
     *
     * Works recursively in case of select-optgroups. If no parameters are
     * parsed, it uses the list attribute of this element.
     *
     * @param   $options    (array)     list elements and subgroups
     * @param   $value      (string)    value to be marked as selected
     * @return  $list       (string)    options-part of the HTML-select-element
     **/
    protected function htmlList($options = null, $value = null) {
        if ($value == null)     $value      = $this->htmlValue();
        if ($options == null)   $options    = $this->list;

        $options    = $this->htmlEscape($options);
        $list       = '';

        if ($this->skin === "select") {
            foreach($options as $index => $option) {
                if (is_array($option)) {
                    $list       .= "<optgroup label=\"{$index}\">" . $this->htmlList($option, $value) . "</optgroup>";
                } else {
                    $selected   = ((string) $index === $value) ? ' selected' : '';
                    $list       .= "<option value=\"{$index}\"{$selected}>{$option}</option>";
                }
            }
        } else {
            $inputAttributes = $this->htmlInputAttributes();

            foreach($options as $index => $option) {
                // typecasted for non-associative arrays
                $selected = ((string) $index === $value) ? " checked=\"yes\"" : '';

                $list .= "<span>" .
                    "<label>" .
                        "<input type=\"radio\" name=\"{$this->name}\"{$inputAttributes} value=\"{$index}\"{$selected}>" .
                        "<span>{$option}</span>" .
                    "</label>" .
                "</span>";
            }
        }
        return $list;
    }

    /**
     * @brief   Renders element to HTML.
     *
     * @return  (string) HTML rendered element
     **/
    public function __toString() {
        $marker             = $this->htmlMarker();
        $label              = $this->htmlLabel();
        $list               = $this->htmlList();
        $wrapperAttributes  = $this->htmlWrapperAttributes();
        $errorMessage       = $this->htmlErrorMessage();

        if ($this->skin === "select") {
            // render HTML select
            $inputAttributes = $this->htmlInputAttributes();

            return "<p {$wrapperAttributes}>" .
                "<label>" .
                    "<span class=\"label\">{$label}{$marker}</span>" .
                    "<select name=\"{$this->name}\"{$inputAttributes}>{$list}</select>" .
                "</label>" .
                $errorMessage .
            "</p>\n";
        } else {
            // render HTML radio button list

            return "<p {$wrapperAttributes}>" .
                "<label>" .
                    "<span class=\"label\">{$label}{$marker}</span>" .
                    "<span>{$list}</span>" .
                "</label>" .
                $errorMessage .
            "</p>\n";
        }
    }

    /**
     * @brief   Converts value to element specific type.
     *
     * @return  void
     **/
    protected function typeCastValue() {
        $this->value = (string) $this->value;
    }
}
