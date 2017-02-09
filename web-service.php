<?php
//Example : <moodle>/block/bath_samis_grades_transfer/web-service.php?method=GetSamisAssessmentDetailsForCourse&samis_code=ED00000
//mod_code
//mav_occur
//ayr_code
//psl_code
$method = $_GET['method'];
$samis_code = $_GET['samis_code'];

switch($method){
    case 'GetSamisAssessmentDetailsForCourse':
         $data = array('MAV_ENTRY'=>array(
            'MODULE_CODE'=>'ED00000',
            'OCC'=> 'A',
            'YEAR'=>'2016/7',
            'PERIOD'=>'AY',
            'ASS_PATTERN'=>'SP00000A'
        ));
        echo json_output($data);
        break;
}
function json_output($data){
    header('Content-type: application/json');
    return json_encode($data);
}
function xml_output(){
    header('Content-type: text/xml');
}