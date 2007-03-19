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
    @error_log( $c->sysabbr.": $type: $component:". vsprintf( $format, $args ) );
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


if ( !function_exists("uuid") ) {
/**
 * Generates a Universally Unique IDentifier, version 4.
 *
 * RFC 4122 (http://www.ietf.org/rfc/rfc4122.txt) defines a special type of Globally
 * Unique IDentifiers (GUID), as well as several methods for producing them. One
 * such method, described in section 4.4, is based on truly random or pseudo-random
 * number generators, and is therefore implementable in a language like PHP.
 *
 * We choose to produce pseudo-random numbers with the Mersenne Twister, and to always
 * limit single generated numbers to 16 bits (ie. the decimal value 65535). That is
 * because, even on 32-bit systems, PHP's RAND_MAX will often be the maximum *signed*
 * value, with only the equivalent of 31 significant bits. Producing two 16-bit random
 * numbers to make up a 32-bit one is less efficient, but guarantees that all 32 bits
 * are random.
 *
 * The algorithm for version 4 UUIDs (ie. those based on random number generators)
 * states that all 128 bits separated into the various fields (32 bits, 16 bits, 16 bits,
 * 8 bits and 8 bits, 48 bits) should be random, except : (a) the version number should
 * be the last 4 bits in the 3rd field, and (b) bits 6 and 7 of the 4th field should
 * be 01. We try to conform to that definition as efficiently as possible, generating
 * smaller values where possible, and minimizing the number of base conversions.
 *
 * @copyright  Copyright (c) CFD Labs, 2006. This function may be used freely for
 *              any purpose ; it is distributed without any form of warranty whatsoever.
 * @author      David Holmes <dholmes@cfdsoftware.net>
 *
 * @return  string  A UUID, made up of 32 hex digits and 4 hyphens.
 */

  function uuid() {

    // The field names refer to RFC 4122 section 4.1.2

    return sprintf('%04x%04x-%04x-%03x4-%04x-%04x%04x%04x',
        mt_rand(0, 65535), mt_rand(0, 65535), // 32 bits for "time_low"
        mt_rand(0, 65535), // 16 bits for "time_mid"
        mt_rand(0, 4095),  // 12 bits before the 0100 of (version) 4 for "time_hi_and_version"
        bindec(substr_replace(sprintf('%016b', mt_rand(0, 65535)), '01', 6, 2)),
            // 8 bits, the last two of which (positions 6 and 7) are 01, for "clk_seq_hi_res"
            // (hence, the 2nd hex digit after the 3rd hyphen can only be 1, 5, 9 or d)
            // 8 bits for "clk_seq_low"
        mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535) // 48 bits for "node"
    );
  }
}

if ( !function_exists("translate") ) {
  require_once("Translation.php");
}

 if ( !function_exists("clone") && version_compare(phpversion(), '5.0') < 0) {
  /**
  * PHP5 screws with the assignment operator changing so that $a = $b means that
  * $a becomes a reference to $b.  There is a clone() that we can use in PHP5, so
  * we have to emulate that for PHP4.  Bleargh.
  */
  eval( 'function clone($object) { return $object; }' );
}

?>