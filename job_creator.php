<?php

class JobCreator
{
    public string $composerJsonPath = 'composer.json';

    public string $branch = '';

    public string $githubRepository = '';

    private string $installerVersion = '';

    private string $parentBranch = '';

    private ?string $composerPhpConstraint = '';

    private string $phpVersionOverride = '';

    /**
     * Get the correct version of silverstripe/installer to include for the given repository and branch
     */
    public function getInstallerVersion(): string
    {
        $repo = explode('/', $this->githubRepository)[1];
        // repo should not use installer
        if (in_array($repo, NO_INSTALLER_LOCKSTEPPED_REPOS) || in_array($repo, NO_INSTALLER_UNLOCKSTEPPED_REPOS)) {
            return '';
        }
        $branch = $this->getCleanedBranch(true);
        $isReleaseBranch = preg_match('#^[0-9\.]+-release$#', $branch);
        $cmsMajor = $this->getCmsMajor();
        // repo is a lockstepped repo
        if (in_array($repo, LOCKSTEPPED_REPOS) && (is_numeric($branch) || $isReleaseBranch)) {
            if ($isReleaseBranch) {
                return 'dev-' . preg_replace('#^([0-9])#', $cmsMajor, $branch);
            }
            // e.g. ['4', '11']
            $portions = explode('.', $branch);
            if (count($portions) == 1) {
                return $cmsMajor . '.x-dev';
            } else {
                return $cmsMajor . '.' . $portions[1] . '.x-dev';
            }
        }
        // hardcoded installer version for repo version
        foreach (array_keys(INSTALLER_TO_REPO_MINOR_VERSIONS) as $installerVersion) {
            foreach (INSTALLER_TO_REPO_MINOR_VERSIONS[$installerVersion] as $_repo => $_repoVersions) {
                $repoVersions = is_array($_repoVersions) ? $_repoVersions : [$_repoVersions];
                foreach ($repoVersions as $repoVersion) {
                    if ($repo === $_repo && $repoVersion === preg_replace('#-release$#', '', $branch)) {
                        if ($isReleaseBranch) {
                            return 'dev-' . $installerVersion . '-release';
                        }
                        return $installerVersion . '.x-dev';
                    }
                }
            }
        }
        // has a lockstepped .x-dev requirement in composer.json
        if (file_exists($this->composerJsonPath)) {
            $json = json_decode(file_get_contents($this->composerJsonPath));
            foreach (LOCKSTEPPED_REPOS as $lockedSteppedRepo) {
                $composerRepo = 'silverstripe/' . str_replace('silverstripe-', '', $lockedSteppedRepo);
                if (isset($json->require->{$composerRepo})) {
                    $version = $json->require->{$composerRepo};
                    if (preg_match('#^([0-9\.]+)\.x\-dev$#', $version, $matches)) {
                        $versionNumber = $matches[1];
                        // If the lockstepped dependency is "three less" (e.g. silverstripe/admin is 3 major
                        // versions behind silverstripe/installer), account for that here.
                        if (in_array($lockedSteppedRepo, LOCKSTEPPED_REPOS_VERSION_IS_THREE_LESS)) {
                            $versionNumber += 3;
                        }
                        return $versionNumber . '.x-dev';
                    }
                }
            }
        }
        // fallback to use the next-minor or latest-minor version of installer
        $installerVersions = array_keys(INSTALLER_TO_PHP_VERSIONS);
        $installerVersions = array_filter($installerVersions, fn($version) => substr($version, 0, 1) === $cmsMajor);
        if (preg_match('#^[1-9]+[0-9]*$#', $branch)) {
            // next-minor e.g. 4
            return $cmsMajor . '.x-dev';
        } else {
            // current-minor e.g. 4.11
            // remove major versions
            $installerVersions = array_diff($installerVersions, ['4', '5', '6']);
            // get the minor portions of the verisons e.g. [9, 10, 11]
            $minorPortions = array_map(fn($portions) => (int) explode('.', $portions)[1], $installerVersions);
            sort($minorPortions);
            $minorPortion = $minorPortions[count($minorPortions) - 1];
            $installerVersion = $cmsMajor . '.' . $minorPortion;
            if ($isReleaseBranch) {
                return 'dev-' . $installerVersion . '-release';
            }
            return $installerVersion . '.x-dev';
        }
    }
    
    public function createJob(int $phpIndex, array $opts): array
    {
        $default = [
            # ensure there's a default value for all possible return keys
            # this allows us to use `if [[ "${{ matrix.key }}" == "true" ]]; then` in gha-ci/ci.yml
            'installer_version' => $this->installerVersion,
            'php' => $this->getPhpVersion($phpIndex),
            'parent_branch' => $this->parentBranch,
            'db' => DB_MYSQL_57,
            'composer_require_extra' => '',
            'composer_args' => '',
            'composer_install' => false,
            'name_suffix' => '',
            'phpunit' => false,
            'phpunit_suite' => 'all',
            'phplinting' => false,
            'phpcoverage' => false,
            'endtoend' => false,
            'endtoend_suite' => 'root',
            'endtoend_config' => '',
            'js' => false,
        ];
        return array_merge($default, $opts);
    }

    private function isAllowedPhpVersion(string $phpVersion)
    {
        $phpVersion = (float) $phpVersion;
        // no composer.json file or php version no defined in composer.json, just allow the php version
        if ($this->composerPhpConstraint === null) {
            return true;
        }
        if ($this->composerPhpConstraint === '') {
            if (!file_exists($this->composerJsonPath)) {
                $this->composerPhpConstraint = null;
                return true;
            }
            $json = json_decode(file_get_contents($this->composerJsonPath));
            if (!isset($json->require->php)) {
                $this->composerPhpConstraint = null;
                return true;
            }
            $this->composerPhpConstraint = $json->require->php;
        }
        $constraints = explode('||', $this->composerPhpConstraint);
        $constraints = array_map(function($php) {
            return preg_replace('#([0-9\.\*]+) *- *([0-9\.\*]+)#', '$1-$2', trim($php));
        }, $constraints);
        foreach ($constraints as $constraint) {
            $subConstraintMatchedCount = 0;
            $subConstraints = explode(' ', $constraint);
            // handle hypenated ranges
            for ($i = 0; $i < count($subConstraints); $i++) {
                $subConstraint = $subConstraints[$i];
                if (preg_match('#([0-9\.\*]+)-([0-9\.\*]+)#', $subConstraint, $matches)) {
                    $subConstraints[$i] = '>=' . $matches[1];
                    $subConstraints[] = '<=' . $matches[2];
                }
            }
            foreach ($subConstraints as $subConstraint) {
                $composerVersion = preg_replace('#[^0-9\.\.*]#', '', $subConstraint);
                $isSemver = preg_match('#^[0-9\*]+\.[0-9\*]+\.[0-9\*]+$#', $composerVersion);
                // remove any wildcards
                $composerVersion = str_replace('.*', '', $composerVersion);
                if ($composerVersion == '*') {
                    return true;
                }
                $op = preg_replace('#[^\^~><=]#', '', $subConstraint);
                // convert semver versions to minors
                // github actions will use the latest patch so can assume that ignoring the patch version is safe
                $composerVersion = preg_replace('#^([0-9]+)\.([0-9]+)\.[0-9]$#', '$1.$2', $composerVersion);
                $composerVersion = (float) $composerVersion;
                // treat ^ and ~ as if we are comparing semver versions (more intuitive for dev)
                // even though we are comparing floats (minor versions)
                // ~x.y and ^x.y.0 are the same and match any version within major
                if ($op == '') {
                    $op = '~';
                }
                if (!$isSemver && $op == '~') {
                    $op = '^';
                }
                if (
                    ($op == '^' && floor($phpVersion) == floor($composerVersion) && $phpVersion >= $composerVersion) ||
                    ($op == '~' && $phpVersion == $composerVersion) ||
                    ($op == '>=' && $phpVersion >= $composerVersion) ||
                    ($op == '<' && $phpVersion < $composerVersion) ||
                    ($op == '>' && $phpVersion > $composerVersion) ||
                    ($op == '<' && $phpVersion < $composerVersion) ||
                    ($op == '<=' && $phpVersion <= $composerVersion)
                ) {
                    $subConstraintMatchedCount++;
                }
            }
            if ($subConstraintMatchedCount == count($subConstraints)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the branch name from the installer version and left only the minor version
     * e.g. 4.10.x-dev -> 4.10
     */
    private function getBranchName(): string
    {
        $version = str_replace('.x-dev', '', $this->installerVersion);
        $repo = explode('/', $this->githubRepository)[1];
        if (in_array($repo, NO_INSTALLER_LOCKSTEPPED_REPOS)) {
            $cmsMajor = $this->getCmsMajor();
            $branch = $this->getCleanedBranch();
            if (preg_match('#^[1-9]$#', $branch)) {
                $version = $cmsMajor;
            } elseif (preg_match('#^[1-9]\.([0-9]+)$#', $branch, $matches)) {
                $version = sprintf('%d.%d', $cmsMajor, $matches[1]);
            }
        }

        return $version;
    }

    private function getPhpVersion(int $phpIndex): string
    {
        if ($this->phpVersionOverride) {
            return $this->phpVersionOverride;
        }
        $phpVersions = $this->getListOfPhpVersionsByBranchName();
        // Use the max allowed php version
        if (!array_key_exists($phpIndex, $phpVersions)) {
            for ($i = count($phpVersions) - 1; $i >= 0; $i--) {
                $phpVersion = $phpVersions[$i];
                if ($this->isAllowedPhpVersion($phpVersion)) {
                    return $phpVersion;
                }
            }
        }
        // return the minimum compatible allowed PHP version, respecting $phpIndex
        foreach (array_slice($phpVersions, $phpIndex) as $phpVersion) {
            if ($this->isAllowedPhpVersion($phpVersion)) {
                return $phpVersion;
            }
        }
        // didn't find anything, disregard $phpIndex and try and find anything compatible
        foreach (array_reverse(array_slice($phpVersions, 0, $phpIndex)) as $phpVersion) {
            if ($this->isAllowedPhpVersion($phpVersion)) {
                return $phpVersion;
            }
        }
        
        throw new Exception("No valid PHP version allowed");
    }

    private function getCmsMajor(): string
    {
        $cmsMajor = $this->getCmsMajorFromBranch();
        if ($cmsMajor == '') {
            $cmsMajor = $this->getCmsMajorFromComposerJson();
        }
        // fallback to cms 4
        return $cmsMajor ?: '4';
    }

    private function getCmsMajorFromBranch(): string
    {
        $branch = $this->getCleanedBranch();
        $repo = explode('/', $this->githubRepository)[1];
        $branchMajor = '';
        if (preg_match('#^[1-9]$#', $branch)) {
            $branchMajor = $branch;
        } elseif (preg_match('#^([1-9])\.[0-9]+$#', $branch, $matches)) {
            $branchMajor = $matches[1];
        }
        foreach (array_keys(CMS_TO_REPO_MAJOR_VERSIONS) as $cmsMajor) {
            if (isset(CMS_TO_REPO_MAJOR_VERSIONS[$cmsMajor][$repo])) {
                if (CMS_TO_REPO_MAJOR_VERSIONS[$cmsMajor][$repo] === $branchMajor) {
                    return $cmsMajor;
                }
            }
        }
        return '';
    }

    private function getCmsMajorFromComposerJson(): string
    {
        if (!file_exists($this->composerJsonPath)) {
            return '';
        }
        $json = json_decode(file_get_contents($this->composerJsonPath));
        foreach ($json->require as $dep => $version) {
            // will match the first numeric character
            if (!preg_match('#([0-9])#', $version, $m)) {
                continue;
            }
            $composerVersionMajor = $m[1];
            if (strpos($dep, '/') === false) {
                continue;
            }
            $repo = '';
            if (preg_match('#^silverstripe/recipe-#', $dep) ||
                in_array($dep, [
                'silverstripe/comment-notifications',
                'silverstripe/MinkFacebookWebDriver',
                'silverstripe/vendor-plugin',
            ])) {
                $repo = str_replace('silverstripe/', '', $dep);
            } elseif (preg_match('#^(silverstripe|cwp)/#', $dep)) {
                $repo = str_replace('/', '-', $dep);
            }
            foreach (array_keys(CMS_TO_REPO_MAJOR_VERSIONS) as $cmsMajor) {
                if (isset(CMS_TO_REPO_MAJOR_VERSIONS[$cmsMajor][$repo])) {
                    if (CMS_TO_REPO_MAJOR_VERSIONS[$cmsMajor][$repo] === $composerVersionMajor) {
                        return $cmsMajor;
                    }
                }
            }
        }
        return '';
    }

    private function getCleanedBranch(bool $allowReleaseSuffix = false): string
    {
        $branch = $this->branch;
        // e.g. pulls/4.10/some-bugfix or pulls/4/some-feature
        // for push events to the creative-commoners account
        if (preg_match('#^pulls/([0-9\.]+(-release)?)/#', $branch, $matches)) {
            $branch = $matches[1];
        }
        // fallback to parent branch if available
        if (
            !is_numeric($branch) &&
            $this->parentBranch && 
            (is_numeric($this->parentBranch) || preg_match('#^[0-9\.]+-release$#', $this->parentBranch))
        ) {
            $branch = $this->parentBranch;
        }
        // e.g. 4.10-release
        if (!$allowReleaseSuffix) {
            $branch = preg_replace('#^([0-9\.]+)-release$#', '$1', $branch);
        }
        return $branch;
    }
    
    private function parseBoolValue($value): bool
    {
        return ($value === true || $value === 'true');
    }

    private function createPhpunitJobs(
        array $matrix,
        bool $composerInstall,
        bool $simpleMatrix,
        string $suite,
        array $run
    ): array {
        if ($simpleMatrix || $composerInstall) {
            $matrix['include'][] = $this->createJob(0, [
                'phpunit' => true,
                'phpunit_suite' => $suite,
            ]);
        } else {
            $matrix['include'][] = $this->createJob(0, [
                'composer_args' => '--prefer-lowest',
                'db' => DB_MYSQL_57,
                'phpunit' => true,
                'phpunit_suite' => $suite,
            ]);
            // this same mysql pdo test is also created for the phpcoverage job, so only add it here if
            // not creating a phpcoverage job.
            // note: phpcoverage also runs unit tests
            if ($this->getCmsMajor() === '4' && !$this->doRunPhpCoverage($run)) {
                $matrix['include'][] = $this->createJob(1, [
                    'db' => DB_MYSQL_57_PDO,
                    'phpunit' => true,
                    'phpunit_suite' => $suite,
                ]);
            }
            if ($this->getCmsMajor() === '4') {
                $matrix['include'][] = $this->createJob(3, [
                    'db' => DB_MYSQL_80,
                    'phpunit' => true,
                    'phpunit_suite' => $suite,
                ]);
            } elseif ($this->getCmsMajor() === '5') {
                // phpunit tests for cms 5 are run on php 8.1, 8.2 or 8.3 and mysql 8.0 or mariadb
                $phpToDB = $this->generatePhpToDBMap();
                foreach ($phpToDB as $php => $db) {
                    $matrix['include'][] = $this->createJob($this->getIndexByPHPVersion($php), [
                        'db' => $db,
                        'phpunit' => true,
                        'phpunit_suite' => $suite,
                    ]);
                }
            }
        }
        return $matrix;
    }

    /**
     * Return the list of php versions for the branch
     */
    private function getListOfPhpVersionsByBranchName(): array
    {
        return INSTALLER_TO_PHP_VERSIONS[$this->getBranchName()] ?? INSTALLER_TO_PHP_VERSIONS['4'];
    }

    /**
     * Return the index of the php version in the list of php versions for the branch
     */
    private function getIndexByPHPVersion(string $version): int
    {
        return array_search($version, $this->getListOfPhpVersionsByBranchName()) ?? 0;
    }

    /**
     * Generate a map of php versions to db versions
     * e.g. [ '8.1' => 'mariadb', '8.2' => 'mysql80' ]
     */
    private function generatePhpToDBMap(): array
    {
        $map = [];
        $phpVersions = $this->getListOfPhpVersionsByBranchName();
        $dbs = [DB_MARIADB, DB_MYSQL_80];
        foreach ($phpVersions as $key => $phpVersion) {
            if (count($phpVersions) < 3) {
                $map[$phpVersion] = $dbs[$key];
            } else {
                if ($key === 0) continue;
                $map[$phpVersion] = array_key_exists($key, $dbs) ? $dbs[$key - 1] : DB_MYSQL_80;
            }
        }

        return $map;
    }

    private function doRunPhpCoverage(array $run): bool
    {
        // (currently disabled) always run on silverstripe account, unless phpcoverage_force_off is set to true
        if (false && preg_match('#^silverstripe/#', $this->githubRepository)) {
            return !$run['phpcoverage_force_off'];
        }
        return $run['phpcoverage'];
    }

    private function buildDynamicMatrix(
        array $matrix,
        array $run,
        bool $composerInstall,
        bool $simpleMatrix
    ): array {
        if ($run['phpunit'] && (file_exists('phpunit.xml') || file_exists('phpunit.xml.dist'))) {
            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->load(file_exists('phpunit.xml') ? 'phpunit.xml' : 'phpunit.xml.dist');
            $xpath = new DOMXPath($dom);
            // assume phpunit.xml has defined testsuites
            foreach ($xpath->query('//testsuite') as $testsuite) {
                if (!$testsuite->hasAttribute('name') || $testsuite->getAttribute('name') == 'Default') {
                    continue;
                }
                $suite = $testsuite->getAttribute('name');
                $matrix = $this->createPhpunitJobs($matrix, $composerInstall, $simpleMatrix, $suite, $run);
            }
            // phpunit.xml has no defined testsuites, or only defaults a "Default"
            if (count($matrix['include']) == 0) {
                $matrix = $this->createPhpunitJobs($matrix, $composerInstall, $simpleMatrix, 'all', $run);
            }
        }
        // skip phpcs on silverstripe-installer which include sample file for use in projects
        if ($run['phplinting'] && (file_exists('phpcs.xml') || file_exists('phpcs.xml.dist')) && !preg_match('#/silverstripe-installer$#', $this->githubRepository)) {
            $matrix['include'][] = $this->createJob(0, [
                'phplinting' => true
            ]);
        }
        // phpcoverage also runs unit tests
        if ($this->doRunPhpCoverage($run, $this->githubRepository)) {
            if ($simpleMatrix || $composerInstall || $this->getCmsMajor() !== '4') {
                $matrix['include'][] = $this->createJob(0, [
                    'phpcoverage' => true
                ]);
            } else {
                $matrix['include'][] = $this->createJob(2, [
                    'db' => DB_MYSQL_57_PDO,
                    'phpcoverage' => true
                ]);
            }
        }
        // endtoend / behat
        if ($run['endtoend'] && file_exists('behat.yml')) {
            $cmsMajor = $this->getCmsMajor();
            $graphql3 = !$simpleMatrix && $cmsMajor == '4';
            $job = $this->createJob(0, [
                'endtoend' => true,
                'endtoend_suite' => 'root',
                'composer_require_extra' => $graphql3 ? 'silverstripe/graphql:^3' : ''
            ]);
            // use minimum version of 7.4 for endtoend because was having apt dependency issues
            // in CI when using php 7.3:
            // The following packages have unmet dependencies:
            // libpcre2-dev : Depends: libpcre2-8-0 (= 10.39-3+ubuntu20.04.1+deb.sury.org+2) but
            // 10.40-1+ubuntu20.04.1+deb.sury.org+1 is to be installed
            if ($job['php'] == '7.3') {
                $job['php'] = '7.4';
            }
            $matrix['include'][] = $job;
            if (!$simpleMatrix && !$composerInstall) {
                $matrix['include'][] = $this->createJob(3, [
                    'db' => DB_MYSQL_80,
                    'endtoend' => true,
                    'endtoend_suite' => 'root'
                ]);
            }
        }
        // javascript tests
        if ($run['js'] && file_exists('package.json')) {
            $matrix['include'][] = $this->createJob(0, [
                'js' => true
            ]);
        }
        return $matrix;
    }

    public function getInputs(string $yml): array
    {
        $message = 'Failed to parse yml';
        try {
            $inputs = yaml_parse($yml);
        } catch (Exception $e) {
            throw new Exception($message);
        }
        if (!$inputs) {
            throw new Exception($message);
        }
        foreach (['github_my_ref', 'parent_branch'] as $key) {
            if (array_key_exists($key, $inputs)) {
                if (!preg_match("#$key: *'#", $yml)) {
                    throw new Exception("$key needs to be surrounded by single-quotes");
                }
            }
        }
        return $inputs;
    }

    public function createJson(string $yml): string
    {
        $inputs = $this->getInputs($yml);
        // $myRef will either be a branch for push (i.e cron) and pull-request (target branch), or a semver tag
        $myRef = $inputs['github_my_ref'];
        $unstableTagRx = '#^([0-9]+)\.([0-9]+)\.[0-9]+-((alpha|beta|rc))[0-9]+$#';
        $isTag = preg_match('#^([0-9]+)\.([0-9]+)\.[0-9]+$#', $myRef, $m) || preg_match($unstableTagRx, $myRef, $m);
        $this->branch = $isTag ? sprintf('%d.%d', $m[1], $m[2]) : $myRef;

        // parent branch is a best attempt to get the parent branch of the branch via bash
        // it's used for working out the version of installer to use on github push events
        if (array_key_exists('parent_branch', $inputs)) {
            $this->parentBranch = $inputs['parent_branch'];
        }

        $this->githubRepository = $inputs['github_repository'];
        $this->installerVersion = $this->getInstallerVersion();
        if (preg_match($unstableTagRx, $myRef, $m)) {
            $this->installerVersion = str_replace('.x-dev', '.0-' . $m[3] . '1', $this->installerVersion);
        }

        $run = [];
        $extraJobs = [];
        $composerInstall = false;
        $dynamicMatrix = true;
        $simpleMatrix = false;
        foreach ($inputs as $input => $value) {
            if (in_array($input, [
                'endtoend',
                'js',
                'phpunit',
                'phpcoverage',
                'phpcoverage_force_off',
                'phplinting',
            ])) {
                $run[$input] = $this->parseBoolValue($value);
            } else if ($input === 'extra_jobs') {
                if ($value === 'none') {
                    $value = [];
                }
                $extraJobs = $value;
            } else if ($input === 'composer_install') {
                $composerInstall = $this->parseBoolValue($value);
            } else if ($input === 'dynamic_matrix') {
                $dynamicMatrix = $this->parseBoolValue($value);
            } else if ($input === 'simple_matrix') {
                $simpleMatrix = $this->parseBoolValue($value);
            } else if (in_array($input, ['github_my_ref', 'github_repository', 'parent_branch'])) {
                continue;
            } else {
                throw new LogicException("Unhandled input $input");
            }
        }
        $matrix = ['include' => []];

        if ($composerInstall) {
            $json = json_decode(file_get_contents($this->composerJsonPath));
            if (isset($json->config->platform->php) && preg_match('#^[0-9\.]+$#', $json->config->platform->php)) {
                $this->phpVersionOverride = $json->config->platform->php;
            }
        }

        if ($dynamicMatrix) {
            $matrix = $this->buildDynamicMatrix($matrix, $run, $composerInstall, $simpleMatrix);
        }

        // extra jobs
        foreach ($extraJobs as $arr) {
            $matrix['include'][] = $this->createJob(0, $arr);
        }

        // convert everything to strings and sanatise values
        foreach ($matrix['include'] as $i => $job) {
            foreach ($job as $key => $val) {
                if ($val === true) {
                    $val = 'true';
                }
                if ($val === false) {
                    $val = 'false';
                }
                // all values must be strings
                $val = (string) $val;
                // remove any dodgy characters
                $val = str_replace(["\f", "\r", "\n", "\t", "'", '"', '&', '|'], '', $val);
                // only allow visible ascii chars - see [:print:] in the table at https://www.regular-expressions.info/posixbrackets.html#posixbrackets
                $val = preg_replace('#[^\x20-\x7E]#', '', $val);
                // limit name_suffix length and be strict as it is used in the artifact file name
                if ($key === 'name_suffix' && strlen($val) > 20) {
                    $val = preg_replace('#[^a-zA-Z0-9_\- ]#', '', $val);
                    $val = substr($val, 0, 20);
                }
                // composer_require_extra is used in silverstripe/gha-ci `composer require`, so throw an
                // exception if there are any dodgy characters
                if ($key === 'composer_require_extra' && preg_match('#[^A-Za-z0-9\-\.\^\/~: ]#', $val)) {
                    throw new InvalidArgumentException("Invalid composer_require_extra $val");
                }
                // ensure x.0 versions of PHP retain the minor version
                if ($key === 'php' && preg_match('#^[1-9]$#', $val)) {
                    $val = "$val.0";
                }
                // add value back to matrix
                $matrix['include'][$i][$key] = $val;
            }
        }

        // job/artifacts names
        foreach ($matrix['include'] as $i => $job) {
            $name = [
                $job['php']
            ];
            if (strpos($job['composer_args'], '--prefer-lowest') !== false) {
                $name[] = 'prf-low';
            }
            $name[] = $job['db'];
            if ($job['phpunit'] == 'true') {
                $name[] = 'phpunit';
                $name[] = $job['phpunit_suite'];
            }
            if ($job['endtoend'] == 'true') {
                $name[] = 'endtoend';
                $name[] = $job['endtoend_suite'] ?: 'root';
            }
            if ($job['js'] == 'true') {
                $name[] = 'js';
            }
            if ($job['phpcoverage'] == 'true') {
                $name[] = 'phpcoverage';
            }
            if ($job['phplinting'] == 'true') {
                $name[] = 'phplinting';
            }
            $name[] = $job['name_suffix'];
            $name = array_filter($name);
            $matrix['include'][$i]['name'] = implode(' ', $name);
        }

        // output json, keep it on a single line so do not use pretty print
        $json = json_encode($matrix, JSON_UNESCAPED_SLASHES);
        // Remove indents
        $json = preg_replace("#^ +#", "", $json);
        $json = str_replace("\n", '', $json);
        return trim($json);
    }
}