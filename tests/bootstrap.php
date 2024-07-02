<?php
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    throw new RuntimeException('Run composer install first');
}
require_once $autoloadPath;

// working directory will be root
include 'consts.php';
include 'job_creator.php';
