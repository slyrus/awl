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

class VCalendar extends vComponent {

  var $contained_type;
  var $timezones;

  function __construct($content=null) {
    $this->contained_type = null;
    $this->timezones = array();
    if ( is_array($content) ) {
      parent::__construct();
      $this->Initialise($content);
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
   * 
   */
  function AddTimeZone(vComponent $vtz, $in_components=false) {
    $tzid = $vtz->GetPValue('TZID');
    if ( empty($tzid) ) {
      dbg_error_log('ERROR','Ignoring invalid VTIMEZONE with no TZID parameter!');
      return;
    }
    $this->timezones[$tzid] = $vtz;
  }

  
  /**
   * Apply standard properties for a VCalendar
   * @param array $extra_properties Key/value pairs of additional properties
   */
  function Initialise( $extra_properties = null ) {
    $this->SetType('VCALENDAR');
    $this->AddProperty('PRODID', '-//davical.org//NONSGML AWL Calendar//EN');
    $this->AddProperty('VERSION', '2.0');
    $this->AddProperty('CALSCALE', 'GREGORIAN');
    if ( is_array($extra_properties) ) {
      foreach( $extra_properties AS $k => $v ) {
        $this->AddProperty($k,$v);
      }
    }
  }
}
