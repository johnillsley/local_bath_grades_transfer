<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 03/05/2017
 * Time: 15:55
 */
class local_bath_grades_transfer_assessment_grades
{
    /**
     * @var
     */
    public $student;
    public $name;
    /**
     * @var
     */
    public $assess_pattern;
    /**
     * @var
     */
    public $assess_item;
    /**
     * @var
     */
    public $attempt;
    /**
     * @var
     */
    public $grade; //TODO this might be removed in the future
    /**
     * @var
     */
    public $mark;
    /**
     * @var
     */
    public $year;
    /**
     * @var
     */
    public $period;
    /**
     * @var
     */
    public $module;

    /**
     * @return mixed
     */
    public function getYear() {
        return $this->year;
    }

    /**
     * @return mixed
     */
    public function getPeriod() {
        return $this->period;
    }

    /**
     * @return mixed
     */
    public function getModule() {
        return $this->module;
    }

    /**
     * @return mixed
     */
    public function getOccurrence() {
        return $this->occurrence;
    }
    /**
     * @var
     */
    public $occurrence;
    /**
     * @var
     */
    public static $samis_data;


    /**
     * local_bath_grades_transfer_assessment_grades constructor.
     * @internal param $student
     * @internal param local_bath_grades_transfer_samis_attributes $samis_attributes
     * @internal param $assess_pattern
     * @internal param $assess_item
     * @internal param $attempt
     * @internal param $mark
     * @internal param $grade
     */
    /*public function __construct($student,
                                \local_bath_grades_transfer_samis_attributes $samis_attributes,
                                $assess_pattern,
                                $assess_item,
                                $attempt,
                                $mark,
                                $grade) {
        $this->assess_item = $assess_item;
        $this->samis_attributes = $samis_attributes;
        $this->student = $student;
        $this->mark = $mark;
        $this->grade = $grade;
        $this->attempt = $attempt;
        $this->assess_pattern = $assess_pattern;
        $this->samis_data = new \local_bath_grades_transfer_external_data();
    }*/
    /**
     * local_bath_grades_transfer_assessment_grades constructor.
     */
    public function __construct() {
    }

    /**
     * @return mixed
     */
    public function getStudent() {
        return $this->student;
    }

    /**
     * @return mixed
     */
    public function getMark() {
        return $this->mark;
    }
    public function getName(){
        return $this->name;
    }

    /**
     * @param $mark
     */
    public function setMark($mark) {
        $this->mark = $mark;
    }

    /**
     * @return mixed
     */
    public function getAssessmentItem() {
        return $this->assess_item;
    }

    /**
     * @return mixed
     */
    public function getAssessPattern() {
        return $this->assess_pattern;
    }

    /**
     * @return mixed
     */
    public function getAssessItem() {
        return $this->assess_item;
    }

    /**
     * @return mixed
     */
    public function getAttempt() {
        return $this->attempt;
    }

    /**
     * @return mixed
     */
    public function getGrade() {
        return $this->grade;
    }

    /**
     * @param $lookup
     * @return array
     */
    public static function get_grade_strucuture_samis(\local_bath_grades_transfer_assessment_lookup $lookup) {
        //Check that it is a valid lookup
        echo "\n\n+++++++++GETTING GRADE STRUCUTURE FROM SAMIS +++++++++  \n\n";
        $structure = array();

        self::$samis_data = new \local_bath_grades_transfer_external_data();
            //From the attributes and map_code, get the grade structure.
            $remote_grade_structure = self::$samis_data->get_remote_grade_structure($lookup);
            if (!empty($remote_grade_structure)) {
                foreach ($remote_grade_structure->assessments as $assessment) {
                    foreach ($assessment as $objAssessmentData) {
                        $structure[(string)$objAssessmentData->student] = array('assessment'=>self::instantiate($objAssessmentData));
                    }
                }
            }

        return $structure;
    }

    /**
     * @param $record
     * @return local_bath_grades_transfer_assessment_grades
     */
    private static function instantiate($record) {
        $object = new self;
        foreach ($record as $key => $value) {
            if ($object->has_attribute($key)) {
                $object->$key = (string)$value;
            }
        }
        return $object;
    }

    /**
     * @param $attribute
     * @return bool
     */
    private function has_attribute($attribute) {
        $object_vars = get_object_vars($this);
        return array_key_exists($attribute, $object_vars);
    }


}