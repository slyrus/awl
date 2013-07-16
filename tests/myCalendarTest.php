<?php
/**
 * Created by JetBrains PhpStorm.
 * User: milan
 * Date: 7/12/13
 * Time: 8:57 AM
 * To change this template use File | Settings | File Templates.
 */

require_once('inc/vCalendar.php');

class myCalendarTest extends PHPUnit_Framework_TestCase {

    function getData($filename){
        $file = fopen($filename, 'r');
        $data = fread($file, filesize($filename));
        fclose($file);
        return $data;
    }

    function test1(){


        $mycalendar = new vCalendar($this->getData('tests/data/0000-Setup-PUT-collection.test'));
        $test = $mycalendar->Render();

        $timezones = $mycalendar->GetComponents('VTIMEZONE',true);
        $components = $mycalendar->GetComponents('VTIMEZONE',false);


        $resources = array();
        foreach($components as $comp){

            $uid = $comp->GetPValue('UID');
            $resources[$uid][] = $comp;


        }

        foreach($resources as $key => $res){
            $testcal = new vCalendar();
            $testcal->SetComponents($res);
            $t = $testcal->Render();
            $t = $testcal->Render();
        }

        $mycalendar->Render();
    }

}
