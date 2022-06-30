<?php

class JobCreator
{
    private $installerVersion = null;

    /**
     * Get the correct version of silverstripe/installer to include for the given repository and branch
     */
    public function getInstallerVersion(string $githubRepository, string $branch): string
    {
        $repo = explode('/', $githubRepository)[1];
        if (in_array($repo, NO_INSTALLER_REPOS)) {
            return '';
        }
        // e.g. pulls/4.10/some-bugfix or pulls/4/some-feature
        // for push events to the creative-commoners account
        if (preg_match('#^pulls/([0-9\.]+)/#', $branch, $matches)) {
            $branch = $matches[1];
        }
        // e.g. 4.10-release
        $branch = preg_replace('#^([0-9\.]+)-release$#', '$1', $branch);
        if (in_array($repo, LOCKSTEPED_REPOS) && is_numeric($branch)) {
            // e.g. ['4', '11']
            $portions = explode('.', $branch);
            if (count($portions) == 1) {
                return '4.x-dev';
            } else {
                return '4.' . $portions[1] . '.x-dev';
            }
        }
        // use the latest minor version of installer
        $installerVersions = array_keys(INSTALLER_TO_PHP_VERSIONS);
        // remove '4' version
        $installerVersions = array_diff($installerVersions, ['4']);
        // get the minor portions of the verisons e.g. [9, 10, 11]
        $minorPortions = array_map(fn($portions) => (int) explode('.', $portions)[1], $installerVersions);
        sort($minorPortions);
        return '4.' . $minorPortions[count($minorPortions) - 1] . '.x-dev';
    }
    
    public function createJob(int $phpIndex, array $opts): array
    {
        $installerKey = str_replace('.x-dev', '', $this->installerVersion);
        $installerKey = $installerKey ?: '4';
        $phpVersions = INSTALLER_TO_PHP_VERSIONS[$installerKey];
        $default = [
            # ensure there's a default value for all possible return keys
            # this allows us to use `if [[ "${{ matrix.key }}" == "true" ]]; then` in gha-ci/ci.yml
            'installer_version' => $this->installerVersion,
            'php' => $phpVersions[$phpIndex] ?? $phpVersions[count($phpVersions) - 1],
            'db' => DB_MYSQL_57,
            'composer_require_extra' => '',
            'composer_args' => '',
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
    
    private function parseBoolValue(mixed $value): bool
    {
        return ($value === true || $value === 'true');
    }

    private function createPhpunitJobs(
        array $matrix,
        bool $simpleMatrix,
        string $suite,
        array $run,
        string $githubRepository
    ): array {
        if ($simpleMatrix) {
            $matrix['include'][] = $this->createJob(0, [
                'phpunit' => true,
                'phpunit_suite' => $suite,
            ]);
        } else {
            $matrix['include'][] = $this->createJob(0, [
                'composer_args' => '--prefer-lowest',
                'phpunit' => true,
                'phpunit_suite' => $suite,
            ]);
            $matrix['include'][] = $this->createJob(1, [
                'db' => DB_PGSQL,
                'phpunit' => true,
                'phpunit_suite' => $suite,
            ]);
            // this same mysql pdo test is also created for the phpcoverage job, so only add it here if
            // not creating a phpcoverage job.
            // note: phpcoverage also runs unit tests
            if (!$this->doRunPhpCoverage($run, $githubRepository)) {
                $matrix['include'][] = $this->createJob(2, [
                    'db' => DB_MYSQL_57_PDO,
                    'phpunit' => true,
                    'phpunit_suite' => $suite,
                ]);
            }
            $matrix['include'][] = $this->createJob(3, [
                'db' => DB_MYSQL_80,
                'phpunit' => true,
                'phpunit_suite' => $suite,
            ]);
        }
        return $matrix;
    }

    private function doRunPhpCoverage(array $run, string $githubRepository): bool
    {
        // always run on silverstripe account, unless phpcoverage_force_off is set to true
        if (preg_match('#^silverstripe/#', $githubRepository)) {
            return !$run['phpcoverage_force_off'];
        }
        return $run['phpcoverage'];
    }

    private function buildDynamicMatrix(
        array $matrix,
        array $run,
        bool $simpleMatrix,
        string $githubRepository
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
                $matrix = $this->createPhpunitJobs($matrix, $simpleMatrix, $suite, $run, $githubRepository);
            }
            // phpunit.xml has no defined testsuites, or only defaults a "Default"
            if (count($matrix['include']) == 0) {
                $matrix = $this->createPhpunitJobs($matrix, $simpleMatrix, 'all', $run, $githubRepository);
            }
        }
        // skip phpcs on silverstripe-installer which include sample file for use in projects
        if ($run['phplinting'] && (file_exists('phpcs.xml') || file_exists('phpcs.xml.dist')) && !preg_match('#/silverstripe-installer$#', $githubRepository)) {
            $matrix['include'][] = $this->createJob(0, [
                'phplinting' => true
            ]);
        }
        // phpcoverage also runs unit tests
        if ($this->doRunPhpCoverage($run, $githubRepository)) {
            if ($simpleMatrix) {
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
            $matrix['include'][] = $this->createJob(0, [
                'endtoend' => true,
                'endtoend_suite' => 'root'
            ]);
            if (!$simpleMatrix) {
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
        if (array_key_exists('github_my_ref', $inputs)) {
            if (!preg_match("#github_my_ref: *'#", $yml)) {
                throw new Exception('github_my_ref needs to be surrounded by single-quotes');
            }
        }
        return $inputs;
    }

    public function createJson(string $yml): string
    {
        $inputs = $this->getInputs($yml);
        // $myRef will either be a branch for push (i.e cron) and pull-request (target branch), or a semver tag
        $myRef = $inputs['github_my_ref'];
        $isTag = preg_match('#^[0-9]+\.[0-9]+\.[0-9]+$#', $myRef, $m);
        $branch = $isTag ? sprintf('%d.%d', $m[1], $m[2]) : $myRef;

        $githubRepository = $inputs['github_repository'];
        $this->installerVersion = $this->getInstallerVersion($githubRepository, $branch);

        $run = [];
        $extraJobs = [];
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
            } else if ($input === 'dynamic_matrix') {
                $dynamicMatrix = $this->parseBoolValue($value);
            } else if ($input === 'simple_matrix') {
                $simpleMatrix = $this->parseBoolValue($value);
            } else if (in_array($input, ['github_my_ref', 'github_repository'])) {
                continue;
            } else {
                throw new LogicException("Unhandled input $input");
            }
        }
        $matrix = ['include' => []];

        if ($dynamicMatrix) {
            $matrix = $this->buildDynamicMatrix($matrix, $run, $simpleMatrix, $githubRepository);
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