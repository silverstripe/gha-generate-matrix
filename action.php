<?php

include 'consts.php';
include 'job_creator.php';

// Reads inputs.yml and creates a new json matrix
$inputs = yaml_parse(file_get_contents('__inputs.yml'));
if ($inputs === false) {
    echo 'Unable to parse __inputs.yml';
    exit(1);
}
$jobCreator = new JobCreator();
echo $jobCreator->createJson($inputs);
