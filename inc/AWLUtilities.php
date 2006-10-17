<?php
/**
* Utility functions of a general nature which are used by
* most AWL library classes.
*
* @package   awl
* @subpackage   Utilities
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst IT Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

if ( !function_exists('dbg_error_log') ) {
  /**
  * Writes a debug message into the error log using printf syntax.  If the first
  * parameter is "ERROR" then the message will _always_ be logged.
  * Otherwise, the first parameter is a "component" name, and will only be logged
  * if $c->dbg["component"] is set to some non-null value.
  *
  * If you want to see every log message then $c->dbg["ALL"] can be set, to
  * override the debugging status of the individual components.
  *
  * @var string $component The component to identify itself, or "ERROR", or "LOG:component"
  * @var string $format A format string for the log message
  * @var [string $parameter ...] Parameters for the format string.
  */
  function dbg_error_log() {
    global $c;
    $argc = func_num_args();
    $args = func_get_args();
    $type = "DBG";
    $component = array_shift($args);
    if ( substr( $component, 0, 3) == "LOG" ) {
      // Special escape case for stuff that always gets logged.
      $type = 'LOG';
      $component = substr($component,4);
    }
    else if ( $component == "ERROR" ) {
      $type = "***";
    }
    else if ( isset($c->dbg["ALL"]) ) {
      $type = "ALL";
    }
    else if ( !isset($c->dbg[strtolower($component)]) ) return;

    if ( 2 <= $argc ) {
      $format = array_shift($args);
    }
    else {
      $format = "%s";
    }
    error_log( $c->sysabbr.": $type: $component:". vsprintf( $format, $args ) );
  }
}



if ( !function_exists('apache_request_headers') ) {
  /**
  * Forward compatibility so we can use the non-deprecated name in PHP4
  * @package awl
  */
  function apache_request_headers() {
    return getallheaders();
  }
}



if ( !function_exists('dbg_log_array') ) {
  /**
  * Function to dump an array to the error log, possibly recursively
  *
  * @var string $component Which component should this log message identify itself from
  * @var string $name What name should this array dump identify itself as
  * @var array $arr The array to be dumped.
  * @var boolean $recursive Should the dump recurse into arrays/objects in the array
  */
  function dbg_log_array( $component, $name, $arr, $recursive = false ) {
    if ( !isset($arr) || (gettype($arr) != 'array' && gettype($arr) != 'object') ) {
      dbg_error_log( $component, "%s: array is not set, or is not an array!", $name);
      return;
    }
    foreach ($arr as $key => $value) {
      dbg_error_log( $component, "%s: >>%s<< = >>%s<<", $name, $key,
                      (gettype($value) == 'array' || gettype($value) == 'object' ? gettype($value) : $value) );
      if ( $recursive && (gettype($value) == 'array' || (gettype($value) == 'object' && "$key" != 'self' && "$key" != 'parent') ) ) {
        dbg_log_array( $component, "$name"."[$key]", $value, $recursive );
      }
    }
  }
}



if ( !function_exists("session_salted_md5") ) {
  /**
  * Make a salted MD5 string, given a string and (possibly) a salt.
  *
  * If no salt is supplied we will generate a random one.
  *
  * @param string $instr The string to be salted and MD5'd
  * @param string $salt Some salt to sprinkle into the string to be MD5'd so we don't get the same PW always hashing to the same value.
  * @return string The salt, a * and the MD5 of the salted string, as in SALT*SALTEDHASH
  */
  function session_salted_md5( $instr, $salt = "" ) {
    if ( $salt == "" ) $salt = substr( md5(rand(100000,999999)), 2, 8);
    dbg_error_log( "Login", "Making salted MD5: salt=$salt, instr=$instr, md5($salt$instr)=".md5($salt . $instr) );
    return ( sprintf("*%s*%s", $salt, md5($salt . $instr) ) );
  }
}



if ( !function_exists("session_validate_password") ) {
  /**
  * Checks what a user entered against the actual password on their account.
  * @param string $they_sent What the user entered.
  * @param string $we_have What we have in the database as their password.  Which may (or may not) be a salted MD5.
  * @return boolean Whether or not the users attempt matches what is already on file.
  */
  function session_validate_password( $they_sent, $we_have ) {
    if ( ereg('^\*\*.+$', $we_have ) ) {
      //  The "forced" style of "**plaintext" to allow easier admin setting
      return ( "**$they_sent" == $we_have );
    }

    if ( ereg('^\*(.+)\*.+$', $we_have, $regs ) ) {
      // A nicely salted md5sum like "*<salt>*<salted_md5>"
      $salt = $regs[1];
      $md5_sent = session_salted_md5( $they_sent, $salt ) ;
      return ( $md5_sent == $we_have );
    }

    // Anything else is bad
    return false;

  }
}



if ( !function_exists("replace_uri_params") ) {
  /**
  * Given a URL (presumably the current one) and a parameter, replace the value of parameter,
  * extending the URL as necessary if the parameter is not already there.
  * @param string $uri The URI we will be replacing parameters in.
  * @param array $replacements An array of replacement pairs array( "replace_this" => "with this" )
  * @return string The URI with the replacements done.
  */
  function replace_uri_params( $uri, $replacements ) {
    $replaced = $uri;
    foreach( $replacements AS $param => $new_value ) {
      $rxp = preg_replace( '/([\[\]])/', '\\\\$1', $param );  // Some parameters may be arrays.
      $regex = "/([&?])($rxp)=([^&]+)/";
      dbg_error_log("core", "Looking for [%s] to replace with [%s] regex is %s and searching [%s]", $param, $new_value, $regex, $replaced );
      if ( preg_match( $regex, $replaced ) )
        $replaced = preg_replace( $regex, "\$1$param=$new_value", $replaced);
      else
        $replaced .= "&$param=$new_value";
    }
    if ( ! preg_match( '/\?/', $replaced  ) ) {
      $replaced = preg_replace("/&(.+)$/", "?\$1", $replaced);
    }
    $replaced = str_replace("&amp;", "--AmPeRsAnD--", $replaced);
    $replaced = str_replace("&", "&amp;", $replaced);
    $replaced = str_replace("--AmPeRsAnD--", "&amp;", $replaced);
    dbg_error_log("core", "URI <<$uri>> morphed to <<$replaced>>");
    return $replaced;
  }
}

?>