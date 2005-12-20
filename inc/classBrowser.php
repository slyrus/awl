<?php
/////////////////////////////////////////////////////////////
//   C L A S S   F O R   A P M S   B R O W S E   A R E A S //
/////////////////////////////////////////////////////////////

/////////////////////////////////////////////////////////////
// First we need a class for the columns in the browser
/////////////////////////////////////////////////////////////
class BrowserColumn
{
  var $Field;
  var $Header;
  var $Format;
  var $Sql;
  var $Align;
  var $Class;

  function BrowserColumn( $field, $header="", $align="", $format="", $sql="", $class="" ) {
    $this->Field  = $field;
    $this->Sql    = $sql;
    $this->Header = $header;
    $this->Format = $format;
    $this->Class  = $class;
    $this->Align  = $align;
  }

  function GetTarget() {
    if ( $this->Sql == "" ) return $this->Field;
    return "$this->Sql AS $this->Field";
  }

  function RenderHeader( $order_field, $order_direction, $browser_array_key=0 ) {
    global $c;
    if ( $this->Align == "" ) $this->Align = "left";
    $html = '<th class="'.$this->Align.'" '. ($this->Class == "" ? "" : "class=\"$this->Class\"") . '>';

    $direction = 'A';
    $image = "";
    if ( $order_field == $this->Field ) {
      if ( strtoupper( substr( $order_direction, 0, 1) ) == 'A' ) {
        $image = 'down';
        $direction = 'D';
      }
      else {
        $image = 'up';
      }
      $image = "<img class=\"order\" src=\"$c->images/$image.gif\">";
    }
    $html .= '<a href="'.replace_uri_params( $_SERVER['REQUEST_URI'], array( "o[$browser_array_key]" => $this->Field, "d[$browser_array_key]" => $direction ) ).'" class="order">';
    $html .= ($this->Header == "" ? $this->Field : $this->Header);
    $html .= "$image</a></th>\n";
    return $html;
  }

  function RenderValue( $value, $extraclass="" ) {
    $value = str_replace( "\n", "<br />", $value );
    if ( substr(strtolower($this->Format),0,3) == "<td" ) {
      $html = sprintf($this->Format,$value);
    }
    else {
      $html = '<td class="'.$this->Align.'" ';
      $html .= ($this->Class == "" ? "" : "class=\"$this->Class\"");
      $html .= ($extraclass == "" ? "" : "class=\"$extraclass\"");
      $html .= '>';
      $html .= ($this->Format == "" ? $value : sprintf($this->Format,$value));
      $html .= "</td>\n";
    }
    return $html;
  }
}


/////////////////////////////////////////////////////////////
// Now the actual Browser class...
/////////////////////////////////////////////////////////////
class Browser
{
  var $Title;
  var $Columns;
  var $HiddenColumns;
  var $Joins;
  var $Where;
  var $Order;
  var $OrderField;
  var $OrderDirection;
  var $OrderBrowserKey;
  var $Limit;
  var $Query;
  var $BeginRow;
  var $CloseRow;
  var $BeginRowArgs;
  var $Totals;
  var $TotalFuncs;

  function Browser( $title = "" ) {
    global $c, $session;
    $this->Title = $title;
    $this->Order = "";
    $this->Limit = "";
    $this->BeginRow = "<tr class=\"row%d\">\n";
    $this->CloseRow = "</tr>\n";
    $this->BeginRowArgs = array('#even');
    $this->Totals = array();
    $session->Log("DBG: New browser called $title");
  }

  function AddColumn( $field, $header="", $align="", $format="", $sql="", $class="" ) {
    $this->Columns[] = new BrowserColumn( $field, $header, $align, $format, $sql, $class );
  }

  function AddHidden( $field, $sql="" ) {
    $this->HiddenColumns[] = new BrowserColumn( $field, "", "", "", $sql );
  }

  function SetTitle( $new_title ) {
    $this->Title = $new_title;
  }

  function SetJoins( $join_list ) {
    $this->Joins = $join_list;
  }

  function SetWhere( $where_clause ) {
    $this->Where = $where_clause;
  }

  function MoreWhere( $operator, $more_where ) {
    if ( $this->Where == "" ) {
      $this->Where = $more_where;
      return;
    }
    $this->Where = "$this->Where $operator $more_where";
  }

  function AndWhere( $more_where ) {
    $this->MoreWhere("AND",$more_where);
  }

  function OrWhere( $more_where ) {
    $this->MoreWhere("OR",$more_where);
  }

  function AddOrder( $field, $direction, $browser_array_key=0 ) {
    if ( isset( $_GET['o'][$browser_array_key]) && isset($_GET['d'][$browser_array_key]) ) {
      $field = $_GET['o'][$browser_array_key];
      $direction = $_GET['d'][$browser_array_key];
    }
    if ( $this->Order == "" )
      $this->Order = "ORDER BY ";
    else
      $this->Order .= ", ";

    $this->OrderField = clean_component_name($field);
    $this->OrderBrowserKey = $browser_array_key;
    $this->Order .= $this->OrderField;

    if ( preg_match( '/^A/i', $direction) ) {
      $this->Order .= " ASC";
      $this->OrderDirection = 'A';
    }
    else {
      $this->Order .= " DESC";
      $this->OrderDirection = 'D';
    }
  }

  function AddTotal( $column_name, $total_function = false ) {
    $this->Totals[$column_name] = 0;
    if ( $total_function != false ) {
      $this->TotalFuncs[$column_name] = $total_function;
    }
  }


  function RowFormat( $whatever )
  {
    $argc = func_num_args();
    $this->BeginRow = func_get_arg(0);
    $this->CloseRow = func_get_arg(1);

    $this->BeginRowArgs = array();
    for( $i=2; $i < $argc; $i++ ) {
      $this->BeginRowArgs[] = func_get_arg($i);
    }
  }


  function DoQuery() {
    $target_fields = "";
    foreach( $this->Columns AS $k => $column ) {
      if ( $target_fields != "" ) $target_fields .= ", ";
      $target_fields .= $column->GetTarget();
    }
    if ( isset($this->HiddenColumns) ) {
      foreach( $this->HiddenColumns AS $k => $column ) {
        if ( $target_fields != "" ) $target_fields .= ", ";
        $target_fields .= $column->GetTarget();
      }
    }
    $where_clause = ((isset($this->Where) && $this->Where != "") ? "WHERE $this->Where" : "" );
    $sql = sprintf( "SELECT %s FROM %s %s %s %s", $target_fields, $this->Joins, $where_clause, $this->Order, $this->Limit);
    $this->Query = new PgQuery( $sql );
    return $this->Query->Exec("Browse:$this->Title:DoQuery");
  }


  function Render( $title_tag = 'h1' ) {
    global $c, $session;

    if ( !isset($this->Query) ) $this->DoQuery();  // Ensure the query gets run before we render!

    $session->Log("DBG: Rendering browser $this->Title");
    $html = '<div id="browser">';
    if ( $this->Title != "" ) {
      $html = "<$title_tag>$this->Title</$title_tag>\n";
    }
    $html .= "<table>\n";
    $html .= "<tr class=\"header\">\n";
    foreach( $this->Columns AS $k => $column ) {
      $html .= $column->RenderHeader( $this->OrderField, $this->OrderDirection, $this->OrderBrowserKey );
    }
    $html .= "</tr>\n";

    while( $row = $this->Query->Fetch() ) {
      foreach( $this->BeginRowArgs AS $k => $fld ) {
        $rowanswers[$k] = $row->{$fld};
        if ( !isset( $rowanswers[$k] ) ) {
          switch( $fld ) {
            case '#even':
              $rowanswers[$k] = ($this->Query->rownum % 2);
              break;
            default:
              $rowanswers[$k] = "";
          }
        }
      }
      $html .= vsprintf( $this->BeginRow, $rowanswers);
      foreach( $this->Columns AS $k => $column ) {
        $html .= $column->RenderValue($row->{$column->Field});
        if ( isset($this->Totals[$column->Field]) ) {
//          $session->Log("DBG: Rendering total %s through function %s", $column->Field, $this->TotalFuncs[$column->Field] );
          if ( isset($this->TotalFuncs[$column->Field]) && function_exists($this->TotalFuncs[$column->Field]) ) {
            // Run the amount through the callback function  $floatval = my_function( $row, $fieldval );
            $this->Totals[$column->Field] += $this->TotalFuncs[$column->Field]( $row, $row->{$column->Field} );
          }
          else {
            // Just add the amount
            $this->Totals[$column->Field] += $row->{$column->Field};
          }
        }
      }
      $html .= $this->CloseRow;
    }

    if ( count($this->Totals) > 0 ) {
      $html .= "<tr class=\"totals\">\n";
      foreach( $this->Columns AS $k => $column ) {
        if ( isset($this->Totals[$column->Field]) ) {
          $html .= $column->RenderValue( $this->Totals[$column->Field], "totals" );
        }
        else {
          $html .= $column->RenderValue( "" );
        }
      }
      $html .= "</tr>\n";
    }

    $html .= "</table>\n";
    $html .= '</div>';
    return $html;
  }

}
?>