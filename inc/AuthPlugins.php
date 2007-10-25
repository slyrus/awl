<?php
/**
* The authentication handling plugins can be used by the Session class to
* provide authentication.
*
* Each authenticate hook needs to:
*   - Accept a username / password
*   - Confirm the username / password are correct
*   - Create (or update) a 'usr' record in our database
*   - Return the 'usr' record as an object
*   - Return === false when authentication fails
*
* It can expect that:
*   - Configuration data will be in $c->authenticate_hook['config'], which might be an array, or whatever is needed.
*
* In order to be called:
*   - This file should be included
*   - $c->authenticate_hook['call'] should be set to the name of the plugin
*   - $c->authenticate_hook['config'] should be set up with any configuration data for the plugin
*
* @package   awl
* @subpackage   AuthPlugin
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst IT Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

require_once("AWLUtilities.php");

/**
* Authenticate against a different PostgreSQL database which contains a usr table in
* the AWL format.
*
* @package   awl
*/
function auth_other_awl( $username, $password ) {
  global $c;

  $authconn = pg_Connect($c->authenticate_hook['config']['connection']);
  if ( ! $authconn ) {
    echo <<<EOERRMSG
  <html><head><title>Database Connection Failure</title></head><body>
  <h1>Database Error</h1>
  <h3>Could not connect to PostgreSQL database</h3>
  </body>
  </html>
EOERRMSG;
    exit(1);
  }

  if ( isset($c->authenticate_hook['config']['columns']) )
    $cols = $c->authenticate_hook['config']['columns'];
  else
    $cols = "*";

  $qry = new PgQuery("SELECT $cols FROM usr WHERE lower(username) = ? ", strtolower($username) );
  $qry->SetConnection($authconn);
  if ( $qry->Exec('Login',__LINE,__FILE__) && $qry->rows == 1 ) {
    $usr = $qry->Fetch();
    if ( session_validate_password( $password, $usr->password ) ) {

      $qry = new PgQuery("SELECT * FROM usr WHERE user_no = $usr->user_no;" );
      if ( $qry->Exec('Login',__LINE,__FILE__) && $qry->rows == 1 )
        $type = "UPDATE";
      else
        $type = "INSERT";

      include_once("DataUpdate.php");
      $qry = new PgQuery( sql_from_object( $usr, $type, 'usr', "WHERE user_no=$usr->user_no" ) );
      $qry->Exec('Login',__LINE,__FILE__);

      /**
      * We disallow login by inactive users _after_ we have updated the local copy
      */
      if ( isset($usr->active) && $usr->active == 'f' ) return false;

      return $usr;
    }
  }

  return false;

}

?>