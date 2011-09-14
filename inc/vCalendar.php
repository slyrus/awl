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

    
}
