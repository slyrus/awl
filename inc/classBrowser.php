<?php
/**
* Table browser / lister class
*
* Browsers are constructed from BrowserColumns and can support sorting
* and other interactive behaviour.  Cells may contain data which is
* formatted as a link, or the entire row may be linked through an onclick
* action.
*
* @package   awl
* @subpackage   Browser
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Andrew McMillan
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

/**
* BrowserColumns are the basic building blocks.  You can specify just the
* field name, and the column header or you can get fancy and specify an
* alignment, format string, SQL formula and cell CSS class.
* @package   awl
*/
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


/**
* Start a new Browser, add columns, set a join and Render it to create a basic
* list of records in a table.
* You can, of course, get a lot fancier with setting ordering, where clauses
* totalled columns and so forth.
* @package   awl
*/
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

  /**
  * The Browser class constructor
  *
  * @param string $title A title for the browser (optional).
  */
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

  /**
  * Add a column to the Browser.
  *
  * This constructs a new BrowserColumn, appending it to the array of columns
  * in this Browser.
  *
  * Note that if the $format parameter starts with '<td>' the format will replace
  * the column format, otherwise it will be used within '<td>...</td>' tags.
  * @see BrowserColumn
  *
  * @param string $field The name of the field.
  * @param string $header A column header for the field.
  * @param string $align An alignment for column values.
  * @param string $format A sprintf format for displaying column values.
  * @param string $sql An SQL fragment for calculating the value.
  * @param string $class A CSS class to apply to the cells of this column.
  */
  function AddColumn( $field, $header="", $align="", $format="", $sql="", $class="" ) {
    $this->Columns[] = new BrowserColumn( $field, $header, $align, $format, $sql, $class );
  }

  /**
  * Add a hidden column - one that is present in the SQL result, but for
  * which there is no column displayed.
  *
  * This can be useful for including a value in (e.g.) clickable links or title
  * attributes which is not actually displayed as a visible column.
  *
  * @param string $field The name of the field.
  * @param string $sql An SQL fragment to calculate the field, if it is calculated.
  */
  function AddHidden( $field, $sql="" ) {
    $this->HiddenColumns[] = new BrowserColumn( $field, "", "", "", $sql );
  }

  /**
  * Set the Title for the browse.
  *
  * This can also be set in the constructor but if you create a template Browser
  * and then clone it in a loop you may want to assign a different Title for each
  * instance.
  *
  * @param string $more_where The extra part of the where clause
  */
  function SetTitle( $new_title ) {
    $this->Title = $new_title;
  }

  /**
  * Set the tables and joins for the SQL.
  *
  * For a single table this should just contain the name of that table, but for
  * multiple tables it should be the full content of the SQL 'FROM ...' clause
  * (excluding the actual 'FROM' keyword).
  *
  * @param string $join_list
  */
  function SetJoins( $join_list ) {
    $this->Joins = $join_list;
  }

  /**
  * Set the SQL Where clause to a specific value.
  *
  * The WHERE keyword should not be included.
  *
  * @param string $where_clause A valide SQL WHERE ... clause.
  */
  function SetWhere( $where_clause ) {
    $this->Where = $where_clause;
  }

  /**
  * Add an [operator] ... to the SQL Where clause
  *
  * You will generally want to call OrWhere or AndWhere rather than
  * this function, but hey: who am I to tell you how to code!
  *
  * @param string $operator The operator to combine with previous where clause parts.
  * @param string $more_where The extra part of the where clause
  */
  function MoreWhere( $operator, $more_where ) {
    if ( $this->Where == "" ) {
      $this->Where = $more_where;
      return;
    }
    $this->Where = "$this->Where $operator $more_where";
  }

  /**
  * Add an OR ...  to the SQL Where clause
  *
  * @param string $more_where The extra part of the where clause
  */
  function AndWhere( $more_where ) {
    $this->MoreWhere("AND",$more_where);
  }

  /**
  * Add an OR ... to the SQL Where clause
  *
  * @param string $more_where The extra part of the where clause
  */
  function OrWhere( $more_where ) {
    $this->MoreWhere("OR",$more_where);
  }

  /**
  * Add an ordering to the browser widget.
  *
  * The ordering can be overridden by GET parameters which will be
  * rendered into the column headers so that a user can click on
  * the column headers to control the actual order.
  *
  * @param string $field The name of the field to be ordered by.
  * @param string $direction A for Ascending, otherwise it will be descending order.
  * @param string $browser_array_key Use this to distinguish between multiple
  *               browser widgets on the same page.  Leave it empty if you only
  *               have a single browser instance.
  */
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

  /**
  * Mark a column as something to be totalled.  You can also specify the name of
  * a function which may modify the value before the actual totalling.
  *
  * The callback function will be called with each row, with the first argument
  * being the entire record object and the second argument being only the column
  * being totalled.  The callback should return a number, to be added to the total.
  *
  * @param string $column_name The name of the column to be totalled.
  * @param string $total_function The name of the callback function.
  */
  function AddTotal( $column_name, $total_function = false ) {
    $this->Totals[$column_name] = 0;
    if ( $total_function != false ) {
      $this->TotalFuncs[$column_name] = $total_function;
    }
  }


  /**
  * Set the format for an output row.
  *
  * The row format is set as an sprintf format string for the start of the row,
  * and a plain text string for the close of the row.  Subsequent arguments
  * are interpreted as names of fields, the values of which will be sprintf'd
  * into the beginrow string for each row.
  *
  * Some special field names exist beginning with the '#' character which have
  * 'magic' functionality, including '#even' which will insert '0' for even
  * rows and '1' for odd rows, allowing a nice colour alternation if the
  * beginrow format refers to it like: 'class="r%d"' so that even rows will
  * become 'class="r0"' and odd rows will be 'class="r1"'.
  *
  * At present only '#even' exists, although other magic values may be defined
  * in future.
  *
  * @param string $beginrow The new printf format for the start of the row.
  * @param string $closerow The new string for the close of the row.
  * @param string $rowargs ... The row arguments which will be sprintf'd into
  * the $beginrow format for each row
  */
  function RowFormat( $beginrow, $closerow, $rowargs )
  {
    $argc = func_num_args();
    $this->BeginRow = func_get_arg(0);
    $this->CloseRow = func_get_arg(1);

    $this->BeginRowArgs = array();
    for( $i=2; $i < $argc; $i++ ) {
      $this->BeginRowArgs[] = func_get_arg($i);
    }
  }


  /**
  * This method is used to build and execute the database query.
  *
  * You need not call this method, since Browser::Render() will call it for
  * you if you have not done so at that point.
  *
  * @return boolean The success / fail status of the PgQuery::Exec()
  */
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


  /**
  * This method is used to render the browser as HTML.  If the query has
  * not yet been executed then this will call DoQuery to do so.
  *
  * The browser (including the title) will be displayed in a div with id="browser" so
  * that you can style '#browser tr.header', '#browser tr.totals' and so forth.
  *
  * @param string $title_tag The tag to use around the browser title (default 'h1')
  * @return string The rendered HTML fragment to display to the user.
  */
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