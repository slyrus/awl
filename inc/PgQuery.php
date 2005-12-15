<?php
/**
* PostGreSQL query class and associated functions
*
* (long description)
*
* @package   pgquery
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Andrew McMillan
* @license   http://gnu.org/copyleft/gpl.html GNU GPL
*/

///////////////////////
//   Connect to DB   //
///////////////////////
if ( !isset($dbconn) ) {
  die( 'Database is not connected!' );
}


/**
* Duration of times entered - to see how long a query has taken
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
* Quote the given string so it can be safely used within string delimiters
* in a query.
* Andrew <- "Why are these outside the class?"
* @param mixed $str Data to be quoted
* @return mixed "NULL" string, quoted string or original data
*/
function qpg($str = null)
{
  switch (strtolower(gettype($str))) {
    case 'null':
      return 'NULL';
    case 'integer':
    case 'double' :
      return $str;
    case 'boolean':
      return $str ? 'TRUE' : 'FALSE';
    case 'string':
    default:
      $str = str_replace("'", "''", $str);
      //PostgreSQL treats a backslash as an escape character.
      $str = str_replace('\\', '\\\\', $str);
      return "'$str'";
  }
}

/**
* Log error with file and line location
* @param mixed $str Data to be quoted
* @return empty string
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

  return '';
}

/**
* Replaces PostGreSQL query with
* escaped parameters in preparation for execution.
* Andrew <- "Why are these outside the class?"
* @return built query string
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


/////////////////////////////////////////////////////////////
//   C L A S S   F O R   D A T A B A S E   Q U E R I E S   //
/////////////////////////////////////////////////////////////
/**
 * The PgQuery Class.
 *
 * This class builds and executes PostGreSQL Queries
 *
 * <b>usage</b>
 * <code>
 * $qry = new PgQuery($sql,$param1,$param2,...);
 * $qry->Exec();
 * while($row = $qry->Fetch()) {
 * // do stuff with $row
 * }
 * </code>
 *
 * @package   pgquery
 * @author    Andrew McMillan <andrew@catalyst.net.nz>
 * @copyright Andrew McMillan
 */
class PgQuery
{
  /**#@+
  * @access private
  */
  /**
  * stores a query string
  * @var string
  */
  var $querystring;

  /**
  * stores a resource result
  * @var resource
  */
  var $result;

  /**
  * number of rows from pg_numrows - for fetching result
  * @var int
  */
  var $rows;

  /**
  * number of current row
  * @var int
  */
  var $rownum = -1;

  /**
  * stores the query execution time - used to deal with long queries
  * @var string
  */
  var $execution_time;

  /**
  * how long the query should take before a warning is issued
  * @var double
  */
  var $query_time_warning = 0.3;

  /**
  * Where we called this query from so we can find it in our code!
  * @var string
  */
  var $location;

  /**
  * the object of rows
  * @var object
  */
  var $object;

  /**
  * The error message, if it fails
  * @var string
  */
  var $errorstring;
  /**#@-*/


 /**
  * constructor
  */
  function PgQuery()
  {
    $this->result = 0;
    $this->rows = 0;
    $this->execution_time = 0;
    $this->rownum = -1;

    $argc = func_num_args();
//    $qry = func_get_arg(0);

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
  * @param mixed $str Data to be quoted
  * @return mixed "NULL" string, quoted string or original data
  */
  function quote($str = null)
  {
    return qpg($str);
  }

  /**
  * Ask Andrew for further information
  */
  function Plain( $field )
  {
    // Abuse the array type to extend our ability to avoid \\ and ' replacement
    return array( 'plain' => $field );
  }

  /**
  * Execute the query, log any debugging
  * @param string $location
  * @param int $line line number where exec was called
  * @param string $file file where exec was called
  * @return object result of query
  */
  function Exec( $location = '', $line = 0, $file = '' )
   {
    global $dbconn, $debuggroups;
    $this->location = trim($location);
    if ( $this->location == "" ) $this->location = substr($GLOBALS['PHP_SELF'],1);

    if ( isset($debuggroups['querystring']) )
    {
      log_error( $this->location, 'query', $this->querystring, $line, $file );
    }

    $t1 = microtime(); // get start time
    $this->result = pg_exec( $dbconn, $this->querystring ); // execute the query
    $this->rows = pg_numrows($this->result); // number of rows returned
    $t2 = microtime(); // get end time
    $this->execution_time = sprintf( "%2.06lf", duration( $t1, $t2 )); // calculate difference
    $locn = sprintf( "%-12.12s", $this->location );

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
      log_error( $locn, 'DBGQ', "Took: $this->execution_time for $this->querystring", $line, $file );
    }

     return $this->result;
  }

  /**
  * Fetch an object from the result resource
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
  * In the case that you may like to fetch the same row twice
  */
  function UnFetch()
  {
    global $debuggroups;
    $this->rownum--;
    if ( $this->rownum < -1 ) $this->rownum = -1;
  }

  /**
  * Fetch backwards from the result resource
  * @param boolean $as_array True if thing to be returned is array
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
  function BuildOptionList( $current = '', $location = 'options' )
  {
    $result = '';

    // The query may already have been executed
    if ( $this->rows > 0 || $this->Exec($location) ) {
      $this->rownum = -1;
      while( $row = $this->Fetch(true) )
      {
        $selected = ( ( $row[0] == $current || $row[1] == $current ) ? ' selected="selected"' : '' );
        $nextrow = "<option value=\"$row[0]\"$selected>".htmlentities($row[1])."</option>";
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
