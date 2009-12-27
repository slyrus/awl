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
  * @var prefixes
  */
  var $prefixes;

  /**
  * Holds the root document for the tree
  * @var root
  */
  var $root;

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
      if ( isset($prefix) && ($prefix == "" || isset($this->prefixes[$prefix])) ) $prefix = null;
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
      if ( isset($this->namespaces[$namespace]) && $this->namespaces[$namespace] != $prefix ) {
        dbg_error_log("ERROR", "Cannot use the same namespace with two different prefixes");
        exit;
      }
      $this->prefixes[$prefix] = $prefix;
      $this->namespaces[$namespace] = $prefix;
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
  * Special helper for namespaced tags.
  *
  * @param object $element The tag are adding a new namespaced element to
  * @param string $tag the tag name, possibly prefixed with the namespace
  * @param mixed  $content The content of the tag
  * @param array  $attributes An array of key/value pairs of attributes.
  * @param string $namespace The namespace for the tag
  *
  */
  function NSElement( &$element, $in_tag, $content=false, $attributes=false, $namespace=null ) {
    if ( $namespace == null && preg_match('/^(.*):([^:]+)$/', $in_tag, $matches) ) {
      $namespace = $matches[1];
      $tag = $matches[2];
    }
    else {
      $tag = $in_tag;
    }

    if ( isset($namespace) && !isset($this->namespaces[$namespace]) ) $this->AddNamespace( $namespace );
    $element->NewElement( $tag, $content, $attributes, $namespace );
  }


  /**
  * Special helper for tags in the DAV: namespace.
  *
  * @param object $element The tag are adding a new namespaced element to
  * @param string $tag the tag name
  * @param mixed  $content The content of the tag
  * @param array  $attributes An array of key/value pairs of attributes.
  */
  function DAVElement( &$element, $tag, $content=false, $attributes=false ) {
    $this->NSElement( $element, $tag, $content, $attributes, 'DAV:' );
  }


  /**
  * Special helper for tags in the urn:ietf:params:xml:ns:caldav namespace.
  *
  * @param object $element The tag are adding a new namespaced element to
  * @param string $tag the tag name
  * @param mixed  $content The content of the tag
  * @param array  $attributes An array of key/value pairs of attributes.
  */
  function CalDAVElement( &$element, $tag, $content=false, $attributes=false ) {
    if ( !isset($this->namespaces['urn:ietf:params:xml:ns:caldav']) ) $this->AddNamespace( 'urn:ietf:params:xml:ns:caldav', 'C' );
    $this->NSElement( $element, $tag, $content, $attributes, 'urn:ietf:params:xml:ns:caldav' );
  }


  /**
  * Special helper for tags in the urn:ietf:params:xml:ns:caldav namespace.
  *
  * @param object $element The tag are adding a new namespaced element to
  * @param string $tag the tag name
  * @param mixed  $content The content of the tag
  * @param array  $attributes An array of key/value pairs of attributes.
  */
  function CalendarserverElement( &$element, $tag, $content=false, $attributes=false ) {
    if ( !isset($this->namespaces['http://calendarserver.org/ns/']) ) $this->AddNamespace( 'http://calendarserver.org/ns/', 'A' );
    $this->NSElement( $element, $tag, $content, $attributes, 'http://calendarserver.org/ns/' );
  }


  /**
  * @param string $tagname The tag name of the new element
  * @param mixed $content Either a string of content, or an array of sub-elements
  * @param array $attributes An array of attribute name/value pairs
  * @param array $xmlns An XML namespace specifier
  */
  function NewXMLElement( $tagname, $content=false, $attributes=false, $xmlns=null ) {
    if ( isset($xmlns) && !isset($this->namespaces[$xmlns]) ) $this->AddNamespace( $xmlns );
    return new XMLElement($tagname, $content, $attributes, $xmlns );
  }

  /**
  * Render the document tree into (nicely formatted) XML
  *
  * @param mixed $root A root XMLElement or a tagname to create one with the remaining parameters.
  * @param mixed $content Either a string of content, or an array of sub-elements
  * @param array $attributes An array of attribute name/value pairs
  * @param array $xmlns An XML namespace specifier
  *
  * @return A rendered namespaced XML document.
  */
  function Render( $root, $content=false, $attributes=false, $xmlns=null ) {
    if ( is_object($root) ) {
      /** They handed us a pre-existing object.  We'll just use it... */
      $this->root = $root;
    }
    else {
      /** We got a tag name, so we need to create the root element */
      $this->root = $this->NewXMLElement( $root, $content, $attributes, $xmlns );
    }

    /**
    * Add our namespace attributes here.
    */
    foreach( $this->namespaces AS $n => $p ) {
      $this->root->SetAttribute( 'xmlns'.($p == '' ? '' : ':') . $p, $n);
    }

    /** And render... */
    return $this->root->Render(0,'<?xml version="1.0" encoding="utf-8" ?>');
  }

  /**
  * Return a DAV::href XML element, or an array of them
  * @param mixed $url The URL (or array of URLs) to be wrapped in DAV::href tags
  *
  * @return XMLElement The newly created XMLElement object.
  */
  function href($url) {
    if ( is_array($url) ) {
      $set = array();
      foreach( $url AS $href ) {
        $set[] = $this->href( $href );
      }
      return $set;
    }
    return $this->NewXMLElement('href', $url, false, 'DAV:');
  }

}


