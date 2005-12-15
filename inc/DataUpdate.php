<?php
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


//////////////////////////////////////////////////////////////////////////////////////////////
//   C L A S S   F O R   R E C O R D S   T O   B E   W R I T T E N   T O   D A T A B A S E  //
//////////////////////////////////////////////////////////////////////////////////////////////
class DBRecord
{
  var $Fields;

  // Not sure what we need when we "new DBRecord" but bound to be something...
  function DBRecord() {
  }

  // Sets a single field in the record
  function Set($fname) {
  }

  // Returns a single field from the record
  function Get($fname) {
  }

  // To write the record to the database
  function Write() {
  }

  // To read the record from the database
  function Read() {
  }
}

?>