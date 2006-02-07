<?php
/**
* Classes to handle validation of form data.
*
* @package   AWL
* @subpackage   Validation
* @author    Emily Mossman <emily@catalyst.net.nz>
* @copyright Emily Mossman
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

/**
* Rules used for validation of form fields.
*/
class Validation
{
  /**#@+
  * @access private
  */
  /**
  * List of rules for validation
  * @var rules
  */
  var $rules = array();

  /**
  * The javascript function name to call onsubmit of the form
  * @var func_name
  */
  var $func_name = "";

  /**#@-*/

  /**
  * Initialise a new validation.
  * @param string $func_name The javascript function name to call onsubmit of the form
  */
  function Validation($func_name)
  {
    $this->func_name = $func_name;
  }


  /**
  * Adds a validation rule for a specific field upon submission of the form.
  * You must call RenderRules below RenderFields when outputing the page
  * @param string $fieldname The name of the field.
  * @param string $error_message The message to display on unsuccessful validation.
  * @param string $function_name The function to call to validate the field,
  * taking one parameter, which is the field and returns true if the field is valid.
  * @param string $jsparam An optional parameter to pass to the javascript function, eg regexp.
  * Must be an object or regexp, or a string with extra quotes within them.
  */
  function AddRule( $fieldname, $error_message, $function_name, $jsparam = '' )
  {
    $this->rules[] = array($fieldname, $error_message, $function_name, $jsparam);
  }

  /**
  * Returns the javascript for form validation using the rules.
  * @param string $onsubmit The name of the function called on submission of the form.
  * @return string HTML/Javascript for form validation.
  */
  function RenderJavascript()
  {
    if(! count($this->rules) ) return "";

    $html = <<<EOHTML
<script language="JavaScript">
function $this->func_name(form)
{
  var error_message = "";\n
EOHTML;

    foreach($this->rules as $rule) {
      list($fieldname, $error_message, $function_name, $jsparam) = $rule;

      if ("" != $jsparam) $jsparam = ", " . $jsparam ; // for regexp

    $html .= <<<EOHTML
if(!$function_name(form.$fieldname$jsparam)) error_message += "$error_message\\n";
EOHTML;
    }

    $html .= <<<EOHTML
if(error_message == "") return true;
alert("Errors:"+"\\n"+error_message);
return false;
}
</script>
EOHTML;

    return $html;
  }

  /**
  * Validates the form according to it's rules.
  * @param object $object The data object that requires form validation.
  * @return boolean True if the validation succeeded.
  */
  function Validate($object)
  {
    global $c;
    if(! count($this->rules) ) return;

    foreach($this->rules as $rule) {
      list($fieldname, $error_message, $function_name, $jsparam) = $rule;

      if (!$this->$function_name($object->Get($fieldname))) {
        $valid = false;
        $c->messages[] = $error_message;
      }

    }

    return $valid;
  }

  /**
  * Checks if a string is empty
  * @param string $field_string The field value that is being checked.
  * @return boolean True if the string is not empty.
  */
  function not_empty($field_string)
  {
    return ($field_string != "");
  }
}

?>