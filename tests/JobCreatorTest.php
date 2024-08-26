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
        return [
            // general test
            ['myaccount/silverstripe-framework', '4', 0, ['phpunit' => true], [
                'installer_version' => '',
                'php' => '7.4',
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
            ['myaccount/silverstripe-installer', '4.10', 99, [], [
                'php' => max(MetaData::PHP_VERSIONS_FOR_CMS_RELEASES['4.10'])
            ]],
            ['myaccount/silverstripe-installer', '4.11', 99, [], [
                'php' => max(MetaData::PHP_VERSIONS_FOR_CMS_RELEASES['4.11'])
            ]],
            ['myaccount/silverstripe-installer', '4', 99, [], [
                'php' => max(MetaData::PHP_VERSIONS_FOR_CMS_RELEASES['4'])
            ]],
            ['myaccount/silverstripe-installer', '5.0', 99, [], [
                'php' => max(MetaData::PHP_VERSIONS_FOR_CMS_RELEASES['5.0'])
            ]],
            ['myaccount/silverstripe-installer', '5', 99, [], [
                'php' => max(MetaData::PHP_VERSIONS_FOR_CMS_RELEASES['5'])
            ]],
            ['myaccount/silverstripe-installer', '6', 99, [], [
                'php' => max(MetaData::PHP_VERSIONS_FOR_CMS_RELEASES['6'])
            ]],
        ];
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
        return [
            ['name' => '4'],
            ['name' => '4.10'],
            ['name' => '4.11'],
            ['name' => '4.12'],
            ['name' => '4.13'],
            ['name' => '5'],
            ['name' => '5.1'],
            ['name' => '5.2'],
            ['name' => '6'],
            ['name' => '6.0'],
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

    public function provideGetInstallerVersion(): array
    {
        $nextMinorCms4 = '4.x-dev';
        $currentMinorCms4 = $this->getCurrentMinorInstallerVersion('4') . '.x-dev';
        return [
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
            // lockstepped repo with 4.* naming
            'framework1' => ['myaccount/silverstripe-framework', '4', '4.x-dev'],
            'framework2' => ['myaccount/silverstripe-framework', '4.10', '4.10.x-dev'],
            'framework3' => ['myaccount/silverstripe-framework', 'burger', $currentMinorCms4],
            'framework4' => ['myaccount/silverstripe-framework', 'pulls/4/mybugfix', '4.x-dev'],
            'framework5' => ['myaccount/silverstripe-framework', 'pulls/4.10/mybugfix', '4.10.x-dev'],
            'framework6' => ['myaccount/silverstripe-framework', 'pulls/burger/myfeature', $currentMinorCms4],
            'framework7' => ['myaccount/silverstripe-framework', '5', '5.x-dev'],
            'framework8' => ['myaccount/silverstripe-framework', '5.1', '5.1.x-dev'],
            'framework9' => ['myaccount/silverstripe-framework', '6', '6.x-dev'],
            // lockstepped repo with 1.* naming
            'admin1' => ['myaccount/silverstripe-admin', '1', '4.x-dev'],
            'admin2' => ['myaccount/silverstripe-admin', '1.10', '4.10.x-dev'],
            'admin3' => ['myaccount/silverstripe-admin', 'burger', $currentMinorCms4],
            'admin4' => ['myaccount/silverstripe-admin', 'pulls/1/mybugfix', '4.x-dev'],
            'admin5' => ['myaccount/silverstripe-admin', 'pulls/1.10/mybugfix', '4.10.x-dev'],
            'admin6' => ['myaccount/silverstripe-admin', 'pulls/burger/myfeature', $currentMinorCms4],
            'admin7' => ['myaccount/silverstripe-admin', '2', '5.x-dev'],
            'admin8' => ['myaccount/silverstripe-admin', '2.1', '5.1.x-dev'],
            'admin9' => ['myaccount/silverstripe-admin', '3', '6.x-dev'],
            // non-lockedstepped repo
            'tagfield1' => ['myaccount/silverstripe-tagfield', '2', $nextMinorCms4],
            'tagfield2' => ['myaccount/silverstripe-tagfield', '2.9', $currentMinorCms4],
            'tagfield3' => ['myaccount/silverstripe-tagfield', 'burger', $currentMinorCms4],
            'tagfield4' => ['myaccount/silverstripe-tagfield', 'pulls/2/mybugfix', $nextMinorCms4],
            'tagfield5' => ['myaccount/silverstripe-tagfield', 'pulls/2.9/mybugfix', $currentMinorCms4],
            'tagfield6' => ['myaccount/silverstripe-tagfield', 'pulls/burger/myfeature', $currentMinorCms4],
            // non-lockstepped repo, fallback to major version of installer (is missing 6.0 installer branch)
            'tagfield7' => ['myaccount/silverstripe-tagfield', '4.0', '6.x-dev', [['name' => '6']], ['silverstripe/framework' => '^6']],
            // hardcoded repo version
            'session-manager1' => ['myaccount/silverstripe-session-manager', '1', $nextMinorCms4],
            'session-manager2' => ['myaccount/silverstripe-session-manager', '1.2', '4.10.x-dev'],
            'session-manager3' => ['myaccount/silverstripe-session-manager', 'burger', $currentMinorCms4],
            // hardcoded repo version using array
            'html5-1' => ['myaccount/silverstripe-html5', '2', $nextMinorCms4],
            'html5-2' => ['myaccount/silverstripe-html5', '2.2', '4.10.x-dev'],
            'html5-3' => ['myaccount/silverstripe-html5', '2.3', '4.10.x-dev'],
            'html5-4' => ['myaccount/silverstripe-html5', '2.4', '4.11.x-dev'],
            'html5-5' => ['myaccount/silverstripe-html5', 'burger', $currentMinorCms4],
            // force installer unlockedstepped repo
            'serve1' => ['myaccount/silverstripe-serve', '2', $nextMinorCms4],
            'behat1' => ['myaccount/silverstripe-behat-extension', '2', $nextMinorCms4],
            // repos that shouldn't have installer
            'vendor-plugin1' => ['myaccount/vendor-plugin', '1', ''],
            'vendor-plugin2' => ['myaccount/vendor-plugin', '2', ''],
            'recipe-plugin1' => ['myaccount/recipe-plugin', '1', ''],
            // random third-party repo
            'random-notype' => ['myaccount/my-module', '4.0', '', [['name' => '6']], ['silverstripe/framework' => '^6']],
            'random-module' => ['myaccount/my-module', '4.0', '6.x-dev', [['name' => '6']], ['silverstripe/framework' => '^6'], 'silverstripe-module'],
            'random-vendormodule' => ['myaccount/my-module', '4.0', '6.x-dev', [['name' => '6']], ['silverstripe/framework' => '^6'], 'silverstripe-vendormodule'],
            'random-recipe' => ['myaccount/my-module', '4.0', '6.x-dev', [['name' => '6']], ['silverstripe/framework' => '^6'], 'silverstripe-recipe'],
            'random-theme' => ['myaccount/my-module', '4.0', '6.x-dev', [['name' => '6']], ['silverstripe/framework' => '^6'], 'silverstripe-theme'],
            'random-notype-nodeps' => ['myaccount/my-module', '4.0', '', [['name' => '6']]],
        ];
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
        return [
            // behat without @job1/@job2 test
            [
                implode("\n", [
                    $this->getGenericYml(),
                    <<<EOT
                    github_repository: 'myaccount/silverstripe-framework'
                    github_my_ref: '5'
                    parent_branch: ''
                    EOT
                ]),
                true,
                false,
                false,
                [
                    [
                        'installer_version' => '5.x-dev',
                        'php' => '8.1',
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
                        'name' => '8.1 prf-low mysql57 phpunit all',
                    ],
                    [
                        'installer_version' => '5.x-dev',
                        'php' => '8.2',
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
                        'name' => '8.2 mariadb phpunit all',
                    ],
                    [
                        'installer_version' => '5.x-dev',
                        'php' => '8.3',
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
                        'name' => '8.3 mysql80 phpunit all',
                    ],
                    [
                        'installer_version' => '5.x-dev',
                        'php' => '8.1',
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
                        'name' => '8.1 mysql57 endtoend root',
                    ],
                    [
                        'installer_version' => '5.x-dev',
                        'php' => '8.3',
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
                        'name' => '8.3 mysql80 endtoend root',
                    ],
                ]
            ],
            // behat with @job1/@job2 test
            [
                implode("\n", [
                    $this->getGenericYml(),
                    <<<EOT
                    github_repository: 'myaccount/silverstripe-framework'
                    github_my_ref: '5'
                    parent_branch: ''
                    EOT
                ]),
                true,
                true,
                false,
                [
                    [
                        'installer_version' => '5.x-dev',
                        'php' => '8.1',
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
                        'name' => '8.1 prf-low mysql57 phpunit all',
                    ],
                    [
                        'installer_version' => '5.x-dev',
                        'php' => '8.2',
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
                        'name' => '8.2 mariadb phpunit all',
                    ],
                    [
                        'installer_version' => '5.x-dev',
                        'php' => '8.3',
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
                        'name' => '8.3 mysql80 phpunit all',
                    ],
                    [
                        'installer_version' => '5.x-dev',
                        'php' => '8.1',
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
                        'name' => '8.1 mysql57 endtoend root job1',
                    ],
                    [
                        'installer_version' => '5.x-dev',
                        'php' => '8.3',
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
                        'name' => '8.3 mysql80 endtoend root job1',
                    ],
                    [
                        'installer_version' => '5.x-dev',
                        'php' => '8.1',
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
                        'name' => '8.1 mysql57 endtoend root job2',
                    ],
                    [
                        'installer_version' => '5.x-dev',
                        'php' => '8.3',
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
                        'name' => '8.3 mysql80 endtoend root job2',
                    ],
                ]
            ],
            // behat with @job1/@job2 test with missing @job tag
            [
                implode("\n", [
                    $this->getGenericYml(),
                    <<<EOT
                    github_repository: 'myaccount/silverstripe-framework'
                    github_my_ref: '5'
                    parent_branch: ''
                    EOT
                ]),
                true,
                true,
                true,
                []
            ],
            // general test for v4
            [
                implode("\n", [
                    $this->getGenericYml(),
                    <<<EOT
                    github_repository: 'myaccount/silverstripe-framework'
                    github_my_ref: '4.11'
                    parent_branch: ''
                    EOT
                ]),
                false,
                false,
                false,
                [
                    [
                        'installer_version' => '4.11.x-dev',
                        'php' => '7.4',
                        'db' => DB_MYSQL_57,
                        'composer_require_extra' => '',
                        'composer_args' => '--prefer-lowest',
                        'composer_install' => 'false',
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
                        'name' => '7.4 prf-low mysql57 phpunit all',
                    ],
                    [
                        'installer_version' => '4.11.x-dev',
                        'php' => '8.0',
                        'db' => DB_MYSQL_57_PDO,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'composer_install' => 'false',
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
                        'name' => '8.0 mysql57pdo phpunit all',
                    ],
                    [
                        'installer_version' => '4.11.x-dev',
                        'php' => '8.1',
                        'db' => DB_MYSQL_80,
                        'composer_require_extra' => '',
                        'composer_args' => '',
                        'composer_install' => 'false',
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
                        'name' => '8.1 mysql80 phpunit all',
                    ],
                ]
            ],
            // general test for v5
            [
                implode("\n", [
                    $this->getGenericYml(),
                    <<<EOT
                    github_repository: 'myaccount/silverstripe-framework'
                    github_my_ref: '5'
                    parent_branch: ''
                    EOT
                ]),
                false,
                false,
                false,
                [
                    [
                        'installer_version' => '5.x-dev',
                        'php' => '8.1',
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
                        'name' => '8.1 prf-low mysql57 phpunit all',
                    ],
                    [
                        'installer_version' => '5.x-dev',
                        'php' => '8.2',
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
                        'name' => '8.2 mariadb phpunit all',
                    ],
                    [
                        'installer_version' => '5.x-dev',
                        'php' => '8.3',
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
                        'name' => '8.3 mysql80 phpunit all',
                    ],
                ]
            ],
            // general test for v6
            [
                implode("\n", [
                    $this->getGenericYml(),
                    <<<EOT
                    github_repository: 'myaccount/silverstripe-framework'
                    github_my_ref: '6'
                    parent_branch: ''
                    EOT
                ]),
                false,
                false,
                false,
                [
                    [
                        'installer_version' => '6.x-dev',
                        'php' => '8.3',
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
                        'name' => '8.3 prf-low mysql57 phpunit all',
                    ],
                    [
                        'installer_version' => '6.x-dev',
                        'php' => '8.3',
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
                        'name' => '8.3 mariadb phpunit all',
                    ],
                ]
            ],
            // general test for v5.1
            [
                implode("\n", [
                    $this->getGenericYml(),
                    <<<EOT
                    github_repository: 'myaccount/silverstripe-framework'
                    github_my_ref: '5.1'
                    parent_branch: ''
                    EOT
                ]),
                false,
                false,
                false,
                [
                    [
                        'installer_version' => '5.1.x-dev',
                        'php' => '8.1',
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
                        'name' => '8.1 prf-low mysql57 phpunit all',
                    ],
                    [
                        'installer_version' => '5.1.x-dev',
                        'php' => '8.1',
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
                        'name' => '8.1 mariadb phpunit all',
                    ],
                    [
                        'installer_version' => '5.1.x-dev',
                        'php' => '8.2',
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
                        'name' => '8.2 mysql80 phpunit all',
                    ],
                ]
            ],
            // general test for v5.2
            [
                implode("\n", [
                    $this->getGenericYml(),
                    <<<EOT
                    github_repository: 'myaccount/silverstripe-framework'
                    github_my_ref: '5.2'
                    parent_branch: ''
                    EOT
                ]),
                false,
                false,
                false,
                [
                    [
                        'installer_version' => '5.2.x-dev',
                        'php' => '8.1',
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
                        'name' => '8.1 prf-low mysql57 phpunit all',
                    ],
                    [
                        'installer_version' => '5.2.x-dev',
                        'php' => '8.2',
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
                        'name' => '8.2 mariadb phpunit all',
                    ],
                    [
                        'installer_version' => '5.2.x-dev',
                        'php' => '8.3',
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
                        'name' => '8.3 mysql80 phpunit all',
                    ],
                ]
            ],
        ];
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
        $latest = $this->getCurrentMinorInstallerVersion('4') . '.x-dev';
        return [
            [
                implode("\n", [
                    $this->getGenericYml(),
                    <<<EOT
                    github_repository: 'myaccount/silverstripe-versioned'
                    github_my_ref: 'myaccount-patch-1'
                    parent_branch: '4.10'
                    EOT
                ]),
                '4.10.x-dev'
            ],
            [
                implode("\n", [
                    $this->getGenericYml(),
                    <<<EOT
                    github_repository: 'myaccount/silverstripe-versioned'
                    github_my_ref: 'myaccount-patch-1'
                    parent_branch: '4.10-release'
                    EOT
                ]),
                '4.11.x-dev'
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
                $latest
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
        // use a hardcoded entry from INSTALLER_TO_REPO_MINOR_VERSIONS so that we get
        // framework 4.10.x-dev which creates php 7.3 jobs, this is so that this unit test
        // keeps working as we increment the latest version of installer
        $repo = 'silverstripe-elemental-bannerblock';
        $minorVersion = '2.4';
        if (INSTALLER_TO_REPO_MINOR_VERSIONS['4.10'][$repo] != $minorVersion) {
            throw new Exception('Required const is missing for unit testing');
        }
        $yml = implode("\n", [
            $this->getGenericYml(),
            <<<EOT
            github_repository: 'myaccount/$repo'
            github_my_ref: '$minorVersion'
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
                $this->assertSame($expectedPhp, $job->php);
            }
        } finally {
            unlink('__composer.json');
        }
    }

    public function provideGetPhpVersion(): array
    {
        return [
            ['', ['7.3', '7.4', '8.0', '8.0']],
            ['*', ['7.3', '7.4', '8.0', '8.0']],
            ['*.*', ['7.3', '7.4', '8.0', '8.0']],
            ['^7.4 || ^8.0', ['7.4', '7.4', '8.0', '8.0']],
            ['^7', ['7.3', '7.4', '7.4', '7.4']],
            ['~7.3', ['7.3', '7.4', '7.4', '7.4']],
            ['>7.2', ['7.3', '7.4', '8.0', '8.0']],
            ['>= 8', ['8.0', '8.0', '8.0', '8.0']],
            ['<7.4', ['7.3', '7.3', '7.3', '7.3']],
            ['<=7.4', ['7.3', '7.4', '7.4', '7.4']],
            ['^8.0.3', ['8.0', '8.0', '8.0', '8.0']],
            ['7.3.3', ['7.3', '7.3', '7.3', '7.3']],
            ['8.0.*', ['8.0', '8.0', '8.0', '8.0']],
            ['8.*', ['8.0', '8.0', '8.0', '8.0']],
            ['>=7.3 <8', ['7.3', '7.4', '7.4', '7.4']],
            ['>=7.3 <8.1', ['7.3', '7.4', '8.0', '8.0']],
            ['>=7.3 <7.4', ['7.3', '7.3', '7.3', '7.3']],
            ['^7 <7.4', ['7.3', '7.3', '7.3', '7.3']],
            ['7.3-7.4', ['7.3', '7.4', '7.4', '7.4']],
            ['7.3 - 8.0', ['7.3', '7.4', '8.0', '8.0']],
        ];
    }

    /**
     * @dataProvider provideDynamicMatrix
     */
    public function testDynamicMatrix(string $value, int $jobCount)
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
        if ($value !== '') {
            $yml .= "\ndynamic_matrix: $value";
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
        return [
            ['true', 3],
            ['false', 0],
            ['', 3],
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

    /**
     * @dataProvider provideGraphql3
     */
    public function testGraphql3(string $simpleMatrix, string $githubMyRef, array $jobsRequiresGraphql3): void
    {
        if (!function_exists('yaml_parse')) {
            $this->markTestSkipped('yaml extension is not installed');
        }
        $yml = implode("\n", [
            $this->getGenericYml(),
            // using silverstripe/recipe-cms because it there is currently support it for getting the
            // major version of installer set based on github_my_ref
            <<<EOT
            github_repository: 'silverstripe/recipe-cms'
            github_my_ref: '$githubMyRef'
            simple_matrix: $simpleMatrix
            EOT
        ]);
        try {
            // create a temporary fake behat.yml file so that the dynamic matrix include endtoend jobs
            file_put_contents('behat.yml', '');
            $creator = new JobCreator();
            $json = json_decode($creator->createJson($yml));
            $j = 0;
            foreach ($json->include as $job) {
                if ($job->endtoend == 'false') {
                    continue;
                }
                $b = !$jobsRequiresGraphql3[$j];
                $this->assertTrue(strpos($job->composer_require_extra, 'silverstripe/graphql:^3') !== $b);
                $j++;
            }
        } finally {
            unlink('behat.yml');
        }
    }

    public function provideGraphql3(): array
    {
        return [
            ['false', '4.11', [true, false]],
            ['true', '4.11', [false]],
            ['false', '5.0', [false, false]],
            ['true', '5.0', [false]],
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
        $currentMinorCms4 = $this->getCurrentMinorInstallerVersion('4') . '.x-dev';
        return [
            // priority given to branch name
            ['myaccount/silverstripe-framework', '4', [], 'silverstripe-module', '4.x-dev'],
            ['myaccount/silverstripe-framework', '4.10', [], 'silverstripe-vendormodule', '4.10.x-dev'],
            ['myaccount/silverstripe-framework', 'burger', [], 'silverstripe-theme', $currentMinorCms4],
            ['myaccount/silverstripe-framework', '5', [], 'silverstripe-recipe', '5.x-dev'],
            ['myaccount/silverstripe-framework', '5.10', [], 'silverstripe-vendormodule', '5.10.x-dev'],
            ['myaccount/silverstripe-framework', '6', [], 'silverstripe-recipe', '6.x-dev'],
            ['myaccount/silverstripe-framework', '6.10', [], 'silverstripe-vendormodule', '6.10.x-dev'],
            // fallback to looking at deps in composer.json, use current minor of installer .x-dev
            // CMS 5
            ['myaccount/silverstripe-admin', 'mybranch', ['silverstripe/framework' => '5.x-dev'], 'silverstripe-module', '5.x-dev'],
            ['myaccount/silverstripe-admin', 'mybranch', ['silverstripe/framework' => '5.0.x-dev'], 'silverstripe-vendormodule', '5.0.x-dev'],
            ['myaccount/silverstripe-admin', 'mybranch', ['silverstripe/framework' => '^5'], 'silverstripe-theme', '5.2.x-dev'],
            ['myaccount/silverstripe-somemodule', 'mybranch', ['silverstripe/cms' => '^5'], 'silverstripe-recipe', '5.2.x-dev'],
            ['myaccount/silverstripe-somemodule', 'mybranch', ['silverstripe/admin' => '^2'], 'silverstripe-vendormodule', '5.2.x-dev'],
            ['myaccount/silverstripe-somemodule', '3', ['silverstripe/framework' => '^5'], 'silverstripe-vendormodule', '5.x-dev'],
            ['myaccount/silverstripe-somemodule', '3', ['silverstripe/framework' => '^5'], 'package', ''],
            ['myaccount/silverstripe-somemodule', '3', ['silverstripe/framework' => '^5'], '', ''],
            ['myaccount/silverstripe-somemodule', '3', [], '', ''],
            // CMS 6 - note some of the 6.x-dev $expected will need to change once once
            // the `6.0` branches are created - currently only `6` branches exist
            ['myaccount/silverstripe-admin', 'mybranch', ['silverstripe/framework' => '6.x-dev'], 'silverstripe-module', '6.x-dev'],
            ['myaccount/silverstripe-admin', 'mybranch', ['silverstripe/framework' => '6.0.x-dev'], 'silverstripe-vendormodule', '6.0.x-dev'],
            ['myaccount/silverstripe-admin', 'mybranch', ['silverstripe/framework' => '^6'], 'silverstripe-theme', '6.x-dev'],
            ['myaccount/silverstripe-somemodule', 'mybranch', ['silverstripe/cms' => '^6'], 'silverstripe-recipe', '6.x-dev'],
            ['myaccount/silverstripe-somemodule', 'mybranch', ['silverstripe/admin' => '^3'], 'silverstripe-vendormodule', '6.x-dev'],
            ['myaccount/silverstripe-somemodule', '4', ['silverstripe/framework' => '^6'], 'silverstripe-vendormodule', '6.x-dev'],
            ['myaccount/silverstripe-somemodule', '4', ['silverstripe/framework' => '^6'], 'package', ''],
            ['myaccount/silverstripe-somemodule', '4', ['silverstripe/framework' => '^6'], '', ''],
            ['myaccount/silverstripe-somemodule', '4', [], '', ''],
            // // recipe-plugin and vendor-plugin do not override framework
            ['myaccount/silverstripe-admin', 'mybranch', ['silverstripe/recipe-plugin' => '^2', 'silverstripe/framework' => '^6'], 'silverstripe-vendormodule', '6.x-dev'],
            ['myaccount/silverstripe-admin', 'mybranch', ['silverstripe/vendor-plugin' => '^2', 'silverstripe/framework' => '^6'], 'silverstripe-vendormodule', '6.x-dev'],
        ];
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
        return [
            'composerinstall_nophpversion_framework4' => [
                'true',
                '',
                '4.x-dev',
                'silverstripe-module',
                [
                    '7.4 mysql57 phpunit all'
                ]
            ],
            'composerinstall_nophpversion_framework5' => [
                'true',
                '',
                '5.x-dev',
                'silverstripe-vendormodule',
                [
                    '8.1 mysql57 phpunit all'
                ]
            ],
            'composerinstall_nophpversion_framework6' => [
                'true',
                '',
                '6.x-dev',
                'silverstripe-vendormodule',
                [
                    '8.3 mysql57 phpunit all'
                ]
            ],
            'composerinstall_definedphpversion_framework5' => [
                'true',
                '21.99',
                '5.x-dev',
                'silverstripe-recipe',
                [
                    '21.99 mysql57 phpunit all'
                ]
            ],
            'composerinstall_invalidphpversion_framework5' => [
                'true',
                'fish',
                '5.x-dev',
                'silverstripe-theme',
                [
                    '8.1 mysql57 phpunit all'
                ]
            ],
            'composerupgrade_nophpversion_framework4' => [
                'false',
                '',
                '4.x-dev',
                'silverstripe-module',
                [
                    '7.4 prf-low mysql57 phpunit all',
                    '8.0 mysql57pdo phpunit all',
                    '8.1 mysql80 phpunit all'
                ]
            ],
            'composerupgrade_nophpversion_framework5' => [
                'false',
                '',
                '5.x-dev',
                'silverstripe-vendormodule',
                [
                    '8.1 prf-low mysql57 phpunit all',
                    '8.2 mariadb phpunit all',
                    '8.3 mysql80 phpunit all',
                ]
            ],
            'composerupgrade_nophpversion_framework6' => [
                'false',
                '',
                '6.x-dev',
                'silverstripe-vendormodule',
                [
                    '8.3 prf-low mysql57 phpunit all',
                    '8.3 mariadb phpunit all',
                ]
            ],
            'composerupgrade_definedphpversion_framework5' => [
                'false',
                '21.99',
                '5.x-dev',
                'silverstripe-recipe',
                [
                    '8.1 prf-low mysql57 phpunit all',
                    '8.2 mariadb phpunit all',
                    '8.3 mysql80 phpunit all',
                ]
            ],
            'composerupgrade_invalidphpversion_framework5' => [
                'false',
                'fish',
                '5.x-dev',
                'silverstripe-theme',
                [
                    '8.1 prf-low mysql57 phpunit all',
                    '8.2 mariadb phpunit all',
                    '8.3 mysql80 phpunit all',
                ]
            ],
            'composerupgrade_invalidphpversion_framework6' => [
                'false',
                'fish',
                '6.x-dev',
                'silverstripe-theme',
                [
                    '8.3 prf-low mysql57 phpunit all',
                    '8.3 mariadb phpunit all',
                ]
            ],
            'composerupgrade_nophpversion_framework51' => [
                'false',
                '',
                '5.1.x-dev',
                'silverstripe-module',
                [
                    '8.1 prf-low mysql57 phpunit all',
                    '8.1 mariadb phpunit all',
                    '8.2 mysql80 phpunit all',
                ]
            ],
            'composerupgrade_definedphpversion_framework51' => [
                'false',
                '21.99',
                '5.1.x-dev',
                'silverstripe-vendormodule',
                [
                    '8.1 prf-low mysql57 phpunit all',
                    '8.1 mariadb phpunit all',
                    '8.2 mysql80 phpunit all',
                ]
            ],
            'composerupgrade_invalidphpversion_framework51' => [
                'false',
                'fish',
                '5.1.x-dev',
                'silverstripe-recipe',
                [
                    '8.1 prf-low mysql57 phpunit all',
                    '8.1 mariadb phpunit all',
                    '8.2 mysql80 phpunit all',
                ]
            ],
            'composerupgrade_nophpversion_framework52' => [
                'false',
                '',
                '5.2.x-dev',
                'silverstripe-theme',
                [
                    '8.1 prf-low mysql57 phpunit all',
                    '8.2 mariadb phpunit all',
                    '8.3 mysql80 phpunit all',
                ]
            ],
            'composerupgrade_definedphpversion_framework52' => [
                'false',
                '21.99',
                '5.2.x-dev',
                'silverstripe-module',
                [
                    '8.1 prf-low mysql57 phpunit all',
                    '8.2 mariadb phpunit all',
                    '8.3 mysql80 phpunit all',
                ]
            ],
            'composerupgrade_invalidphpversion_framework52' => [
                'false',
                'fish',
                '5.2.x-dev',
                'silverstripe-vendormodule',
                [
                    '8.1 prf-low mysql57 phpunit all',
                    '8.2 mariadb phpunit all',
                    '8.3 mysql80 phpunit all',
                ]
            ],
            'composerupgrade_invalidphpversion_notmodule1' => [
                'false',
                'fish',
                '*',
                'package',
                [
                    '7.4 prf-low mysql57 phpunit all',
                    '8.0 mysql57pdo phpunit all',
                    '8.1 mysql80 phpunit all',
                ]
            ],
            'composerupgrade_invalidphpversion_notmodule2' => [
                'false',
                'fish',
                '*',
                '',
                [
                    '7.4 prf-low mysql57 phpunit all',
                    '8.0 mysql57pdo phpunit all',
                    '8.1 mysql80 phpunit all',
                ]
            ],
        ];
    }
}
