<?php
/**
* Session handling class and associated functions
*
* This subpackage provides some functions that are useful around web
* application session management.
*
* The class is intended to be as lightweight as possible while holding
* all session data in the database:
*  - Session hash is not predictable.
*  - No clear text information is held in cookies.
*  - Passwords are generally salted MD5 hashes, but individual users may
*    have plain text passwords set by an administrator.
*  - Temporary passwords are supported.
*  - Logout is supported
*  - "Remember me" cookies are supported, and will result in a new
*    Session for each browser session.
*
* @package   awl
* @subpackage   Session
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Andrew McMillan
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

require_once('PgQuery.php');

if ( isset($_GET['logout']) ) {
  error_log("$sysname: Session: DBG: Logging out");
  setcookie( 'sid', '', 0,'/');
  unset($_COOKIE['sid']);
  unset($_COOKIE['lsid']); // Allow a cookied person to be un-logged-in for one page view.

  if ( isset($_GET['forget']) ) setcookie( 'lsid', '', 0,'/');
}

$session = new Session();

if ( isset($_POST['lostpass']) ) {
  if ( $debuggroups['Login'] )
    $session->Log( "DBG: User '$_POST[username]' has lost the password." );

  $session->SendTemporaryPassword();
}
else if ( isset($_POST['username']) && isset($_POST['password']) ) {
  // Try and log in if we have a username and password
  $session->Login( $_POST['username'], $_POST['password'] );
  if ( $debuggroups['Login'] )
    $session->Log( "DBG: User $_POST[username] - $session->fullname ($session->user_no) login status is $session->logged_in" );
}
else if ( !isset($_COOKIE['sid']) && isset($_COOKIE['lsid']) && $_COOKIE['lsid'] != "" ) {
  // Validate long-term session details
  $session->LSIDLogin( $_COOKIE['lsid'] );
  if ( $debuggroups['Login'] )
    $session->Log( "DBG: User $session->username - $session->fullname ($session->user_no) login status is $session->logged_in" );
}

/**
* Make a salted MD5 string, given a string and (possibly) a salt.
*
* If no salt is supplied we will generate a random one.
*
* @param string $instr The string to be salted and MD5'd
* @param string $salt Some salt to sprinkle into the string to be MD5'd so we don't get the same PW always hashing to the same value.
* @return string The salt, a * and the MD5 of the salted string, as in SALT*SALTEDHASH
*/
function session_salted_md5( $instr, $salt = "" ) {
  global $debuggroups, $session;
  if ( $salt == "" ) $salt = substr( md5(rand(100000,999999)), 2, 8);
  if ( $debuggroups['Login'] )
    $session->Log( "DBG: Making salted MD5: salt=$salt, instr=$instr, md5($salt$instr)=".md5($salt . $instr) );
  return ( sprintf("*%s*%s", $salt, md5($salt . $instr) ) );
}

/**
* Checks what a user entered against the actual password on their account.
* @param string $they_sent What the user entered.
* @param string $we_have What we have in the database as their password.  Which may (or may not) be a salted MD5.
* @return boolean Whether or not the users attempt matches what is already on file.
*/
function session_validate_password( $they_sent, $we_have ) {
  global $debuggroups, $session;

  if ( $debuggroups['Login'] )
    $session->Log( "DBG: Comparing they_sent=$they_sent with $we_have" );

  // In some cases they send us a salted md5 of the password, rather
  // than the password itself (i.e. if it is in a cookie)
  $pwcompare = $we_have;
  if ( ereg('^\*(.+)\*.+$', $they_sent, $regs ) ) {
    $pwcompare = session_salted_md5( $we_have, $regs[1] );
    if ( $they_sent == $pwcompare ) return true;
  }

  if ( ereg('^\*\*.+$', $we_have ) ) {
    //  The "forced" style of "**plaintext" to allow easier admin setting
    // error_log( "$system_name: vpw: DBG: comparing=**they_sent" );
    return ( "**$they_sent" == $pwcompare );
  }

  if ( ereg('^\*(.+)\*.+$', $we_have, $regs ) ) {
    // A nicely salted md5sum like "*<salt>*<salted_md5>"
    $salt = $regs[1];
    $md5_sent = session_salted_md5( $they_sent, $salt ) ;
    if ( $debuggroups['Login'] )
      $session->Log( "DBG: Salt=$salt, comparing=$md5_sent with $pwcompare or $we_have" );
    return ( $md5_sent == $pwcompare );
  }

  // Blank passwords are bad
  if ( "" == "$we_have" || "" == "$they_sent" ) return false;

  // Otherwise they just have a plain text string, which we
  // compare directly, but case-insensitively
  return ( $they_sent == $pwcompare || strtolower($they_sent) == strtolower($we_have) );
}

/**
* Checks what a user entered against any currently valid temporary passwords on their account.
* @param string $they_sent What the user entered.
* @param int $user_no Which user is attempting to log on.
* @return boolean Whether or not the user correctly guessed a temporary password within the necessary window of opportunity.
*/
function check_temporary_passwords( $they_sent, $user_no ) {
  global $debuggroups, $session;

  $sql = 'SELECT 1 AS ok FROM tmp_password WHERE user_no = ? AND password = ? AND valid_until > current_timestamp';
  $qry = new PgQuery( $sql, $user_no, $they_sent );
  if ( $qry->Exec('Session::check_temporary_passwords') ) {
    $session->Log("DBG: Rows = $qry->rows");
    if ( $row = $qry->Fetch() ) {
      $session->Log("DBG: OK = $row->ok");
      // Remove all the temporary passwords for that user...
      $sql = 'DELETE FROM tmp_password WHERE user_no = ? ';
      $qry = new PgQuery( $sql, $user_no );
      $qry->Exec('Session::check_temporary_passwords');
      return true;
    }
  }
  return false;
}

/**
* @package   awl
* @subpackage   Session
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Andrew McMillan
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
class Session
{
  var $user_no = 0;
  var $session_id = 0;
  var $username = 'guest';
  var $full_name = 'Guest';
  var $email = '';
  var $roles;
  var $logged_in = false;
  var $cause = '';
  var $just_logged_in = false;

  /**
  * Create a new Session object.
  *
  * If a session identifier is supplied, or we can find one in a cookie, we validate it
  * and consider the person logged in.  We read some useful session and user data in
  * passing as we do this.
  *
  * The session identifier contains a random value, hashed, to provide validation. This
  * could be hijacked if the traffic was sniffable so sites who are paranoid about security
  * should only do this across SSL.
  *
  * A worthwhile enhancement would be to add some degree of external configurability to
  * that read.
  *
  * @param string $sid A session identifier.
  */
  function Session( $sid="" )
  {
    $this->roles = array();
    $this->logged_in = false;
    $this->just_logged_in = false;

    if ( $sid == "" ) {
      if ( ! isset($_COOKIE['sid']) ) return;
      $sid = $_COOKIE['sid'];
    }

    list( $session_id, $session_key ) = explode( ';', $sid, 2 );

    // We need some tidy way to configure this appropriately for the actual database
    // schema to avoid a second SELECT to read related records.
    $sql = "SELECT session.*, usr.*
        FROM session, usr
        WHERE usr.user_no = session.user_no
        AND session_id = ?
        AND (md5(session_start::text) = ? OR session_key = ?)
        ORDER BY session_start DESC LIMIT 1";

    $qry = new PgQuery($sql, $session_id, $session_key, $session_key);
    if ( $qry->Exec('Session') && $qry->rows == 1 )
    {
      $this->AssignSessionDetails( $qry->Fetch() );
      $qry = new PgQuery('UPDATE session SET session_end = current_timestamp WHERE session_id=?', $session_id);
      $qry->Exec('Session');
    }
    else
    {
      //  Kill the existing cookie, which appears to be bogus
      setcookie('sid', '', 0,'/');
      $this->cause = 'ERR: Other than one session record matches. ' . $qry->rows;
      $this->Log( "WARN: Login $this->cause" );
    }
  }


  /**
  * Utility function to log stuff with printf expansion.
  *
  * This function could be expanded to log something identifying the session, but
  * somewhat strangely this has not yet been done.
  *
  * @param string $whatever A log string
  * @param mixed $whatever... Further parameters to be replaced into the log string a la printf
  */
  function Log( $whatever )
  {
    global $c;

    $argc = func_num_args();
    $format = func_get_arg(0);
    if ( $argc == 1 || ($argc == 2 && func_get_arg(1) == "0" ) ) {
      error_log( "$c->sysabbr: $format" );
    }
    else {
      $args = array();
      for( $i=1; $i < $argc; $i++ ) {
        $args[] = func_get_arg($i);
      }
      error_log( "$c->sysabbr: " . vsprintf($format,$args) );
    }
  }

  /**
  * Checks whether a user is allowed to do something.
  *
  * The check is performed to see if the user has that role.
  *
  * @param string $whatever The role we want to know if the user has.
  * @return boolean Whether or not the user has the specified role.
  */
  function AllowedTo ( $whatever ) {
    return ( $this->logged_in && isset($this->roles[$whatever]) && $this->roles[$whatever] );
  }


/**
* Internal function used to get the user's roles from the database.
*/
  function GetRoles () {
    $this->roles = array();
    $qry = new PgQuery( 'SELECT role_name FROM role_member m join roles r ON r.role_no = m.role_no WHERE user_no = ? ', $this->user_no );
    if ( $qry->Exec('Session::GetRoles') && $qry->rows > 0 ) {
      while( $role = $qry->Fetch() ) {
        $this->roles[$role->role_name] = true;
      }
    }
  }


/**
* Internal function used to assign the session details to a user's new session.
* @param object $u The user+session object we (probably) read from the database.
*/
  function AssignSessionDetails( $u ) {
    $this->user_no = $u->user_no;
    $this->username = $u->username;
    $this->fullname = $u->fullname;
    $this->email = $u->email;
    $this->config_data = $u->config_data;
    $this->session_id = $u->session_id;

    $this->GetRoles();
    $this->logged_in = true;
  }


/**
* Attempt to perform a login action.
*
* This will validate the user's username and password.  If they are OK then a new
* session id will be created and the user will be cookied with it for subsequent
* pages.  A logged in session will be created, and the $_POST array will be cleared
* of the username, password and submit values.  submit will also be cleared from
* $_GET and $GLOBALS, just in case.
*
* @param string $username The user's login name, or at least what they entered it as.
* @param string $password The user's password, or at least what they entered it as.
* @return boolean Whether or not the user correctly guessed a temporary password within the necessary window of opportunity.
*/
  function Login( $username, $password ) {
    global $c, $debuggroups;
    $rc = false;
    if ( $debuggroups['Login'] )
      $this->Log( "DBG: Login: Attempting login for $username" );

    $sql = "SELECT * FROM usr WHERE lower(username) = ? ";
    $qry = new PgQuery( $sql, strtolower($username), md5($password), $password );
    if ( $qry->Exec('Session::UPWLogin') && $qry->rows == 1 ) {
      $usr = $qry->Fetch();
      if ( session_validate_password( $password, $usr->password ) || check_temporary_passwords( $password, $usr->user_no ) ) {
        // Now get the next session ID to create one from...
        $qry = new PgQuery( "SELECT nextval('session_session_id_seq')" );
        if ( $qry->Exec('Login') && $qry->rows == 1 ) {
          $seq = $qry->Fetch();
          $session_id = $seq->nextval;
          $session_key = md5( rand(1010101,1999999999) . microtime() );  // just some random shite
          if ( $debuggroups['Login'] )
            $this->Log( "DBG:: Login: Valid username/password for $username ($usr->user_no)" );

          // And create a session
          $sql = "INSERT INTO session (session_id, user_no, session_key) VALUES( ?, ?, ? )";
          $qry = new PgQuery( $sql, $session_id, $usr->user_no, $session_key );
          if ( $qry->Exec('Login') ) {
            // Assign our session ID variable
            $sid = "$session_id;$session_key";

            //  Create a cookie for the sesssion
            setcookie('sid',$sid, 0,'/');
            // Recognise that we have started a session now too...
            $this->Session($sid);
            $this->Log( "DBG: Login: INFO: New session $session_id started for $username ($usr->user_no)" );
            if ( isset($_POST['remember']) && intval($_POST['remember']) > 0 ) {
              $cookie .= md5( $this->user_no ) . ";";
              $cookie .= session_salted_md5($usr->user_no . $usr->username . $usr->password);
              setcookie( "lsid", $cookie, time() + (86400 * 3600), "/" );   // will expire in ten or so years
            }
            $this->just_logged_in = true;
            unset($_POST['username']);
            unset($_POST['password']);
            unset($_POST['submit']);
            unset($_GET['submit']);
            unset($GLOBALS['submit']);
            $rc = true;
            return $rc;
          }
   // else ...
          $this->cause = 'ERR: Could not create new session.';
        }
        else {
          $this->cause = 'ERR: Could not increment session sequence.';
        }
      }
      else {
        $client_messages[] = 'Invalid username or password.';
        if ( $debuggroups['Login'] )
          $this->cause = 'WARN: Invalid password.';
        else
          $this->cause = 'WARN: Invalid username or password.';
      }
    }
    else {
    $client_messages[] = 'Invalid username or password.';
    if ( $debuggroups['Login'] )
      $this->cause = 'WARN: Invalid username.';
    else
      $this->cause = 'WARN: Invalid username or password.';
    }

    $this->Log( "DBG: Login $this->cause" );
    $rc = false;
    return $rc;
  }



/**
* Attempts to logs in using a long-term session ID
*
* This is all horribly insecure, but its hard not to be.
*
* @param string $lsid The user's value of the lsid cookie.
* @return boolean Whether or not the user's lsid cookie got them in the door.
*/
  function LSIDLogin( $lsid ) {
    global $sysname, $debuggroups, $client_messages;
    if ( $debuggroups['Login'] )
      $this->Log( "DBG: Login: Attempting login for $lsid" );

    list($md5_user_no,$validation_string) = split( ';', $lsid );
    $qry = new PgQuery( "SELECT * FROM usr WHERE md5(user_no)=?;", $md5_user_no );
    if ( $qry->Exec('Session::LSIDLogin') && $qry->rows == 1 ) {
      $usr = $qry->Fetch();
      list( $x, $salt, $y) = split('\*', $validation_string);
      $my_validation = session_salted_md5($usr->user_no . $usr->username . $usr->password, $salt);
      if ( $validation_string == $my_validation ) {
        // Now get the next session ID to create one from...
        $qry = new PgQuery( "SELECT nextval('session_session_id_seq')" );
        if ( $qry->Exec('Login') && $qry->rows == 1 ) {
          $seq = $qry->Fetch();
          $session_id = $seq->nextval;
          $session_key = md5( rand(1010101,1999999999) . microtime() );  // just some random shite
          if ( $debuggroups['Login'] )
            $this->Log( "DBG:: Login: Valid username/password for $username ($usr->user_no)" );

          // And create a session
          $sql = "INSERT INTO session (session_id, user_no, session_key) VALUES( ?, ?, ? )";
          $qry = new PgQuery( $sql, $session_id, $usr->user_no, $session_key );
          if ( $qry->Exec('Login') ) {
            // Assign our session ID variable
            $sid = "$session_id;$session_key";

            //  Create a cookie for the sesssion
            setcookie('sid',$sid, 0,'/');
            // Recognise that we have started a session now too...
            $this->Session($sid);
            $this->Log( "DBG: Login: INFO: New session $session_id started for $this->username ($usr->user_no)" );
            return true;
          }
   // else ...
          $this->cause = 'ERR: Could not create new session.';
        }
        else {
          $this->cause = 'ERR: Could not increment session sequence.';
        }
      }
      else {
        $this->Log("DBG: $validation_string != $my_validation ($salt - $usr->user_no, $usr->username, $usr->password)");
        $client_messages[] = 'Invalid username or password.';
        if ( $debuggroups['Login'] )
          $this->cause = 'WARN: Invalid password.';
        else
          $this->cause = 'WARN: Invalid username or password.';
      }
    }
    else {
    $client_messages[] = 'Invalid username or password.';
    if ( $debuggroups['Login'] )
      $this->cause = 'WARN: Invalid username.';
    else
      $this->cause = 'WARN: Invalid username or password.';
    }

    $this->Log( "DBG: Login $this->cause" );
    return false;
  }


/**
* Checks that this user is logged in, and presents a login screen if they aren't.
*
* The function can optionally confirm whether they are a member of one of a list
* of groups, and deny access if they are not a member of any of them.
*
* @param string $groups The list of groups that the user must be a member of one of to be allowed to proceed.
* @return boolean Whether or not the user is logged in and is a member of one of the required groups.
*/
  function LoginRequired( $groups = "" ) {
    global $c, $session;

    if ( $this->logged_in && $groups == "" ) return;
    if ( ! $this->logged_in ) {
      $c->messages[] = "You must log in to use this system.";
      include_once("page-header.php");
      if ( function_exists("local_index_not_logged_in") ) {
        local_index_not_logged_in();
      }
      else {
        echo <<<EOTEXT
<div id="logon">
<h1>Log On Please</h1>
<p>For access to the $c->system_name you should log on with
the username and password that have been issued to you.</p>

<p>If you would like to request access, please e-mail $c->admin_email.</p>
<form action="$action_target" method="post">
<table>
<tr>
<th class="prompt">User Name:</th>
<td class="entry">
<input class="text" type="text" name="username" size="12"></td>
</tr>
<tr>
<th class="prompt">Password:</th>
<td class="entry">
<input class="password" type="password" name="password" size="12">
 &nbsp;<label>forget&nbsp;me&nbsp;not: <input class="checkbox" type="checkbox" name="remember" value="1"></label>
</td>
</tr>
<tr>
<th class="prompt">&nbsp;</th>
<td class="entry">
<input type="submit" value="GO!" alt="go" name="submit" class="submit">
</td>
</tr>
</table>
<p>
If you have forgotten your password then: <input type="submit" value="Help! I've forgotten my password!" alt="Enter a username, if you know it, and click here." name="lostpass" class="submit">
</p>
</form>
</div>

EOTEXT;
      }
    }
    else {
      $valid_groups = split(",", $groups);
      foreach( $valid_groups AS $k => $v ) {
        if ( $this->AllowedTo($v) ) return;
      }
      $c->messages[] = "You are not authorised to use this function.";
      include_once("page-header.php");
    }

    include("page-footer.php");
    exit;
  }



/**
* Sends a temporary password in response to a request from a user.
*
* This is probably only going to be called from somewhere internal, but perhaps
* an application might want to decide when to call it.
*
* This function includes EMail.php to actually send the password.
*/
  function SendTemporaryPassword( ) {
    global $c;

    include("EMail.php");
    $page_content = "";
    $password_sent = false;
    $where = "";
    if ( isset($_POST['username']) && $_POST['username'] != "" ) {
      $where = "WHERE active AND usr.username = ". qpg($_POST['username'] );
    }
    if ( ! $password_sent && isset($_POST['email_address']) && $_POST['email_address'] != "" ) {
      $where = "WHERE active AND usr.email = ". qpg($_POST['email_address'] );
    }

    if ( $where != "" ) {
      $tmp_passwd = "";
      for ( $i=0; $i < 8; $i++ ) {
        $tmp_passwd .= substr( "#.-=*%@0123456789abcdefghijklmnopqrstuvwxyz", rand(0,42), 1);
      }
      $sql = "SELECT * FROM usr $where";
      $qry = new PgQuery( $sql );
      $qry->Exec("Session::SendTemporaryPassword");
      if ( $qry->rows > 0 ) {
        $sql = "BEGIN;";

        include_once("EMail.php");
        $mail = new EMail( "Temporary Password for $c->system_name" );
        $mail->SetFrom($c->admin_email );
        $usernames = "";
        while ( $row = $qry->Fetch() ) {
          $sql .= "INSERT INTO tmp_password ( user_no, password) VALUES( $row->user_no, '$tmp_passwd');";
          $mail->AddTo( "$row->fullname <$row->email>" );
          $usernames .= "        $row->username\n";
        }
        if ( $mail->To != "" ) {
          $sql .= "COMMIT;";
          $qry = new PgQuery( $sql );
          $qry->Exec("Session::SendTemporaryPassword");
          $body = <<<EOTEXT
A temporary password has been requested for $c->system_name.

Temporary Password: $tmp_passwd

This has been applied to the following usernames:
$usernames
and will be valid for 24 hours.

If you have any problems, please contact the system administrator.

EOTEXT;
          $mail->SetBody($body);
          $mail->Send();
          $password_sent = true;
        }
      }
    }

    if ( ! $password_sent && ((isset($_POST['username']) && $_POST['username'] != "" )
                              || (isset($_POST['email_address']) && $_POST['email_address'] != "" )) ) {
      // Username or EMail were non-null, but we didn't find that user.

      $page_content = <<<EOTEXT
<div id="logon">
<h1>Unable to Reset Password</h1>
<p>We were unable to reset your password at this time.  Please contact
<a href="mailto:$c->admin_email">$c->admin_email</a>
to arrange for an administrator to reset your password.</p>
<p>Thank you.</p>
</div>
EOTEXT;
    }

    if ( $password_sent ) {
      $page_content = <<<EOTEXT
<div id="logon">
<h1>Temporary Password Sent</h1>
<p>A temporary password has been e-mailed to you.  This password
will be valid for 24 hours and you will be required to change
your password after logging in.</p>
<p><a href="/">Click here to return to the login page.</a></p>
</div>
EOTEXT;
    }
    else {
      $page_content = <<<EOTEXT
<div id="logon">
<h1>Forgotten Password</h1>
<form action="$action_target" method="post">
<table>
<tr>
<th class="prompt">Enter your User Name:</th>
<td class="entry"><input class="text" type="text" name="username" size="12"></td>
</tr>
<tr>
<th class="prompt">Or your EMail Address:</th>
<td class="entry"><input class="text" type="text" name="email_address" size="50"></td>
</tr>
<tr>
<th class="prompt">and click on -></th>
<td class="entry">
<input class="submit" type="submit" value="Send me a temporary password" alt="Enter a username, or e-mail address, and click here." name="lostpass">
</td>
</tr>
</table>
<p>Note: If you have multiple accounts with the same e-mail address, they will <em>all</em>
be assigned a new temporary password, but only the one(s) that you use that temporary password
on will have the existing password invalidated.</p>
<p>Any temporary password will only be valid for 24 hours.</p>
</form>
</div>
EOTEXT;
    }
    include_once("page-header.php");
    echo $page_content;
    include_once("page-footer.php");
    exit(0);
  }
}

?>