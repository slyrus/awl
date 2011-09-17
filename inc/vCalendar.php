<?php
/**
* A Class for handling vCalendar data.
*
* When parsed the underlying structure is roughly as follows:
*
*   vCalendar( array(vComponent), array(vProperty), array(vTimezone) )
*
* with the TIMEZONE data still currently included in the component array (likely
* to change in the future) and the timezone array only containing vComponent objects
* (which is also likely to change).
*
* @package awl
* @subpackage vCalendar
* @author Andrew McMillan <andrew@mcmillan.net.nz>
* @copyright Morphoss Ltd <http://www.morphoss.com/>
* @license   http://gnu.org/copyleft/lgpl.html GNU LGPL v3 or later
*
*/

require_once('vComponent.php');

class vCalendar extends vComponent {

  /**
   * These variables are mostly used to improve efficiency by caching values as they are
   * retrieved to speed any subsequent access.
   * @var string $contained_type
   * @var array $timezones
   * @var string $organizer
   * @var array $attendees
   */
  private $contained_type;
  private $timezones;
  private $organizer;
  private $attendees;
  
  /**
   * Constructor.  If a string is passed it will be parsed as if it was an iCalendar object,
   * otherwise a new vCalendar will be initialised with basic content. If an array of key value
   * pairs is provided they will also be used as top-level properties.
   * 
   * Typically this will be used to set a METHOD property on the VCALENDAR as something like:
   *   $shinyCalendar = new vCalendar( array('METHOD' => 'REQUEST' ) );
   *  
   * @param mixed $content Can be a string to be parsed, or an array of key value pairs.
   */
  function __construct($content=null) {
    $this->contained_type = null;
    $this->timezones = array();
    if ( empty($content) || is_array($content) ) {
      parent::__construct();
      $this->SetType('VCALENDAR');
      $this->AddProperty('PRODID', '-//davical.org//NONSGML AWL Calendar//EN');
      $this->AddProperty('VERSION', '2.0');
      $this->AddProperty('CALSCALE', 'GREGORIAN');
      if ( !empty($content) ) {
        foreach( $content AS $k => $v ) {
          $this->AddProperty($k,$v);
        }
      }
    }
    else {
      parent::__construct($content);
      foreach( $this->components AS $k => $comp ) {
        if ( $comp->GetType() == 'VTIMEZONE' ) {
          $this->AddTimeZone($comp, true);
        }
        else if ( empty($this->contained_type) ) {
          $this->contained_type = $comp->GetType();
        }
      }
    }
  }

  
  /**
   * Add a timezone component to this vCalendar.
   */
  function AddTimeZone(vComponent $vtz, $in_components=false) {
    $tzid = $vtz->GetPValue('TZID');
    if ( empty($tzid) ) {
      dbg_error_log('ERROR','Ignoring invalid VTIMEZONE with no TZID parameter!');
      return;
    }
    $this->timezones[$tzid] = $vtz;
    if ( !$in_components ) $this->AddComponent($vtz);
  }

  
  /**
   * Get a timezone component for a specific TZID in this calendar.
   * @param string $tzid The TZID for the timezone to be retrieved.
   * @return vComponent The timezone as a vComponent.
   */
  function GetTimeZone( $tzid ) {
    if ( empty($this->timezones[$tzid]) ) return null;
    return $this->timezones[$tzid];
  }


  /**
   * Get the organizer of this VEVENT/VTODO
   */
  function GetOrganizer() {
    if ( !isset($this->organizer) ) {
      $organizers = $this->GetPropertiesByPath('/VCALENDAR/*/ORGANIZER');
      $organizer = (count($organizers) > 0 ? $organizers[0]->Value() : false);
      $this->organizer = (empty($organizer) ? false : $organizer );
    }
    return $this->organizer;
  }

  
  /**
   * Get the attendees of this VEVENT/VTODO
   */
  function GetAttendees() {
    if ( !isset($this->attendees) ) {
      $attendees = $this->GetPropertiesByPath('/VCALENDAR/*/ATTENDEE');
      $wr_attendees = $this->GetPropertiesByPath('/VCALENDAR/*/X-WR-ATTENDEE');
      if ( count ( $wr_attendees ) > 0 ) {
        dbg_error_log( 'PUT', 'Non-compliant iCal request.  Using X-WR-ATTENDEE property' );
        foreach( $wr_attendees AS $k => $v ) {
          $attendees[] = $v;
        }
      }
      $this->attendees = $attendees;
    }
    return $this->attendees;
  }

  
 
  /**
  * Test a PROP-FILTER or COMP-FILTER and return a true/false
  * COMP-FILTER (is-defined | is-not-defined | (time-range?, prop-filter*, comp-filter*))
  * PROP-FILTER (is-defined | is-not-defined | ((time-range | text-match)?, param-filter*))
  *
  * @param array $filter An array of XMLElement defining the filter
  *
  * @return boolean Whether or not this vCalendar passes the test
  */
  function StartFilter( $filters ) {
    dbg_error_log('vCalendar', ':StartFilter we have %d filters to test', count($filters) );

    if ( count($filters) != 1 ) return false;
    
    $tag = $filters[0]->GetTag();
    $name = $filters[0]->GetAttribute("name");
    if ( $tag != "urn:ietf:params:xml:ns:caldav:comp-filter" || $name != 'VCALENDAR' ) return false;
    return $this->TestFilter($filters[0]->GetContent());
  }

  
  /**
   * Work out what Olson timezone this VTIMEZONE really is.  Perhaps we should put this
   * into a vTimezone class.
   * @param vComponent $vtz The VTIMEZONE component.
   * @return string The Olson name for the timezone.
   */
  function GetOlsonName( vComponent $vtz ) {
    $tzstring = $vtz->GetProperty('TZID');
    $tzid = olson_from_tzstring($tzstring);
    if ( !empty($tzid) ) return $tzid;
    
    $tzstring = $vtz->GetProperty('X-LIC-LOCATION');
    $tzid = olson_from_tzstring($tzstring);
    if ( !empty($tzid) ) return $tzid;
    
    $tzcdo =  $vtz->GetProperty('TZID');
    switch( $tzcdo ) {
      /**
       * List of Microsoft CDO Timezone IDs from here:
	   * http://msdn.microsoft.com/en-us/library/aa563018%28loband%29.aspx
	   */
      case 0:    return('UTC');
      case 1:    return('Europe/London');
      case 2:    return('Europe/Lisbon');
      case 3:    return('Europe/Paris');
      case 4:    return('Europe/Berlin');
      case 5:    return('Europe/Bucharest');
      case 6:    return('Europe/Prague');
      case 7:    return('Europe/Athens');
      case 8:    return('America/Brasilia');
      case 9:    return('America/Halifax');
      case 10:   return('America/New_York');
      case 11:   return('America/Chicago');
      case 12:   return('America/Denver');
      case 13:   return('America/Los_Angeles');
      case 14:   return('America/Anchorage');
      case 15:   return('Pacific/Honolulu');
      case 16:   return('Pacific/Apia');
      case 17:   return('Pacific/Auckland');
      case 18:   return('Australia/Brisbane');
      case 19:   return('Australia/Adelaide');
      case 20:   return('Asia/Tokyo');
      case 21:   return('Asia/Singapore');
      case 22:   return('Asia/Bangkok');
      case 23:   return('Asia/Kolkata');
      case 24:   return('Asia/Muscat');
      case 25:   return('Asia/Tehran');
      case 26:   return('Asia/Baghdad');
      case 27:   return('Asia/Jerusalem');
      case 28:   return('America/St_Johns');
      case 29:   return('Atlantic/Azores');
      case 30:   return('America/Noronha');
      case 31:   return('Africa/Casablanca');
      case 32:   return('America/Argentina/Buenos_Aires');
      case 33:   return('America/La_Paz');
      case 34:   return('America/Indiana/Indianapolis');
      case 35:   return('America/Bogota');
      case 36:   return('America/Regina');
      case 37:   return('America/Tegucigalpa');
      case 38:   return('America/Phoenix');
      case 39:   return('Pacific/Kwajalein');
      case 40:   return('Pacific/Fiji');
      case 41:   return('Asia/Magadan');
      case 42:   return('Australia/Hobart');
      case 43:   return('Pacific/Guam');
      case 44:   return('Australia/Darwin');
      case 45:   return('Asia/Shanghai');
      case 46:   return('Asia/Novosibirsk');
      case 47:   return('Asia/Karachi');
      case 48:   return('Asia/Kabul');
      case 49:   return('Africa/Cairo');
      case 50:   return('Africa/Harare');
      case 51:   return('Europe/Moscow');
      case 53:   return('Atlantic/Cape_Verde');
      case 54:   return('Asia/Yerevan');
      case 55:   return('America/Panama');
      case 56:   return('Africa/Nairobi');
      case 58:   return('Asia/Yekaterinburg');
      case 59:   return('Europe/Helsinki');
      case 60:   return('America/Godthab');
      case 61:   return('Asia/Rangoon');
      case 62:   return('Asia/Kathmandu');
      case 63:   return('Asia/Irkutsk');
      case 64:   return('Asia/Krasnoyarsk');
      case 65:   return('America/Santiago');
      case 66:   return('Asia/Colombo');
      case 67:   return('Pacific/Tongatapu');
      case 68:   return('Asia/Vladivostok');
      case 69:   return('Africa/Ndjamena');
      case 70:   return('Asia/Yakutsk');
      case 71:   return('Asia/Dhaka');
      case 72:   return('Asia/Seoul');
      case 73:   return('Australia/Perth');
      case 74:   return('Asia/Riyadh');
      case 75:   return('Asia/Taipei');
      case 76:   return('Australia/Sydney');

      case 57: // null
      case 52: // null
      default: // null
    }
    return null;
  }   
}
