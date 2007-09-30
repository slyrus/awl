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
    if ( $propstring != null && gettype($propstring) == 'string' ) {
      $this->ParseFrom($propstring);
    }
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
    $start = substr( $propstring, 0, $pos);

    $unescaped = str_replace( '\\n', "\n", substr( $propstring, $pos + 1));
    $unescaped = str_replace( '\\N', "\n", $unescaped);
    $this->content = preg_replace( "/\\\\([,;:\"\\\\])/", '$1', $unescaped);

    $parameters = explode(';',$start);
    $this->name = array_shift( $parameters );
    $this->parameters = array();
    foreach( $parameters AS $k => $v ) {
      $pos = strpos($v,'=');
      $name = substr( $v, 0, $pos);
      $value = substr( $v, $pos + 1);
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
    $escaped = $this->content;
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
    $this->rendered = wordwrap( sprintf( "%s%s:%s", $this->name, $this->RenderParameters(), $escaped), 73, " \r\n ", true );
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

  /**
  * A basic constructor
  */
  function iCalComponent( $content = null ) {
    $this->type = "";
    $this->properties = array();
    $this->components = array();
    $this->rendered = "";
    if ( $content != null && gettype($content) == 'string' ) {
      $this->ParseFrom($content);
    }
  }

  /**
  * Parse the text $content into sets of iCalProp & iCalComponent within this iCalComponent
  * @param string $content The raw RFC2445-compliant iCalendar component, including BEGIN:TYPE & END:TYPE
  */
  function ParseFrom( $content ) {
    $this->rendered = $content;
    $content = $this->UnwrapComponent($content);

    $lines = preg_split('/\r?\n/', $content );

    $type = false;
    $subtype = false;
    $finish = null;
    $subfinish = null;
    foreach( $lines AS $k => $v ) {
      if ( preg_match('/^\s*$/', $v ) ) continue;
      dbg_error_log( "iCalendar",  "::ParseFrom: Parsing line: $v");
      if ( $type === false ) {
        if ( preg_match( '/^BEGIN:(.+)$/', $v, $matches ) ) {
          // We have found the start of the main component
          $type = $matches[1];
          $finish = "END:$type";
          $this->type = $type;
          dbg_error_log( "iCalendar", "::ParseFrom: Start component of type '%s'", $type);
        }
        else {
          dbg_error_log( "iCalendar", "::ParseFrom: Ignoring crap before start of component");
          unset($lines[$k]);  // The content has crap before the start
          if ( $v != "" ) $this->rendered = null;
        }
      }
      else if ( $type == null ) {
        dbg_error_log( "iCalendar", "::ParseFrom: Ignoring crap after end of component");
        unset($lines[$k]);  // The content has crap after the end
        if ( $v != "" ) $this->rendered = null;
      }
      else if ( $v == $finish ) {
        dbg_error_log( "iCalendar", "::ParseFrom: End of component");
        $type = null;  // We have reached the end of our component
      }
      else {
        if ( $subtype === false && preg_match( '/^BEGIN:(.+)$/', $v, $matches ) ) {
          // We have found the start of a sub-component
          $subtype = $matches[1];
          $subfinish = "END:$subtype";
          $subcomponent = "$v\r\n";
          dbg_error_log( "iCalendar", "::ParseFrom: Found a subcomponent '%s'", $subtype);
        }
        else if ( $subtype ) {
          // We are inside a sub-component
          $subcomponent .= $this->WrapComponent($v);
          if ( $v == $subfinish ) {
            dbg_error_log( "iCalendar", "::ParseFrom: End of subcomponent '%s'", $subtype);
            // We have found the end of a sub-component
            $this->components[] = new iCalComponent($subcomponent);
            $subtype = false;
          }
          else
            dbg_error_log( "iCalendar", "::ParseFrom: Inside a subcomponent '%s'", $subtype );
        }
        else {
          dbg_error_log( "iCalendar", "::ParseFrom: Parse property of component");
          // It must be a normal property line within a component.
          $this->properties[] = new iCalProp($v);
        }
      }
    }
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
    return wordwrap( $content, 73, " \r\n ", false ) . "\r\n";
  }

  /**
  * Return the type of component which this is
  */
  function GetType() {
    return $this->type;
  }


  /**
  * Set the type of component which this is
  */
  function SetType( $type ) {
    if ( isset($this->rendered) ) unset($this->rendered);
    $this->type = $type;
    return $this->type;
  }


  /**
  * Get all properties, or the properties matching a particular type
  */
  function GetProperties( $type = null ) {
    $properties = $this->properties;
    if ( $type != null ) {
      foreach( $properties AS $k => $v ) {
        if ( $v->Name() != $type ) {
          unset($properties[$k]);
        }
      }
      $properties = array_values($properties);
    }
    return $properties;
  }


  /**
  * Clear all properties, or the properties matching a particular type
  * @param string $type The type of property - omit for all properties
  */
  function ClearProperties( $type = null ) {
    if ( $type != null ) {
      // First remove all the existing ones of that type
      foreach( $this->properties AS $k => $v ) {
        if ( $v->Name() == $type ) {
          unset($this->properties[$k]);
          if ( isset($this->rendered) ) unset($this->rendered);
        }
      }
      $this->properties = array_values($this->properties);
    }
    else {
      if ( isset($this->rendered) ) unset($this->rendered);
      $this->properties = array();
    }
  }


  /**
  * Set all properties, or the ones matching a particular type
  */
  function SetProperties( $new_properties, $type = null ) {
    if ( isset($this->rendered) && count($new_properties) > 0 ) unset($this->rendered);
    $this->ClearProperties($type);
    foreach( $new_properties AS $k => $v ) {
      $this->AddProperty($v);
    }
  }


  /**
  * Adds a new property
  *
  * @param iCalProp $new_property The new property to append to the set
  */
  function AddProperty( $new_property ) {
    if ( isset($this->rendered) ) unset($this->rendered);
    $this->properties[] = $new_property;
  }


  /**
  * Get all sub-components, or at least get those matching a type
  * @return array an array of the sub-components
  */
  function &FirstNonTimezone( $type = null ) {
    foreach( $this->components AS $k => $v ) {
      if ( $v->GetType() != 'VTIMEZONE' ) return $v;
    }
    return false;
  }


  /**
  * Get all sub-components, or at least get those matching a type
  * @return array an array of the sub-components
  */
  function GetComponents( $type = null ) {
    $components = $this->components;
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

  /**
  * Clear all components, or the components matching a particular type
  * @param string $type The type of component - omit for all components
  */
  function ClearComponents( $type = null ) {
    if ( $type != null ) {
      // First remove all the existing ones of that type
      foreach( $this->components AS $k => $v ) {
        if ( $v->GetType() == $type ) {
          unset($this->components[$k]);
          if ( isset($this->rendered) ) unset($this->rendered);
        }
      }
    }
    else {
      if ( isset($this->rendered) ) unset($this->rendered);
      $this->components = array();
    }
  }


  /**
  * Sets some or all sub-components of the component to the supplied new components
  *
  * @param array of iCalComponent $new_components The new components to replace the existing ones
  * @param string $type The type of components to be replaced.  Defaults to null, which means all components will be replaced.
  */
  function SetComponents( $new_components, $type = null ) {
    if ( isset($this->rendered) ) unset($this->rendered);
    if ( count($new_components) > 0 ) $this->ClearComponents($type);
    $this->components = $this->components + $new_components;
  }


  /**
  * Adds a new subcomponent
  *
  * @param iCalComponent $new_component The new component to append to the set
  */
  function AddComponent( $new_component ) {
    if ( isset($this->rendered) ) unset($this->rendered);
    $this->components[] = $new_component;
  }


  /**
  *
  */
  function Render() {
    if ( ! isset($this->rendered) ) {
      $this->rendered = "BEGIN:$this->type\r\n";
      foreach( $this->properties AS $v ) {   $this->rendered .= $v->Render() . "\r\n";  }
      foreach( $this->components AS $v ) {   $this->rendered .= $v->Render();  }
      $this->rendered .= "END:$this->type";
      $this->rendered = $this->WrapComponent($this->rendered);
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
  * The component-ised version of the iCalendar
  * @var component iCalComponent
  */
  var $component;

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

    $this->tz_locn = "";
    if ( !isset($args) || !(is_array($args) || is_object($args)) ) return;
    if ( is_object($args) ) {
      settype($args,'array');
    }

    $this->component = new iCalComponent();
    if ( isset($args['icalendar']) ) {
      $this->component->ParseFrom($args['icalendar']);
      $this->lines = preg_split('/\r?\n/', $args['icalendar'] );
      $this->SaveTimeZones();
      $first = $this->component->FirstNonTimezone();
      $this->type = $first->GetType();
      $this->properties = $first->GetProperties();
      $this->properties['VCALENDAR'] = array('***ERROR*** This class is being referenced in an unsupported way!');
      return;
    }
    if ( isset($args['type'] ) ) {
      $this->type = $args['type'];
    }
    else {
      $this->type = 'VEVENT';  // Default to event
    }
    $this->component->SetType('VCALENDAR');
    $this->component->SetProperties(
        array(
          new iCalProp('PRODID:-//Catalyst.Net.NZ//NONSGML AWL Calendar//EN'),
          new iCalProp('VERSION:2.0')
        )
    );
    $first = new iCalComponent();
    $first->SetType($this->type);
    $this->properties = array();

    foreach( $args AS $k => $v ) {
      dbg_error_log( "iCalendar", ":Initialise: %s to >>>%s<<<", $k, $v );
      $property = new iCalProp();
      $property->Name($k);
      $property->Value($v);
      $this->properties[] = $property;
    }
    $first->SetProperties($this->properties);
    $this->component->SetComponents = array( $first );

    $this->properties['VCALENDAR'] = array('***ERROR*** This class is being referenced in an unsupported way!');

    /**
    * TODO: Need to handle timezones!!!
    */
    if ( $this->tz_locn == "" ) {
      $this->tz_locn = $this->Get("tzid");
      if ( (!isset($this->tz_locn) || $this->tz_locn == "") && isset($c->local_tzid) ) {
        $this->tz_locn = $c->local_tzid;
      }
    }
  }


  /**
  * Save any timezones by TZID in the PostgreSQL database for future re-use.
  */
  function SaveTimeZones() {
    global $c;

    $timezones = $this->component->GetComponents('VTIMEZONE');
    if ( $timezones === false || count($timezones) == 0 ) return;
    $this->vtimezone = $timezones[0]->Render();  // Backward compatibility

    $tzid = $this->Get('TZID');
    if ( isset($c->save_time_zone_defs) && $c->save_time_zone_defs ) {
      foreach( $timezones AS $k => $tz ) {
        $tzids = $tz->GetProperties('TZID');
        $tznames = $tz->GetProperties('X-LIC-LOCATION');
        if ( count($tzids) != 1 || count($tznames) > 1 ) {
          dbg_error_log( "icalendar", "::SaveTimeZones: Timezone contains %d TZID properties!  Skipped.", count($tzids) );
          continue;
        }
        $tzid = $tzids[0]->Value();
        $qry = new PgQuery( "SELECT tz_locn FROM time_zone WHERE tz_id = ?;", $tzid );
        if ( $qry->Exec('iCalendar') && $qry->rows == 1 ) {
          $row = $qry->Fetch();
          if ( !isset($first_tzid) ) $first_tzid = $row->tz_locn;
          continue;
        }

        if ( $tzid != "" && $qry->rows == 0 ) {

          if ( count($tznames) > 0 ) {
            $tzname = $tznames[0]->Value();
          }
          else {
            /**
            * Try and convert the TZID to a string like "Pacific/Auckland" if possible.
            */
            $tzname = preg_replace('#^(.*[^a-z])?([a-z]+/[a-z]+)$#i','$2',$tzid );
          }

          $qry2 = new PgQuery( "INSERT INTO time_zone (tz_id, tz_locn, tz_spec) VALUES( ?, ?, ? );",
                                      $tzid, $tzname, $tz->Render() );
          $qry2->Exec("iCalendar");
        }
      }
    }
    if ( ! isset($this->tzid) && isset($first_tzid) ) $this->tzid = $first_tzid;

    if ( (!isset($this->tz_locn) || $this->tz_locn == '') && isset($first_tzid) && $first_tzid != '' ) {
      $tzname = preg_replace('#^(.*[^a-z])?([a-z]+/[a-z]+)$#i','$2', $first_tzid );
      if ( preg_match( '#\S+/\S+#', $tzname) ) {
        $this->tz_locn = $tzname;
      }
      dbg_error_log( "icalendar", " TZCrap: TZID '%s', Location '%s', Perhaps: %s", $tzid, $this->tz_locn, $tzname );
    }

    if ( (!isset($this->tz_locn) || $this->tz_locn == "") && isset($c->local_tzid) ) {
      $this->tz_locn = $c->local_tzid;
    }
    if ( ! isset($this->tzid) && isset($this->tz_locn) ) $this->tzid = $this->tz_locn;
  }


  /**
  * @deprecated
  * An array of property names that we should always want when rendering an iCalendar
  */
  function DefaultPropertyList() {
    return array( "UID", "DTSTAMP", "DTSTART", "DURATION", "LAST-MODIFIED","CLASS", "TRANSP", "SEQUENCE", "DUE", "SUMMARY", "RRULE" );
  }

  /**
  * @deprecated
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
  * @deprecated
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
  * @deprecated
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
  * @deprecated
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
  * @deprecated
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
  * Get the value of a property in the first non-VTIMEZONE
  */
  function Get( $key ) {
    if ( strtoupper($key) == 'TZID' ) {
      // backward compatibility hack
      dbg_error_log( "icalendar", " TZCrap: TZID '%s', Location '%s', Perhaps: %s", $tzid, $this->tz_locn, $tzname );
      if ( isset($this->tzid) ) return $this->tzid;
      return $this->tz_locn;
    }
    /**
    * The property we work on is the first non-VTIMEZONE we find.
    */
    $component = $this->component->FirstNonTimezone();
    $properties = $component->GetProperties(strtoupper($key));
    if ( count($properties) == 1 ) {
      return $properties[0]->Value();
    }
    else if ( count($properties) == 0 ) {
      return null;
    }
    return $properties;
  }


  /**
  * Put the value of a property
  */
  function Put( $key, $value ) {
    if ( $value == "" ) return;
    $key = strtoupper($key);
    $property = new iCalProp();
    $property->Name($key);
    $property->Value($value);
    if (isset($this->component->rendered) ) unset( $this->component->rendered );
    $component = $this->component->FirstNonTimezone();
    $component->SetProperties( array($property), $key);
    return $this->Get($key);
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
  * @deprecated
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
  * @deprecated
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
  * @deprecated
  * Returns the footer we always use at the finish of our iCalendar resources
  */
  function iCalFooter() {
    return "END:VCALENDAR\r\n";
  }


  /**
  * Render the iCalendar object as a text string which is a single VEVENT (or other)
  *
  * @param boolean $as_calendar Whether or not to wrap the event in a VCALENDAR
  * @param string $type The type of iCalendar object (VEVENT, VTODO, VFREEBUSY etc.)     @deprecated
  * @param array $properties The names of the properties we want in our rendered result. @deprecated
  */
  function Render( $as_calendar = true, $type = null, $properties = false ) {
    if ( $as_calendar ) {
      return $this->component->Render();
    }
    else {
      $components = $this->component->GetComponents($type);
      $rendered = "";
      foreach( $components AS $k => $v ) {
        $rendered .= $v->Render();
      }
      return $rendered;
    }
  }

}

?>