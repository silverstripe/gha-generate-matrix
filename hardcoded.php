<?php

// Used to generate data for consts.php INSTALLER_TO_REPO_MINOR_VERSIONS
// Copy .cow.pat.json from the current release into the directory this script is being run from (file is .gitignored)

include 'consts.php';

$filename = '.cow.pat.json';
if (!file_exists($filename)) {
    throw new \RuntimeException("Copy $filename into the directory this is being called from");
}

$versions = [];

function repoName(string $name)
{
    $prefix = '';
    if (!preg_match('#^(cwp/)#', $name) &&
        !preg_match('#(/recipe-|/silverstripe-|/comment-notifications$)#', $name)
    ) {
        $prefix = 'silverstripe-';
    }
    if (preg_match('#(/agency-extensions|/starter-theme|/watea-theme)$#', $name)) {
        $prefix = 'cwp-';
    }
    $name = preg_replace('#/agency-extensions$#', '/agencyextensions', $name);
    return $prefix . explode('/', $name)[1];
}

function parseNode(string $name, stdClass $node, array &$versions)
{
    $repoName = repoName($name);
    if (!in_array($repoName, LOCKSTEPPED_REPOS) &&
        !in_array($repoName, NO_INSTALLER_LOCKSTEPPED_REPOS) &&
        !in_array($repoName, NO_INSTALLER_UNLOCKSTEPPED_REPOS)
    ) {
        preg_match('#^([0-9]+\.[0-9]+)#', $node->Version, $m);
        $versions[$repoName] = $m[1];
    }
    foreach ((array) $node->Items as $itemName => $item) {
        parseNode($itemName, $item, $versions);
    }
}

foreach (json_decode(file_get_contents($filename)) as $itemName => $item) {
    parseNode($itemName, $item, $versions);
}

ksort($versions);
foreach ($versions as $repoName => $version) {
    echo "        '$repoName' => '$version',\n";
}

// Find any repositories which were in the previous version that aren't in this one
$missing = array_diff(array_keys(INSTALLER_TO_REPO_MINOR_VERSIONS[array_key_last(INSTALLER_TO_REPO_MINOR_VERSIONS)]), array_keys($versions));
if (!empty($missing)) {
    $formatColor = "\033[31m";
    $endFormat = "\033[0m";
    echo "\n" . $formatColor . 'Warning: The following modules were in the last release in INSTALLER_TO_REPO_MINOR_VERSIONS but are missing from .cow.pat.json:' . $endFormat . "\n";
    echo implode("\n", $missing) . "\n";
}
