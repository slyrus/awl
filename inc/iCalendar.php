<?php
/**
* A Class for handling iCalendar data
*
* @package awl
* @subpackage iCalendar
* @author Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst IT Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
require_once("XMLElement.php");

/**
* A Class for representing properties within an iCalendar
*
* @package awl
*/
class iCalProp {
  /**#@+
   * @access private
   */

  /**
   * The name of this property
   *
   * @var string
   */
  var $name;

  /**
   * An array of parameters to this property, represented as key/value pairs.
   *
   * @var array
   */
  var $parameters;

  /**
   * The value of this property.
   *
   * @var string
   */
  var $content;

  /**
   * The original value that this was parsed from, if that's the way it happened.
   *
   * @var string
   */
  var $rendered;

  /**#@-*/

  /**
   * The constructor parses the incoming string, which is formatted as per RFC2445 as a
   *   propname[;param1=pval1[; ... ]]:propvalue
   * however we allow ourselves to assume that the RFC2445 content unescaping has already
   * happened, which is reasonable as this is done in iCalendar::BuildFromText().
   *
   * @param string $propstring The string from the iCalendar which contains this property.
   */
  function iCalProp( $propstring = null ) {
    $this->name = "";
    $this->content = "";
    $this->parameters = array();
    unset($this->rendered);
    if ( $propstring != null ) $this->ParseFrom($propstring);
  }


  /**
   * The constructor parses the incoming string, which is formatted as per RFC2445 as a
   *   propname[;param1=pval1[; ... ]]:propvalue
   * however we allow ourselves to assume that the RFC2445 content unescaping has already
   * happened, which is reasonable as this is done in iCalendar::BuildFromText().
   *
   * @param string $propstring The string from the iCalendar which contains this property.
   */
  function ParseFrom( $propstring ) {
    $this->rendered = $propstring;
    $pos = strpos( $propstring, ':');
    $start = substr( $propstring, 0, $pos1 - 1);
    $this->content = substr( $propstring, $pos1 + 1);
    $parameters = explode(';',$start);
    $this->name = array_shift( $parameters );
    $this->parameters = array();
    foreach( $parameters AS $k => $v ) {
      $pos = strpos($v,'=');
      $name = substr( $v, 0, $pos1 - 1);
      $value = substr( $v, $pos1 + 1);
      $this->parameters[$name] = $value;
    }
  }


  /**
   * Get/Set name property
   *
   * @param string $newname [optional] A new name for the property
   *
   * @return string The name for the property.
   */
  function Name( $newname = null ) {
    if ( $newname != null ) {
      $this->name = $newname;
      if ( isset($this->rendered) ) unset($this->rendered);
    }
    return $this->name;
  }


  /**
   * Get/Set the content of the property
   *
   * @param string $newvalue [optional] A new value for the property
   *
   * @return string The value of the property.
   */
  function Value( $newvalue = null ) {
    if ( $newvalue != null ) {
      $this->content = $newvalue;
      if ( isset($this->rendered) ) unset($this->rendered);
    }
    return $this->content;
  }

  /**
   * Test if our value contains a string
   *
   * @param string $search The needle which we shall search the haystack for.
   *
   * @return string The name for the property.
   */
  function TextMatch( $search ) {
    return strstr( $this->content, $search );
  }


  /**
   * Get the value of a parameter
   *
   * @param string $name The name of the parameter to retrieve the value for
   *
   * @return string The value of the parameter
   */
  function GetParameterValue( $name ) {
    if ( $this->parameters[$name] ) return $this->parameters[$name];
  }

  /**
   * Set the value of a parameter
   *
   * @param string $name The name of the parameter to set the value for
   *
   * @param string $value The value of the parameter
   */
  function SetParameterValue( $name, $value ) {
    if ( isset($this->rendered) ) unset($this->rendered);
    $this->parameters[$name] = $value;
  }

  /**
  * Render the set of parameters as key1=value1[;key2=value2[; ...]] with
  * any colons or semicolons escaped.
  */
  function RenderParameters() {
    $rendered = "";
    foreach( $this->parameters AS $k => $v ) {
      $escaped = preg_replace( "/([;:\"])/", '\\\\$1', $v);
      $rendered .= sprintf( ";%s=%s", $k, $escaped );
    }
    return $rendered;
  }


  /**
  * Render a suitably escaped RFC2445 content string.
  */
  function Render() {
    // If we still have the string it was parsed in from, it hasn't been screwed with
    // and we can just return that without modification.
    if ( isset($this->rendered) ) return $this->rendered;

    $property = preg_replace( '/[;].*$/', '', $this->name );
    $escaped = $this->value;
    switch( $property ) {
        /** Content escaping does not apply to these properties culled from RFC2445 */
      case 'ATTACH':                case 'GEO':                       case 'PERCENT-COMPLETE':      case 'PRIORITY':
      case 'DURATION':              case 'FREEBUSY':                  case 'TZOFFSETFROM':          case 'TZOFFSETTO':
      case 'TZURL':                 case 'ATTENDEE':                  case 'ORGANIZER':             case 'RECURRENCE-ID':
      case 'URL':                   case 'EXRULE':                    case 'SEQUENCE':              case 'CREATED':
      case 'RRULE':                 case 'REPEAT':                    case 'TRIGGER':
        break;

      case 'COMPLETED':             case 'DTEND':
      case 'DUE':                   case 'DTSTART':
      case 'DTSTAMP':               case 'LAST-MODIFIED':
      case 'CREATED':               case 'EXDATE':
      case 'RDATE':
        if ( isset($this->parameters['VALUE']) && $this->parameters['VALUE'] == 'DATE' ) {
          $escaped = substr( $escaped, 0, 8);
        }
        break;

        /** Content escaping applies by default to other properties */
      default:
        $escaped = str_replace( '\\', '\\\\', $escaped);
        $escaped = preg_replace( '/\r?\n/', '\\n', $escaped);
        $escaped = preg_replace( "/([,;:\"])/", '\\\\$1', $escaped);
    }
    $this->rendered = wordwrap( sprintf( "%s%s:%s", $this->name, $this->RenderParameters(), $escaped), 73, " \r\n ", true ) . "\r\n";
    return $this->rendered;
  }

}


/**
* A Class for representing components within an iCalendar
*
* @package awl
*/
class iCalComponent {
  /**#@+
   * @access private
   */

  /**
   * The type of this component, such as 'VEVENT', 'VTODO', 'VTIMEZONE', etc.
   *
   * @var string
   */
  var $type;

  /**
   * An array of properties, which are iCalProp objects
   *
   * @var array
   */
  var $properties;

  /**
   * An array of (sub-)components, which are iCalComponent objects
   *
   * @var array
   */
  var $components;

  /**
   * The rendered result (or what was originally parsed, if there have been no changes)
   *
   * @var array
   */
  var $rendered;

  /**#@-*/

  function iCalComponent() {
    $this->type = "";
    $this->properties = array();
    $this->components = array();
    $this->rendered = "";
  }


  function ParseFrom( $content ) {
    $this->rendered = $content;
    $content = $this->UnwrapComponent($content);

    $lines = preg_split('/\r?\n/', $content );

    $type = false;
    $subtype = false;
    $finish = null;
    $subfinish = null;
    foreach( $lines AS $k => $v ) {
      if ( $type === false ) {
        if ( preg_match( '^BEGIN:(.+)$', $v, $matches ) ) {
          // We have found the start of the main component
          $type = $matches[1];
          $finish = 'END:$type';
          $this->type = $type;
        }
        else {
          $this->rendered = null;
          unset($lines[$k]);  // The content has crap before the start
        }
      }
      else if ( $type == null ) {
        unset($lines[$k]);  // The content has crap after the end
        $this->rendered = null;
      }
      else if ( $v == $finish ) {
        $type = null;  // We have reached the end of our component
      }
      else {
        if ( $subtype === false && preg_match( '^BEGIN:(.+)$', $v, $matches ) ) {
          // We have found the start of a sub-component
          $subtype = $matches[1];
          $subfinish = 'END:$subtype';
          $subcomponent = "$v\r\n";
        }
        else if ( $subtype ) {
          // We are inside a sub-component
          $subcomponent .= "$v\r\n";
          if ( $v == $subfinish ) {
            // We have found the end of a sub-component
            $subcomponent .= "$v\r\n";
            $this->components[] = new iCalComponent($subcomponent);
            $subtype = false;
          }
        }
        else {
          // It must be a normal property line within a component.
          $property = new iCalProp($v);
          $this->properties[$property->Name()] = clone($property);
        }
      }
    }

    $this->_current_parse_line = 0;
    $this->properties = $this->ParseSomeLines('');
  }


  /**
    * This unescapes the (CRLF + linear space) wrapping specified in RFC2445. According
    * to RFC2445 we should always end with CRLF but the CalDAV spec says that normalising
    * XML parsers often muck with it and may remove the CR.  We accept either case.
    */
  function UnwrapComponent( $content ) {
    return preg_replace('/\r?\n[ \t]/', '', $content );
  }

  /**
    * This imposes the (CRLF + linear space) wrapping specified in RFC2445. According
    * to RFC2445 we should always end with CRLF but the CalDAV spec says that normalising
    * XML parsers often muck with it and may remove the CR.  We output RFC2445 compliance.
    */
  function WrapComponent( $content ) {
    return wordwrap( $content, 73, " \r\n ", true ) . "\r\n";
  }

  function GetType() {
    return $this->type;
  }


  function SetType( $type ) {
    if ( isset($this->rendered) ) unset($this->rendered);
    $this->type = $type;
    return $this->type;
  }


  function GetProperty( $name ) {
    return $this->properties[$name];
  }


  function SetProperty( $name, $value ) {
    if ( isset($this->rendered) ) unset($this->rendered);
    $this->properties[$name] = $value;
    return $this->properties[$name];
  }


  function GetPropertyParameter( $property, $name ) {
    if ( ! isset($this->properties[$property]) ) return null;
    return $this->properties[$property]->GetParameterValue($name);
  }


  function SetPropertyParameter( $property, $name, $value ) {
    if ( isset($this->rendered) ) unset($this->rendered);
    $this->properties[$property]->SetParameterValue($name,$value);
    return $value;
  }


  function GetComponents( $type = null ) {
    $components = clone($this->components);
    if ( $type != null ) {
      foreach( $components AS $k => $v ) {
        if ( $v->GetType() != $type ) {
          unset($components[$k]);
        }
      }
      $components = array_values($components);
    }
    return $components;
  }


  function SetComponents( $new_components ) {
    if ( isset($this->rendered) ) unset($this->rendered);
    $this->components  = clone($new_components);
  }


  function Render() {
    if ( ! isset($this->rendered) ) {
      $this->rendered = "BEGIN:$this->type\r\n";
      foreach( $this->properties AS $v ) {   $this->rendered .= $v->Render();  }
      foreach( $this->components AS $v ) {   $this->rendered .= $v->Render();  }
      $this->rendered .= "END:$this->type\r\n";
    }
    return $this->rendered;
  }

}


/**
* A Class for handling Events on a calendar
*
* @package awl
*/
class iCalendar {
  /**#@+
  * @access private
  */

  /**
  * An array of arbitrary properties, containing arbitrary arrays of arbitrary properties
  * @var properties array
  */
  var $properties;

  /**
  * An array of the lines of this iCalendar resource
  * @var lines array
  */
  var $lines;

  /**
  * The typical location name for the standard timezone such as "Pacific/Auckland"
  * @var tz_locn string
  */
  var $tz_locn;

  /**
  * The type of iCalendar data VEVENT/VTODO/VJOURNAL
  * @var type string
  */
  var $type;

  /**#@-*/

  /**
  * The constructor takes an array of args.  If there is an element called 'icalendar'
  * then that will be parsed into the iCalendar object.  Otherwise the array elements
  * are converted into properties of the iCalendar object directly.
  */
  function iCalendar( $args ) {
    global $c;

    $this->parsing_vtimezone = false;
    $this->tz_locn = "";
    if ( !isset($args) || !(is_array($args) || is_object($args)) ) return;
    if ( is_object($args) ) {
      settype($args,'array');
    }

    if ( isset($args['icalendar']) ) {
      $this->BuildFromText($args['icalendar']);
      $this->DealWithTimeZones();
      return;
    }
    if ( isset($args['type'] ) ) {
      $this->type = $args['type'];
    }
    else {
      $this->type = 'VEVENT';  // Default to event
    }
    $this->properties = array( 'VCALENDAR' => array( array( $this->type => array() )));

    foreach( $args AS $k => $v ) {
      dbg_error_log( "iCalendar", ":Initialise: %s to >>>%s<<<", $k, $v );
      $this->Put($k,$v);
    }

    if ( $this->tz_locn == "" ) {
      $this->tz_locn = $this->Get("tzid");
      if ( (!isset($this->tz_locn) || $this->tz_locn == "") && isset($c->local_tzid) ) {
        $this->tz_locn = $c->local_tzid;
      }
    }
  }

  /**
  * An array of property names that we should always want when rendering an iCalendar
  */
  function DefaultPropertyList() {
    return array( "uid", "dtstamp", "dtstart", "duration", "last-modified","class", "transp", "sequence", "due", "summary" );
  }

  /**
  * A function to extract the contents of a BEGIN:SOMETHING to END:SOMETHING (perhaps multiply)
  * and return just that bit (or, of course, those bits :-)
  *
  * @var string The type of thing(s) we want returned.
  * @var integer The number of SOMETHINGS we want to get.
  *
  * @return string A string from BEGIN:SOMETHING to END:SOMETHING, possibly multiple of these
  */
  function JustThisBitPlease( $type, $count=1 ) {
    $answer = "";
    $intags = false;
    $start = "BEGIN:$type";
    $finish = "END:$type";
    dbg_error_log( "iCalendar", ":JTBP: Looking for %d subsets of type %s", $count, $type );
    reset($this->lines);
    foreach( $this->lines AS $k => $v ) {
      if ( !$intags && $v == $start ) {
        $answer .= $v . "\n";
        $intags = true;
      }
      else if ( $intags && $v == $finish ) {
        $answer .= $v . "\n";
        $intags = false;
      }
      else if ( $intags ) {
        $answer .= $v . "\n";
      }
    }
    return $answer;
  }


  /**
  * Function to parse lines from BEGIN:SOMETHING to END:SOMETHING into a nested array structure
  *
  * @var string The "SOMETHING" from the BEGIN:SOMETHING line we just met
  * @return arrayref An array of the things we found between (excluding) the BEGIN & END, some of which might be sub-arrays
  */
  function &ParseSomeLines( $type ) {
    $props = array();
    $properties =& $props;
    while( isset($this->lines[$this->_current_parse_line]) ) {
      $i = $this->_current_parse_line++;
      $line =& $this->lines[$i];
      dbg_error_log( "iCalendar", ":Parse:%s LINE %03d: >>>%s<<<", $type, $i, $line );
      if ( $this->parsing_vtimezone ) {
        $this->vtimezone .= $line."\n";
      }
      if ( preg_match( '/^(BEGIN|END):([^:]+)$/', $line, $matches ) ) {
        if ( $matches[1] == 'END' && $matches[2] == $type ) {
          if ( $type == 'VTIMEZONE' ) {
            $this->parsing_vtimezone = false;
          }
          return $properties;
        }
        else if( $matches[1] == 'END' ) {
          dbg_error_log("ERROR"," iCalendar: parse error: Unexpected END:%s when we were looking for END:%s", $matches[2], $type );
          return $properties;
        }
        else if( $matches[1] == 'BEGIN' ) {
          $subtype = $matches[2];
          if ( $subtype == 'VTIMEZONE' ) {
            $this->parsing_vtimezone = true;
            $this->vtimezone = $line."\n";
          }
          if ( !isset($properties['INSIDE']) ) $properties['INSIDE'] = array();
          $properties['INSIDE'][] = $subtype;
          if ( !isset($properties[$subtype]) ) $properties[$subtype] = array();
          $properties[$subtype][] = $this->ParseSomeLines($subtype);
        }
      }
      else {
        // Parse the property
        @list( $property, $value ) = preg_split('/:/', $line, 2 );
        if ( strpos( $property, ';' ) > 0 ) {
          $parameterlist = preg_split('/;/', $property );
          $property = array_shift($parameterlist);
          foreach( $parameterlist AS $pk => $pv ) {
            if ( $pv == "VALUE=DATE" ) {
              $value .= 'T000000';
            }
            elseif ( preg_match('/^([^;:=]+)=([^;:=]+)$/', $pv, $matches) ) {
              switch( $matches[1] ) {
                case 'TZID': $properties['TZID'] = $matches[2];  break;
                default:
                  dbg_error_log( "icalendar", " FYI: Ignoring Resource '%s', Property '%s', Parameter '%s', Value '%s'", $type, $property, $matches[1], $matches[2] );
              }
            }
          }
        }
        if ( $this->parsing_vtimezone && (!isset($this->tz_locn) || $this->tz_locn == "") && $property == 'X-LIC-LOCATION' ) {
          $this->tz_locn = $value;
        }
        $properties[strtoupper($property)] = $this->RFC2445ContentUnescape($value);
      }
    }
    return $properties;
  }


  /**
  * Build the iCalendar object from a text string which is a single iCalendar resource
  *
  * @var string The RFC2445 iCalendar resource to be parsed
  */
  function BuildFromText( $icalendar ) {
    /**
     * This unescapes the (CRLF + linear space) wrapping specified in RFC2445. According
     * to RFC2445 we should always end with CRLF but the CalDAV spec says that normalising
     * XML parsers often muck with it and may remove the CR.
     */
    $icalendar = preg_replace('/\r?\n[ \t]/', '', $icalendar );

    $this->lines = preg_split('/\r?\n/', $icalendar );

    $this->_current_parse_line = 0;
    $this->properties = $this->ParseSomeLines('');

    /**
    * Our 'type' is the type of non-timezone inside a VCALENDAR
    */
    if ( isset($this->properties['VCALENDAR'][0]['INSIDE']) ) {
      foreach ( $this->properties['VCALENDAR'][0]['INSIDE']  AS $k => $v ) {
        if ( $v == 'VTIMEZONE' ) continue;
        $this->type = $v;
        break;
      }
    }

  }


  /**
  * Returns a content string with the RFC2445 escaping removed
  *
  * @param string $escaped The incoming string to be escaped.
  * @return string The string with RFC2445 content escaping removed.
  */
  function RFC2445ContentUnescape( $escaped ) {
    $unescaped = str_replace( '\\n', "\n", $escaped);
    $unescaped = str_replace( '\\N', "\n", $unescaped);
    $unescaped = preg_replace( "/\\\\([,;:\"\\\\])/", '$1', $unescaped);
    return $unescaped;
  }



  /**
  * Do what must be done with time zones from on file.  Attempt to turn
  * them into something that PostgreSQL can understand...
  */
  function DealWithTimeZones() {
    global $c;

    $tzid = $this->Get('TZID');
    if ( isset($c->save_time_zone_defs) && $c->save_time_zone_defs ) {
      $qry = new PgQuery( "SELECT tz_locn FROM time_zone WHERE tz_id = ?;", $tzid );
      if ( $qry->Exec('iCalendar') && $qry->rows == 1 ) {
        $row = $qry->Fetch();
        $this->tz_locn = $row->tz_locn;
      }
      dbg_error_log( "icalendar", " TZCrap: TZID '%s', DB Rows=%d, Location '%s'", $tzid, $qry->rows, $this->tz_locn );
    }

    if ( (!isset($this->tz_locn) || $this->tz_locn == '') && $tzid != '' ) {
      /**
      * In case there was no X-LIC-LOCATION defined, let's hope there is something in the TZID
      * that we can use.  We are looking for a string like "Pacific/Auckland" if possible.
      */
      $tzname = preg_replace('#^(.*[^a-z])?([a-z]+/[a-z]+)$#i','$1',$tzid );
      /**
      * Unfortunately this kind of thing will never work well :-(
      *
      if ( strstr( $tzname, ' ' ) ) {
        $words = preg_split('/\s/', $tzname );
        $tzabbr = '';
        foreach( $words AS $i => $word ) {
          $tzabbr .= substr( $word, 0, 1);
        }
        $this->tz_locn = $tzabbr;
      }
      */
      if ( preg_match( '#\S+/\S+#', $tzname) ) {
        $this->tz_locn = $tzname;
      }
      dbg_error_log( "icalendar", " TZCrap: TZID '%s', Location '%s', Perhaps: %s", $tzid, $this->tz_locn, $tzname );
    }

    if ( $tzid != '' && isset($c->save_time_zone_defs) && $c->save_time_zone_defs && $qry->rows != 1 && isset($this->vtimezone) && $this->vtimezone != "" ) {
      $qry2 = new PgQuery( "INSERT INTO time_zone (tz_id, tz_locn, tz_spec) VALUES( ?, ?, ? );",
                                   $tzid, $this->tz_locn, $this->vtimezone );
      $qry2->Exec("iCalendar");
    }

    if ( (!isset($this->tz_locn) || $this->tz_locn == "") && isset($c->local_tzid) ) {
      $this->tz_locn = $c->local_tzid;
    }
  }


  /**
  * Get the value of a property
  */
  function Get( $key ) {
    if ( isset($this->properties['VCALENDAR'][0][$this->type][0][strtoupper($key)]) ) return $this->properties['VCALENDAR'][0][$this->type][0][strtoupper($key)];
  }


  /**
  * Put the value of a property
  */
  function Put( $key, $value ) {
    if ( $value == "" ) return;
    return $this->properties['VCALENDAR'][0][$this->type][0][strtoupper($key)] = $value;
  }


  /**
  * Returns a PostgreSQL Date Format string suitable for returning HTTP (RFC2068) dates
  * Preferred is "Sun, 06 Nov 1994 08:49:37 GMT" so we do that.
  */
  function HttpDateFormat() {
    return "'Dy, DD Mon IYYY HH24:MI:SS \"GMT\"'";
  }


  /**
  * Returns a PostgreSQL Date Format string suitable for returning iCal dates
  */
  function SqlDateFormat() {
    return "'YYYYMMDD\"T\"HH24MISS'";
  }


  /**
  * Returns a PostgreSQL Date Format string suitable for returning dates which
  * have been cast to UTC
  */
  function SqlUTCFormat() {
    return "'YYYYMMDD\"T\"HH24MISS\"Z\"'";
  }


  /**
  * Returns a PostgreSQL Date Format string suitable for returning iCal durations
  *  - this doesn't work for negative intervals, but events should not have such!
  */
  function SqlDurationFormat() {
    return "'\"PT\"HH24\"H\"MI\"M\"'";
  }

  /**
  * Returns a suitably escaped RFC2445 content string.
  *
  * @param string $name The incoming name[;param] prefixing the string.
  * @param string $value The incoming string to be escaped.
  */
  function RFC2445ContentEscape( $name, $value ) {
    $property = preg_replace( '/[;].*$/', '', $name );
    switch( $property ) {
        /** Content escaping does not apply to these properties culled from RFC2445 */
      case 'ATTACH':                case 'GEO':                       case 'PERCENT-COMPLETE':      case 'PRIORITY':
      case 'COMPLETED':             case 'DTEND':                     case 'DUE':                   case 'DTSTART':
      case 'DURATION':              case 'FREEBUSY':                  case 'TZOFFSETFROM':          case 'TZOFFSETTO':
      case 'TZURL':                 case 'ATTENDEE':                  case 'ORGANIZER':             case 'RECURRENCE-ID':
      case 'URL':                   case 'EXDATE':                    case 'EXRULE':                case 'RDATE':
      case 'RRULE':                 case 'REPEAT':                    case 'TRIGGER':               case 'CREATED':
      case 'DTSTAMP':               case 'LAST-MODIFIED':             case 'SEQUENCE':
        break;

        /** Content escaping applies by default to other properties */
      default:
        $value = str_replace( '\\', '\\\\', $value);
        $value = preg_replace( '/\r?\n/', '\\n', $value);
        $value = preg_replace( "/([,;:\"])/", '\\\\$1', $value);
    }
    $result = wordwrap("$name:$value", 73, " \r\n ", true ) . "\r\n";
    return $result;
  }

  /**
   * Return all sub-components of the given type, which are part of the
   * component we pass in as an array of lines.
   *
   * @param array $component The component to be parsed
   * @param string $type The type of sub-components to be extracted
   * @param int $count The number of sub-components to extract (default: 9999)
   *
   * @return array The sub-component lines
   */
  function ExtractSubComponent( $component, $type, $count=9999 ) {
    $answer = array();
    $intags = false;
    $start = "BEGIN:$type";
    $finish = "END:$type";
    dbg_error_log( "iCalendar", ":ExtractSubComponent: Looking for %d subsets of type %s", $count, $type );
    reset($component);
    foreach( $component AS $k => $v ) {
      if ( !$intags && $v == $start ) {
        $answer[] = $v;
        $intags = true;
      }
      else if ( $intags && $v == $finish ) {
        $answer[] = $v;
        $intags = false;
      }
      else if ( $intags ) {
        $answer[] = $v;
      }
    }
    return $answer;
  }


  /**
   * Extract a particular property from the provided component.  In doing so we
   * make the assumption that the content has previously been unescaped (which is
   * done in the BuildFromText() method).
   *
   * @param array $component An array of lines of this component
   * @param string $type The type of parameter
   *
   * @return array An array of iCalProperty objects
   */
  function ExtractProperty( $component, $type, $count=9999 ) {
    $answer = array();
    dbg_error_log( "iCalendar", ":ExtractProperty: Looking for %d properties of type %s", $count, $type );
    reset($component);
    foreach( $component AS $k => $v ) {
      if ( preg_match( "/$type"."[;:]/i", $v ) ) {
        $answer[] = new iCalProp($v);
        dbg_error_log( "iCalendar", ":ExtractProperty: Found property %s", $type );
        if ( --$count < 1 ) return $answer;
      }
    }
    return $answer;
  }


  /**
   * Applies the filter conditions, possibly recursively, to the value which will be either
   * a single property, or an array of lines of the component under test.
   *
   * @TODO Eventually we need to handle all of these possibilities, which will mean writing
   * several routines:
   *  - Get Property from Component
   *  - Get Parameter from Property
   *  - Test TimeRange
   * For the moment we will leave these, until there is a perceived need.
   *
   * @param array $filter An array of XMLElement defining the filter(s)
   * @param mixed $value Either a string which is the single property, or an array of lines, for the component.
   * @return boolean Whether the filter passed / failed.
   */
  function ApplyFilter( $filter, $value ) {
    foreach( $filter AS $k => $v ) {
      $tag = $v->GetTag();
      $value_type = gettype($value);
      $value_defined = (isset($value) && $value_type == 'string') || ($value_type == 'array' && count($value) > 0 );
      if ( $tag == 'URN:IETF:PARAMS:XML:NS:CALDAV:IS-NOT-DEFINED' && $value_defined ) {
        dbg_error_log( "iCalendar", ":ApplyFilter: Value is set ('%s'), want unset, for filter %s", count($value), $tag );
        return false;
      }
      elseif ( $tag == 'URN:IETF:PARAMS:XML:NS:CALDAV:IS-DEFINED' && !$value_defined ) {
        dbg_error_log( "iCalendar", ":ApplyFilter: Want value, but it is not set for filter %s", $tag );
        return false;
      }
      else {
        switch( $tag ) {
          case 'URN:IETF:PARAMS:XML:NS:CALDAV:TIME-RANGE':
            /** TODO: While this is unimplemented here at present, most time-range tests should occur at the SQL level. */
            break;
          case 'URN:IETF:PARAMS:XML:NS:CALDAV:TEXT-MATCH':
            $search = $v->GetContent();
            // In this case $value will either be a string, or an array of iCalProp objects
            // since TEXT-MATCH does not apply to COMPONENT level - only property/parameter
            if ( gettype($value) != 'string' ) {
              if ( gettype($value) == 'array' ) {
                $match = false;
                foreach( $value AS $k1 => $v1 ) {
                  // $v1 could be an iCalProp object
                  if ( $match = $v1->TextMatch($search)) break;
                }
              }
              else {
                dbg_error_log( "iCalendar", ":ApplyFilter: TEXT-MATCH will only work on strings or arrays of iCalProp.  %s unsupported", gettype($value) );
                return true;  // We return _true_ in this case, so the client sees the item
              }
            }
            else {
              $match = strstr( $value, $search[0] );
            }
            $negate = $v->GetAttribute("NEGATE-CONDITION");
            if ( isset($negate) && strtolower($negate) == "yes" && $match ) {
              dbg_error_log( "iCalendar", ":ApplyFilter: TEXT-MATCH of %s'%s' against '%s'", (isset($negate) && strtolower($negate) == "yes"?'!':''), $search, $value );
              return false;
            }
            break;
          case 'URN:IETF:PARAMS:XML:NS:CALDAV:COMP-FILTER':
            $subfilter = $v->GetContent();
            $component = $this->ExtractSubComponent($value,$v->GetAttribute("NAME"));
            if ( ! $this->ApplyFilter($subfilter,$component) ) return false;
            break;
          case 'URN:IETF:PARAMS:XML:NS:CALDAV:PROP-FILTER':
            $subfilter = $v->GetContent();
            $properties = $this->ExtractProperty($value,$v->GetAttribute("NAME"));
            if ( ! $this->ApplyFilter($subfilter,$properties) ) return false;
            break;
          case 'URN:IETF:PARAMS:XML:NS:CALDAV:PARAM-FILTER':
            $subfilter = $v->GetContent();
            $parameter = $this->ExtractParameter($value,$v->GetAttribute("NAME"));
            if ( ! $this->ApplyFilter($subfilter,$parameter) ) return false;
            break;
        }
      }
    }
    return true;
  }

  /**
   * Test a PROP-FILTER or COMP-FILTER and return a true/false
   * COMP-FILTER (is-defined | is-not-defined | (time-range?, prop-filter*, comp-filter*))
   * PROP-FILTER (is-defined | is-not-defined | ((time-range | text-match)?, param-filter*))
   *
   * @param array $filter An array of XMLElement defining the filter
   *
   * @return boolean Whether or not this iCalendar passes the test
   */
  function TestFilter( $filters ) {

    foreach( $filters AS $k => $v ) {
      $tag = $v->GetTag();
      $name = $v->GetAttribute("NAME");
      $filter = $v->GetContent();
      if ( $tag == "URN:IETF:PARAMS:XML:NS:CALDAV:PROP-FILTER" ) {
        $value = $this->ExtractProperty($this->lines,$name);
      }
      else {
        $value = $this->ExtractSubComponent($this->lines,$v->GetAttribute("NAME"));
      }
      if ( count($value) == 0 ) unset($value);
      if ( ! $this->ApplyFilter($filter,$value) ) return false;
    }
    return true;
  }

  /**
  * Returns the header we always use at the start of our iCalendar resources
  */
  function iCalHeader() {
    return <<<EOTXT
BEGIN:VCALENDAR\r
PRODID:-//Catalyst.Net.NZ//NONSGML AWL Calendar//EN\r
VERSION:2.0\r

EOTXT;
  }



  /**
  * Returns the footer we always use at the finish of our iCalendar resources
  */
  function iCalFooter() {
    return "END:VCALENDAR\r\n";
  }


  /**
  * Render the iCalendar object as a text string which is a single VEVENT (or other)
  *
  * @param boolean $as_calendar Whether or not to wrap the event in a VCALENDAR
  * @param string $type The type of iCalendar object (VEVENT, VTODO, VFREEBUSY etc.)
  * @param array $properties The names of the properties we want in our rendered result.
  */
  function Render( $as_calendar = true, $type = 'VEVENT', $properties = false ) {
    /**
    * FIXME This should really render the full nested structure of the event.
    */
    if ( !is_array($properties) ) {
      $properties = array( "uid", "dtstamp", "dtstart", "duration", "summary", "uri", "last-modified",
                          "location", "description", "class", "transp", "sequence", "due" );
    }

    $wrap_at = 75;
    $result = ($as_calendar ? $this->iCalHeader() : "");
    $result .= "BEGIN:$type\r\n";

    foreach( $properties AS $k => $v ) {
      $v = strtoupper($v);
      $value = $this->Get($v);
      if ( isset($value) && $value != "" ) {
        dbg_error_log( "iCalendar", "Rendering '%s' which is '%s'", $v, $value );
        $result .= $this->RFC2445ContentEscape($v,$value);
      }
    }

    /**
    * FIXME I think that DTEND/DURATION don't apply to VTODO, and DUE applies instead
    */
    // DTEND and DURATION may not exist together
    $dtend = $this->Get('DTEND');
    $duration = $this->Get('DURATION');
    if ( isset($dtend) && $dtend != "" && ( !isset($duration) || $duration == "") ) {
      dbg_error_log( "iCalendar", "Rendering '%s' which is '%s'", 'DTEND', $dtend );
      $result .= $this->RFC2445ContentEscape('DTEND',$dtend);
    }

    if ( isset($this->vtimezone) && $this->vtimezone != "" ) {
      $result .= $this->vtimezone;
    }

    $result .= "END:$type\r\n";
    $result .= ($as_calendar ? $this->iCalFooter() : "" );

    return $result;
  }

}

?>