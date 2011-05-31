<?php
/**
 * Populate form example
 *
 * The htmlform->populate method provides a comfortable way to fill in
 * default values before displaying the form. It's more convenient than setting
 * the 'defaultValue' parameter for each input element individually.
 **/

/**
 * Load the library...
 **/
require_once('../../htmlform.php');

/**
 * Create the example form 'populateForm'
 **/
$form = new depage\htmlform\htmlform('populateForm');

/**
 * Create input elements
 **/
$form->addText('username', array('label' => 'User name'));
$form->addEmail('email', array('label' => 'Email address'));

/**
 * The populate method selects input elements by name. Hence, elements in
 * fieldsets/steps are accessed exactly like elements directly attached to the
 * form.
 **/
$fieldset = $form->addFieldset('fieldset', array('label' => 'User name'));
$fieldset->addText('fieldsetText', array('label' => 'Text in fieldset'));

/**
 * Parsing an associative array to the populate method
 * ('element name' => value).
 **/
$form->populate(
    array(
        'username'      => 'depage',
        'email'         => 'mail@somedomain.org',
        'fieldsetText'  => 'It works!',
    )
);

$form->process();

if ($form->validate()) {
    /**
     * Success, do something useful with the data and clear the session.
     **/
    echo('<pre>');
    var_dump($form->getValues());
    echo('</pre>');

    $form->clearSession();
} else {
    echo ('<link type="text/css" rel="stylesheet" href="test.css">');
    /**
     * Display the form.
     **/
    echo ($form);
}
