<?php

use PHPUnit\Framework\TestCase;

class JobCreatorTest extends TestCase
{
    /**
     * @dataProvider provideGetInstallerVersion
     */
    public function testGetInstallerVersion(
        string $githubRepository,
        string $branch,
        string $expected
    ): void {
        $creator = new JobCreator();
        $actual = $creator->getInstallerVersion($githubRepository, $branch);
        $this->assertSame($expected, $actual);
    }

    private function getLatestInstallerVersion(): string
    {
        $versions = array_keys(INSTALLER_TO_PHP_VERSIONS);
        natsort($versions);
        $versions = array_reverse($versions);
        return $versions[0];
    }

    public function provideGetInstallerVersion(): array
    {
        $latest = $this->getLatestInstallerVersion() . '.x-dev';
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
                <<<EOT
                endtoend: true
                js: true
                phpcoverage: false
                phpcoverage_force_off: false
                phplinting: true
                phpunit: true
                simple_matrix: false
                github_repository: 'myaccount/silverstripe-versioned'
                github_my_ref: 'pulls/1.10/module-standards'
                EOT,
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
