<?php

use PHPUnit\Framework\TestCase;
use SilverStripe\SupportedModules\MetaData;

class JobCreatorTest extends TestCase
{
    /**
     * @dataProvider provideCreateJob
     */
    public function testCreateJob(
        string $githubRepository,
        string $branch,
        int $phpIndex,
        array $opts,
        array $expected
    ): void {
        $creator = new JobCreator();
        $creator->githubRepository = $githubRepository;
        $creator->repoName = explode('/', $githubRepository)[1];
        $creator->branch = $creator->getCleanedBranch($branch);
        $creator->parseRepositoryMetadata();
        $actual = $creator->createJob($phpIndex, $opts);
        foreach ($expected as $key => $expectedVal) {
            $this->assertSame($expectedVal, $actual[$key]);
        }
    }

    public function provideCreateJob(): array
    {
        $lowestMajor = MetaData::LOWEST_SUPPORTED_CMS_MAJOR;
        $highestMajor = MetaData::HIGHEST_STABLE_CMS_MAJOR;
        $highestMajorPlus1 = $this->offsetMajorVersion($highestMajor, 1);
        $scenarios = [
            // general test
            ['myaccount/silverstripe-framework', $lowestMajor, 0, ['phpunit' => true], [
                'installer_version' => '',
                'php' => min(MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[$lowestMajor]),
                'db' => DB_MYSQL_57,
                'composer_require_extra' => '',
                'composer_args' => '',
                'composer_install' => false,
                'name_suffix' => '',
                'phpunit' => true,
                'phpunit_suite' => 'all',
                'phplinting' => false,
                'phpcoverage' => false,
                'endtoend' => false,
                'endtoend_suite' => 'root',
                'endtoend_config' => '',
                'endtoend_tags' => '',
                'js' => false,
                'doclinting' => false,
                'needs_full_setup' => false,
            ]],
            // test that NO_INSTALLER_LOCKSTEPPED_REPOS base max PHP version from $branch
            ['myaccount/silverstripe-installer', $lowestMajor . '.0', 99, [], [
                'php' => max(MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[$lowestMajor . '.0'])
            ]],
            ['myaccount/silverstripe-installer', $lowestMajor . '.1', 99, [], [
                'php' => max(MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[$lowestMajor . '.1'])
            ]],
            ['myaccount/silverstripe-installer', $lowestMajor, 99, [], [
                'php' => max(MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[$lowestMajor])
            ]],
            ['myaccount/silverstripe-installer', $highestMajor . '.0', 99, [], [
                'php' => max(MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[$highestMajor . '.0'])
            ]],
            ['myaccount/silverstripe-installer', $highestMajor, 99, [], [
                'php' => max(MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[$highestMajor])
            ]],
        ];
        // Make sure we can deal with pre-release majors
        if (array_key_exists($highestMajorPlus1, MetaData::PHP_VERSIONS_FOR_CMS_RELEASES)) {
            $scenarios[] = ['myaccount/silverstripe-installer', $highestMajorPlus1, 99, [], [
                'php' => max(MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[$highestMajorPlus1])
            ]];
        }
        return $scenarios;
    }

    /**
     * @dataProvider provideGetInstallerVersion
     */
    public function testGetInstallerVersion(
        string $githubRepository,
        string $branch,
        string $expected,
        array $customInstallerBranches = [],
        array $customComposerDeps = [],
        string $packageType = ''
    ): void {
        try {
            $installerBranchesJson = json_encode($this->getInstallerBranchesJson());
            if ($customInstallerBranches) {
                $installerBranchesJson = json_encode($customInstallerBranches);
            }
            $creator = new JobCreator();
            $creator->composerJsonPath = '__composer.json';
            if ($customComposerDeps || $packageType) {
                $this->writeComposerJson($customComposerDeps, $packageType);
            }
            $creator->githubRepository = $githubRepository;
            $creator->repoName = explode('/', $githubRepository)[1];
            $creator->branch = $creator->getCleanedBranch($branch);
            $creator->parseRepositoryMetadata();
            $actual = $creator->getInstallerVersion($installerBranchesJson);
            $this->assertSame($expected, $actual);
        } finally {
            if (file_exists('__composer.json')) {
                unlink('__composer.json');
            }
        }
    }

    private function getInstallerBranchesJson(): array
    {
        $lowestMajor = MetaData::LOWEST_SUPPORTED_CMS_MAJOR;
        $highestMajor = MetaData::HIGHEST_STABLE_CMS_MAJOR;
        $highestMajorPlus1 = $this->offsetMajorVersion($highestMajor, 1);
        return [
            ['name' => $lowestMajor],
            ['name' => $lowestMajor . '.0'],
            ['name' => $lowestMajor . '.1'],
            ['name' => $lowestMajor . '.2'],
            ['name' => $lowestMajor . '.3'],
            ['name' => $lowestMajor . '.4'],
            ['name' => $highestMajor],
            ['name' => $highestMajor . '.0'],
            ['name' => $highestMajor . '.1'],
            ['name' => $highestMajor . '.2'],
            ['name' => $highestMajorPlus1],
            ['name' => $highestMajorPlus1 . '.0'],
        ];
    }

    private function getCurrentMinorInstallerVersion(string $cmsMajor): string
    {
        $versions = array_keys(MetaData::PHP_VERSIONS_FOR_CMS_RELEASES);
        $versions = array_filter($versions, fn($version) => substr($version, 0, 1) === $cmsMajor);
        natsort($versions);
        $versions = array_reverse($versions);
        return $versions[0];
    }

    private function offsetMajorVersion(string $majorVersion, int $offset): string
    {
        return (string)($majorVersion + $offset);
    }

    public function provideGetInstallerVersion(): array
    {
        $lowestMajor = MetaData::LOWEST_SUPPORTED_CMS_MAJOR;
        $highestMajor = MetaData::HIGHEST_STABLE_CMS_MAJOR;
        $highestMajorPlus1 = $this->offsetMajorVersion($highestMajor, 1);

        $versionsInConst = array_keys(INSTALLER_TO_REPO_MINOR_VERSIONS);
        $latestVersionInConst = end($versionsInConst);
        $nextMinorLowestMajor = $lowestMajor . '.x-dev';
        $currentMinorLowestMajor = $this->getCurrentMinorInstallerVersion($lowestMajor) . '.x-dev';
        $scenarios = [
            // no-installer repo
            'recipe-cms1' => ['myaccount/recipe-cms', '4', ''],
            'recipe-cms2' => ['myaccount/recipe-cms', '4.10', ''],
            'recipe-cms3' => ['myaccount/recipe-cms', 'burger', ''],
            'recipe-cms4' => ['myaccount/recipe-cms', 'pulls/4/myfeature', ''],
            'recipe-cms5' => ['myaccount/recipe-cms', 'pulls/4.10/myfeature', ''],
            'recipe-cms6' => ['myaccount/recipe-cms', 'pulls/burger/myfeature', ''],
            'recipe-cms7' => ['myaccount/recipe-cms', '5', ''],
            'recipe-cms8' => ['myaccount/recipe-cms', '5.1', ''],
            'recipe-cms9' => ['myaccount/recipe-cms', '6', ''],
            'recipe-cms10' => ['myaccount/recipe-cms', '6.0', ''],
            // lockstepped repo with branch name matching the CMS major version
            'framework1' => ['myaccount/silverstripe-framework', $lowestMajor, $lowestMajor . '.x-dev'],
            'framework2' => ['myaccount/silverstripe-framework', $lowestMajor . '.10', $lowestMajor . '.10.x-dev'],
            'framework3' => ['myaccount/silverstripe-framework', 'burger', $currentMinorLowestMajor],
            'framework4' => ['myaccount/silverstripe-framework', 'pulls/' . $lowestMajor . '/mybugfix', $lowestMajor . '.x-dev'],
            'framework5' => ['myaccount/silverstripe-framework', 'pulls/' . $lowestMajor . '.10/mybugfix', $lowestMajor . '.10.x-dev'],
            'framework6' => ['myaccount/silverstripe-framework', 'pulls/burger/myfeature', $currentMinorLowestMajor],
            'framework7' => ['myaccount/silverstripe-framework', $highestMajor, $highestMajor . '.x-dev'],
            'framework8' => ['myaccount/silverstripe-framework', $highestMajor . '.1', $highestMajor . '.1.x-dev'],
            // lockstepped repo with branch different to the CMS major version
            'admin1' => ['myaccount/silverstripe-admin', $this->offsetMajorVersion($lowestMajor, -3), $lowestMajor . '.x-dev'],
            'admin2' => ['myaccount/silverstripe-admin', $this->offsetMajorVersion($lowestMajor, -3) . '.1', $lowestMajor . '.1.x-dev'],
            'admin3' => ['myaccount/silverstripe-admin', 'burger', $currentMinorLowestMajor],
            'admin4' => ['myaccount/silverstripe-admin', 'pulls/' . $this->offsetMajorVersion($lowestMajor, -3) . '/mybugfix', $lowestMajor . '.x-dev'],
            'admin5' => ['myaccount/silverstripe-admin', 'pulls/' . $this->offsetMajorVersion($lowestMajor, -3) . '.1/mybugfix', $lowestMajor . '.1.x-dev'],
            'admin6' => ['myaccount/silverstripe-admin', 'pulls/burger/myfeature', $currentMinorLowestMajor],
            'admin7' => ['myaccount/silverstripe-admin', $this->offsetMajorVersion($highestMajor, -3), $highestMajor . '.x-dev'],
            'admin8' => ['myaccount/silverstripe-admin', $this->offsetMajorVersion($highestMajor, -3) . '.1', $highestMajor . '.1.x-dev'],
            // non-lockedstepped repo
            'tagfield1' => ['myaccount/silverstripe-tagfield', $this->offsetMajorVersion($lowestMajor, -2), $nextMinorLowestMajor],
            'tagfield2' => ['myaccount/silverstripe-tagfield', $this->offsetMajorVersion($lowestMajor, -2) . '.9', $currentMinorLowestMajor],
            'tagfield3' => ['myaccount/silverstripe-tagfield', 'burger', $currentMinorLowestMajor],
            'tagfield4' => ['myaccount/silverstripe-tagfield', 'pulls/' . $this->offsetMajorVersion($lowestMajor, -2) . '/mybugfix', $nextMinorLowestMajor],
            'tagfield5' => ['myaccount/silverstripe-tagfield', 'pulls/' . $this->offsetMajorVersion($lowestMajor, -2) . '.9/mybugfix', $currentMinorLowestMajor],
            'tagfield6' => ['myaccount/silverstripe-tagfield', 'pulls/burger/myfeature', $currentMinorLowestMajor],
            // non-lockstepped repo, fallback to major version of installer as no branch `99` or `99.x` exists for installer.
            'tagfield8' => [
                'myaccount/silverstripe-tagfield',
                $this->offsetMajorVersion($highestMajor, -2) . '.0',
                $latestVersionInConst . '.x-dev',
                [['name' => '99']],
                ['silverstripe/framework' => '^99']
            ],
            // hardcoded repo version
            'session-manager1' => ['myaccount/silverstripe-session-manager', $this->offsetMajorVersion($lowestMajor, -3), $nextMinorLowestMajor],
            'session-manager2' => ['myaccount/silverstripe-session-manager', $this->offsetMajorVersion($lowestMajor, -3) . '.2', $lowestMajor . '.2.x-dev'],
            'session-manager3' => ['myaccount/silverstripe-session-manager', 'burger', $currentMinorLowestMajor],
            // force installer unlockedstepped repo
            'behat1' => ['myaccount/silverstripe-behat-extension', $this->offsetMajorVersion($lowestMajor, -2), $nextMinorLowestMajor],
            // repos that shouldn't have installer
            'vendor-plugin1' => ['myaccount/vendor-plugin', $this->offsetMajorVersion($lowestMajor, -3), ''],
            'vendor-plugin2' => ['myaccount/vendor-plugin', $this->offsetMajorVersion($highestMajor, -3), ''],
            'recipe-plugin1' => ['myaccount/recipe-plugin', $this->offsetMajorVersion($lowestMajor, -3), ''],
            // random third-party repo (note the actual branch for the 3rd party repo doesn't change affect the result)
            'random-notype' => ['myaccount/my-module', '4.0', '', [['name' => $highestMajor]], ['silverstripe/framework' => '^' . $highestMajor]],
            'random-module' => ['myaccount/my-module', '4.0', $highestMajor . '.x-dev', [['name' => $highestMajor]], ['silverstripe/framework' => '^' . $highestMajor], 'silverstripe-module'],
            'random-vendormodule' => ['myaccount/my-module', '4.0', $highestMajor . '.x-dev', [['name' => $highestMajor]], ['silverstripe/framework' => '^' . $highestMajor], 'silverstripe-vendormodule'],
            'random-recipe' => ['myaccount/my-module', '4.0', $highestMajor . '.x-dev', [['name' => $highestMajor]], ['silverstripe/framework' => '^' . $highestMajor], 'silverstripe-recipe'],
            'random-theme' => ['myaccount/my-module', '4.0', $highestMajor . '.x-dev', [['name' => $highestMajor]], ['silverstripe/framework' => '^' . $highestMajor], 'silverstripe-theme'],
            'random-notype-nodeps' => ['myaccount/my-module', '4.0', '', [['name' => $highestMajor]]],
        ];

        // Make sure we can deal with pre-release majors
        if (array_key_exists($highestMajorPlus1, MetaData::PHP_VERSIONS_FOR_CMS_RELEASES)) {
            $scenarios['framework9'] = ['myaccount/silverstripe-framework', $highestMajorPlus1, $highestMajorPlus1 . '.x-dev'];
            $scenarios['admin9'] = ['myaccount/silverstripe-admin', $this->offsetMajorVersion($highestMajorPlus1, -3), $highestMajorPlus1 . '.x-dev'];
        }

        return $scenarios;
    }

    /**
     * @dataProvider provideCreateJson
     */
    public function testCreateJson(
        string $yml,
        bool $behatFile,
        bool $featureFiles,
        bool $featureFileMissingTag,
        array $expected
    ) {
        if (!function_exists('yaml_parse')) {
            $this->markTestSkipped('yaml extension is not installed');
        }
        try {
            if ($behatFile) {
                file_put_contents('behat.yml', '');
            }
            if ($featureFiles && !file_exists('_test_features')) {
                mkdir('_test_features');
                file_put_contents('_test_features/feature1.feature', '@job1');
                file_put_contents('_test_features/feature2.feature', '@job2');
                if ($featureFileMissingTag) {
                    file_put_contents('_test_features/feature2.feature', '@missing');
                }
            }
            if ($featureFileMissingTag) {
                $this->expectException(RuntimeException::class);
                $this->expectExceptionMessage('At least one .feature files missing a @job[0-9]+ tag');
            }
            $creator = new JobCreator();
            $json = json_decode($creator->createJson($yml));
            $this->assertSame(count($expected), count($json->include));
            for ($i = 0; $i < count($expected); $i++) {
                foreach ($expected[$i] as $key => $expectedVal) {
                    $this->assertSame($expectedVal, $json->include[$i]->$key, "\$i is $i, \$key is $key");
                }
            }
        } finally {
            if (file_exists('behat.yml')) {
                unlink('behat.yml');
            }
            if (file_exists('_test_features')) {
                foreach (scandir('_test_features') as $file) {
                    if ($file !== '.' && $file !== '..') {
                        unlink('_test_features/' . $file);
                    }
                }
                rmdir('_test_features');
            }
        }
    }

    public function provideCreateJson(): array
    {
        $lowestMajor = MetaData::LOWEST_SUPPORTED_CMS_MAJOR;
        $highestMajor = MetaData::HIGHEST_STABLE_CMS_MAJOR;
        $highestMajorPlus1 = $this->offsetMajorVersion($highestMajor, 1);
        $phpLowestMajor = MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[$lowestMajor];
        $phpHighestMajor = MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[$highestMajor];
        $scenarios = [
            'behat without @job1/@job2 test - lowest major' => [
                implode("\n", [
                    $this->getGenericYml(),
                    <<<EOT
                    github_repository: 'myaccount/silverstripe-framework'
                    github_my_ref: '$lowestMajor'
                    parent_branch: ''
                    EOT
                ]),
                true,
                false,
                false,
                [
                    [
                        'installer_version' => $lowestMajor . '.x-dev',
                        'php' => $phpLowestMajor[0],
                        'db' => DB_MYSQL_57,
                        'composer_require_extra' => '',
                        'composer_args' => '--prefer-lowest',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpLowestMajor[0] . ' prf-low mysql57 phpunit all',
                    ],
                    [
                        'installer_version' => $lowestMajor . '.x-dev',
                        'php' => $phpLowestMajor[1],
                        'db' => DB_MARIADB,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpLowestMajor[1] . ' mariadb phpunit all',
                    ],
                    [
                        'installer_version' => $lowestMajor . '.x-dev',
                        'php' => $phpLowestMajor[2],
                        'db' => DB_MYSQL_80,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpLowestMajor[2] . ' mysql80 phpunit all',
                    ],
                    [
                        'installer_version' => $lowestMajor . '.x-dev',
                        'php' => $phpLowestMajor[2],
                        'db' => DB_MYSQL_84,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpLowestMajor[2] . ' mysql84 phpunit all',
                    ],
                    [
                        'installer_version' => $lowestMajor . '.x-dev',
                        'php' => $phpLowestMajor[0],
                        'db' => DB_MYSQL_57,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'false',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'true',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpLowestMajor[0] . ' mysql57 endtoend root',
                    ],
                    [
                        'installer_version' => $lowestMajor . '.x-dev',
                        'php' => $phpLowestMajor[2],
                        'db' => DB_MYSQL_80,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'false',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'true',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpLowestMajor[2] . ' mysql80 endtoend root',
                    ],
                ]
            ],
            'behat without @job1/@job2 test - heighest major' => [
                implode("\n", [
                    $this->getGenericYml(),
                    <<<EOT
                    github_repository: 'myaccount/silverstripe-framework'
                    github_my_ref: '$highestMajor'
                    parent_branch: ''
                    EOT
                ]),
                true,
                false,
                false,
                [
                    [
                        'installer_version' => $highestMajor . '.x-dev',
                        'php' => $phpHighestMajor[0],
                        'db' => DB_MARIADB,
                        'composer_require_extra' => '',
                        'composer_args' => '--prefer-lowest',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpHighestMajor[0]. ' prf-low mariadb phpunit all',
                    ],
                    [
                        'installer_version' => $highestMajor . '.x-dev',
                        'php' => $phpHighestMajor[0],
                        'db' => DB_MYSQL_80,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpHighestMajor[0]. ' mysql80 phpunit all',
                    ],
                    [
                        'installer_version' => $highestMajor . '.x-dev',
                        'php' => $phpHighestMajor[1],
                        'db' => DB_MYSQL_84,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpHighestMajor[1]. ' mysql84 phpunit all',
                    ],
                    [
                        'installer_version' => $highestMajor . '.x-dev',
                        'php' => $phpHighestMajor[0],
                        'db' => DB_MYSQL_80,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'false',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'true',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpHighestMajor[0]. ' mysql80 endtoend root',
                    ],
                    [
                        'installer_version' => $highestMajor . '.x-dev',
                        'php' => $phpHighestMajor[1],
                        'db' => DB_MYSQL_84,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'false',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'true',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpHighestMajor[1]. ' mysql84 endtoend root',
                    ],
                ]
            ],
            'behat with @job1/@job2 test' => [
                implode("\n", [
                    $this->getGenericYml(),
                    <<<EOT
                    github_repository: 'myaccount/silverstripe-framework'
                    github_my_ref: '$lowestMajor'
                    parent_branch: ''
                    EOT
                ]),
                true,
                true,
                false,
                [
                    [
                        'installer_version' => $lowestMajor . '.x-dev',
                        'php' => $phpLowestMajor[0],
                        'db' => DB_MYSQL_57,
                        'composer_require_extra' => '',
                        'composer_args' => '--prefer-lowest',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpLowestMajor[0] . ' prf-low mysql57 phpunit all',
                    ],
                    [
                        'installer_version' => $lowestMajor . '.x-dev',
                        'php' => $phpLowestMajor[1],
                        'db' => DB_MARIADB,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpLowestMajor[1] . ' mariadb phpunit all',
                    ],
                    [
                        'installer_version' => $lowestMajor . '.x-dev',
                        'php' => $phpLowestMajor[2],
                        'db' => DB_MYSQL_80,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpLowestMajor[2] . ' mysql80 phpunit all',
                    ],
                    [
                        'installer_version' => $lowestMajor . '.x-dev',
                        'php' => $phpLowestMajor[2],
                        'db' => DB_MYSQL_84,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpLowestMajor[2] . ' mysql84 phpunit all',
                    ],
                    [
                        'installer_version' => $lowestMajor . '.x-dev',
                        'php' => $phpLowestMajor[0],
                        'db' => DB_MYSQL_57,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'false',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'true',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => 'job1',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpLowestMajor[0] . ' mysql57 endtoend root job1',
                    ],
                    [
                        'installer_version' => $lowestMajor . '.x-dev',
                        'php' => $phpLowestMajor[2],
                        'db' => DB_MYSQL_80,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'false',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'true',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => 'job1',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpLowestMajor[2] . ' mysql80 endtoend root job1',
                    ],
                    [
                        'installer_version' => $lowestMajor . '.x-dev',
                        'php' => $phpLowestMajor[0],
                        'db' => DB_MYSQL_57,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'false',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'true',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => 'job2',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpLowestMajor[0] . ' mysql57 endtoend root job2',
                    ],
                    [
                        'installer_version' => $lowestMajor . '.x-dev',
                        'php' => $phpLowestMajor[2],
                        'db' => DB_MYSQL_80,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'false',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'true',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => 'job2',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpLowestMajor[2] . ' mysql80 endtoend root job2',
                    ],
                ]
            ],
            'behat with @job1/@job2 test with missing @job tag' => [
                implode("\n", [
                    $this->getGenericYml(),
                    <<<EOT
                    github_repository: 'myaccount/silverstripe-framework'
                    github_my_ref: '$highestMajor'
                    parent_branch: ''
                    EOT
                ]),
                true,
                true,
                true,
                []
            ],
            'general test for lowest major' => [
                implode("\n", [
                    $this->getGenericYml(),
                    <<<EOT
                    github_repository: 'myaccount/silverstripe-framework'
                    github_my_ref: '$lowestMajor'
                    parent_branch: ''
                    EOT
                ]),
                false,
                false,
                false,
                [
                    [
                        'installer_version' => $lowestMajor . '.x-dev',
                        'php' => $phpLowestMajor[0],
                        'db' => DB_MYSQL_57,
                        'composer_require_extra' => '',
                        'composer_args' => '--prefer-lowest',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpLowestMajor[0] . ' prf-low mysql57 phpunit all',
                    ],
                    [
                        'installer_version' => $lowestMajor . '.x-dev',
                        'php' => $phpLowestMajor[1],
                        'db' => DB_MARIADB,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpLowestMajor[1] . ' mariadb phpunit all',
                    ],
                    [
                        'installer_version' => $lowestMajor . '.x-dev',
                        'php' => $phpLowestMajor[2],
                        'db' => DB_MYSQL_80,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpLowestMajor[2] . ' mysql80 phpunit all',
                    ],
                    [
                        'installer_version' => $lowestMajor . '.x-dev',
                        'php' => $phpLowestMajor[2],
                        'db' => DB_MYSQL_84,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpLowestMajor[2] . ' mysql84 phpunit all',
                    ],
                ]
            ],
            'general test for highest major' => [
                implode("\n", [
                    $this->getGenericYml(),
                    <<<EOT
                    github_repository: 'myaccount/silverstripe-framework'
                    github_my_ref: '$highestMajor'
                    parent_branch: ''
                    EOT
                ]),
                false,
                false,
                false,
                [
                    [
                        'installer_version' => $highestMajor . '.x-dev',
                        'php' => $phpHighestMajor[0],
                        'db' => DB_MARIADB,
                        'composer_require_extra' => '',
                        'composer_args' => '--prefer-lowest',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpHighestMajor[0]. ' prf-low mariadb phpunit all',
                    ],
                    [
                        'installer_version' => $highestMajor . '.x-dev',
                        'php' => $phpHighestMajor[0],
                        'db' => DB_MYSQL_80,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpHighestMajor[0]. ' mysql80 phpunit all',
                    ],
                    [
                        'installer_version' => $highestMajor . '.x-dev',
                        'php' => $phpHighestMajor[1],
                        'db' => DB_MYSQL_84,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpHighestMajor[1]. ' mysql84 phpunit all',
                    ],
                ]
            ],
            'general test for lowest major with a minor' => [
                implode("\n", [
                    $this->getGenericYml(),
                    <<<EOT
                    github_repository: 'myaccount/silverstripe-framework'
                    github_my_ref: '$lowestMajor.1'
                    parent_branch: ''
                    EOT
                ]),
                false,
                false,
                false,
                [
                    [
                        'installer_version' => $lowestMajor . '.1.x-dev',
                        'php' => $phpLowestMajor[0],
                        'db' => DB_MYSQL_57,
                        'composer_require_extra' => '',
                        'composer_args' => '--prefer-lowest',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpLowestMajor[0] . ' prf-low mysql57 phpunit all',
                    ],
                    [
                        'installer_version' => $lowestMajor . '.1.x-dev',
                        'php' => $phpLowestMajor[0],
                        'db' => DB_MARIADB,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpLowestMajor[0] . ' mariadb phpunit all',
                    ],
                    [
                        'installer_version' => $lowestMajor . '.1.x-dev',
                        'php' => $phpLowestMajor[1],
                        'db' => DB_MYSQL_80,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpLowestMajor[1] . ' mysql80 phpunit all',
                    ],
                    [
                        'installer_version' => $lowestMajor . '.1.x-dev',
                        'php' => $phpLowestMajor[1],
                        'db' => DB_MYSQL_84,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpLowestMajor[1] . ' mysql84 phpunit all',
                    ],
                ]
            ],
            'general test for lowest major with a minor (3 PHP versions)' => [
                implode("\n", [
                    $this->getGenericYml(),
                    <<<EOT
                    github_repository: 'myaccount/silverstripe-framework'
                    github_my_ref: '$lowestMajor.2'
                    parent_branch: ''
                    EOT
                ]),
                false,
                false,
                false,
                [
                    [
                        'installer_version' => $lowestMajor . '.2.x-dev',
                        'php' => $phpLowestMajor[0],
                        'db' => DB_MYSQL_57,
                        'composer_require_extra' => '',
                        'composer_args' => '--prefer-lowest',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpLowestMajor[0] . ' prf-low mysql57 phpunit all',
                    ],
                    [
                        'installer_version' => $lowestMajor . '.2.x-dev',
                        'php' => $phpLowestMajor[1],
                        'db' => DB_MARIADB,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpLowestMajor[1] . ' mariadb phpunit all',
                    ],
                    [
                        'installer_version' => $lowestMajor . '.2.x-dev',
                        'php' => $phpLowestMajor[2],
                        'db' => DB_MYSQL_80,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpLowestMajor[2] . ' mysql80 phpunit all',
                    ],
                    [
                        'installer_version' => $lowestMajor . '.2.x-dev',
                        'php' => $phpLowestMajor[2],
                        'db' => DB_MYSQL_84,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpLowestMajor[2] . ' mysql84 phpunit all',
                    ],
                ]
            ],
        ];

        // Make sure we can deal with pre-release majors
        if (array_key_exists($highestMajorPlus1, MetaData::PHP_VERSIONS_FOR_CMS_RELEASES)) {
            $phpHighestMajorPlus1 = MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[$highestMajorPlus1];
            $scenarios['general test for pre-stable major'] = [
                implode("\n", [
                    $this->getGenericYml(),
                    <<<EOT
                    github_repository: 'myaccount/silverstripe-framework'
                    github_my_ref: '$highestMajorPlus1'
                    parent_branch: ''
                    EOT
                ]),
                false,
                false,
                false,
                [
                    [
                        'installer_version' => $highestMajorPlus1 . '.x-dev',
                        'php' => $phpHighestMajorPlus1[0],
                        'db' => DB_MARIADB,
                        'composer_require_extra' => '',
                        'composer_args' => '--prefer-lowest',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpHighestMajorPlus1[0]. ' prf-low mariadb phpunit all',
                    ],
                    [
                        'installer_version' => $highestMajor . '.x-dev',
                        'php' => $phpHighestMajorPlus1[0],
                        'db' => DB_MYSQL_80,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpHighestMajorPlus1[0]. ' mysql80 phpunit all',
                    ],
                    [
                        'installer_version' => $highestMajorPlus1 . '.x-dev',
                        'php' => $phpHighestMajorPlus1[1],
                        'db' => DB_MYSQL_84,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'true',
                        'name' => $phpHighestMajorPlus1[1]. ' mysql84 phpunit all',
                    ],
                ]
            ];
        }
        return $scenarios;
    }

    /**
     * silverstripe/config is a bit of a special case, so test for it explicitly
     * @dataProvider provideCreateJsonForConfig
     */
    public function testCreateJsonForConfig(string $branch, string $phpConstraint, array $expected): void
    {
        if (!function_exists('yaml_parse')) {
            $this->markTestSkipped('yaml extension is not installed');
        }
        $yml = <<<EOT
        endtoend: false
        js: false
        phpcoverage: false
        phpcoverage_force_off: false
        phplinting: true
        phpunit: true
        doclinting: false
        simple_matrix: false
        composer_install: false
        github_repository: 'silverstripe/silverstripe-config'
        github_my_ref: '$branch'
        EOT;
        try {
            $creator = new JobCreator();
            $creator->composerJsonPath = '__composer.json';
            $composer = <<<COMPOSER
            {
                "name": "silverstripe/framework",
                "require": {
                    "php": "$phpConstraint"
                }
            }
            COMPOSER;
            file_put_contents('__composer.json', $composer);
            $json = json_decode($creator->createJson($yml));
            $this->assertSame(count($expected), count($json->include));
            for ($i = 0; $i < count($expected); $i++) {
                foreach ($expected[$i] as $key => $expectedVal) {
                    $this->assertSame($expectedVal, $json->include[$i]->$key, "\$i is $i, \$key is $key");
                }
            }
        } finally {
            unlink('__composer.json');
        }
    }

    public function provideCreateJsonForConfig(): array
    {
        $lowestMajor = MetaData::LOWEST_SUPPORTED_CMS_MAJOR;
        $highestMajor = MetaData::HIGHEST_STABLE_CMS_MAJOR;
        $highestMajorPlus1 = $this->offsetMajorVersion($highestMajor, 1);
        $phpLowestMajor = MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[$lowestMajor];
        $phpHighestMajor = MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[$highestMajor];
        $scenarios = [
            'lowest major with minor' => [
                // Note branch must match what we expect for silverstripe/config
                'branch' => $this->offsetMajorVersion($lowestMajor, -3) . '.1',
                'phpConstraint' => '^' . $phpLowestMajor[0],
                'expected' => [
                    [
                        'installer_version' => '',
                        'php' => $phpLowestMajor[0],
                        'db' => DB_MYSQL_57,
                        'composer_require_extra' => '',
                        'composer_args' => '--prefer-lowest',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'false',
                        'name' => $phpLowestMajor[0] . ' prf-low mysql57 phpunit all',
                    ],
                    [
                        'installer_version' => '',
                        'php' => $phpLowestMajor[0],
                        'db' => DB_MARIADB,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'false',
                        'name' => $phpLowestMajor[0] . ' mariadb phpunit all',
                    ],
                    [
                        'installer_version' => '',
                        'php' => $phpLowestMajor[1],
                        'db' => DB_MYSQL_80,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'false',
                        'name' => $phpLowestMajor[1] . ' mysql80 phpunit all',
                    ],
                    [
                        'installer_version' => '',
                        'php' => $phpLowestMajor[1],
                        'db' => DB_MYSQL_84,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'false',
                        'name' => $phpLowestMajor[1] . ' mysql84 phpunit all',
                    ],
                ],
            ],
            'lowest major no minor' => [
                'branch' => $this->offsetMajorVersion($lowestMajor, -3),
                'phpConstraint' => '^' . $phpLowestMajor[0],
                'expected' => [
                    [
                        'installer_version' => '',
                        'php' => $phpLowestMajor[0],
                        'db' => DB_MYSQL_57,
                        'composer_require_extra' => '',
                        'composer_args' => '--prefer-lowest',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'false',
                        'name' => $phpLowestMajor[0] . ' prf-low mysql57 phpunit all',
                    ],
                    [
                        'installer_version' => '',
                        'php' => $phpLowestMajor[1],
                        'db' => DB_MARIADB,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'false',
                        'name' => $phpLowestMajor[1] . ' mariadb phpunit all',
                    ],
                    [
                        'installer_version' => '',
                        'php' => $phpLowestMajor[2],
                        'db' => DB_MYSQL_80,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'false',
                        'name' => $phpLowestMajor[2] . ' mysql80 phpunit all',
                    ],
                    [
                        'installer_version' => '',
                        'php' => $phpLowestMajor[2],
                        'db' => DB_MYSQL_84,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'false',
                        'name' => $phpLowestMajor[2] . ' mysql84 phpunit all',
                    ],
                ],
            ],
            'lowest major dual PHP support' => [
                'branch' => $this->offsetMajorVersion($lowestMajor, -3),
                'phpConstraint' => '^7.4 || ^' . $phpLowestMajor[0],
                'expected' => [
                    [
                        'installer_version' => '',
                        'php' => $phpLowestMajor[0],
                        'db' => DB_MYSQL_57,
                        'composer_require_extra' => '',
                        'composer_args' => '--prefer-lowest',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'false',
                        'name' => $phpLowestMajor[0] . ' prf-low mysql57 phpunit all',
                    ],
                    [
                        'installer_version' => '',
                        'php' => $phpLowestMajor[1],
                        'db' => DB_MARIADB,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'false',
                        'name' => $phpLowestMajor[1] . ' mariadb phpunit all',
                    ],
                    [
                        'installer_version' => '',
                        'php' => $phpLowestMajor[2],
                        'db' => DB_MYSQL_80,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'false',
                        'name' => $phpLowestMajor[2] . ' mysql80 phpunit all',
                    ],
                    [
                        'installer_version' => '',
                        'php' => $phpLowestMajor[2],
                        'db' => DB_MYSQL_84,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'false',
                        'name' => $phpLowestMajor[2] . ' mysql84 phpunit all',
                    ],
                ],
            ],
            'lowest major dual support composer pipe' => [
                'branch' => $this->offsetMajorVersion($lowestMajor, -3),
                'phpConstraint' => '^7.4 | ^' . $phpLowestMajor[0],
                'expected' => [
                    [
                        'installer_version' => '',
                        'php' => $phpLowestMajor[0],
                        'db' => DB_MYSQL_57,
                        'composer_require_extra' => '',
                        'composer_args' => '--prefer-lowest',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'false',
                        'name' => $phpLowestMajor[0] . ' prf-low mysql57 phpunit all',
                    ],
                    [
                        'installer_version' => '',
                        'php' => $phpLowestMajor[1],
                        'db' => DB_MARIADB,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'false',
                        'name' => $phpLowestMajor[1] . ' mariadb phpunit all',
                    ],
                    [
                        'installer_version' => '',
                        'php' => $phpLowestMajor[2],
                        'db' => DB_MYSQL_80,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'false',
                        'name' => $phpLowestMajor[2] . ' mysql80 phpunit all',
                    ],
                    [
                        'installer_version' => '',
                        'php' => $phpLowestMajor[2],
                        'db' => DB_MYSQL_84,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'false',
                        'name' => $phpLowestMajor[2] . ' mysql84 phpunit all',
                    ],
                ],
            ],
            'current major' => [
                'branch' => $this->offsetMajorVersion($highestMajor, -3),
                'phpConstraint' => '^' . $phpHighestMajor[0],
                'expected' => [
                    [
                        'installer_version' => '',
                        'php' => $phpHighestMajor[0],
                        'db' => DB_MARIADB,
                        'composer_require_extra' => '',
                        'composer_args' => '--prefer-lowest',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'false',
                        'name' => $phpHighestMajor[0] . ' prf-low mariadb phpunit all',
                    ],
                    [
                        'installer_version' => '',
                        'php' => $phpHighestMajor[0],
                        'db' => DB_MYSQL_80,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'false',
                        'name' => $phpHighestMajor[0] . ' mysql80 phpunit all',
                    ],
                    [
                        'installer_version' => '',
                        'php' => $phpHighestMajor[1],
                        'db' => DB_MYSQL_84,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'false',
                        'name' => $phpHighestMajor[1] . ' mysql84 phpunit all',
                    ],
                ],
            ],
        ];

        // Make sure we can deal with pre-release majors
        if (array_key_exists($highestMajorPlus1, MetaData::PHP_VERSIONS_FOR_CMS_RELEASES)) {
            $phpHighestMajorPlus1 = MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[$highestMajorPlus1];
            $scenarios['next major'] = [
                'branch' => $this->offsetMajorVersion($highestMajorPlus1, -3),
                'phpConstraint' => '^' . $phpHighestMajorPlus1[0],
                'expected' => [
                    [
                        'installer_version' => '',
                        'php' => $phpHighestMajorPlus1[0],
                        'db' => DB_MARIADB,
                        'composer_require_extra' => '',
                        'composer_args' => '--prefer-lowest',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'false',
                        'name' => $phpHighestMajorPlus1[0] . ' prf-low mariadb phpunit all',
                    ],
                    [
                        'installer_version' => '',
                        'php' => $phpHighestMajorPlus1[0],
                        'db' => DB_MYSQL_80,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'false',
                        'name' => $phpHighestMajorPlus1[0] . ' mysql80 phpunit all',
                    ],
                    [
                        'installer_version' => '',
                        'php' => $phpHighestMajorPlus1[1],
                        'db' => DB_MYSQL_84,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'name_suffix' => '',
                        'phpunit' => 'true',
                        'phpunit_suite' => 'all',
                        'phplinting' => 'false',
                        'phpcoverage' => 'false',
                        'endtoend' => 'false',
                        'endtoend_suite' => 'root',
                        'endtoend_config' => '',
                        'endtoend_tags' => '',
                        'js' => 'false',
                        'doclinting' => 'false',
                        'needs_full_setup' => 'false',
                        'name' => $phpHighestMajorPlus1[1] . ' mysql84 phpunit all',
                    ],
                ],
            ];
        }
        return $scenarios;
    }

    /**
     * @dataProvider provideParentBranch
     */
    public function testParentBranch(string $yml, string $expected)
    {
        if (!function_exists('yaml_parse')) {
            $this->markTestSkipped('yaml extension is not installed');
        }
        try {
            $this->writeInstallerBranchesJson();
            $creator = new JobCreator();
            $json = json_decode($creator->createJson($yml));
            $this->assertSame($expected, $json->include[0]->installer_version);
        } finally {
            unlink('__installer_branches.json');
        }
    }

    private function getGenericYml(): string
    {
        return <<<EOT
        endtoend: true
        js: true
        phpcoverage: false
        phpcoverage_force_off: false
        phplinting: true
        phpunit: true
        doclinting: true
        simple_matrix: false
        composer_install: false
        EOT;
    }

    public function provideParentBranch(): array
    {
        $lowestMajor = MetaData::LOWEST_SUPPORTED_CMS_MAJOR;
        $lowestMajorCurrentMinor = $this->getCurrentMinorInstallerVersion($lowestMajor) . '.x-dev';
        return [
            [
                implode("\n", [
                    $this->getGenericYml(),
                    <<<EOT
                    github_repository: 'myaccount/silverstripe-versioned'
                    github_my_ref: 'myaccount-patch-1'
                    parent_branch: '$lowestMajor.10'
                    EOT
                ]),
                $lowestMajor . '.10.x-dev'
            ],
            [
                implode("\n", [
                    $this->getGenericYml(),
                    <<<EOT
                    github_repository: 'myaccount/silverstripe-versioned'
                    github_my_ref: 'myaccount-patch-1'
                    parent_branch: 'burger'
                    EOT
                ]),
                $lowestMajorCurrentMinor
            ],
        ];
    }

    /**
     * @dataProvider provideGetInputsValid
     */
    public function testGetInputsValid(string $yml, array $expected)
    {
        if (!function_exists('yaml_parse')) {
            $this->markTestSkipped('yaml extension is not installed');
        }
        $creator = new JobCreator();
        $actual = $creator->getInputs($yml);
        $this->assertSame($expected, $actual);
    }

    public function provideGetInputsValid(): array
    {
        return [
            [
                implode("\n", [
                    $this->getGenericYml(),
                    <<<EOT
                    github_repository: 'myaccount/silverstripe-versioned'
                    github_my_ref: 'pulls/1.10/module-standards'
                    EOT
                ]),
                [
                    'endtoend' => true,
                    'js' => true,
                    'phpcoverage' => false,
                    'phpcoverage_force_off' => false,
                    'phplinting' => true,
                    'phpunit' => true,
                    'doclinting' => true,
                    'simple_matrix' => false,
                    'composer_install' => false,
                    'github_repository' => 'myaccount/silverstripe-versioned',
                    'github_my_ref'=> 'pulls/1.10/module-standards'
                ]
            ],
        ];
    }

    /**
     * @dataProvider provideGetInputsInvalid
     */
    public function testGetInputsInvalid(string $yml, string $expectedMessage)
    {
        if (!function_exists('yaml_parse')) {
            $this->markTestSkipped('yaml extension is not installed');
        }
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($expectedMessage);
        $creator = new JobCreator();
        $creator->getInputs($yml);
    }

    public function provideGetInputsInvalid(): array
    {
        return [
            // missing quotes around github_my_ref (would turn into an int, so 1.10 becomes 1.1)
            [
                <<<EOT
                github_my_ref: 1.10
                EOT,
                'github_my_ref needs to be surrounded by single-quotes'
            ],
            [
                <<<EOT
                parent_branch: 1.10
                EOT,
                'parent_branch needs to be surrounded by single-quotes'
            ],
            // invalid yml
            [
                <<<EOT
                this: --
                    is: - total: ' nonsense
                    "
                EOT,
                'Failed to parse yml'
            ],
        ];
    }

    /**
     * @dataProvider provideGetPhpVersion
     */
    public function testGetPhpVersion($composerPhpConstraint, $expectedPhps): void
    {
        if (!function_exists('yaml_parse')) {
            $this->markTestSkipped('yaml extension is not installed');
        }
        $lowestMajor = MetaData::LOWEST_SUPPORTED_CMS_MAJOR;
        $repo = 'silverstripe-framework';
        $yml = implode("\n", [
            $this->getGenericYml(),
            <<<EOT
            github_repository: 'myaccount/$repo'
            github_my_ref: '$lowestMajor.4'
            EOT
        ]);
        try {
            $creator = new JobCreator();
            $creator->composerJsonPath = '__composer.json';
            $composer = new stdClass();
            $composer->require = new stdClass();
            if ($composerPhpConstraint) {
                $composer->require->php = $composerPhpConstraint;
            }
            file_put_contents('__composer.json', json_encode($composer, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES));
            $json = json_decode($creator->createJson($yml));
            foreach ($json->include as $i => $job) {
                $expectedPhp = $expectedPhps[$i];
                $this->assertSame($expectedPhp, $job->php, "{$i}th entry");
            }
        } finally {
            unlink('__composer.json');
        }
    }

    public function provideGetPhpVersion(): array
    {
        // NOTE: These will need to be updated to deal with cross-major PHP versions (e.g. PHP 8 and PHP 9)
        // When that time comes, refer to when this had CMS 4 PHP versions in it.
        $phpLowestMajor = MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[MetaData::LOWEST_SUPPORTED_CMS_MAJOR];
        return [
            'no constraint' => [
                'composerPhpConstraint' => '',
                'expectedPhps' => [...$phpLowestMajor, $phpLowestMajor[2]],
            ],
            'all versions' => [
                'composerPhpConstraint' => '*',
                'expectedPhps' => [...$phpLowestMajor, $phpLowestMajor[2]],
            ],
            'all minor versions' => [
                'composerPhpConstraint' => '*.*',
                'expectedPhps' => [...$phpLowestMajor, $phpLowestMajor[2]],
            ],
            '|| constraint' => [
                'composerPhpConstraint' => '^7.4 || ^' . $phpLowestMajor[0],
                'expectedPhps' => [...$phpLowestMajor, $phpLowestMajor[2]],
            ],
            '|| constraint and start from not-lowest version' => [
                'composerPhpConstraint' => '^7.4 || ^' . $phpLowestMajor[1],
                'expectedPhps' => [$phpLowestMajor[1], $phpLowestMajor[1], $phpLowestMajor[2], $phpLowestMajor[2]],
            ],
            '^ constraint' => [
                'composerPhpConstraint' => '^' . $phpLowestMajor[0],
                'expectedPhps' => [...$phpLowestMajor, $phpLowestMajor[2]],
            ],
            '^ constraint and start from not-lowest version' => [
                'composerPhpConstraint' => '^' . $phpLowestMajor[1],
                'expectedPhps' => [$phpLowestMajor[1], $phpLowestMajor[1], $phpLowestMajor[2], $phpLowestMajor[2]],
            ],
            '^ constraint with full patch' => [
                'composerPhpConstraint' => '^' . $phpLowestMajor[1] . '.3',
                'expectedPhps' => [$phpLowestMajor[1], $phpLowestMajor[1], $phpLowestMajor[2], $phpLowestMajor[2]],
            ],
            '~ constraint' => [
                'composerPhpConstraint' => '~' . $phpLowestMajor[0],
                'expectedPhps' => [...$phpLowestMajor, $phpLowestMajor[2]],
            ],
            '~ constraint and start from not-lowest version' => [
                'composerPhpConstraint' => '~' . $phpLowestMajor[1],
                'expectedPhps' => [$phpLowestMajor[1], $phpLowestMajor[1], $phpLowestMajor[2], $phpLowestMajor[2]],
            ],
            '~ constraint with full patch' => [
                'composerPhpConstraint' => '~' . $phpLowestMajor[1] . '.0',
                'expectedPhps' => [$phpLowestMajor[1], $phpLowestMajor[1], $phpLowestMajor[1], $phpLowestMajor[1]],
            ],
            '> constraint' => [
                'composerPhpConstraint' => '>' . $phpLowestMajor[0],
                'expectedPhps' => [$phpLowestMajor[1], $phpLowestMajor[1], $phpLowestMajor[2], $phpLowestMajor[2]],
            ],
            '>= constraint' => [
                'composerPhpConstraint' => '>=' . $phpLowestMajor[0],
                'expectedPhps' => [...$phpLowestMajor, $phpLowestMajor[2]],
            ],
            '< constraint' => [
                'composerPhpConstraint' => '<' . $phpLowestMajor[2],
                'expectedPhps' => [$phpLowestMajor[0], $phpLowestMajor[1], $phpLowestMajor[1], $phpLowestMajor[1]],
            ],
            '<= constraint' => [
                'composerPhpConstraint' => '<=' . $phpLowestMajor[2],
                'expectedPhps' => [...$phpLowestMajor, $phpLowestMajor[2]],
            ],
            'explicit version' => [
                'composerPhpConstraint' => $phpLowestMajor[1] . '.3',
                'expectedPhps' => [$phpLowestMajor[1], $phpLowestMajor[1], $phpLowestMajor[1], $phpLowestMajor[1]],
            ],
            'explicit minor with * patch' => [
                'composerPhpConstraint' => $phpLowestMajor[1] . '.*',
                'expectedPhps' => [$phpLowestMajor[1], $phpLowestMajor[1], $phpLowestMajor[1], $phpLowestMajor[1]],
            ],
            'explicit major with * minor' => [
                'composerPhpConstraint' => substr($phpLowestMajor[0], 0, 1) . '.*',
                'expectedPhps' => [...$phpLowestMajor, $phpLowestMajor[2]],
            ],
            'range constraint' => [
                'composerPhpConstraint' => '>=' . $phpLowestMajor[1] . ' <' . $phpLowestMajor[2],
                'expectedPhps' => [$phpLowestMajor[1], $phpLowestMajor[1], $phpLowestMajor[1], $phpLowestMajor[1]],
            ],
            '^ constraint less than something' => [
                'composerPhpConstraint' => '^' . substr($phpLowestMajor[0], 0, 1) . ' <' . $phpLowestMajor[2],
                'expectedPhps' => [$phpLowestMajor[0], $phpLowestMajor[1], $phpLowestMajor[1], $phpLowestMajor[1]],
            ],
            'range constraint other syntax' => [
                'composerPhpConstraint' => $phpLowestMajor[1] . '-' . $phpLowestMajor[2],
                'expectedPhps' => [$phpLowestMajor[1], $phpLowestMajor[1], $phpLowestMajor[2], $phpLowestMajor[2]],
            ],
        ];
    }

    /**
     * @dataProvider provideDynamicMatrix
     */
    public function testDynamicMatrix(string $isDynamic, int $jobCount)
    {
        if (!function_exists('yaml_parse')) {
            $this->markTestSkipped('yaml extension is not installed');
        }
        $yml = implode("\n", [
            $this->getGenericYml(),
            <<<EOT
            github_repository: 'myaccount/somerepo'
            github_my_ref: 'somebranch'
            EOT
        ]);
        if ($isDynamic !== '') {
            $yml .= "\ndynamic_matrix: $isDynamic";
        }
        try {
            $this->writeInstallerBranchesJson();
            $creator = new JobCreator();
            $json = json_decode($creator->createJson($yml));
            $this->assertSame($jobCount, count($json->include));
        } finally {
            unlink('__installer_branches.json');
        }
    }

    public function provideDynamicMatrix(): array
    {
        // We expect 4 dynamic jobs for the lowest supported major
        // one for each mysql57, mysql80, mysql84, mariadb
        // Note this will change when CMS 6 becomes the lowest major
        return [
            ['true', 4],
            ['false', 0],
            ['', 4],
        ];
    }

    /**
     * @dataProvider provideGitHubMyRefTags
     */
    public function testGitHubMyRefTags(string $githubMyRef, string $expectedInstallerVersion)
    {
        if (!function_exists('yaml_parse')) {
            $this->markTestSkipped('yaml extension is not installed');
        }
        $yml = implode("\n", [
            $this->getGenericYml(),
            <<<EOT
            github_repository: 'silverstripe/silverstripe-framework'
            github_my_ref: '$githubMyRef'
            EOT
        ]);
        $creator = new JobCreator();
        $this->assertStringContainsString(
            "\"installer_version\":\"$expectedInstallerVersion\"",
            $creator->createJson($yml)
        );
    }

    public function provideGitHubMyRefTags(): array
    {
        return [
            ['4.10', '4.10.x-dev'],
            ['4.10.6', '4.10.x-dev'],
            ['5.0.0-beta2', '5.0.0-beta1'],
        ];
    }

    private function writeComposerJson(array $composerDeps, string $repoType = '', $filename = '__composer.json')
    {
        $composer = new stdClass();
        if ($repoType) {
            $composer->type = $repoType;
        }
        $composer->require = new stdClass();
        foreach ($composerDeps as $dep => $version) {
            $composer->require->{$dep} = $version;
        }
        file_put_contents($filename, json_encode($composer, JSON_UNESCAPED_SLASHES));
    }

    private function writeInstallerBranchesJson()
    {
        $installerBranchesJson = $this->getInstallerBranchesJson();
        file_put_contents('__installer_branches.json', json_encode($installerBranchesJson, JSON_UNESCAPED_SLASHES));
    }

    /**
     * @dataProvider provideGetInstallerVersionFromComposer
     */
    public function testGetInstallerVersionFromComposer(
        string $githubRepository,
        string $branch,
        array $composerDeps,
        string $repoType,
        string $expected
    ): void {
        if (!function_exists('yaml_parse')) {
            $this->markTestSkipped('yaml extension is not installed');
        }
        $yml = implode("\n", [
            $this->getGenericYml(),
            <<<EOT
            github_repository: '$githubRepository'
            github_my_ref: '$branch'
            EOT
        ]);
        try {
            $this->writeComposerJson($composerDeps, $repoType);
            $this->writeInstallerBranchesJson();
            $creator = new JobCreator();
            $creator->composerJsonPath = '__composer.json';
            $json = json_decode($creator->createJson($yml));
            $this->assertSame($expected, $json->include[0]->installer_version);
        } finally {
            unlink('__composer.json');
            unlink('__installer_branches.json');
        }
    }

    public function provideGetInstallerVersionFromComposer(): array
    {
        $lowestMajor = MetaData::LOWEST_SUPPORTED_CMS_MAJOR;
        $highestMajor = MetaData::HIGHEST_STABLE_CMS_MAJOR;
        $highestMajorPlus1 = $this->offsetMajorVersion($highestMajor, 1);
        $scenarios = [
            // priority given to branch name
            ['myaccount/silverstripe-framework', $lowestMajor, [], 'silverstripe-module', $lowestMajor . '.x-dev'],
            ['myaccount/silverstripe-framework', $lowestMajor . '.10', [], 'silverstripe-vendormodule', $lowestMajor . '.10.x-dev'],
            ['myaccount/silverstripe-framework', 'burger', [], 'silverstripe-theme', $lowestMajor . '.4.x-dev'],
            ['myaccount/silverstripe-framework', $highestMajor, [], 'silverstripe-recipe', $highestMajor . '.x-dev'],
            ['myaccount/silverstripe-framework', $highestMajor . '.10', [], 'silverstripe-vendormodule', $highestMajor . '.10.x-dev'],
            // fallback to looking at deps in composer.json, use current minor of installer .x-dev
            // lowest major
            ['myaccount/silverstripe-admin', 'mybranch', ['silverstripe/framework' => $lowestMajor . '.x-dev'], 'silverstripe-module', $lowestMajor . '.x-dev'],
            ['myaccount/silverstripe-admin', 'mybranch', ['silverstripe/framework' => $lowestMajor . '.0.x-dev'], 'silverstripe-vendormodule', $lowestMajor . '.0.x-dev'],
            ['myaccount/silverstripe-admin', 'mybranch', ['silverstripe/framework' => '^' . $lowestMajor], 'silverstripe-theme', $lowestMajor . '.4.x-dev'],
            ['myaccount/silverstripe-somemodule', 'mybranch', ['silverstripe/cms' => '^' . $lowestMajor], 'silverstripe-recipe', $lowestMajor . '.4.x-dev'],
            ['myaccount/silverstripe-somemodule', 'mybranch', ['silverstripe/admin' => '^' . $this->offsetMajorVersion($lowestMajor, -3)], 'silverstripe-vendormodule', $lowestMajor . '.4.x-dev'],
            ['myaccount/silverstripe-somemodule', '3', ['silverstripe/framework' => '^' . $lowestMajor], 'silverstripe-vendormodule', $lowestMajor . '.x-dev'],
            ['myaccount/silverstripe-somemodule', '3', ['silverstripe/framework' => '^' . $lowestMajor], 'package', ''],
            ['myaccount/silverstripe-somemodule', '3', ['silverstripe/framework' => '^' . $lowestMajor], '', ''],
            ['myaccount/silverstripe-somemodule', '3', [], '', ''],
            // highest major
            ['myaccount/silverstripe-admin', 'mybranch', ['silverstripe/framework' => $highestMajor . '.x-dev'], 'silverstripe-module', $highestMajor . '.x-dev'],
            ['myaccount/silverstripe-admin', 'mybranch', ['silverstripe/framework' => $highestMajor . '.0.x-dev'], 'silverstripe-vendormodule', $highestMajor . '.0.x-dev'],
            ['myaccount/silverstripe-admin', 'mybranch', ['silverstripe/framework' => '^' . $highestMajor], 'silverstripe-theme', $highestMajor . '.0.x-dev'],
            ['myaccount/silverstripe-somemodule', 'mybranch', ['silverstripe/cms' => '^' . $highestMajor], 'silverstripe-recipe', $highestMajor . '.0.x-dev'],
            ['myaccount/silverstripe-somemodule', 'mybranch', ['silverstripe/admin' => '^' . $this->offsetMajorVersion($highestMajor, -3)], 'silverstripe-vendormodule', $highestMajor . '.0.x-dev'],
            ['myaccount/silverstripe-somemodule', '4', ['silverstripe/framework' => '^' . $highestMajor], 'silverstripe-vendormodule', $highestMajor . '.x-dev'],
            ['myaccount/silverstripe-somemodule', '4', ['silverstripe/framework' => '^' . $highestMajor], 'package', ''],
            ['myaccount/silverstripe-somemodule', '4', ['silverstripe/framework' => '^' . $highestMajor], '', ''],
            ['myaccount/silverstripe-somemodule', '4', [], '', ''],
            // recipe-plugin and vendor-plugin do not override framework
            ['myaccount/silverstripe-admin', 'mybranch', ['silverstripe/recipe-plugin' => '^' . $this->offsetMajorVersion($lowestMajor, -3), 'silverstripe/framework' => '^' . $highestMajor], 'silverstripe-vendormodule', $highestMajor . '.0.x-dev'],
            ['myaccount/silverstripe-admin', 'mybranch', ['silverstripe/vendor-plugin' => '^' . $this->offsetMajorVersion($lowestMajor, -3), 'silverstripe/framework' => '^' . $highestMajor], 'silverstripe-vendormodule', $highestMajor . '.0.x-dev'],
        ];

        // Make sure we can deal with pre-release majors
        if (array_key_exists($highestMajorPlus1, MetaData::PHP_VERSIONS_FOR_CMS_RELEASES)) {
            $scenarios = array_merge($scenarios, [
                ['myaccount/silverstripe-framework', $highestMajorPlus1, [], 'silverstripe-recipe', $highestMajorPlus1 . '.x-dev'],
                ['myaccount/silverstripe-framework', $highestMajorPlus1 . '.10', [], 'silverstripe-vendormodule', $highestMajorPlus1 . '.10.x-dev'],
                ['myaccount/silverstripe-admin', 'mybranch', ['silverstripe/framework' => $highestMajorPlus1 . '.x-dev'], 'silverstripe-module', $highestMajorPlus1 . '.x-dev'],
                ['myaccount/silverstripe-admin', 'mybranch', ['silverstripe/framework' => $highestMajorPlus1 . '.0.x-dev'], 'silverstripe-vendormodule', $highestMajorPlus1 . '.0.x-dev'],
                ['myaccount/silverstripe-admin', 'mybranch', ['silverstripe/framework' => '^' . $highestMajorPlus1], 'silverstripe-theme', $highestMajorPlus1 . '.0.x-dev'],
                ['myaccount/silverstripe-somemodule', 'mybranch', ['silverstripe/cms' => '^' . $highestMajorPlus1], 'silverstripe-recipe', $highestMajorPlus1 . '.0.x-dev'],
                ['myaccount/silverstripe-somemodule', 'mybranch', ['silverstripe/admin' => '^' . $this->offsetMajorVersion($highestMajorPlus1, -3)], 'silverstripe-vendormodule', $highestMajorPlus1 . '.0.x-dev'],
                ['myaccount/silverstripe-somemodule', '4', ['silverstripe/framework' => '^' . $highestMajorPlus1], 'silverstripe-vendormodule', $highestMajorPlus1 . '.x-dev'],
                ['myaccount/silverstripe-somemodule', '4', ['silverstripe/framework' => '^' . $highestMajorPlus1], 'package', ''],
                ['myaccount/silverstripe-somemodule', '4', ['silverstripe/framework' => '^' . $highestMajorPlus1], '', ''],
            ]);
        }
        return $scenarios;
    }

    /**
     * @dataProvider provideComposerInstall
     */
    public function testComposerInstall(
        string $composerInstall,
        string $configPlatformPhp,
        string $frameworkVersion,
        string $repoType,
        array $expected
    ): void {
        $yml = implode("\n", [
            str_replace('composer_install: false', 'composer_install: ' . $composerInstall, $this->getGenericYml()),
            <<<EOT
            github_repository: 'silverstripe/fake-module'
            github_my_ref: 'mybranch'
            EOT
        ]);
        try {
            $creator = new JobCreator();
            $creator->composerJsonPath = '__composer.json';
            $composer = new stdClass();
            if ($repoType) {
                $composer->type = $repoType;
            }
            $composer->require = new stdClass();
            $composer->require->{'silverstripe/framework'} = $frameworkVersion;
            if ($configPlatformPhp) {
                $composer->config = new stdClass();
                $composer->config->platform = new stdClass();
                $composer->config->platform->php = $configPlatformPhp;
            }
            file_put_contents('__composer.json', json_encode($composer, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES));
            $json = json_decode($creator->createJson($yml));
            $actual = array_map(function ($include) {
                return $include->name;
            }, $json->include);
            $this->assertSame($expected, $actual);
        } finally {
            unlink('__composer.json');
        }
    }

    public function provideComposerInstall(): array
    {
        $lowestMajor = MetaData::LOWEST_SUPPORTED_CMS_MAJOR;
        $highestMajor = MetaData::HIGHEST_STABLE_CMS_MAJOR;
        $highestMajorPlus1 = $this->offsetMajorVersion($highestMajor, 1);
        $phpLowestMajor = MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[$lowestMajor];
        $phpHighestMajor = MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[$highestMajor];
        $scenarios = [
            'composerinstall_nophpversion_framework lowest' => [
                'true',
                '',
                $lowestMajor . '.x-dev',
                'silverstripe-module',
                [
                    $phpLowestMajor[0] . ' mysql57 phpunit all'
                ]
            ],
            'composerinstall_nophpversion_framework highest' => [
                'true',
                '',
                $highestMajor . '.x-dev',
                'silverstripe-vendormodule',
                [
                    $phpHighestMajor[0] . ' mysql80 phpunit all'
                ]
            ],
            'composerinstall_definedphpversion_framework lowest' => [
                'true',
                '21.99',
                $lowestMajor . '.x-dev',
                'silverstripe-recipe',
                [
                    '21.99 mysql57 phpunit all'
                ]
            ],
            'composerinstall_invalidphpversion_framework lowest' => [
                'true',
                'fish',
                $lowestMajor . '.x-dev',
                'silverstripe-theme',
                [
                    $phpLowestMajor[0] . ' mysql57 phpunit all'
                ]
            ],
            'composerupgrade_nophpversion_framework lowest' => [
                'false',
                '',
                $lowestMajor . '.x-dev',
                'silverstripe-module',
                [
                    $phpLowestMajor[0] . ' prf-low mysql57 phpunit all',
                    $phpLowestMajor[1] . ' mariadb phpunit all',
                    $phpLowestMajor[2] . ' mysql80 phpunit all',
                    $phpLowestMajor[2] . ' mysql84 phpunit all',
                ]
            ],
            'composerupgrade_nophpversion_framework highest' => [
                'false',
                '',
                $highestMajor . '.x-dev',
                'silverstripe-vendormodule',
                [
                    $phpHighestMajor[0] . ' prf-low mariadb phpunit all',
                    $phpHighestMajor[0] . ' mysql80 phpunit all',
                    $phpHighestMajor[1] . ' mysql84 phpunit all',
                ]
            ],
            'composerupgrade_definedphpversion_framework lowest' => [
                'false',
                '21.99',
                $lowestMajor . '.x-dev',
                'silverstripe-recipe',
                [
                    $phpLowestMajor[0] . ' prf-low mysql57 phpunit all',
                    $phpLowestMajor[1] . ' mariadb phpunit all',
                    $phpLowestMajor[2] . ' mysql80 phpunit all',
                    $phpLowestMajor[2] . ' mysql84 phpunit all',
                ]
            ],
            'composerupgrade_invalidphpversion_framework lowest' => [
                'false',
                'fish',
                $lowestMajor . '.x-dev',
                'silverstripe-theme',
                [
                    $phpLowestMajor[0] . ' prf-low mysql57 phpunit all',
                    $phpLowestMajor[1] . ' mariadb phpunit all',
                    $phpLowestMajor[2] . ' mysql80 phpunit all',
                    $phpLowestMajor[2] . ' mysql84 phpunit all',
                ]
            ],
            'composerupgrade_invalidphpversion_framework highest' => [
                'false',
                'fish',
                $highestMajor . '.x-dev',
                'silverstripe-theme',
                [
                    $phpHighestMajor[0] . ' prf-low mariadb phpunit all',
                    $phpHighestMajor[0] . ' mysql80 phpunit all',
                    $phpHighestMajor[1] . ' mysql84 phpunit all',
                ]
            ],
            'composerupgrade_nophpversion_framework lowest with minor' => [
                'false',
                '',
                $lowestMajor . '.1.x-dev',
                'silverstripe-module',
                [
                    $phpLowestMajor[0] . ' prf-low mysql57 phpunit all',
                    $phpLowestMajor[0] . ' mariadb phpunit all',
                    $phpLowestMajor[1] . ' mysql80 phpunit all',
                    $phpLowestMajor[1] . ' mysql84 phpunit all',
                ]
            ],
            'composerupgrade_definedphpversion_framework lowest with minor' => [
                'false',
                '21.99',
                $lowestMajor . '.1.x-dev',
                'silverstripe-vendormodule',
                [
                    $phpLowestMajor[0] . ' prf-low mysql57 phpunit all',
                    $phpLowestMajor[0] . ' mariadb phpunit all',
                    $phpLowestMajor[1] . ' mysql80 phpunit all',
                    $phpLowestMajor[1] . ' mysql84 phpunit all',
                ]
            ],
            'composerupgrade_invalidphpversion_framework lowest with minor' => [
                'false',
                'fish',
                $lowestMajor . '.1.x-dev',
                'silverstripe-recipe',
                [
                    $phpLowestMajor[0] . ' prf-low mysql57 phpunit all',
                    $phpLowestMajor[0] . ' mariadb phpunit all',
                    $phpLowestMajor[1] . ' mysql80 phpunit all',
                    $phpLowestMajor[1] . ' mysql84 phpunit all',
                ]
            ],
            'composerupgrade_invalidphpversion_notmodule1' => [
                'false',
                'fish',
                '*',
                'package',
                [
                    $phpLowestMajor[0] . ' prf-low mysql57 phpunit all',
                    $phpLowestMajor[1] . ' mariadb phpunit all',
                    $phpLowestMajor[2] . ' mysql80 phpunit all',
                    $phpLowestMajor[2] . ' mysql84 phpunit all',
                ]
            ],
            'composerupgrade_invalidphpversion_notmodule2' => [
                'false',
                'fish',
                '*',
                '',
                [
                    $phpLowestMajor[0] . ' prf-low mysql57 phpunit all',
                    $phpLowestMajor[1] . ' mariadb phpunit all',
                    $phpLowestMajor[2] . ' mysql80 phpunit all',
                    $phpLowestMajor[2] . ' mysql84 phpunit all',
                ]
            ],
        ];

        // Make sure we can deal with pre-release majors
        if (array_key_exists($highestMajorPlus1, MetaData::PHP_VERSIONS_FOR_CMS_RELEASES)) {
            $phpHighestMajorPlus1 = MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[$highestMajorPlus1];
            $scenarios['composerinstall_nophpversion_framework next'] = [
                'true',
                '',
                $highestMajorPlus1 . '.x-dev',
                'silverstripe-vendormodule',
                [
                    $phpHighestMajorPlus1[0] . ' mysql80 phpunit all'
                ]
            ];
            $scenarios['composerupgrade_nophpversion_framework next'] = [
                'false',
                '',
                $highestMajorPlus1 . '.x-dev',
                'silverstripe-vendormodule',
                [
                    $phpHighestMajorPlus1[0] . ' prf-low mariadb phpunit all',
                    $phpHighestMajorPlus1[0] . ' mysql80 phpunit all',
                    $phpHighestMajorPlus1[1] . ' mysql84 phpunit all',
                ]
            ];
            $scenarios['composerupgrade_invalidphpversion_framework next'] = [
                'false',
                'fish',
                $highestMajorPlus1 . '.x-dev',
                'silverstripe-theme',
                [
                    $phpHighestMajorPlus1[0] . ' prf-low mariadb phpunit all',
                    $phpHighestMajorPlus1[0] . ' mysql80 phpunit all',
                    $phpHighestMajorPlus1[1] . ' mysql84 phpunit all',
                ]
            ];
        }
        return $scenarios;
    }

    public function testDuplicateJobsRemoved(): void
    {
        $lowestMajor = MetaData::LOWEST_SUPPORTED_CMS_MAJOR;
        $phpLowestMajor = MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[$lowestMajor];
        if (!function_exists('yaml_parse')) {
            $this->markTestSkipped('yaml extension is not installed');
        }
        $yml = implode("\n", [
            $this->getGenericYml(),
            <<<EOT
            github_repository: 'myaccount/silverstripe-framework'
            github_my_ref: '$lowestMajor'
            parent_branch: ''
            extra_jobs:
              - php: {$phpLowestMajor[1]}
                phpunit: true
                phpunit_suite: fish
              - php: {$phpLowestMajor[2]}
                phpunit: true
                phpunit_suite: fish
              - php: {$phpLowestMajor[2]}
                phpunit: true
                phpunit_suite: fish
              - php: {$phpLowestMajor[2]}
                endtoend: true
            EOT
        ]);
        $creator = new JobCreator();
        $json = json_decode($creator->createJson($yml));
        $actual = [];
        foreach ($json->include as $job) {
            $actual[] = $job->name;
        }
        $expected = [
            $phpLowestMajor[0] . ' prf-low mysql57 phpunit all',
            $phpLowestMajor[1] . ' mariadb phpunit all',
            $phpLowestMajor[2] . ' mysql80 phpunit all',
            $phpLowestMajor[2] . ' mysql84 phpunit all',
            $phpLowestMajor[1] . ' mysql57 phpunit fish',
            $phpLowestMajor[2] . ' mysql57 phpunit fish',
            $phpLowestMajor[2] . ' mysql57 endtoend root',
        ];
        $this->assertSame($expected, $actual);
    }

    public function providePhpFallbackDoorman(): array
    {
        $phpLowestMajor = MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[MetaData::LOWEST_SUPPORTED_CMS_MAJOR];
        $phpHighestMajor = MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[MetaData::HIGHEST_STABLE_CMS_MAJOR];
        $scenarios = [
            'php lowest' => [
                'php' => '^' . $phpLowestMajor[0],
                'exception' => false,
                'expected' => [
                    $phpLowestMajor[0] . ' prf-low mariadb phpunit all',
                    $phpLowestMajor[1] . ' mysql80 phpunit all',
                    $phpLowestMajor[2] . ' mysql84 phpunit all',
                ],
            ],
            'php highest' => [
                'php' => '^' . $phpHighestMajor[0],
                'exception' => false,
                'expected' => [
                    $phpHighestMajor[0] . ' prf-low mariadb phpunit all',
                    $phpHighestMajor[0] . ' mysql80 phpunit all',
                    $phpHighestMajor[1] . ' mysql84 phpunit all',
                ],
            ],
            'none' => [
                'php' => 'none',
                'exception' => true,
                'expected' => null,
            ],
        ];

        // Make sure we can deal with pre-release majors
        $highestMajorPlus1 = $this->offsetMajorVersion(MetaData::HIGHEST_STABLE_CMS_MAJOR, 1);
        if (array_key_exists($highestMajorPlus1, MetaData::PHP_VERSIONS_FOR_CMS_RELEASES)) {
            $phpHighestMajorPlus1 = MetaData::PHP_VERSIONS_FOR_CMS_RELEASES[$highestMajorPlus1];
            $scenarios['php next'] = [
                'php' => '^' . $phpHighestMajorPlus1[0],
                'exception' => false,
                'expected' => [
                    $phpHighestMajorPlus1[0] . ' prf-low mariadb phpunit all',
                    $phpHighestMajorPlus1[0] . ' mysql80 phpunit all',
                    $phpHighestMajorPlus1[1] . ' mysql84 phpunit all',
                ],
            ];
        }
        return $scenarios;
    }

    /**
     * @dataProvider providePhpFallbackDoorman
     */
    public function testPhpFallbackDoorman(string $php, bool $exception, ?array $expected): void
    {
        if (!function_exists('yaml_parse')) {
            $this->markTestSkipped('yaml extension is not installed');
        }
        if ($exception) {
            $this->expectException(Exception::class);
        }
        try {
            $yml = implode("\n", [
                <<<EOT
                composer_install: false
                endtoend: true
                js: true
                phpcoverage: false
                phpcoverage_force_off: false
                phplinting: true
                phpunit: true
                doclinting: true
                phpunit_skip_suites: ''
                dynamic_matrix: true
                simple_matrix: false
                github_repository: 'silverstripe/doorman'
                github_my_ref: '5'
                parent_branch: ''
                EOT
            ]);
            $creator = new JobCreator();
            $creator->composerJsonPath = '__composer.json';
            $this->writeComposerJson(['php' => $php]);
            $creator->githubRepository = 'silverstripe/doorman';
            $creator->repoName = 'doorman';
            $creator->branch = '5';
            $creator->parseRepositoryMetadata();
            $json = json_decode($creator->createJson($yml));
            $actual = array_map(fn($job) => $job->name, $json->include);
            $this->assertSame($expected, $actual);
        } finally {
            if (file_exists('__composer.json')) {
                unlink('__composer.json');
            }
        }
    }
}
