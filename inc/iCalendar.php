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
        $properties[strtoupper($property)] = $value;
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
    // According to RFC2445 we should always end with CRLF, but the CalDAV spec says
    // that normalising XML parses often muck with it and may remove the CR.
    $icalendar = preg_replace('/\r?\n /', '', $icalendar );

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
      $tzname = preg_replace('#^[^a-z]*([a-z]+/[a-z]+)$#i','$1',$tzid );
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

    if ( $tzid != '' && isset($c->save_time_zone_defs) && $c->save_time_zone_defs && $qry->rows != 1 ) {
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
  * @param string The incoming name[;param] prefixing the string.
  * @param string The incoming string to be escaped.
  */
  function RFC2445ContentEscape( $name, $value ) {
    $value = str_replace( '\\', '\\\\', $value);
    $value = str_replace( "\n", '\\n', $value);
    $value = str_replace( "\r", '\\r', $value);
    $value = preg_replace( "/([,;:\"\'])/", '\\\\$1', $value);
    $result = wordwrap("$name:$value", 75, " \r\n ", true ) . "\r\n";
    return $result;
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