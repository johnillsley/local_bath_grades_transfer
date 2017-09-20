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
    public $grade;
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
    public $mappingid;

    /**
     * @return mixed
     */
    public function getyear() {
        return $this->year;
    }

    /**
     * @return mixed
     */
    public function getperiod() {
        return $this->period;
    }

    /**
     * @return mixed
     */
    public function getmodule() {
        return $this->module;
    }

    /**
     * @return mixed
     */
    public function getoccurrence() {
        return $this->occurrence;
    }

    /**
     * @var
     */
    public $occurrence;
    /**
     * @var
     */
    public static $samisdata;


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

    public function getName() {
        return $this->name;
    }

    /**
     * Set a mark for the grade structure
     * @param $mark
     */
    public function setmark($mark) {
        $this->mark = $mark;
    }

    /**
     *
     * @return mixed
     */
    public function getassessmentitem() {
        return $this->assess_item;
    }

    /**
     * Get Assessment Pattern
     * @return mixed
     */
    public function getassesspattern() {
        return $this->assess_pattern;
    }

    /**
     * Get assess item
     * @return mixed
     */
    public function getAssessItem() {
        return $this->assess_item;
    }

    /**
     * @return mixed
     */
    public function getattempt() {
        return $this->attempt;
    }

    /** Get grade mark
     * @return mixed
     */
    public function getgrade() {
        return $this->grade;
    }

    /**
     * @param $lookup
     * @return array
     */
    public static function get_grade_strucuture_samis(\local_bath_grades_transfer_assessment_lookup $lookup) {
        //Check that it is a valid lookup
        //echo "\n\n+++++++++GETTING GRADE STRUCUTURE FROM SAMIS +++++++++  \n\n";
        $structure = array();

        self::$samisdata = new \local_bath_grades_transfer_external_data();
        //From the attributes and map_code, get the grade structure.
        $remotegradestructure = self::$samisdata->get_remote_grade_structure($lookup);
        if (!empty($remotegradestructure)) {
            foreach ($remotegradestructure->assessments as $assessment) {

                //var_dump($assessment);
                if (!empty($assessment)) {
                    foreach ($assessment as $objassessmentdata) {
                        $structure[(string)$objassessmentdata->student] =
                            array('assessment' => self::instantiate($objassessmentdata));
                    }
                }

            }
        }
        return $structure;
    }

    /** Instantiate a new class object
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

    /** Check if it has the attribute
     * @param $attribute
     * @return bool
     */
    private function has_attribute($attribute) {
        $object_vars = get_object_vars($this);
        return array_key_exists($attribute, $object_vars);
    }


}