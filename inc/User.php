<?php
/**
* A class to handle reading, writing, viewing, editing and validating
* usr records.
*
* @package   awl
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Andrew McMillan
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

/**
* We need to access some session information.
*/
require_once("Session.php");

/**
* We use the DataEntry class for data display and updating
*/
require_once("DataEntry.php");

/**
* We use the DataUpdate class and inherit from DBRecord
*/
require_once("DataUpdate.php");

/**
* A class to handle reading, writing, viewing, editing and validating
* usr records.
* @package   awl
* @subpackage   User
*/
class User extends DBRecord {
  /**#@+
  * @access private
  */
  /**
  * A unique user number that is auto assigned on creation and invariant thereafter
  * @var string
  */
  var $user_no;
  /**#@-*/

  /**
  * The constructor initialises a new record, potentially reading it from the database.
  * @param int $id The user_no, or 0 if we are creating a new one
  */
  function User( $id ) {
    global $session;

    // Call the parent constructor
    $this->DBRecord();

    $this->user_no = 0;
    $keys = array();

    $id = intval("$id");
    if ( $id > 0 ) {
      // Initialise
      $keys['user_no'] = $id;
      $this->user_no = $id;
    }

    // Initialise the record, possibly from the file.
    $this->Initialise('usr',$keys);

    $this->EditMode = ( ($_GET['edit'] && $this->AllowedTo($this->WriteType))
                    || (0 == $this->user_no && $this->AllowedTo("insert") ) );

    if ( $this->user_no == 0 ) {
      $session->Log("DBG: Initialising new user values");

      // Initialise to standard default values

    }
  }


  /**
  * Can the user do this?
  * @param string $whatever What the user wants to do
  * @return boolean Whether they are allowed to.
  */
  function AllowedTo ( $whatever )
  {
    global $session;

    $rc = false;
    switch( strtolower($whatever) ) {
      case 'update':
        $rc = ( $session->AllowedTo("Admin")
                || ($this->user_no > 0 && $session->user_no == $this->user_no) );
        break;

      case 'changepassword':
        $rc = ( $session->AllowedTo("Admin")
                || ($this->user_no > 0 && $session->user_no == $this->user_no)
                || ("insert" == $this->WriteType) );
        break;

      case 'admin':
      case 'create':
      case 'insert':
        $rc =  ( $session->AllowedTo("Admin") );
        break;

      default:
        $rc = ( isset($session->roles[$whatever]) && $session->roles[$whatever] );
    }
    return $rc;
  }


  /**
  * Get the group memberships for the user
  */
  function GetRoles () {
    $this->roles = array();
    $qry = new PgQuery( 'SELECT group_name AS role_name FROM group_member m join ugroup g ON g.group_no = m.group_no WHERE user_no = ? ', $this->user_no );
    if ( $qry->Exec('Login') && $qry->rows > 0 ) {
      while( $role = $qry->Fetch() ) {
        $this->roles[$role->role_name] = true;
      }
    }
  }


  /**
  * Render the form / viewer as HTML to show the user
  * @return string An HTML fragment to display in the page.
  */
  function Render( ) {
    global $session;

    $html = "";
    $session->Log("DBG: User::Render: type=$this->WriteType, edit_mode=$this->EditMode" );

    $ef = new EntryForm( $REQUEST_URI, $this->Values, $this->EditMode );
    $ef->NoHelp();  // Prefer this style, for the moment

    if ( $ef->editmode ) {
      $html .= $ef->StartForm( array("autocomplete" => "off" ) );
      if ( $this->user_no > 0 ) $html .= $ef->HiddenField( "user_no", $this->user_no );
    }

    $html .= "<table width=\"100%\" class=\"data\" cellspacing=\"0\" cellpadding=\"0\">\n";

    $html .= $this->RenderFields($ef);

    $html .= "</table>\n";
    if ( $ef->editmode ) {
      $html .= '<div id="footer">';
      $html .= $ef->SubmitButton( "submit", (("insert" == $this->WriteType) ? "Create" : "Update") );
      $html .= '</div>';
      $html .= $ef->EndForm();
    }

    return $html;
  }

  /**
  * Render the core details to show to the user
  * @return string An HTML fragment to display in the page.
  */
  function RenderFields($ef ) {
    global $session;

    $html = $ef->BreakLine("User Details");

    $html .= $ef->DataEntryLine( "User Name", "%s", "text", "username",
              array( "size" => 20, "title" => "The name this user can log into the system with.") );
    if ( $ef->editmode && $this->AllowedTo('ChangePassword') ) {
      $this->Set('new_password','******');
      unset($_POST['new_password']);
      $html .= $ef->DataEntryLine( "New Password", "%s", "password", "new_password",
                array( "size" => 20, "title" => "The user's password for logging in.") );
      $this->Set('confirm_password', '******');
      unset($_POST['confirm_password']);
      $html .= $ef->DataEntryLine( "Confirm", "%s", "password", "confirm_password",
                array( "size" => 20, "title" => "Confirm the new password.") );
    }

    $html .= $ef->DataEntryLine( "Full Name", "%s", "text", "fullname",
              array( "size" => 50, "title" => "The description of the system.") );

    $html .= $ef->DataEntryLine( "Email", "%s", "text", "email",
              array( "size" => 50, "title" => "The user's e-mail address.") );

    $html .= $ef->DataEntryLine( "Active", "%s", "checkbox", "active",
              array( "_label" => "User is active",
                     "title" => "Is this user active?.") );

    $html .= $ef->DataEntryLine( "EMail OK", "%s", "date", "email_ok",
              array( "title" => "When the user's e-mail account was validated.") );

    $html .= $ef->DataEntryLine( "Joined", substr($this->Get('joined'),0,16) );
    $html .= $ef->DataEntryLine( "Updated", substr($this->Get('updated'),0,16) );
    $html .= $ef->DataEntryLine( "Last used", substr($this->Get('last_used'),0,16) );

    return $html;
  }

  /**
  * Validate the information the user submitted
  * @return boolean Whether the form data validated OK.
  */
  function Validate( ) {
    global $session, $c;
    $session->Log("DBG: User::Validate: Validating user");

    $valid = true;

    if ( $this->Get('fullname') == "" ) {
      $c->messages[] = "ERROR: The full name may not be blank.";
      $valid = false;
    }

    // Password changing is a little special...
    if ( $_POST['new_password'] != "******" && $_POST['new_password'] != ""  ) {
      if ( $_POST['new_password'] == $_POST['confirm_password'] ) {
        $this->Set('password',$_POST['new_password']);
      }
      else {
        $c->messages[] = "ERROR: The new password must match the confirmed password.";
        $valid = false;
      }
    }

    $session->Log("DBG: User::Validate: User %s validation", ($valid ? "passed" : "failed"));
    return $valid;
  }

  /**
  *
  */
  function Write() {
    global $session;
    if ( parent::Write() && $this->WriteType == 'insert' ) {
      $qry = new PgQuery( "SELECT currval('usr_user_no_seq');" );
      $qry->Exec("User::Write");
      $sequence_value = $qry->Fetch(true);  // Fetch as an array
      $this->user_no = $sequence_value[0];
    }
  }

}
?>