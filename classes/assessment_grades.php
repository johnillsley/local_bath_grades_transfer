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

/**
 * Grade transfer assessment grades class
 * This class gives access to grades in SAMIS for reading and writing
 *
 * @package    local_bath_grades_transfer
 * @author     Hittesh Ahuja <h.ahuja@bath.ac.uk>
 * @author     John Illsley <j.s.illsley@bath.ac.uk>
 * @copyright  2017 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/bath_grades_transfer/classes/assessment.php');

class local_bath_grades_transfer_assessment_grades extends local_bath_grades_transfer_assessment
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
     * @var
     */
    public $occurrence;

    public function __construct() {
        parent::__construct();
    }

    /**
     * Fetches the ASSESSMENT DATA from SAMIS Web Service
     * @param $lookup
     * @return array
     */
    public function get_grade_strucuture_samis(\local_bath_grades_transfer_assessment_lookup $lookup) {
        // Check that it is a valid lookup.
        $structure = array();

        //From the attributes and map_code, get the grade structure.
        try {
            $remotegradestructures = $this->samisdata->get_remote_grade_structure($lookup);
            // Note: there is a speartate grade structure for each MAV occurrence.
            foreach ( $remotegradestructures as $remotegradestructure ) {
                if (!empty($remotegradestructure)) {
                    foreach ($remotegradestructure->assessments as $assessment) {
                        if (!empty($assessment)) {
                            foreach ($assessment as $objassessmentdata) {
                                $structure[(string)$objassessmentdata->student] =
                                    array('assessment' => self::instantiate($objassessmentdata));
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            throw $e;
        }
        return $structure;
    }
}