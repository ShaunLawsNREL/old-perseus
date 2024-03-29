<?php

// Load Perseus.
require_once('init.inc');

// Instantiate a System object.
$system = new Perseus\RegistrationSystem(dirname(__FILE__));

// Instantiate the database service.
include($system->config_file);
$system->newService('db', $db);

// Instantiate the Installer and install.
$installer = new Perseus\RegistrationSystemInstaller($system);
$installer->install();

// Instantiate the form.
$form_settings = array('action' => 'index.php', 'name' => 'registration');
$form = new Perseus\Tools\RegistrationForm($system, $form_settings);

// Instantiate the mailer.
$mailer = new Perseus\PhpMail($system);

include_once('top.inc');

//Check whether the form has been submitted

if (array_key_exists('check_submit', $_POST)) {

  //Converts the new line characters (\n) in the text area into HTML line breaks
  // (the <br /> tag).
  $_POST['dietary_needs'] = nl2br($_POST['dietary_needs']);

  // Store the submitted data.
  $data = $_POST;
  unset($data['check_submit']);
  unset($data['submit']);
  $system->db()->insert('registration', $data);

  // Get the field labels/data for the email body.
  foreach ($data as $label => $value) {
    if ('dietary_needs' == $label) {
      $label = 'Dietary needs';
    } elseif ('meal' == $label) {
      $value = (1 == $value) ? 'Yes' : 'No';
    }
    $submission .= ucfirst($label) . ': ' . $value . '<br />';
  }
  // Email the submitted data, if the site .
  try {
    if (!empty($system->settings['site_email']['mail'])) {
      $mailer->addRecipient($system->settings['site_email']['mail'], $system->settings['site_email']['name']);
      $mailer->from($data['mail'], $data['name']);
      $mailer->replyTo($data['mail'], $data['name']);
      $mailer->subject('BESC Characterization Workshop registration: ' . $data['name']);
      $body = 'The following information has been added to the BESC Characterization Workshop registration database:<br />';
      $body .= '<br />';
      $body .= $submission;
      pd($body);
      $mailer->body($body);
      $mailer->send();
    } else {
      throw new Exception('Unable to email submission - site email not specified in settings/settings.php' . '.', SYSTEM_ERROR);
    }
  }
  catch(Exception $e) {$system->handleException($e);}

  // Print out the values received in the browser.
  echo '<h1>Bioenergy Science Center (BESC) Characterization Workshop</h1>';
  echo '<br /><p>Thank you for submitting your registration. The data that we received was:</p>';
  echo $submission;

  print $system->theme('system/messages', $system->getMessages(SYSTEM_NOTICE));
  
} else {
  ?>
          <h1>Bioenergy Science Center (BESC) Characterization Workshop</h1>
          <h3>January 5th and 6th, 2014</h3>
          <div class="location label">Meeting Location:</div>
          <div class="location">The Mission Inn Hotel & Spa<br />
            3649 Mission Inn Ave.<br />
            Riverside, CA 92501<br />
          </div>
          <p>Hosted by the National Renewable Energy Laboratory</p>
          <br />
          <?php print $form->render(); ?>
          <?php print $system->theme('system/messages', $system->getMessages(SYSTEM_NOTICE)); ?>
<?php
  }
  include_once('bottom.inc');
?>