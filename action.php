<?php

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    throw new RuntimeException('Run composer install before this script');
}

require_once $autoloadPath;

include 'consts.php';
include 'job_creator.php';

// Reads inputs.yml and creates a new json matrix
$yml = file_get_contents('__inputs.yml');
$jobCreator = new JobCreator();
echo $jobCreator->createJson($yml);
