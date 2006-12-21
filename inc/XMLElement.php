<?php
/**
* A class to assist with construction of XML documents
*
* @package   awl
* @subpackage   XMLElement
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst .Net Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
require_once("AWLUtilities.php");

/**
* A class for XML elements which may have attributes, or contain
* other XML sub-elements
*
* @package   awl
*/
class XMLElement {
  var $tagname;
  var $xmlns;
  var $attributes;
  var $content;
  var $_parent;

  /**
  * Constructor - nothing fancy as yet.
  *
  * @param string The tag name of the new element
  * @param mixed Either a string of content, or an array of sub-elements
  * @param array An array of attribute name/value pairs
  */
  function XMLElement( $tagname, $content=false, $attributes=false ) {
    $this->tagname=$tagname;
    if ( gettype($content) == "object" ) {
      // Subtree to be parented here
      $this->content = array(&$content);
    }
    else {
      // Array or text
      $this->content = $content;
    }
    $this->attributes = $attributes;
    if ( isset($this->attributes['xmlns']) ) {
      $this->xmlns = $this->attributes['xmlns'];
    }
  }

  /**
  * Set an element attribute to a value
  *
  * @param string The attribute name
  * @param string The attribute value
  */
  function SetAttribute($k,$v) {
    if ( gettype($this->attributes) != "array" ) $this->attributes = array();
    $this->attributes[$k] = $v;
    if ( strtolower($k) == 'xmlns' ) {
      $this->xmlns = $v;
    }
  }

  /**
  * Set the whole content to a value
  *
  * @param mixed The element content, which may be text, or an array of sub-elements
  */
  function SetContent($v) {
    $this->content = $v;
  }

  /**
  * Accessor for the tag name
  *
  * @return string The tag name of the element
  */
  function GetTag() {
    return $this->tagname;
  }

  /**
  * Accessor for the attributes
  *
  * @return array The attributes of this element
  */
  function GetAttributes() {
    return $this->attributes;
  }

  /**
  * Accessor for the content
  *
  * @return array The content of this element
  */
  function GetContent() {
    return $this->content;
  }

  /**
  * Return an array of elements matching the specified tag
  *
  * @return array The XMLElements within the tree which match this tag
  */
  function GetElements( $tag, $recursive=false ) {
    $elements = array();
    printf( "Getting elements like %s %swithin %s\n", $tag, ($recursive?'recursively ':''), $this->tagname );
    if ( gettype($this->content) == "array" ) {
      foreach( $this->content AS $k => $v ) {
        if ( $v->tagname == $tag ) {
          $elements[] = $v;
        }
        if ( $recursive ) {
          $elements = $elements + $this->GetElements($tag,true);
        }
      }
    }
    return $elements;
  }


  /**
  * Return an array of elements matching the specified path
  *
  * @return array The XMLElements within the tree which match this tag
  */
  function GetPath( $path ) {
    $elements = array();
    // printf( "Querying within '%s' for path '%s'\n", $this->tagname, $path );
    if ( !preg_match( '#(/)?([^/]+)(/?.*)$#', $path, $matches ) ) return $elements;
    // printf( "Matches: %s -- %s -- %s\n", $matches[1], $matches[2], $matches[3] );
    if ( $matches[2] == '*' || $matches[2] == $this->tagname ) {
      if ( $matches[3] == '' ) {
        /**
        * That is the full path
        */
        $elements[] = $this;
      }
      else if ( gettype($this->content) == "array" ) {
        /**
        * There is more to the path, so we recurse into that sub-part
        */
        foreach( $this->content AS $k => $v ) {
          $elements = array_merge( $elements, $v->GetPath($matches[3]) );
        }
      }
    }

    if ( $matches[1] != '/' && gettype($this->content) == "array" ) {
      /**
      * If our input $path was not rooted, we recurse further
      */
      foreach( $this->content AS $k => $v ) {
        $elements = array_merge( $elements, $v->GetPath($path) );
      }
    }
    // printf( "Found %d within '%s' for path '%s'\n", count($elements), $this->tagname, $path );
    return $elements;
  }


  /**
  * Add a sub-element
  *
  * @param object An XMLElement to be appended to the array of sub-elements
  */
  function AddSubTag(&$v) {
    if ( gettype($this->content) != "array" ) $this->content = array();
    $this->content[] =& $v;
    return count($this->content);
  }

  /**
  * Add a new sub-element
  *
  * @param string The tag name of the new element
  * @param mixed Either a string of content, or an array of sub-elements
  * @param array An array of attribute name/value pairs
  */
  function &NewElement( $tagname, $content=false, $attributes=false ) {
    if ( gettype($this->content) != "array" ) $this->content = array();
    $element =& new XMLElement($tagname,$content,$attributes);
    $this->content[] =& $element;
    return $element;
  }


  /**
  * Render just the internal content
  *
  * @return string The content of this element, as a string without this element wrapping it.
  */
  function RenderContent($indent=0,$xmldef="") {
    $r = "";
    if ( is_array($this->content) ) {
      /**
      * Render the sub-elements with a deeper indent level
      */
      $r .= "\n";
      foreach( $this->content AS $k => $v ) {
        if ( is_object($v) ) {
          $r .= $v->Render($indent+1);
        }
      }
      $r .= substr("                        ",0,$indent);
    }
    else {
      /**
      * Render the content, with special characters escaped
      *
      */
      $r .= htmlspecialchars($this->content, ENT_NOQUOTES );
    }
    return $r;
  }


  /**
  * Render the document tree into (nicely formatted) XML
  *
  * @param int The indenting level for the pretty formatting of the element
  */
  function Render($indent=0,$xmldef="") {
    $r = ( $xmldef == "" ? "" : $xmldef."\n");
    $r .= substr("                        ",0,$indent) . '<' . $this->tagname;
    if ( gettype($this->attributes) == "array" ) {
      /**
      * Render the element attribute values
      */
      foreach( $this->attributes AS $k => $v ) {
        $r .= sprintf( ' %s="%s"', $k, htmlspecialchars($v) );
      }
    }
    if ( (is_array($this->content) && count($this->content) > 0) || (!is_array($this->content) && strlen($this->content) > 0) ) {
      $r .= ">";
      $r .= $this->RenderContent($indent,$xmldef);
      $r .= '</' . $this->tagname.">\n";
    }
    else {
      $r .= "/>\n";
    }
    return $r;
  }
}


/**
* Rebuild an XML tree in our own style from the parsed XML tags using
* a tail-recursive approach.
*
* @param array $xmltags An array of XML tags we get from using the PHP XML parser
* @param intref &$start_from A pointer to our current integer offset into $xmltags
* @return mixed Either a single XMLElement, or an array of XMLElement objects.
*/
function BuildXMLTree( $xmltags, &$start_from ) {
  $content = array();

  for( $i=0; $i<10; $i++ ) {
    $tagdata = $xmltags[$start_from++];
    if ( !isset($tagdata) ) break;
    if ( $tagdata['type'] == "close" ) break;
    if ( $tagdata['type'] == "open" ) {
      $subtree = BuildXMLTree( $xmltags, $start_from );
      $content[] = new XMLElement($tagdata['tag'],$subtree);
    }
    else if ( $tagdata['type'] == "complete" ) {
      $content[] = new XMLElement($tagdata['tag'],$tagdata['value'],$tagdata['attributes']);
    }
  }

  /**
  * If there is only one element, return it directly, otherwise return the
  * array of them
  */
  if ( count($content) == 1 ) {
    return $content[0];
  }
  return $content;
}

?>