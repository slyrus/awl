<?php
/**
* Handling of namespacing for XML documents
*
* @package awl
* @subpackage XMLDocument
* @author Andrew McMillan <andrew@morphoss.com>
* @copyright Morphoss Ltd - http://www.morphoss.com/
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2 or later
*
*/

require_once("XMLElement.php");

/**
* A class for XML Documents which will contain namespaced XML elements
*
* @package   awl
*/
class XMLDocument {

  /**#@+
  * @access private
  */
  /**
  * holds the namespaces which this document has been configured for.
  * @var namespaces
  */
  var $namespaces;

  /**
  * holds the prefixes which are shorthand for the namespaces.
  * @var namespaces
  */
  var $prefixes;

  /**
  * Simple XMLDocument constructor
  *
  * @param array $namespaces An array of 'namespace' => 'prefix' pairs, where the prefix is used as a short form for the namespace.
  */
  function XMLDocument( $namespaces = null ) {
    $this->namespaces = array();
    $this->prefixes = array();
    if ( $namespaces != null ) {
      foreach( $namespaces AS $ns => $prefix ) {
        $this->namespaces[$ns] = $prefix;
        $this->prefixes[$prefix] = $prefix;
      }
    }
    $this->next_prefix = 0;
  }

  /**
  * Add a new namespace to the document, optionally specifying it's short prefix
  *
  * @param string $namespace The full namespace name to be added
  * @param string $prefix An optional short form for the namespace.
  */
  function AddNamespace( $namespace, $prefix = null ) {
    if ( !isset($this->namespaces[$namespace]) ) {
      if ( $prefix == null ) {
        //  Try and build a prefix based on the first alphabetic character of the last element of the namespace
        if ( preg_match('/^(.*):([^:]+)$/', $namespace, $matches) ) {
          $alpha = preg_replace( '/[^a-z]/i', '', $matches[2] );
          $prefix = strtoupper(substr($alpha,0,1));
        }
        else {
          $prefix = 'X';
        }
        $i = "";
        if ( isset($this->prefixes[$prefix]) ) {
          for ( $i=1; $i<10 && isset($this->prefixes["$prefix$i"]); $i++ ) {
          }
        }
        if ( isset($this->prefixes["$prefix$i"]) ) {
          dbg_error_log("ERROR", "Cannot find a free prefix for this namespace");
          exit;
        }
        $prefix = "$prefix$i";
        dbg_error_log("XMLDocument", "auto-assigning prefix of '%s' for ns of '%s'", $prefix, $namespace );
      }
      else if ( $prefix == "" || isset($this->prefixes[$prefix]) ) {
        dbg_error_log("ERROR", "Cannot assign the same prefix to two different namespaces");
        exit;
      }

      $this->prefixes[$prefix] = $prefix;
      $this->namespaces[$namespace] = $prefix;
    }
    else {
      if ( $prefix != null && $this->namespaces[$namespace] != $prefix ) {
        dbg_error_log("ERROR", "Cannot use the same namespace with two different prefixes");
        exit;
      }
    }
  }


  /**
  * Return a tag with namespace stripped and replaced with a short form, and the ns added to the document.
  *
  */
  function GetXmlNsArray() {

    $ns = array();
    foreach( $this->namespaces AS $n => $p ) {
      if ( $p == "" ) $ns["xmlns"] = $n; else $ns["xmlns:$p"] = $n;
    }

    return $ns;
  }


  /**
  * Return a tag with namespace stripped and replaced with a short form, and the ns added to the document.
  *
  * @param string $in_tag The tag we want a namespace prefix on.
  * @param string $namespace The namespace we want it in (which will be parsed from $in_tag if not present
  * @param string $prefix The prefix we would like to use.  Leave it out and one will be assigned.
  *
  * @return string The tag with a namespace prefix consistent with previous tags in this namespace.
  */
  function Tag( $in_tag, $namespace=null, $prefix=null ) {

    if ( $namespace == null ) {
      // Attempt to split out from namespace:tag
      if ( preg_match('/^(.*):([^:]+)$/', $in_tag, $matches) ) {
        $namespace = $matches[1];
        $tag = $matches[2];
      }
      else {
        // There is nothing we can do here
        return $in_tag;
      }
    }
    else {
      $tag = $in_tag;
    }

    if ( !isset($this->namespaces[$namespace]) ) {
      $this->AddNamespace( $namespace, $prefix );
    }
    $prefix = $this->namespaces[$namespace];

    return $prefix . ($prefix == "" ? "" : ":") . $tag;
  }


  /**
  * Special helper for tags in the Apple Calendarserver namespace.
  *
  * @param string $in_tag The tag we want a namespace prefix on.
  *
  * @return string The tag with a namespace prefix consistent with previous tags in this namespace.
  */
  function Calendarserver( $tag ) {
    return $this->Tag( $tag, 'http://calendarserver.org/ns/', "A" );
  }


  /**
  * Special helper for tags in the CalDAV namespace.
  *
  * @param string $in_tag The tag we want a namespace prefix on.
  *
  * @return string The tag with a namespace prefix consistent with previous tags in this namespace.
  */
  function Caldav( $tag ) {
    return $this->Tag( $tag, 'urn:ietf:params:xml:ns:caldav', "C" );
  }

}