<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

//Example : <moodle>/block/bath_samis_grades_transfer/web-service.php?method=GetSamisAssessmentDetailsForCourse&samis_code=ED00000
//mod_code
//mav_occur
//ayr_code
//psl_code
$method = $_GET['method'];
//$samis_code = $_GET['samis_code'];
switch ($method) {
    case 'GetSamisAssessmentDetailsForCourse':
        $data = <<<XML
<?xml version="1.0" encoding="UTF-8" ?>
<records>
    <assessments>
        <assessment>
            <module>CH40236</module>
            <occurrence>A</occurrence>
            <year>2016/7</year>
            <period>AY</period>
            <map_code>CH40236A</map_code>
            <mab_seq>01</mab_seq>
            <mab_name>CH40236 Exam</mab_name>
        </assessment>
    </assessments>
</records>
XML;
        break;
    case 'GetSamisGradesStructure':
         $data = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<records>
   <assessments>
          
      <assessment>
                 
         <module>CH40236</module>
                 
         <occurrence>A</occurrence>
                 
         <year>2016/7</year>
                 
         <period>AY</period>
                 
         <assess_item>01</assess_item>
                 
         <student>029005235/1</student>
                 
         <mark>33</mark>
             
      </assessment>
          
      <assessment>
                 
         <module>CH40236</module>
                 
         <occurrence>A</occurrence>
                 
         <year>2016/7</year>
                 
         <period>AY</period>
                 
         <assess_item>01</assess_item>
                 
         <student>029005237/1</student>
                 
         <mark>44</mark>
             
      </assessment>
          
      <assessment>
                 
         <module>CH40236</module>
                 
         <occurrence>A</occurrence>
                 
         <year>2016/7</year>
                 
         <period>AY</period>
                 
         <assess_item>01</assess_item>
                 
         <student>029125235/1</student>
                 
         <mark>80</mark>
             
      </assessment>
   </assessments>
</records>
XML;
        break;
    case 'GetSPRFromBUCSID':
        $data = <<<XML
<?xml version="1.0" encoding="UTF-8" ?>
<records>
    <spr_code>029005235/1</spr_code>
</records>
XML;
        break;
}
echo $data;
function json_output($data) {
    header('Content-type: application/json');
    return json_encode($data);
}

