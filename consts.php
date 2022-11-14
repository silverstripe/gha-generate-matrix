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
    // recipe-blog requires a theme for the unit tests to work, the config and dependency for which are
    // supplied by installer. Just adding a theme as a dev-dependency is insufficient because we'd still
    // lack the yml config to activate that theme
    'recipe-blog',
];

// Repositories that do not require silverstripe/installer to be explicitly added as a dependency for testing
const NO_INSTALLER_LOCKSTEPPED_REPOS = [
    // these are/include recipe-cms or recipe-core, so we don't want to composer require installer
    // in .travis.yml they used the 'self' provision rather than 'standard'
    'recipe-authoring-tools',
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
    'api.silverstripe.org',
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
        'MinkFacebookWebDriver' => '2',
        'recipe-authoring-tools' => '2',
        'recipe-blog' => '2',
        'recipe-ccl' => '3',
        'recipe-cms' => '5',
        'recipe-collaboration' => '2',
        'recipe-content-blocks' => '3',
        'recipe-core' => '5',
        'recipe-form-building' => '2',
        'recipe-kitchen-sink' => '5',
        'recipe-plugin' => '2',
        'recipe-reporting-tools' => '2',
        'recipe-services' => '2',
        'recipe-testing' => '3',
        'silverstripe-installer' => '5',
        'silverstripe-admin' => '2',
        'silverstripe-asset-admin' => '2',
        'silverstripe-assets' => '2',
        'silverstripe-behat-extension' => '5',
        'silverstripe-campaign-admin' => '2',
        'silverstripe-cms' => '5',
        'silverstripe-config' => '2',
        'silverstripe-errorpage' => '2',
        'silverstripe-event-dispatcher' => '1',
        'silverstripe-framework' => '5',
        'silverstripe-frameworktest' => '1',
        'silverstripe-graphql' => '5',
        'silverstripe-login-forms' => '5',
        'silverstripe-mimevalidator' => '3',
        'silverstripe-registry' => '3',
        'silverstripe-reports' => '5',
        'silverstripe-serve' => '3',
        'silverstripe-session-manager' => '2',
        'silverstripe-siteconfig' => '5',
        'silverstripe-testsession' => '3',
        'silverstripe-versioned' => '2',
        'silverstripe-versioned-admin' => '2',
        'vendor-plugin' => '2',
    ],
];

// use hardcoded.php to bulk update update this after creating a .cow.pat.json
// for multiple versions, use an array e.g. silverstripe-mymodule => ['2.3', '2.4']
const INSTALLER_TO_REPO_MINOR_VERSIONS = [
    '4.10' => [
        'comment-notifications' => '2.2',
        'cwp' => '2.9',
        'cwp-agencyextensions' => '2.6',
        'cwp-core' => '2.9',
        'cwp-pdfexport' => '1.3',
        'cwp-search' => '1.6',
        'cwp-starter-theme' => '3.2',
        'cwp-watea-theme' => '3.1',
        'silverstripe-advancedworkflow' => '5.6',
        'silverstripe-akismet' => '4.2',
        'silverstripe-auditor' => '2.4',
        'silverstripe-blog' => '3.9',
        'silverstripe-ckan-registry' => '1.4',
        'silverstripe-comments' => '3.7',
        'silverstripe-composer-update-checker' => '2.1',
        'silverstripe-config' => '1.3',
        'silverstripe-content-widget' => '2.3',
        'silverstripe-contentreview' => '4.4',
        'silverstripe-crontask' => '2.4',
        'silverstripe-documentconverter' => '2.2',
        'silverstripe-elemental' => '4.8',
        'silverstripe-elemental-bannerblock' => '2.4',
        'silverstripe-elemental-fileblock' => '2.3',
        'silverstripe-elemental-userforms' => '3.1',
        'silverstripe-environmentcheck' => '2.4',
        'silverstripe-externallinks' => '2.2',
        'silverstripe-fluent' => '4.6',
        'silverstripe-fulltextsearch' => '3.9',
        'silverstripe-graphql' => '3.7',
        'silverstripe-gridfieldqueuedexport' => '2.6',
        'silverstripe-html5' => ['2.2', '2.3'],
        'silverstripe-hybridsessions' => '2.4',
        'silverstripe-iframe' => '2.2',
        'silverstripe-ldap' => '1.3',
        'silverstripe-login-forms' => '4.6',
        'silverstripe-maintenance' => '2.4',
        'silverstripe-mfa' => '4.5',
        'silverstripe-mimevalidator' => '2.3',
        'silverstripe-multivaluefield' => '5.2',
        'silverstripe-queuedjobs' => '4.9',
        'silverstripe-realme' => '4.2',
        'silverstripe-registry' => '2.4',
        'silverstripe-restfulserver' => '2.4',
        'silverstripe-security-extensions' => '4.2',
        'silverstripe-securityreport' => '2.4',
        'silverstripe-segment-field' => '2.5',
        'silverstripe-session-manager' => '1.2',
        'silverstripe-sharedraftcontent' => '2.6',
        'silverstripe-sitewidecontent-report' => '3.2',
        'silverstripe-spamprotection' => '3.2',
        'silverstripe-spellcheck' => '2.3',
        'silverstripe-subsites' => '2.5',
        'silverstripe-tagfield' => '2.8',
        'silverstripe-taxonomy' => '2.3',
        'silverstripe-textextraction' => '3.3',
        'silverstripe-totp-authenticator' => '4.3',
        'silverstripe-userforms' => ['5.11', '5.12'],
        'silverstripe-versionfeed' => '2.2',
        'silverstripe-webauthn-authenticator' => '4.4',
        'silverstripe-widgets' => '2.2',
        'silverstripe-gridfieldextensions' => '3.3',
    ],
    '4.11' => [
        'comment-notifications' => '2.3',
        'cwp' => '2.10',
        'cwp-agencyextensions' => '2.7',
        'cwp-core' => ['2.10', '2.11'],
        'cwp-pdfexport' => '1.4',
        'cwp-search' => '1.7',
        'cwp-starter-theme' => '3.2',
        'cwp-watea-theme' => '3.1',
        'silverstripe-advancedworkflow' => '5.7',
        'silverstripe-akismet' => '4.3',
        'silverstripe-auditor' => '2.5',
        'silverstripe-blog' => '3.10',
        'silverstripe-ckan-registry' => '1.5',
        'silverstripe-comments' => '3.8',
        'silverstripe-composer-update-checker' => '3.0',
        'silverstripe-config' => '1.4',
        'silverstripe-content-widget' => '2.4',
        'silverstripe-contentreview' => '4.5',
        'silverstripe-crontask' => '2.5',
        'silverstripe-documentconverter' => '2.3',
        'silverstripe-elemental' => '4.9',
        'silverstripe-elemental-bannerblock' => '2.5',
        'silverstripe-elemental-fileblock' => '2.4',
        'silverstripe-elemental-userforms' => '3.2',
        'silverstripe-environmentcheck' => '2.5',
        'silverstripe-externallinks' => '2.3',
        'silverstripe-fluent' => '4.7',
        'silverstripe-fulltextsearch' => '3.11',
        'silverstripe-graphql' => '4.0',
        'silverstripe-gridfieldqueuedexport' => '2.7',
        'silverstripe-html5' => '2.4',
        'silverstripe-hybridsessions' => '2.5',
        'silverstripe-iframe' => '2.3',
        'silverstripe-ldap' => '1.4',
        'silverstripe-login-forms' => '4.7',
        'silverstripe-maintenance' => '2.6',
        'silverstripe-mfa' => '4.6',
        'silverstripe-mimevalidator' => '2.4',
        'silverstripe-multivaluefield' => '5.3',
        'silverstripe-queuedjobs' => '4.10',
        'silverstripe-realme' => '4.3',
        'silverstripe-registry' => '2.5',
        'silverstripe-restfulserver' => '2.5',
        'silverstripe-security-extensions' => '4.3',
        'silverstripe-securityreport' => '2.5',
        'silverstripe-segment-field' => '2.6',
        'silverstripe-session-manager' => '1.3',
        'silverstripe-sharedraftcontent' => '2.7',
        'silverstripe-sitewidecontent-report' => '3.3',
        'silverstripe-spamprotection' => '3.3',
        'silverstripe-spellcheck' => '2.4',
        'silverstripe-subsites' => '2.6',
        'silverstripe-tagfield' => '2.9',
        'silverstripe-taxonomy' => '2.4',
        'silverstripe-textextraction' => '3.4',
        'silverstripe-totp-authenticator' => '4.4',
        'silverstripe-userforms' => '5.13',
        'silverstripe-versionfeed' => '2.3',
        'silverstripe-webauthn-authenticator' => '4.5',
        'silverstripe-widgets' => '2.3',
        'silverstripe-gridfieldextensions' => '3.4',
    ],
    '4.12' => [
        'comment-notifications' => '2.3',
        'cwp' => '2.10',
        'cwp-agencyextensions' => '2.7',
        'cwp-core' => '2.11',
        'cwp-pdfexport' => '1.4',
        'cwp-search' => '1.7',
        'cwp-starter-theme' => '3.2',
        'cwp-watea-theme' => '3.1',
        'silverstripe-advancedworkflow' => '5.8',
        'silverstripe-akismet' => '4.4',
        'silverstripe-auditor' => '2.5',
        'silverstripe-blog' => '3.11',
        'silverstripe-ckan-registry' => '1.6',
        'silverstripe-comments' => '3.9',
        'silverstripe-composer-update-checker' => '3.0',
        'silverstripe-config' => '1.5',
        'silverstripe-content-widget' => '2.4',
        'silverstripe-contentreview' => '4.6',
        'silverstripe-crontask' => '2.5',
        'silverstripe-developer-docs' => '4.12',
        'silverstripe-documentconverter' => '2.4',
        'silverstripe-elemental' => '4.10',
        'silverstripe-elemental-bannerblock' => '2.6',
        'silverstripe-elemental-fileblock' => '2.4',
        'silverstripe-elemental-userforms' => '3.2',
        'silverstripe-environmentcheck' => '2.6',
        'silverstripe-externallinks' => '2.3',
        'silverstripe-fluent' => '4.7',
        'silverstripe-fulltextsearch' => '3.11',
        'silverstripe-graphql' => '4.1',
        'silverstripe-gridfieldqueuedexport' => '2.7',
        'silverstripe-html5' => '2.4',
        'silverstripe-hybridsessions' => '2.6',
        'silverstripe-iframe' => '2.3',
        'silverstripe-ldap' => '1.5',
        'silverstripe-login-forms' => '4.8',
        'silverstripe-maintenance' => '2.6',
        'silverstripe-mfa' => '4.7',
        'silverstripe-mimevalidator' => '2.4',
        'silverstripe-multivaluefield' => '5.3',
        'silverstripe-queuedjobs' => '4.11',
        'silverstripe-realme' => '4.3',
        'silverstripe-registry' => '2.5',
        'silverstripe-restfulserver' => '2.5',
        'silverstripe-security-extensions' => '4.4',
        'silverstripe-securityreport' => '2.5',
        'silverstripe-segment-field' => '2.7',
        'silverstripe-session-manager' => '1.4',
        'silverstripe-sharedraftcontent' => '2.8',
        'silverstripe-sitewidecontent-report' => '3.3',
        'silverstripe-spamprotection' => '3.3',
        'silverstripe-spellcheck' => '2.4',
        'silverstripe-subsites' => '2.7',
        'silverstripe-tagfield' => '2.10',
        'silverstripe-taxonomy' => '2.4',
        'silverstripe-textextraction' => '3.4',
        'silverstripe-totp-authenticator' => '4.5',
        'silverstripe-userforms' => '5.14',
        'silverstripe-versionfeed' => '2.3',
        'silverstripe-webauthn-authenticator' => '4.6',
        'silverstripe-widgets' => '2.3',
        'silverstripe-gridfieldextensions' => '3.5',
    ],
];
