<?php

# Manually update this after each minor CMS release
const INSTALLER_TO_PHP_VERSIONS = [
    '4.9' => [
        '7.1',
        '7.2',
        '7.3',
        '7.4'
    ],
    '4.10' => [
        '7.3',
        '7.4',
        '8.0',
    ],
    '4.11' => [
        '7.4',
        '8.0',
        '8.1',
    ],
    '4' => [
        '7.4',
        '8.0',
        '8.1',
    ],
];

const DB_MYSQL_57 = 'mysql57';
const DB_MYSQL_57_PDO = 'mysql57pdo';
const DB_MYSQL_80 = 'mysql80';
const DB_PGSQL = 'pgsql';

// Used when determining the version of installer to used. Intentionally doesn't include recipes
const LOCKSTEPED_REPOS = [
    'silverstripe-admin',
    'silverstripe-asset-admin',
    'silverstripe-assets',
    'silverstripe-campaign-admin',
    'silverstripe-cms',
    'silverstripe-errorpage',
    'silverstripe-framework',
    'silverstripe-reports',
    'silverstripe-siteconfig',
    'silverstripe-versioned',
    'silverstripe-versioned-admin',
    // recipe-solr-search doesn't include recipe-cms or recipe-core unlike our other recipes
    'recipe-solr-search',
];

// Repositories that do not require silverstripe/installer to be explicitly added as a dependency for testing
const NO_INSTALLER_REPOS = [
    // these are/include recipe-cms or recipe-core, so we don't want to composer require installer
    // in .travis.yml they used the 'self' provision rather than 'standard'
    'recipe-authoring-tools',
    'recipe-blog',
    'recipe-ccl',
    'recipe-cms',
    'recipe-collaboration',
    'recipe-content-blocks',
    'recipe-core',
    'recipe-form-building',
    'recipe-kitchen-sink',
    'recipe-reporting-tools',
    'recipe-services',
    'silverstripe-installer',
    // vendor-plugin is not a recipe, though we also do not want installer
    'vendor-plugin'
];
