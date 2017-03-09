<?php
//Example : <moodle>/block/bath_samis_grades_transfer/web-service.php?method=GetSamisAssessmentDetailsForCourse&samis_code=ED00000
//mod_code
//mav_occur
//ayr_code
//psl_code
$method = $_GET['method'];
//$samis_code = $_GET['samis_code'];

switch($method){
    case 'GetSamisAssessmentDetailsForCourse_OLD':
         $data =  array('assessments'=> array('assessment'=>array(
            'module'=>'ED00000',
            'occurrence'=> 'A',
            'year'=>'2016/7',
            'period'=>'AY',
            'assess_item'=>'01',
             'student' => '029005235/1'
        )));
        echo json_output($data);
        break;
    case 'GetSamisAssessmentDetailsForCourse':
        $data =  array('assessments'=> array('assessment'=>array(
            'module'=>'ED00000',
            'occurrence'=> 'A',
            'year'=>'2016/7',
            'period'=>'AY',
            'map_code'=>'ED00000A',
            'mab_seq'=>'01',
            'mab_name'=> 'CourseWork (CW1) '

        ),
            array(
                'module'=>'ED00000',
                'occurrence'=> 'A',
                'year'=>'2016/7',
                'period'=>'AY',
                'map_code'=>'ED00000A',
                'mab_seq'=>'02',
                'mab_name'=> 'CourseWork (CW2) '
            )));
        echo json_output($data);
        break;
}
function json_output($data){
    header('Content-type: application/json');
    return json_encode($data);
}
function xml_output($data){
    header('Content-type: text/xml');
    $xml_user_info = new SimpleXMLElement("<?xml version=\"1.0\"?><records></records>");
    //function call to convert array to xml
    array_to_xml($data,$xml_user_info);
    //saving generated xml file
    $xml_file = $xml_user_info->asXML('users.xml');
}

//function defination to convert array to xml
function array_to_xml($array, &$xml_user_info) {
    foreach($array as $key => $value) {
        if(is_array($value)) {
            if(!is_numeric($key)){
                $subnode = $xml_user_info->addChild("$key");
                array_to_xml($value, $subnode);
            }else{
                $subnode = $xml_user_info->addChild("item$key");
                array_to_xml($value, $subnode);
            }
        }else {
            $xml_user_info->addChild("$key",htmlspecialchars("$value"));
        }
    }
}

//creating object of SimpleXMLElement


