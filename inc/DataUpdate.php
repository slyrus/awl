<?php
/**
* Some functions and a base class to help with updating records.
*
* This subpackage provides some functions that are useful around single
* record database activities such as insert and update.
*
* @package   awl
* @subpackage   DataUpdate
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Andrew McMillan
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

/**
* Get the names of the fields for a particular table
* @param string $tablename The name of the table.
* @return array of string The public fields in the table.
*/
function get_fields( $tablename ) {
  global $sysname;
  $sql = "SELECT f.attname, t.typname FROM pg_attribute f ";
  $sql .= "JOIN pg_class c ON ( f.attrelid = c.oid ) ";
  $sql .= "JOIN pg_type t ON ( f.atttypid = t.oid ) ";
  $sql .= "WHERE relname = ? AND attnum >= 0 order by f.attnum;";
  $qry = new PgQuery( $sql, $tablename );
  $qry->Exec("DataUpdate");
  $fields = array();
  while( $row = $qry->Fetch() ) {
    $fields["$row->attname"] = $row->typname;
    error_log( "$sysname DBG: " . $fields["$row->attname"] . " => " . $row->typname, 0);
  }
  return $fields;
}


/**
* Build SQL INSERT/UPDATE statement from an associative array of fieldnames => values.
* @param array $assoc The associative array of fieldnames => values.
* @param string $type The word "update" or something else (which implies "insert").
* @param string $tablename The name of the table being updated.
* @param string $where What the "WHERE ..." clause needs to be for an UPDATE statement.
* @param string $fprefix An optional string which all fieldnames in $assoc are prefixed with.
* @return string An SQL Update or Insert statement with all fields/values from the array.
*/
function sql_from_associative( $assoc, $type, $tablename, $where, $fprefix = "" ) {
  global $sysname;
  $fields = get_fields($tablename);
  $update = strtolower($type) == "update";
  if ( $update )
    $sql = "UPDATE $tablename SET ";
  else
    $sql = "INSERT INTO $tablename (";

  $flst = "";
  $vlst = "";
  foreach( $fields as $fn => $typ ) {
    $fn = $fprefix . $fn;
    error_log( "$sysname: SFA: DBG: $fn => $typ (".$assoc[$fn].")", 0);
    if ( !isset($assoc[$fn]) && isset($assoc["xxxx$fn"]) ) {
      // Sometimes we will have prepended 'xxxx' to the field name so that the field
      // name differs from the column name in the database.
      $assoc[$fn] = $assoc["xxxx$fn"];
    }
    if ( !isset($assoc[$fn]) ) continue;
    $value = str_replace( "'", "''", str_replace("\\", "\\\\", $assoc[$fn]));
    if ( $fn == "password" ) {
      if ( $value == "******" || $value == "" ) continue;
      if ( !preg_match('/\*[0-9a-z]+\*[0-9a-z]+/', $value ) ) $value = md5($value);
    }
    if ( eregi("(time|date)", $typ ) && $value == "" ) {
      $value = "NULL";
    }
    else if ( eregi("bool", $typ) )  {
      $value = ( $value == "f" ? "FALSE" : "TRUE" );
    }
    else if ( eregi("int", $typ) )  {
      $value = intval( $value );
    }
    else if ( eregi("(text|varchar)", $typ) )  {
      $value = "'$value'";
    }
    else
      $value = "'$value'::$typ";

    if ( $update )
      $flst .= ", $fn = $value";
    else {
      $flst .= ", $fn";
      $vlst .= ", $value";
    }
  }
  $flst = substr($flst,2);
  $vlst = substr($vlst,2);
  $sql .= $flst;
  if ( $update ) {
    $sql .= " $where; ";
  }
  else {
    $sql .= ") VALUES( $vlst ); ";
  }
 return $sql;
}


/**
* Build SQL INSERT/UPDATE statement from the $_POST associative array
* @param string $type The word "update" or something else (which implies "insert").
* @param string $tablename The name of the table being updated.
* @param string $where What the "WHERE ..." clause needs to be for an UPDATE statement.
* @param string $fprefix An optional string which all fieldnames in $assoc are prefixed with.
* @return string An SQL Update or Insert statement with all fields/values from the array.
*/
function sql_from_post( $type, $tablename, $where, $fprefix = "" ) {
  global $sysname;
  $fields = get_fields($tablename);
  $update = strtolower($type) == "update";
  if ( $update )
    $sql = "UPDATE $tablename SET ";
  else
    $sql = "INSERT INTO $tablename (";

  $flst = "";
  $vlst = "";
  foreach( $fields as $fn => $typ ) {
    $fn = $fprefix . $fn;
    error_log( "$sysname: _POST: DBG: $fn => $typ (".$_POST[$fn].")", 0);
    if ( !isset($_POST[$fn]) && isset($_POST["xxxx$fn"]) ) {
      // Sometimes we will have prepended 'xxxx' to the field name so that the field
      // name differs from the column name in the database.
      $_POST[$fn] = $_POST["xxxx$fn"];
    }
    if ( !isset($_POST[$fn]) ) continue;
    $value = str_replace( "'", "''", str_replace("\\", "\\\\", $_POST[$fn]));
    if ( $fn == "password" ) {
      if ( $value == "******" || $value == "" ) continue;
      if ( !preg_match('/\*[0-9a-z]+\*[0-9a-z]+/', $value ) ) $value = md5($value);
    }
    if ( eregi("(time|date)", $typ ) && $value == "" ) {
      $value = "NULL";
    }
    else if ( eregi("bool", $typ) )  {
      $value = ( $value == "f" ? "FALSE" : "TRUE" );
    }
    else if ( eregi("int", $typ) )  {
      $value = intval( $value );
    }
    else if ( eregi("(text|varchar)", $typ) )  {
      $value = "'$value'";
    }
    else
      $value = "'$value'::$typ";

    if ( $update )
      $flst .= ", $fn = $value";
    else {
      $flst .= ", $fn";
      $vlst .= ", $value";
    }
  }
  $flst = substr($flst,2);
  $vlst = substr($vlst,2);
  $sql .= $flst;
  if ( $update ) {
    $sql .= " $where; ";
  }
  else {
    $sql .= ") VALUES( $vlst ); ";
  }
 return $sql;
}


/**
* Since we are going to actually read/write from the database.
*/
require_once("PgQuery.php");

/**
* A Base class to use for records which will be read/written from the database.
* @package   awl
*/
class DBRecord
{
  /**#@+
  * @access private
  */
  /**
  * The database table that this record goes in
  * @var string
  */
  var $Table;

  /**
  * The field names for the record
  * @var array
  */
  var $Fields;

  /**
  * The keys for the record as an array of key => value pairs
  * @var array
  */
  var $Keys;

  /**
  * The field values for the record
  * @var object
  */
  var $Values;

  /**
  * The type of database write we will want: either "update" or "insert"
  * @var object
  */
  var $WriteType;

  /**#@-*/

  /**
  * This will read the record from the database if it's available, and
  * the $keys parameter is a non-empty array.
  * @param string $table The name of the database table
  * @param array $keys An associative array containing fieldname => value pairs for the record key.
  */
  function Initialise( $table, $keys = array() ) {
    $this->Table = $table;
    $this->Fields = get_fields($this->Table);
    $this->Keys = $keys;
    $this->Read();
  }

  /**
  * This will assign $_POST values to the internal Values object for each
  * field that exists in the Fields array.
  */
  function PostToValues( $prefix = "" ) {
    foreach ( $this->Fields AS $fname ) {
      if ( isset($_POST["$prefix$fname"]) ) {
        $this->Values->{$fname} = $_POST["$prefix$fname"];
      }
    }
  }

  /**
  * Sets a single field in the record
  * @param boolean $overwrite_values Controls whether the data values for the key fields will be forced to match the key values
  * @return string A simple SQL where clause, including the initial "WHERE", for each key / value.
  */
  function _BuildWhereClause($overwrite_values=false) {
    $where = "";
    foreach( $this->Keys AS $k => $v ) {
      // At least assign the key fields...
      if ( $overwrite_values ) $this->Values->{$k} = $v;
      // And build the WHERE clause
      $where .= ( $where == "" ? "WHERE " : " AND " );
      $where .= "$k = " . qpg($v);
    }
    return $where;
  }

  /**
  * Sets a single field in the record
  * @param string $fname The name of the field to set the value for
  * @param string $fval The value to set the field to
  * @return mixed The new value of the field (i.e. $fval).
  */
  function Set($fname, $fval) {
    $this->Values->{$fname} = $fval;
    return $fval;
  }

  /**
  * Returns a single field from the record
  * @param string $fname The name of the field to set the value for
  * @return mixed The current value of the field.
  */
  function Get($fname) {
    return $this->Values->{$fname};
  }

  /**
  * To write the record to the database
  * @return boolean Success.
  */
  function Write() {
    $sql = sql_from_associative( $this->Values, $this->WriteType, $this->Table, $this->_BuildWhereClause(), "" );
    $qry = new PgQuery($sql);
    return $qry->Exec( __CLASS__, __LINE__, __FILE__ );
  }

  /**
  * To read the record from the database.
  * If we don't have any keys then the record will be blank.
  * @return boolean Whether we actually read a record.
  */
  function Read() {
    $i_read_the_record = false;
    $this->Values = array();
    $this->Values = settype( $this->Values, "object" );
    if ( count($this->Keys) ) {
      $sql = "SELECT * FROM $this->Table " . $this->_BuildWhereClause(true);
      $qry = new PgQuery($sql);
      if ( $qry->Exec( __CLASS__, __LINE__, __FILE__ ) && $qry->rows > 0 ) {
        $i_read_the_record = true;
        $this->Values = $qry->Fetch();
      }
    }
    $this->WriteType = ( $i_read_the_record ? "update" : "insert" );
    return $i_read_the_record;
  }
}

?>