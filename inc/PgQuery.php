<?php
/**
* PostgreSQL query class and associated functions
*
* This subpackage provides some functions that are useful around database
* activity and a PgQuery class to simplify handling of database queries.
*
* The class is intended to be a very lightweight wrapper with no pretentions
* towards database independence, but it does include some features that have
* proved useful in developing and debugging web-based applications:
*  - All queries are timed, and an expected time can be provided.
*  - Parameters replaced into the SQL will be escaped correctly in order to
*    minimise the chances of SQL injection errors.
*  - Queries which fail, or which exceed their expected execution time, will
*    be logged for potential further analysis.
*  - Debug logging of queries may be enabled globally, or restricted to
*    particular sets of queries.
*  - Simple syntax for iterating through a result set.
*
* The database should be connected in a variable $dbconn before
* PgQuery.php is included.  PgQuery does not attempt to connect
* to the database - the pg_connect() function seems perfectly
* adequate to the task.
*
* We will die if the database is not currently connected.
*
* @package   awl
* @subpackage   PgQuery
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst IT Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

/**
* @global resource $dbconn
* @name $dbconn
* The database connection.
*/
if ( !isset($dbconn) ) {
  /**
  * Attempt to connect to the configured connect strings
  */
  $dbconn = false;
  if ( isset($c->pg_connect) && is_array($c->pg_connect) ) {
    foreach( $c->pg_connect AS $k => $v ) {
      if ( !$dbconn ) $dbconn = pg_Connect($v);
    }
  }
  if ( ! $dbconn ) {
    echo <<<EOERRMSG
  <html><head><title>Database Connection Failure</title></head><body>
  <h1>Database Error</h1>
  <h3>Could not connect to PostgreSQL database</h3>
  </body>
  </html>
EOERRMSG;
    if ( isset($c->pg_connect) && is_array($c->pg_connect) ) {
      dbg_log_array("ERROR", "Connection failed", $c->pg_connect );
    } 
    exit;
  }
  
}


/**
* A duration (in decimal seconds) between two times.
*
* This simple function is used by the PgQuery class because the
* microtime function doesn't return a decimal time, so a simple
* subtraction is not sufficient.
*
* @param microtime $t1 start time
* @param microtime $t2 end time
* @return double difference
*/
function duration( $t1, $t2 )                   // Enter two times from microtime() call
{
  list ( $ms1, $s1 ) = explode ( " ", $t1 );   // Format times - by spliting seconds and microseconds
  list ( $ms2, $s2 ) = explode ( " ", $t2 );
  $s1 = $s2 - $s1;
  $s1 = $s1 + ( $ms2 -$ms1 );
  return $s1;                                  // Return duration of time
}

/**
* Quote the given string (depending on its type) so that it can be used
* safely in a PostgreSQL query without fear of SQL injection errors.
*
* Although this function effectively achieves a similar goal to the pg_escape_string()
* function, it is needed for older versions of PHP (< 4.2.0) and older versions
* of PostgreSQL (< 7.2.0), however.  PgQuery does not attempt to use the newer
* pg_escape_string() function at this stage.
*
* This function is outside the PgQuery class because it is sometimes
* desirable to quote values for SQL command strings in circumstances
* where there is no benefit to using the class.
*
* @param mixed $str Data to be converted to a string suitable for including as a value in SQL.
* @return string NULL, TRUE, FALSE, a plain number, or the original string quoted and with ' and \ characters escaped
*/
function qpg($str = null)
{

  switch (strtolower(gettype($str))) {
    case 'null':
      $rv = 'NULL';
      break;
    case 'integer':
    case 'double' :
      return $str;
    case 'boolean':
      $rv = $str ? 'TRUE' : 'FALSE';
      break;
    case 'string':
    default:
      $str = str_replace("'", "''", $str);
      //PostgreSQL treats a backslash as an escape character.
      $str = str_replace('\\', '\\\\', $str);
      $rv = "'$str'";
  }
  return $rv;
}

/**
* Clean a string of many suspicious characters
*
* While this is a fairly aggressive approach, it applies in many circumstances
* where various strings should not contain things that might screw up (e.g.)
* filesystem semantics.  Although not strictly a PgQuery function it's here
* for the time being until I invent a new "generally useful functions" include.
*
* @param string $unclean The dirty filthy string needing washing.
* @return string The pristine uncontaminated string we can safely use for Just About Anything(tm).
*/
function clean_string( $unclean, $type = 'full' ) {
  global $session;
  if ( $type != 'basic' ) $cleaned = strtolower($unclean); else $cleaned = &$unclean;
  $cleaned = preg_replace( "/['\"!\\\\()\[\]|*\/{}&%@~;:?<>]/", '', $cleaned );
  $session->Dbg( "always", "Cleaned string from <<%s>> to <<%s>>", $unclean, $cleaned );
  return $cleaned;
}

/**
* Log error, optionally with file and line location of the caller.
*
* This function should not really be used outside of PgQuery.  For a more
* useful generic logging interface consider calling $session->Log(...);
*
* @param string $locn    A string identifying the calling location.
* @param string $tag     A tag string, e.g. identifying the type of event.
* @param string $string  The information to be logged.
* @param int    $line    The line number where the logged event occurred.
* @param string $file    The file name where the logged event occurred.
*/
function log_error( $locn, $tag, $string, $line = 0, $file = 0)
{
  GLOBAL $c;
  // replace more than one space with one space
  $string = preg_replace('/\s+/', ' ', $string);

  if ( $line != 0 && $file != 0 ) {
    error_log( "$c->sysabbr $locn $tag: PgQuery error in '$file' on line $line ");
  }

  while( strlen( $string ) > 0 )  {
    error_log( "$c->sysabbr $locn $tag: " . substr( $string, 0, 240), 0 );
    $string = substr( "$string", 240 );
  }

}


/**
* Replaces PostgreSQL query with escaped parameters in preparation
* for execution.
*
* The function takes a variable number of arguments, the first is the
* SQL string, with replaceable '?' characters (a la DBI).  The subsequent
* parameters being the values to replace into the SQL string.
*
* The values passed to the routine are analyzed for type, and quoted if
* they appear to need quoting.  This can go wrong for (e.g.) NULL or
* other special SQL values which are not straightforwardly identifiable
* as needing quoting (or not).  In such cases the parameter can be forced
* to be inserted unquoted by passing it as "array( 'plain' => $param )".
*
* This function is outside the PgQuery class because it is sometimes
* desirable to build SQL command strings in circumstances where there
* is no benefit to using the class.
*
* @param  string The query string with replacable '?' characters.
* @param mixed The values to replace into the SQL string.
* @return The built query string
*/
function awl_replace_sql_args() {
  $argc = func_num_args(); //number of arguments passed to the function
  $qry = func_get_arg(0); //first argument
  $args = func_get_args(); //all argument in an array

  if ( is_array($qry) ) {
    $qry = $args[0][0];
    $args = $args[0];
    $argc = count($args);
  }

// building query string by replacing ? with
// escaped parameters
  $parts = explode( '?', $qry );
  $querystring = $parts[0];
  $z = min( count($parts), $argc );

  for( $i = 1; $i < $z; $i++ ) {
    $arg = $args[$i];
    if ( !isset($arg) ) {
      $querystring .= 'NULL';
    }
    elseif ( is_array($arg) && $arg['plain'] != '' ) {
      // We abuse this, but people should access it through the PgQuery::Plain($v) function
      $querystring .= $arg['plain'];
    }
    else {
  $querystring .= qpg($arg);  //parameter
    }
    $querystring .= $parts[$i]; //extras eg. ","
  }
  if ( isset($parts[$z]) ) $querystring .= $parts[$z]; //puts last part on the end

  return $querystring;
}


/**
* The PgQuery Class.
*
* This class builds and executes PostgreSQL Queries and traverses the
* set of results returned from the query.
*
* <b>Example usage</b>
* <code>
* $sql = "SELECT * FROM mytable WHERE mytype = ?";
* $qry = new PgQuery( $sql, $myunsanitisedtype );
* if ( $qry->Exec("typeselect", __line__, __file__ )
*      && $qry->rows > 0 )
* {
*   while( $row = $qry->Fetch() ) {
*     do_something_with($row);
*   }
* }
* </code>
*
* @package   awl
*/
class PgQuery
{
  /**#@+
  * @access private
  */
  /**
  * stores a query string
  * should be read-only
  * @var string
  */
  var $querystring;

  /**
  * stores a resource result
  * should be internal
  * @var resource
  */
  var $result;

  /**
  * number of current row
  * should be internal, or at least read-only
  * @var int
  */
  var $rownum = -1;

  /**
  * Where we called this query from so we can find it in our code!
  * Debugging may also be selectively enabled for a $location.
  * @var string
  */
  var $location;

  /**
  * The row most recently fetched by a call to Fetch() or FetchBackwards
  * which will either be an array or an object (depending on the Fetch call).
  * @var mixed
  */
  var $object;

  /**#@-*/

  /**#@+
  * @access public
  */
  /**
  * number of rows from pg_numrows - for fetching result
  * should be read-only
  * @var int
  */
  var $rows;

  /**
  * The PostgreSQL error message, if the query fails.
  * Should be read-only, although any successful Exec should clear it
  * @var string
  */
  var $errorstring;

  /**
  * Stores the query execution time - used to deal with long queries.
  * should be read-only
  * @var string
  */
  var $execution_time;

  /**
  * How long the query should take before a warning is issued.
  *
  * This is writable, but a method to set it might be a better interface.
  * The default is 0.3 seconds.
  * @var double
  */
  var $query_time_warning = 0.3;
  /**#@-*/


 /**
  * Constructor
  * @param  string The query string with replacable '?' characters.
  * @param mixed The values to replace into the SQL string.
  * @return The PgQuery object
  */
  function PgQuery()
  {
    $this->result = 0;
    $this->rows = 0;
    $this->execution_time = 0;
    $this->rownum = -1;

    $argc = func_num_args();

    if ( 1 < $argc ) {
      $this->querystring = awl_replace_sql_args( func_get_args() );
    }
    else {
      // If we are only called with a single argument, we do
      // nothing special with any question marks.
      $this->querystring = func_get_arg(0);
    }

    return $this;
  }

  /**
  * Quote the given string so it can be safely used within string delimiters
  * in a query.
  *
  * @see qpg()
  * which is where this is really done.
  *
  * @param mixed $str Data to be converted to a string suitable for including as a value in SQL.
  * @return string NULL, TRUE, FALSE, a plain number, or the original string quoted and with ' and \ characters escaped
  */
  function quote($str = null)
  {
    return qpg($str);
  }

  /**
  * Convert a string which has already been quoted and escaped for PostgreSQL
  * into a magic array so that it will be inserted unmodified into the SQL
  * string.  Use with care!
  *
  * @param string $field The value which has alread been quoted and escaped.
  * @return array An array with the value associated with a key of 'plain'
  */
  function Plain( $field )
  {
    // Abuse the array type to extend our ability to avoid \\ and ' replacement
    $rv = array( 'plain' => $field );
    return $rv;
  }

  /**
  * Execute the query, logging any debugging.
  *
  * <b>Example</b>
  * So that you can nicely enable/disable the queries for a particular class, you
  * could use some of PHPs magic constants in your call.
  * <code>
  * $qry->Exec(__CLASS__, __LINE__, __FILE__);
  * </code>
  *
  *
  * @param string $location The name of the location for enabling debugging or just
  *                         to help our children find the source of a problem.
  * @param int $line The line number where Exec was called
  * @param string $file The file where Exec was called
  * @return resource The actual result of the query (FWIW)
  */
  function Exec( $location = '', $line = 0, $file = '' )
   {
    global $dbconn, $debuggroups, $c;
    $this->location = trim($location);
    if ( $this->location == "" ) $this->location = substr($GLOBALS['PHP_SELF'],1);
    // $locn = sprintf( "%-12.12s", $this->location );
    $locn = $this->location;

    if ( isset($debuggroups['querystring']) ) {
      log_error( $this->location, 'query', $this->querystring, $line, $file );
    }

    $t1 = microtime(); // get start time
    $this->result = pg_exec( $dbconn, $this->querystring ); // execute the query
    $this->rows = pg_numrows($this->result); // number of rows returned
    $t2 = microtime(); // get end time
    $i_took = duration( $t1, $t2 );   // calculate difference
    $c->total_query_time += $i_took;
    $this->execution_time = sprintf( "%2.06lf", $i_took);

    if ( !$this->result ) // query failed
    {
      $this->errorstring = pg_errormessage(); // returns database error message
      log_error( $locn, 'QF', $this->querystring, $line, $file );
      log_error( $locn, 'QF', $this->errorstring, $line, $file );
    }
    elseif ( $this->execution_time > $this->query_time_warning ) // if execution time is too long
    {
      log_error( $locn, 'SQ', "Took: $this->execution_time for $this->querystring", $line, $file ); // SQ == Slow Query :-)
    }
    elseif ( isset($debuggroups[$this->location]) && $debuggroups[$this->location] ) // query successful
    {
      log_error( $locn, 'DBGQ', "Took: $this->execution_time for $this->querystring to find $this->rows rows.", $line, $file );
    }

    return $this->result;
  }


  /**
  * Fetch the next row from the query results
  * @param boolean $as_array True if thing to be returned is array
  * @return mixed query row
  */
  function Fetch($as_array = false)
  {
    global $debuggroups;

    if ( isset($debuggroups["$this->location"]) && $debuggroups["$this->location"] > 2 ) {
      log_error( $this->location, "Fetch", "$this->result Rows: $this->rows, Rownum: $this->rownum");
    }
    if ( ! $this->result ) return false; // no results
    if ( ($this->rownum + 1) >= $this->rows ) return false; // reached the end of results

    $this->rownum++;
    if ( isset($debuggroups["$this->location"]) && $debuggroups["$this->location"] > 1 ) {
      log_error( $this->location, "Fetch", "Fetching row $this->rownum" );
    }
    if ( $as_array )
    {
      $this->object = pg_fetch_array($this->result, $this->rownum);
    }
    else
    {
      $this->object = pg_fetch_object($this->result, $this->rownum);
    }

    return $this->object;
  }

  /**
  * Set row counter back one
  *
  * In the case that you may like to fetch the same row twice, for example
  * if your SQL returns some columns that are the same for each row, and you
  * want to display them cleanly before displaying the other data repeatedly
  * for each row.
  *
  * <b>Example</b>
  * <code>
  * $master_row = $qry->Fetch();
  * $qry->UnFetch();
  * do_something_first($master_row);
  * while( $row = $qry->Fetch() ) {
  *   do_something_repeatedly($row);
  * }
  * </code>
  */
  function UnFetch()
  {
    global $debuggroups;
    $this->rownum--;
    if ( $this->rownum < -1 ) $this->rownum = -1;
  }

  /**
  * Fetch backwards from the result resource
  * @param boolean $as_array True if thing to be returned is array (default: <b>False</b>
  * @return mixed query row
  */
  function FetchBackwards($as_array = false)
  {
    global $debuggroups;

    if ( isset($debuggroups["$this->location"]) && $debuggroups["$this->location"] > 2 ) {
      log_error( $this->location, "FetchBackwards", "$this->result Rows: $this->rows, Rownum: $this->rownum");
    }
    if ( ! $this->result ) return false;
    if ( ($this->rownum - 1) == -1 ) return false;
    if ( $this->rownum == -1 ) $this->rownum = $this->rows;

    $this->rownum--;

    if ( isset($debuggroups["$this->location"]) && $debuggroups["$this->location"] > 1 ) {
      log_error( $this->location, "Fetch", "Fetching row $this->rownum" );
    }
    if ( $as_array )
    {
      $this->object = pg_fetch_array($this->result, $this->rownum);
    }
    else
    {
      $this->object = pg_fetch_object($this->result, $this->rownum);
    }

    return $this->object;
  }

  /**
  * Build an option list from the query.
  * @param string $current Default selection of dro down box (optional)
  * @param string $location for debugging purposes
  * @return string Select box HTML
  */
  function BuildOptionList( $current = '', $location = 'options' ) {
    global $debuggroups;
    $result = '';

    // The query may already have been executed
    if ( $this->rows > 0 || $this->Exec($location) ) {
      $this->rownum = -1;
      while( $row = $this->Fetch(true) )
      {
        if (is_array($current)) {
          $selected = ( ( in_array($row[0],$current,true) || in_array($row[1],$current,true)) ? ' selected="selected"' : '' );
        }
        else {
          $selected = ( ( "$row[0]" == "$current" || "$row[1]" == "$current" ) ? ' selected="selected"' : '' );
        }
        $nextrow = "<option value=\"".htmlentities($row[0])."\"$selected>".htmlentities($row[1])."</option>";
        $result .= $nextrow;
      }
    }
    return $result;
   }

}
///////////////////////////////////////////////////////////////////////////
//   E N D   O F   C L A S S   F O R   D A T A B A S E   Q U E R I E S   //
///////////////////////////////////////////////////////////////////////////

?>