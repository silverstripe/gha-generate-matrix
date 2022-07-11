<?php

use PHPUnit\Framework\TestCase;

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
        $creator->branch = $branch;
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
                'name_suffix' => '',
                'phpunit' => true,
                'phpunit_suite' => 'all',
                'phplinting' => false,
                'phpcoverage' => false,
                'endtoend' => false,
                'endtoend_suite' => 'root',
                'endtoend_config' => '',
                'js' => false,
            ]],
            // test that NO_INSTALLER_LOCKSTEPPED_REPOS base max PHP version from $branch
            ['myaccount/silverstripe-installer', '4.10', 99, [], [
                'php' => max(INSTALLER_TO_PHP_VERSIONS['4.10'])
            ]],
            ['myaccount/silverstripe-installer', '4.11', 99, [], [
                'php' => max(INSTALLER_TO_PHP_VERSIONS['4.11'])
            ]],
            ['myaccount/silverstripe-installer', '4', 99, [], [
                'php' => max(INSTALLER_TO_PHP_VERSIONS['4'])
            ]],
            ['myaccount/silverstripe-installer', '5.0', 99, [], [
                'php' => max(INSTALLER_TO_PHP_VERSIONS['5.0'])
            ]],
            ['myaccount/silverstripe-installer', '5', 99, [], [
                'php' => max(INSTALLER_TO_PHP_VERSIONS['5'])
            ]],
        ];
    }

    /**
     * @dataProvider provideGetInstallerVersion
     */
    public function testGetInstallerVersion(
        string $githubRepository,
        string $branch,
        string $expected
    ): void {
        $creator = new JobCreator();
        $creator->githubRepository = $githubRepository;
        $creator->branch = $branch;
        $actual = $creator->getInstallerVersion();
        $this->assertSame($expected, $actual);
    }

    private function getLatestInstallerVersion(string $cmsMajor): string
    {
        $versions = array_keys(INSTALLER_TO_PHP_VERSIONS);
        $versions = array_filter($versions, fn($version) => substr($version, 0, 1) === $cmsMajor);
        natsort($versions);
        $versions = array_reverse($versions);
        return $versions[0];
    }

    public function provideGetInstallerVersion(): array
    {
        $latest = $this->getLatestInstallerVersion('4') . '.x-dev';
        return [
            // no-installer repo
            ['myaccount/recipe-cms', '4', ''],
            ['myaccount/recipe-cms', '4.10', ''],
            ['myaccount/recipe-cms', 'burger', ''],
            ['myaccount/recipe-cms', 'pulls/4/myfeature', ''],
            ['myaccount/recipe-cms', 'pulls/4.10/myfeature', ''],
            ['myaccount/recipe-cms', 'pulls/burger/myfeature', ''],
            ['myaccount/recipe-cms', '4-release', ''],
            ['myaccount/recipe-cms', '4.10-release', ''],
            // lockstepped repo with 4.* naming
            ['myaccount/silverstripe-framework', '4', '4.x-dev'],
            ['myaccount/silverstripe-framework', '4.10', '4.10.x-dev'],
            ['myaccount/silverstripe-framework', 'burger', $latest],
            ['myaccount/silverstripe-framework', 'pulls/4/mybugfix', '4.x-dev'],
            ['myaccount/silverstripe-framework', 'pulls/4.10/mybugfix', '4.10.x-dev'],
            ['myaccount/silverstripe-framework', 'pulls/burger/myfeature', $latest],
            ['myaccount/silverstripe-framework', '4-release', '4.x-dev'],
            ['myaccount/silverstripe-framework', '4.10-release', '4.10.x-dev'],
            // lockstepped repo with 1.* naming
            ['myaccount/silverstripe-admin', '1', '4.x-dev'],
            ['myaccount/silverstripe-admin', '1.10', '4.10.x-dev'],
            ['myaccount/silverstripe-admin', 'burger', $latest],
            ['myaccount/silverstripe-admin', 'pulls/1/mybugfix', '4.x-dev'],
            ['myaccount/silverstripe-admin', 'pulls/1.10/mybugfix', '4.10.x-dev'],
            ['myaccount/silverstripe-admin', 'pulls/burger/myfeature', $latest],
            ['myaccount/silverstripe-admin', '1-release', '4.x-dev'],
            ['myaccount/silverstripe-admin', '1.10-release', '4.10.x-dev'],
            // non-lockedstepped repo
            ['myaccount/silverstripe-tagfield', '2', $latest],
            ['myaccount/silverstripe-tagfield', '2.9', $latest],
            ['myaccount/silverstripe-tagfield', 'burger', $latest],
            ['myaccount/silverstripe-tagfield', 'pulls/2/mybugfix', $latest],
            ['myaccount/silverstripe-tagfield', 'pulls/2.9/mybugfix', $latest],
            ['myaccount/silverstripe-tagfield', 'pulls/burger/myfeature', $latest],
            ['myaccount/silverstripe-tagfield', '2-release', $latest],
            ['myaccount/silverstripe-tagfield', '2.9-release', $latest],
        ];
    }

    /**
     * @dataProvider provideCreateJson
     */
    public function testCreateJson(string $yml, array $expected)
    {
        if (!function_exists('yaml_parse')) {
            $this->markTestSkipped('yaml extension is not installed');
        }
        $creator = new JobCreator();
        $json = json_decode($creator->createJson($yml));
        for ($i = 0; $i < count($expected); $i++) {
            foreach ($expected[$i] as $key => $expectedVal) {
                $this->assertSame($expectedVal, $json->include[$i]->$key);
            }
        }
    }

    public function provideCreateJson(): array
    {
        return [
            // general test
            [
                implode("\n", [
                    $this->getGenericYml(),
                    <<<EOT
                    github_repository: 'myaccount/silverstripe-framework'
                    github_my_ref: '4.11'
                    parent_branch: ''
                    EOT
                ]),
                [
                    [
                        'installer_version' => '4.11.x-dev',
                        'php' => '7.4',
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
                        'js' => 'false',
                        'name' => '7.4 prf-low mysql57 phpunit all',
                    ],
                    [
                        'installer_version' => '4.11.x-dev',
                        'php' => '8.0',
                        'db' => DB_PGSQL,
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
                        'js' => 'false',
                        'name' => '8.0 pgsql phpunit all',
                    ],
                    [
                        'installer_version' => '4.11.x-dev',
                        'php' => '8.1',
                        'db' => DB_MYSQL_57_PDO,
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
                        'js' => 'false',
                        'name' => '8.1 mysql57pdo phpunit all',
                    ],
                    [
                        'installer_version' => '4.11.x-dev',
                        'php' => '8.1',
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
                        'js' => 'false',
                        'name' => '8.1 mysql80 phpunit all',
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
        $creator = new JobCreator();
        $json = json_decode($creator->createJson($yml));
        $this->assertSame($expected, $json->include[0]->installer_version);
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
        simple_matrix: false
        EOT;
    }

    public function provideParentBranch(): array
    {
        $latest = $this->getLatestInstallerVersion('4') . '.x-dev';
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
                '4.10.x-dev'
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
                    'simple_matrix' => false,
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
}
