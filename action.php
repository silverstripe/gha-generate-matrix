<?php

include 'consts.php';
include 'job_creator.php';

// Reads inputs.yml and creates a new json matrix
$yml = file_get_contents('__inputs.yml');
$jobCreator = new JobCreator();
echo $jobCreator->createJson($yml);
