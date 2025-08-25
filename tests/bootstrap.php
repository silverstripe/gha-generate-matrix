<?php

use SilverStripe\SupportedModules\MetaData;

// working directory will be root
include 'vendor/autoload.php';
include 'consts.php';
include 'job_creator.php';

MetaData::$isRunningUnitTests = true;
