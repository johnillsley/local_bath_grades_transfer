<?php
/**
 * local_bath_grades_transfer test data
 */

// Insert for grade transfer mapping.
$insertmapping = array(
    "courseid" => $course->id,
    'sits_code' => 'AR20387',
    'acyear' => '',
    'period_code' => 'S1',
    'year_group' => null,
    'start_date' => '2010-09-20 00:00:00',
    'end_date' => '2011-02-11 00:00:00',
    'default_map' => '1',
    'type' => 'module',
    'manual' => '0',
    'specified' => '0',
    'active' => '1'
);

// Inserts for table local_bath_grades_lookup.
$insertlookups = array(
    1 => (object)array(
        "id" => 1,
        "samisunitcode" => "AR20387",
        "periodslotcode" => "S1",
        "academicyear" => "",
        "mapcode" => "AR20387A",
        "mabseq" => "01",
        "astcode" => "CW",
        "mabperc" => "10",
        "timecreated" => 1500000000,
        "expired" => 0,
        "samisassessmentid" => "AR20387A_01",
        "mabname" => "Assessment A"
    ),
    2 => (object)array(
        "id" => 2,
        "samisunitcode" => "AR20387",
        "periodslotcode" => "S1",
        "academicyear" => "",
        "mapcode" => "AR20387A",
        "mabseq" => "02",
        "astcode" => "CW",
        "mabperc" => "20",
        "timecreated" => 1500000001,
        "expired" => 0,
        "samisassessmentid" => "AR20387A_02",
        "mabname" => "Assessment B"
    ),
    3 => (object)array(
        "id" => 3,
        "samisunitcode" => "AR20387",
        "periodslotcode" => "S1",
        "academicyear" => "",
        "mapcode" => "AR20387A",
        "mabseq" => "03",
        "astcode" => "CW",
        "mabperc" => "30",
        "timecreated" => 1500000002,
        "expired" => 0,
        "samisassessmentid" => "AR20387A_03",
        "mabname" => "Assessment C"
    ),
    4 => (object)array(
        "id" => 4,
        "samisunitcode" => "AR20387",
        "periodslotcode" => "S1",
        "academicyear" => "",
        "mapcode" => "AR20387A",
        "mabseq" => "04",
        "astcode" => "EX",
        "mabperc" => "40",
        "timecreated" => 1500000003,
        "expired" => 0,
        "samisassessmentid" => "AR20387A_04",
        "mabname" => "Assessment D"
    ),
    5 => (object)array(
        "id" => 5,
        "samisunitcode" => "AR20387",
        "periodslotcode" => "S1",
        "academicyear" => "",
        "mapcode" => "AR20387A",
        "mabseq" => "05",
        "astcode" => "EX",
        "mabperc" => "10",
        "timecreated" => 1500000004,
        "expired" => 1500000005,
        "samisassessmentid" => "AR20387A_05",
        "mabname" => "Assessment E"
    ),
);

// Inserts for table local_bath_grades_lookup.
$insertoccurrences = array(
    1 => (object)array(
        "id" => 1,
        "lookupid" => 1,
        "mavoccur" => "A",
    ),
    2 => (object)array(
        "id" => 2,
        "lookupid" => 1,
        "mavoccur" => "AFR",
    ),
    3 => (object)array(
        "id" => 3,
        "lookupid" => 2,
        "mavoccur" => "A",
    ),
    4 => (object)array(
        "id" => 4,
        "lookupid" => 3,
        "mavoccur" => "A",
    ),
    5 => (object)array(
        "id" => 5,
        "lookupid" => 3,
        "mavoccur" => "AFR",
    ),
    6 => (object)array(
        "id" => 6,
        "lookupid" => 3,
        "mavoccur" => "AV",
    ),
    7 => (object)array(
        "id" => 7,
        "lookupid" => 4,
        "mavoccur" => "A",
    ),
    8 => (object)array(
        "id" => 8,
        "lookupid" => 4,
        "mavoccur" => "AFR",
    ),
    9 => (object)array(
        "id" => 9,
        "lookupid" => 5,
        "mavoccur" => "A",
    ),
    10 => (object)array(
        "id" => 10,
        "lookupid" => 5,
        "mavoccur" => "AFR",
    ),
);