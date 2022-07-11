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
    '5.0' => [
        '8.1',
    ],
    '5' => [
        '8.1',
    ],
];

const DB_MYSQL_57 = 'mysql57';
const DB_MYSQL_57_PDO = 'mysql57pdo';
const DB_MYSQL_80 = 'mysql80';
const DB_PGSQL = 'pgsql';

// Used when determining the version of installer to used. Intentionally doesn't include recipes
const LOCKSTEPPED_REPOS = [
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
const NO_INSTALLER_LOCKSTEPPED_REPOS = [
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
];

const NO_INSTALLER_UNLOCKSTEPPED_REPOS = [
    'vendor-plugin',
    'recipe-plugin',
];

const CMS_TO_REPO_MAJOR_VERSIONS = [
    '4' => [
        'recipe-authoring-tools' => '1',
        'recipe-blog' => '1',
        'recipe-ccl' => '2',
        'recipe-cms' => '4',
        'recipe-collaboration' => '1',
        'recipe-content-blocks' => '2',
        'recipe-core' => '4',
        'recipe-form-building' => '1',
        'recipe-kitchen-sink' => '4',
        'recipe-reporting-tools' => '1',
        'recipe-services' => '1',
        'silverstripe-installer' => '4',
    ],
    '5' => [
        'recipe-authoring-tools' => '2',
        'recipe-blog' => '2',
        'recipe-ccl' => '3',
        'recipe-cms' => '5',
        'recipe-collaboration' => '2',
        'recipe-content-blocks' => '3',
        'recipe-core' => '5',
        'recipe-form-building' => '2',
        'recipe-kitchen-sink' => '5',
        'recipe-reporting-tools' => '2',
        'recipe-services' => '2',
        'silverstripe-installer' => '5',
    ],
];

const INSTALLER_TO_REPO_MINOR_VERSIONS = [
    '4.10' => [
        'html5' => '2.3',
        'silverstripe-elemental-bannerblock' => '2.4',
        'silverstripe-session-manager' => '1.2',
        'silverstripe-userforms' => '5.12',
        'silverstripe-totp-authenticator' => '4.3',
    ]
];
