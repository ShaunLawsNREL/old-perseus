<?php
/**
 * @file
 * Define common form processing and HTML elements.
 */
namespace Perseus;

class Form extends Service {
  // A unique name of the form.
  protected $name;

  // Form parameters
  private $action;
  private $method;
  private $enctype = '';

  // Form fields
  private $fields = array();

  // Default field values
  protected $defaults = array();

  // Weight incrementer for unweighted form fields.
  private $weight = 10;

  /**
   * Constructor
   */
  public function __construct($system, array $settings = array()) {
    parent::__construct($system);

    $this->name    = (isset($settings['name']) ? $settings['name'] : uniqid());
    $this->action  = (isset($settings['action']) ? filter_xss($settings['action']) : filter_xss($_SERVER['PHP_SELF']));
    $this->method  = (isset($settings['method']) ? $method : 'POST');
    $this->enctype = (isset($settings['enctype']) ? $enctype : 'multipart/form-data');
  }

  /**
   * Add a form item to the form.
   */
  public function addItem($type, $data, $weight = 0) {
    try {
      // Make sure the field definition method exists.
      $method = "build" . ucwords($type);
      if (!method_exists($this, $method)) {
        throw new Exception("Undefined form field type: {$type}", SYSTEM_ERROR);
      }

      // Make sure the form field has a name
      if (!isset($data['name'])) {
        throw new Exception("Name not provided for form field.", SYSTEM_ERROR);
      }

      // Autoincrememnt a weight for the item if not provided.
      if (!$weight) {
        $weight = $this->weight += 5;
      }

      // Default form field settings.
      $defaults = array(
        'label' => '',
        'description' => '',
        'options' => array(),
        'attributes' => array('class' => array('form-item')),
        'required' => FALSE,
      );

      // Add default values
      $data += $defaults;

      // Create the item.
      $this->fields["{$weight}:{$data['name']}"] = $this->{$method}($data);
    }
    catch(Exception $e) {System::handleException($e);}
  }

  /**
   * Set default values
   */
  public function setDefaults($vals) {
    // Sanitize the data.
    foreach ($vals as $field => $val) {
      $this->defaults[check_plain($field)] = check_plain($val);
    }
  }

  /**
   * Build a submit button.
   */
  protected function buildHidden(array $data) {
    $data['attributes']['type'] = 'hidden';
    $data['attributes']['name'] = $data['name'];
    $data['attributes']['value'] = $data['value'];
    $text = $this->system->theme('form/hidden', $data);

    // Wrap and return
    $element['attributes']['class'][] = 'text-field';
    $element['attributes']['class'][] = $data['name'];
    $element['output'] = $text;

    return $this->system->theme('form/form-element', $element);
  }

  /**
   * Build a div containing HTML.
   */
  protected function buildHtml(array $data) {
    $data['attributes']['class'][] = $data['name'];
    return $this->system->theme('form/html', $data);
  }

  /**
   * Build a text input.
   */
  protected function buildInput(array $data) {
    $data['attributes']['type'] = 'input';
    $data['attributes']['name'] = $data['name'];
    $text = $this->system->theme('form/input', $data);

    // Wrap and return
    $element['attributes']['class'][] = 'input-field';
    $element['attributes']['class'][] = $data['name'];
    $element['label'] = $data['label'];
    $element['output'] = $text;
    $element['required'] = $data['required'];

    return $this->system->theme('form/form-element', $element);
  }

  /**
   * Build a set of radio options.
   */
  protected function buildRadios(array $data) {
    $radios = array();

    // Calculate grouping
    $groupcnt = (isset($data['cols']) ? ceil(count($data['options']) / $data['cols']) : count($data['options']));
    $g = 1;

    foreach ($data['options'] as $value => $label) {
      $attributes = array(
        'input' => array(
          'value' => $value,
          'name'  => $data['name'],
        ),
        'label' => array(
          'for' => $value,
        ),
      );

      // Add the selected value
      if (isset($data['default']) && $value == $data['default']) {
        $attributes['input']['checked'] = 'checked';
      }

      $radio = $this->system->theme('form/radio', array('attributes' => $attributes, 'label' => $label));
      $radios[ceil($g/$groupcnt)][] = $this->system->theme('form/form-element', array('output' => $radio, 'attributes' => array()));
      $g++;
    }

    $data['output'] = $this->system->theme('form/radios', array('options' => $radios));
    $data['attributes']['class'][] = 'radios';
    $data['attributes']['class'][] = $data['name'];

    return $this->system->theme('form/form-element', $data);
  }

  /**
   * Build a Select list.
   */
  protected function buildSelect(array $data) {
    $options = '';

    // Build the options
    foreach ($data['options'] as $value => $label) {
      $attributes = array('value' => $value);
      $vars = array('label' => $label);

      // Add the selected value
      if (isset($data['default']) && $value == $data['default']) {
        $attributes['selected'] = 'selected';
      }

      $vars['attributes'] = $attributes;

      $option = $this->system->theme('form/select-option', $vars);
      $options .= $option;
    }

    // Build the select element.
    $data['attributes']['name'] = $data['name'];
    $data['output'] = $options;
    $select = $this->system->theme('form/select', $data);

    // Wrap and return
    $element['attributes']['class'][] = 'select';
    $element['attributes']['class'][] = $data['name'];
    $element['label'] = $data['label'];
    $element['output'] = $select;
    $element['required'] = $data['required'];

    return $this->system->theme('form/form-element', $element);
  }

  /**
   * Build a submit button.
   */
  protected function buildSubmit(array $data) {
    $data['attributes']['type'] = 'submit';
    $data['attributes']['name'] = $data['name'];
    return $this->system->theme('form/submit', $data);
  }

  /**
   * Build a table.
   *
   * Not actually a form item.  Need to start branching an straight HTML object.
   */
  protected function buildTable(array $data) {
    return $this->system->theme('table', $data);
  }

  /**
   * Build a text area.
   */
  protected function buildTextarea(array $data) {
    $data['attributes']['name'] = $data['name'];
    $text = $this->system->theme('form/textarea', $data);

    // Wrap and return
    $element['attributes']['class'][] = 'textarea';
    $element['attributes']['class'][] = $data['name'];
    $element['label'] = $data['label'];
    $element['output'] = $text;
    $element['required'] = $data['required'];

    return $this->system->theme('form/form-element', $element);
  }

  /**
   * Render the form.
   */
  public function render() {
    $out = '';

    try {
      // @todo - ksort doesn't work reliably on weight:name keys. Using default
      // weights, the keys will start with with 5:<name>. Using ksort that field
      // will be placed after 10:<name> through 45:<name>.
      ksort($this->fields);

      foreach ($this->fields as $weight => $field) {
        list(,$name) = explode(':', $weight);
        $out .= $field;
      }
    }
    catch(Exception $e) {System::handleException($e);}

    $vars['output'] = $out;
    $vars['attributes'] = array(
      'method'  => $this->method,
      'action'  => $this->action,
      'enctype' => $this->enctype,
      'name'    => $this->name,
      'id'      => unique_id($this->name),
    );

    return $this->system->theme('form/form', $vars);
  }
}
