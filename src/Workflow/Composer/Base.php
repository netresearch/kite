<?php
/**
 * See class comment
 *
 * PHP Version 5
 *
 * @category   Netresearch
 * @package    Netresearch\Kite\Workflow
 * @subpackage Composer
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */

namespace Netresearch\Kite\Workflow\Composer;
use Netresearch\Kite\Service\Composer\Package;
use Netresearch\Kite\Workflow;
use Netresearch\Kite\Exception;

/**
 * Abstract for composer workflows
 *
 * @category   Netresearch
 * @package    Netresearch\Kite\Workflow
 * @subpackage Composer
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */
abstract class Base extends Workflow
{
    /**
     * @var Package[]
     */
    protected $pushPackages = array();

    /**
     * @var array
     */
    protected $whitelists;

    /**
     * @var array
     */
    protected $packageNames;

    /**
     * Configure the variables
     *
     * @return array
     */
    protected function configureVariables()
    {
        $config = $this->getParent()->get('config');
        if (!array_key_exists('composer', $config)) {
            $config['composer'] = [];
        }
        foreach (['whitelistNames', 'whitelistPaths', 'whitelistRemotes'] as $key) {
            if (!array_key_exists($key, $config['composer'])) {
                $config['composer'][$key] = null;
            }
        }

        return [
            'packages' => array(
                'type' => 'array',
                'label' => 'Package name(s) to limit this operation to',
                'shortcut' => 'p',
                'option' => true
            ),
            'whitelistNames' => array(
                'default' => '{config["composer"]["whitelistNames"]}',
                'type' => 'string',
                'label' => 'Regular expression for package names, to limit this operation to',
                'option' => true
            ),
            'whitelistPaths' => array(
                'default' => '{config["composer"]["whitelistPaths"]}',
                'type' => 'string',
                'label' => 'Regular expression for package paths, to limit this operation to',
                'option' => true
            ),
            'whitelistRemotes' => array(
                'default' => '{config["composer"]["whitelistRemotes"]}',
                'type' => 'string',
                'label' => 'Regular expression for package remote urls, to limit this operation to',
                'option' => true
            ),
            '--'
        ] + parent::configureVariables();
    }


    /**
     * Push all packages marked to be pushed
     *
     * @return void
     */
    protected function pushPackages()
    {
        foreach ($this->pushPackages as $i => $package) {
            $this->assertPackageIsWhiteListed($package);
            $this->console->output("Pushing <comment>$package->name</comment>", false);
            $this->git('push', $package->path, array('u' => 'origin', $package->branch));
            $this->console->output(
                str_repeat(chr(8), strlen($package->name))
                . '<info>' . $package->name . '</info>'
            );
            unset($this->pushPackages[ $i ]);
        }
    }

    /**
     * doComposerUpdateIfNecessary and possible
     *
     * @return void
     */
    protected function doComposerUpdate()
    {
        try {
            $this->composer('update');
        } catch (\Exception $e) {
            $this->console->output('<warning>Composer update failed</warning>');
            $this->console->output('<comment>This might have occured because previous pushes to git did not reache the composer repo yet</comment>');
            if ($this->confirm('Retry?')) {
                $this->doComposerUpdate();
            } else {
                $this->doExit('', 1);
            }
        }
    }

    /**
     * Go through all packages and check if packages requiring those packages,
     * still require their (likely new) versions.
     *
     * If not and $autoFix or user agrees, the require-statements in the
     * dependent packages are changed accordingly.
     *
     * @param Package[] $packages              Packages
     * @param bool      $autoFix               Whether to autofix wrong requirements
     * @param bool      $useNewAsCurrentBranch Whether to rewrite requirements in the root package only
     *
     * @return void
     */
    protected function rewriteRequirements(array &$packages, $autoFix = false, $useNewAsCurrentBranch = false)
    {
        $checkedOutPackages = array_keys($packages);
        $unfixedRequirements = 0;
        while ($packageName = array_shift($checkedOutPackages)) {
            $branch = $packages[$packageName]->branch;
            $version = 'dev-' . $branch;
            foreach ($this->getPackages(false, false) as $package) {
                if (array_key_exists($packageName, $package->requires)) {
                    // TODO: Set required version to branch alias, if any
                    $requiredVersion = $package->requires[$packageName];
                    if ($requiredVersion === '@dev') {
                        $requiredVersion = 'dev-master';
                    }
                    if ($requiredVersion !== $version) {
                        $this->assertPackageIsWhiteListed($package);
                        if (!$package->git) {
                            throw new Exception("Package {$package->name} required to be installed from source");
                        }
                        if ($autoFix) {
                            $fix = true;
                            $this->output("Changing required version of {$packageName} in {$package->name} from {$requiredVersion} to {$version}");
                        } else {
                            $this->output("<warning>{$package->name} depends on {$packageName} {$package->requires[$packageName]} and not {$version} as expected</warning>");
                            $this->output('<comment>If you don\'t fix that, the branches will probably change with composer update</comment>');
                            $fix = $this->confirm('Fix that?');
                        }
                        if ($fix) {
                            if ($this->checkoutPackage($package, $branch, true)) {
                                $checkedOutPackages[] = $package->name;
                            }
                            if (!array_key_exists($package->name, $packages)) {
                                $packages[ $package->name ] = $package;
                            }
                            $this->pushPackages[$packageName] = $packages[$packageName];
                            $this->rewriteRequirement($package, $packageName, $version, $aliases);
                        } else {
                            $unfixedRequirements++;
                        }
                    }
                }

                // if useAsCurrent-option is set, only the first package should be altered
                if ($useNewAsCurrentBranch) {
                    break;
                }
            }
        }
        if ($unfixedRequirements) {
            $this->doExit(
                'It seems like a composer update is required but due to probably incorrect dependencies you have to do that manually', 1
            );
        }
    }

    /**
     * Change the require statement for $requiredPackage to the newVersion in
     * $package
     *
     * @param Package $package         The package
     * @param string  $requiredPackage The required package
     * @param string  $newVersion      The new version
     * @param bool    $aliases         Whether the aliases-option is set
     *
     * @return void
     */
    protected function rewriteRequirement($package, $requiredPackage, $newVersion, $aliases = false)
    {
        $this->assertPackageIsWhiteListed($package);

        $currentVersion = $package->requires[$requiredPackage];
        $composerFile = $package->path . '/composer.json';
        $composerFileContents = file_get_contents($composerFile);
        if ($aliases) {
            $newVersion = $this->getNewVersionName($newVersion, $currentVersion);
        }
        $newComposerFileContents = preg_replace(
            sprintf(
                '/(^\s*"require"\s*:\s*\{[^\}]+"%s"\s*:\s*")%s/m',
                preg_quote($requiredPackage, '/'),
                preg_quote($currentVersion, '/')
            ),
            '$1' . $newVersion,
            $composerFileContents
        );
        file_put_contents($composerFile, $newComposerFileContents);
        $package->reloadRequires();
        if ($package->requires[ $requiredPackage ] !== $newVersion) {
            file_put_contents($composerFile, $composerFileContents);
            $this->output('<error>Could not replace version</error> - generated composer.json was:');
            $this->output($newComposerFileContents);
            throw new Exception('Replacing version failed');
        }

        $this->git('commit', $package->path, array('n' => true, 'm' => "Change required version of $requiredPackage to $newVersion", 'composer.json'));
        if (!isset($package->source)) {
            $package->source = new \stdClass();
        }
        $package->source->reference = $this->git('rev-parse', $package->path, array('HEAD'));

        $this->console->output("Made <comment>$package->name</comment> require <comment>$requiredPackage $newVersion</comment>");

        $this->pushPackages[$package->name] = $package;
    }

    /**
     * Get the name of the new method with alias
     *
     * @param string $newversion     the new package version
     * @param string $currentversion the old name of the version of the package
     *
     * @return string
     */
    public function getNewVersionName($newversion, $currentversion)
    {
        $pos = strpos($currentversion, ' as ');

        if ($pos) {
            $currentversion = substr($currentversion, $pos + 4);
        }

        $newversion .= ' as ' . $currentversion;

        return $newversion;
    }

    /**
     * Reload the requires from $package composer.json to $package->requires
     *
     * If $detectChanges, and there are changes on the requirements not in
     * $ignorePackages composer update is requested
     *
     * @param Package $package The package
     *
     * @deprecated Use $package->reloadRequires()
     *
     * @return void
     */
    protected function reloadRequires($package)
    {
        $package->reloadRequires();
    }

    /**
     * Checkout a package at a branch
     *
     * @param Package $package The package
     * @param string  $branch  The branch
     * @param bool    $create  Create the branch if it doesn't exist
     *
     * @return bool|null Whether checkout was successful or null when package is
     *                   already at this branch
     */
    protected function checkoutPackage($package, $branch, $create = false)
    {
        $this->assertPackageIsWhiteListed($package);

        if ($package->branch === $branch) {
            return null;
        }
        if (!$package->git) {
            throw new Exception('Non git package can not be checked out');
        }
        $remoteBranch = 'origin/' . $branch;
        $isRemote = in_array($remoteBranch, $package->branches, true);
        if (in_array($branch, $package->branches, true)) {
            $this->git('checkout', $package->path, $branch);
        } elseif ($isRemote) {
            $this->git('checkout', $package->path, array('b' => $branch, $remoteBranch));
        } elseif ($create) {
            $branches = array_unique(
                array_map(
                    function ($el) {
                        $parts = explode('/', $el);

                        return array_pop($parts);
                    },
                    $package->branches
                )
            );
            sort($branches);
            $inferFromBranch = $this->choose(
                "Select branch to create new branch '$branch' from in {$package->name}",
                $branches, in_array('master', $branches, true) ? 'master' : $branch
            );
            if ($inferFromBranch !== $package->branch) {
                $this->checkoutPackage($package, $inferFromBranch);
            }
            $this->git('checkout', $package->path, array('b' => $branch));
            $package->branches[] = $branch;
        } else {
            return false;
        }

        if ($isRemote) {
            if (!isset($package->upstreams[ $branch ]) || $package->upstreams[ $branch ] !== $remoteBranch) {
                $this->git('branch', $package->path, array('u' => $remoteBranch));
                $package->upstreams[ $branch ] = $remoteBranch;
            }
            $this->git('rebase', $package->path);
        }

        $this->console->output("Checked out <comment>{$package->name}</comment> at <comment>$branch</comment>");

        $package->reloadRequires();
        $package->version = 'dev-' . $branch;
        $package->branch = $branch;

        return true;
    }

    /**
     * Merge a $branch into $package's current branch
     *
     * @param Package $package The package
     * @param string  $branch  The branch
     * @param null    $message The commit message (if any)
     * @param bool    $squash  Whether to squash the changes
     *
     * @return void
     */
    protected function mergePackage($package, $branch, $message = null, $squash = false)
    {
        $this->preparePackageForMerge($package, $branch);
        $mergeOptions = $this->setMergeOptions($branch, $squash);

        try {
            $this->git('merge', $package->path, $mergeOptions);
        } catch (\Exception $e) {
            $this->console->output($e->getMessage());
            $conflictedFiles = $this->resolveMergeConflicts($package);
        }

        if (isset($conflictedFiles) || $this->git('status', $package->path, array('porcelain' => true))) {
            $this->console->output('You are merging package <comment>' . $package->name . '</comment> from <comment>' . $branch . '</comment> into <comment>' . $package->branch . "</comment>.\n");
            if (!$message) {
                $message = $this->answer(
                    'Enter commit message:',
                    'Merged ' . $branch . ' into ' . $package->branch
                );
            }
            $this->git('commit', $package->path, array('n' => true, 'm' => $message));
        }

        $this->console->output("Merged with <comment>$branch</comment> in <comment>{$package->name}</comment>");

        $package->reloadRequires();
        $this->pushPackages[ $package->name ] = $package;
    }

    /**
     * Check that package is white-listed, is git-package and up-to-date
     *
     * @param $package
     * @param $branch
     *
     * @return void
     *
     * @throws Exception
     */
    private function preparePackageForMerge($package, $branch)
    {
        $this->assertPackageIsWhiteListed($package);

        if (!$package->git) {
            throw new Exception('Non git package can not be merged');
        }

        $this->git('fetch', $package->path, array('force' => true, 'origin', $branch . ':' . $branch));
    }

    /**
     * Set the options for the merge command
     *
     * @param string $branch branch to merge
     * @param bool   $squash is --squash-option set
     *
     * @return array
     */
    private function setMergeOptions($branch, $squash)
    {
        $mergeOptions = array();

        if ($squash) {
            $mergeOptions['squash'] = true;
            $mergeOptions['no-commit'] = false;
        } else {
            $mergeOptions['no-commit'] = true;
            $ff = $branch == 'master' ? 'ff' : 'no-ff';
            $mergeOptions[ $ff ] = true;
        }

        $mergeOptions[] = $branch;

        return $mergeOptions;
    }

    /**.
     * Try to solve merge conflicts
     *
     * @param Package $package
     *
     * @return array
     *
     * @throws Exception
     */
    private function resolveMergeConflicts($package)
    {
        $diff = $this->git('diff', $package->path, array('name-only' => true, 'diff-filter' => 'U'));
        $conflictedFiles = array_flip(explode("\n", $diff));
        if (array_key_exists('composer.json', $conflictedFiles)) {
            try {
                $this->resolveRequirementsConflict($package);
                $this->git('add', $package->path, 'composer.json');
            } catch (Exception $conflictSolvingException) {
            }
        }
        if (array_diff(array_keys($conflictedFiles), ['composer.json'])) {
            throw new Exception(
                'There are unresolved conflicts - please resolve them and then commit the result',
                1458307785, isset($conflictSolvingException) ? $conflictSolvingException : null
            );
        } elseif (isset($conflictSolvingException)) {
            throw $conflictSolvingException;
        }

        return $conflictedFiles;
    }

    /**
     * Try to solve conflicts inside the require section of the $package
     * composer.json
     *
     * @param Package $package The package
     *
     * @return void
     */
    private function resolveRequirementsConflict($package)
    {
        $contents = file_get_contents($package->path . '/composer.json');
        $ours = @json_decode(
            preg_replace('/^<{7}.+\n(.+)\n(\|{7}|={7}).+>{7}.+$/smU', '$1', $contents)
        );
        $theirs = @json_decode(
            preg_replace('/^<{7}.+\n={7}\n(.+)\n>{7}.+$/smU', '$1', $contents)
        );
        if (!is_object($ours) || !is_object($theirs)) {
            throw new Exception('Could not regenerate json file from solved conflicts');
        }
        $diff = array_diff_key($theirs = get_object_vars($theirs), $ours = get_object_vars($ours));
        foreach ($ours as $key => $value) {
            if ($key !== 'require' && (!array_key_exists($key, $theirs) || serialize($value) !== serialize($theirs[ $key ]))) {
                $diff[ $key ] = $value;
            }
        }
        if ($diff !== array()) {
            throw new Exception('Can not automerge composer.json due to conflicts outside require object', 1458307516);
        }

        $theirs['require'] = $this->mergeRequirements($package, $ours, $theirs);
        file_put_contents($package->path . '/composer.json', $this->jsonEncode($theirs));
    }

    /**
     * Encode a variable for JSON file
     *
     * @param mixed $var The var
     *
     * @return string
     */
    protected function jsonEncode($var)
    {
        return json_encode(
            $var, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) . "\n";
    }

    /**
     * Merge the requirements from different sides of the current $package
     *
     * @param Package $package The current package
     * @param array   $ours    Our composer.json as array
     * @param array   $theirs  Their composer.json as array
     *
     * @return array
     */
    private function mergeRequirements($package, array $ours, array $theirs)
    {
        $oursRequire = isset($ours['require']) && is_object($ours['require']) ? get_object_vars($ours['require']) : [];
        $theirsRequire = isset($theirs['require']) && is_object($theirs['require']) ? get_object_vars($theirs['require']) : [];
        $mergedRequires = array_merge($oursRequire, $theirsRequire);
        $packages = $this->getPackages(false, false);
        $preferredVersion = 'dev-' . $package->branch;
        foreach ($mergedRequires as $packageName => $version) {
            $actualVersion = ($version === '@dev') ? 'dev-master' : $version;
            if (array_key_exists($packageName, $oursRequire)
                && $version !== $oursRequire[ $packageName ]
                && $actualVersion !== $oursRequire[ $packageName ]
                && $actualVersion !== $preferredVersion
                && array_key_exists($packageName, $packages)
                && in_array($package->branch, $packages[ $packageName ]->branches, true)
            ) {
                $mergedRequires[ $packageName ] = $preferredVersion;
            }
        }

        return $mergedRequires;
    }

    /**
     * Get the allowed packages
     *
     * @param bool $gitOnly     If git packages should be returned only
     * @param bool $allowedOnly If allowed packages should be returned only
     *
     * @return \Netresearch\Kite\Service\Composer\Package[]
     */
    protected function getPackages($gitOnly = true, $allowedOnly = true)
    {
        /* @var $packages \Netresearch\Kite\Service\Composer\Package[] */
        /* @var $package \Netresearch\Kite\Service\Composer\Package */
        $packages = array();
        foreach ($this->get('composer.packages') as $package) {
            if ((!$gitOnly || $package->git) && (!$allowedOnly || $this->isPackageAllowed($package))) {
                $packages[ $package->name ] = $package;
            }
        }

        return $packages;
    }

    /**
     * Assert that package is in white lists
     *
     * @param Package $package The package
     *
     * @throws Exception
     *
     * @return void
     */
    protected function assertPackageIsWhiteListed($package)
    {
        if ($this->isPackageWhiteListed($package) === false) {
            throw new Exception("Package {$package->name} is not in white list");
        }
    }

    /**
     * Determine if a package is not excluded by the packages option or white lists
     *
     * @param Package $package The package
     *
     * @return bool
     */
    protected function isPackageAllowed(Package $package)
    {
        if (!is_array($this->packageNames)) {
            $this->packageNames = array_fill_keys($this->get('packages') ? : [], null);
        }

        if ($this->packageNames) {
            if (!array_key_exists($package->name, $this->packageNames)) {
                return false;
            }
            if ($this->isPackageWhiteListed($package) === false) {
                if ($this->packageNames[ $package->name ] === null) {
                    $this->packageNames[ $package->name ] = $this->confirm("The package $package->name is excluded by your whitelist configuration - are you sure you want to include it anyway?");
                }

                return $this->packageNames[ $package->name ];
            }

            return true;
        }

        return $this->isPackageWhiteListed($package) !== false;
    }

    /**
     * Determine if package is white listed
     *
     * @param Package $package The package
     *
     * @return bool|null
     */
    protected function isPackageWhiteListed(Package $package)
    {
        if (!is_array($this->whitelists)) {
            $this->whitelists = [];
            foreach (['path', 'remote', 'name'] as $whiteListType) {
                $option = $this->get('whitelist' . ucfirst($whiteListType) . 's');
                if ($option) {
                    $this->whitelists[ $whiteListType ] = '#^' . $option . '$#';
                }
            }
        }

        foreach ($this->whitelists as $type => $pattern) {
            $subject = $package->$type;
            if ($type === 'path') {
                $subject = rtrim(
                    $this->console->getFilesystem()->findShortestPath(
                        $this->get('composer.rootPackage.path'),
                        $subject,
                        true
                    ),
                    '/'
                );
            }
            if (preg_match($pattern, $subject)) {
                return true;
            }
        }

        return $this->whitelists ? false : null;
    }
}

?>
