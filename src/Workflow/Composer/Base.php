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
     * @var array
     */
    protected $pushPackages = array();

    /**
     * Push all packages marked to be pushed
     *
     * @return void
     */
    protected function pushPackages()
    {
        if ($this->pushPackages) {
            while ($package = array_shift($this->pushPackages)) {
                $this->console->output("Pushing <comment>$package->name</comment>", false);
                $this->git('push', $package->path, array('u' => 'origin', $package->branch));
                $this->console->output(
                    str_repeat(chr(8), strlen($package->name))
                    . '<info>' . $package->name . '</info>'
                );
            }
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
     * @param \stdClass[] $packages Packages
     * @param bool        $autoFix  Whether to autofix wrong requirements
     *
     * @return void
     */
    protected function rewriteRequirements(array &$packages, $autoFix = false)
    {
        $checkedOutPackages = array_keys($packages);
        $unfixedRequirements = 0;
        while ($packageName = array_shift($checkedOutPackages)) {
            $branch = $packages[$packageName]->branch;
            $version = 'dev-' . $branch;
            foreach ($this->get('composer.packages') as $package) {
                if (array_key_exists($packageName, $package->requires)) {
                    // TODO: Set required version to branch alias, if any
                    $requiredVersion = $package->requires[$packageName];
                    if ($requiredVersion === '@dev') {
                        $requiredVersion = 'dev-master';
                    }
                    if ($requiredVersion !== $version) {
                        if ($autoFix) {
                            $fix = true;
                            $this->output("Changing required version of {$packageName} in {$package->name} from {$requiredVersion} to {$version}");
                        } else {
                            $this->output("<warning>{$package->name} depends on {$packageName} {$package->requires[$packageName]} and not {$version} as expected</warning>");
                            $this->output('<comment>If you don\'t fix that, the branches will probably change with composer update</comment>');
                            $fix = $this->confirm('Fix that?');
                        }
                        if ($fix) {
                            if (!array_key_exists($package->name, $packages)) {
                                $this->checkoutPackage($package, $branch, true);
                                $checkedOutPackages[] = $package->name;
                                $packages[$package->name] = $package;
                            }

                            $this->pushPackages[$packageName] = $packages[$packageName];
                            $this->rewriteRequirement($package, $packageName, $version);
                        } else {
                            $unfixedRequirements++;
                        }
                    }
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
     * Change the require statement for $requiredPackage to the newVersion in $package
     *
     * @param \stdClass $package         The package
     * @param string    $requiredPackage The required package
     * @param string    $newVersion      The new version
     *
     * @return void
     */
    protected function rewriteRequirement($package, $requiredPackage, $newVersion)
    {
        $currentVersion = $package->requires[$requiredPackage];
        $composerFile = $package->path . '/composer.json';
        $composerFileContents = file_get_contents($composerFile);
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
        $this->reloadRequires($package);
        if ($package->requires[$requiredPackage] !== $newVersion) {
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
     * Reload the requires from $package composer.json to $package->requires
     *
     * If $detectChanges, and there are changes on the requirements not in $ignorePackages
     * composer update is requested
     *
     * @param \stdClass $package The package
     *
     * @return void
     */
    protected function reloadRequires($package)
    {
        $file = $package->path . '/composer.json';
        if (file_exists($file)) {
            $composerJson = json_decode(file_get_contents($file));
            $package->requires = isset($composerJson->require) ? get_object_vars($composerJson->require) : array();
        }
    }

    /**
     * Checkout a package at a branch
     *
     * @param object $package The package
     * @param string $branch  The branch
     * @param bool   $create  Create the branch if it doesn't exist
     *
     * @return bool|null Whether checkout was successful or null when package is already at this branch
     */
    protected function checkoutPackage($package, $branch, $create = false)
    {
        if ($package->branch === $branch) {
            return null;
        }
        $remoteBranch = 'origin/' . $branch;
        $isRemote = in_array($remoteBranch, $package->branches, true);
        if (in_array($branch, $package->branches, true)) {
            $this->git('checkout', $package->path, $branch);
        } elseif ($isRemote) {
            $this->git('checkout', $package->path, array('b' => $branch, $remoteBranch));
        } elseif ($create) {
            $this->git('checkout', $package->path, array('b' => $branch));
            $package->branches[] = $branch;
        } else {
            return false;
        }

        if ($isRemote) {
            if (!isset($package->upstreams[$branch]) || $package->upstreams[$branch] !== $remoteBranch) {
                $this->git('branch', $package->path, array('u' => $remoteBranch));
                $package->upstreams[$branch] = $remoteBranch;
            }
            $this->git('rebase', $package->path);
        }

        $this->console->output("Checked out <comment>{$package->name}</comment> at <comment>$branch</comment>");

        $this->reloadRequires($package);
        $package->version = 'dev-' . $branch;
        $package->branch = $branch;

        return true;
    }

    /**
     * Merge a $branch into $package's current branch
     *
     * @param \stdClass $package The package
     * @param string    $branch  The branch
     * @param null      $message The commit message (if any)
     * @param bool      $squash  Whether to squash the changes
     *
     * @return void
     */
    protected function mergePackage($package, $branch, $message = null, $squash = false)
    {
        $this->git('fetch', $package->path, array('force' => true, 'origin', $branch . ':' . $branch));

        $ff = $branch == 'master' ? 'ff' : 'no-ff';
        $optArg = array($ff => true, 'no-commit' => true);
        if ($squash) {
            $optArg['squash'] = true;
        }
        $optArg[] = $branch;

        try {
            $this->git('merge', $package->path, $optArg);
        } catch (\Exception $e) {
            $diff = $this->git('diff', $package->path, array('name-only' => true, 'diff-filter' => 'U'));
            $conflictedFiles = array_flip(explode("\n", $diff));
            if (array_key_exists('composer.json', $conflictedFiles)) {
                $this->resolveRequirementsConflict($package);
                $this->git('add', $package->path, 'composer.json');
                unset($conflictedFiles['composer.json']);
            }
            if ($conflictedFiles) {
                throw new Exception('There are unresolved conflicts - please resolve them and then commit the result');
            }
        }
        if ($this->git('status', $package->path, array('porcelain' => true))) {
            if (!$message) {
                $message = $this->answer(
                    'Enter commit message:',
                    'Merging ' . $branch . ' into ' . $package->branch
                );
            }
            $this->git('commit', $package->path, array('n' => true, 'm' => $message));
        }

        $this->console->output("Merged with <comment>$branch</comment> in <comment>{$package->name}</comment>");

        $this->reloadRequires($package);
        $this->pushPackages[$package->name] = $package;
    }

    /**
     * Try to solve conflicts inside the require section of the $package composer.json
     *
     * @param \stdClass $package The package
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
        $diff = array_diff_key(get_object_vars($theirs), get_object_vars($ours));
        foreach (get_object_vars($ours) as $key => $value) {
            if ($key !== 'require' && (!property_exists($theirs, $key) || serialize($value) !== serialize($theirs->$key))) {
                $diff[$key] = $value;
            }
        }
        if ($diff !== array()) {
            throw new Exception('Can not automerge composer.json due to conflicts outside require object');
        }

        preg_match('/\{[^\{]*<{7}.+?>{7}[^\{]*([\t ]*)\}/smU', $contents, $matches, PREG_OFFSET_CAPTURE);
        $prefix = "\n" . str_repeat($matches[1][0], 2);
        $requireBlock = '';
        foreach ($this->mergeRequirements($package, $ours, $theirs) as $packageName => $version) {
            $requireBlock .= $prefix . '"' . $packageName . '": "' . $version . '",';
        }
        file_put_contents(
            $package->path . '/composer.json',
            substr($contents, 0, $matches[0][1]) . '{'
            . rtrim($requireBlock, ',') . "\n"
            . $matches[1][0] . '}'
            . substr($contents, $matches[0][1] + strlen($matches[0][0]))
        );
    }

    /**
     * Merge the requirements from different sides of the current $package
     *
     * @param \stdClass $package The current package
     * @param array     $ours    Our composer.json as array
     * @param array     $theirs  Their composer.json as array
     *
     * @return array
     */
    private function mergeRequirements($package, $ours, $theirs)
    {
        $oursRequire = get_object_vars($ours['require']);
        $theirsRequire = get_object_vars($theirs['require']);
        $mergedRequires = array_merge($oursRequire, $theirsRequire);
        $packages = $this->get('composer.packages');
        $preferredVersion = 'dev-' . $package->branch;
        foreach ($mergedRequires as $packageName => $version) {
            $actualVersion = ($version === '@dev') ? 'dev-master' : $version;
            if (array_key_exists($packageName, $oursRequire)
                && $version !== $oursRequire[$packageName]
                && $actualVersion !== $oursRequire[$packageName]
                && $actualVersion !== $preferredVersion
                && array_key_exists($packageName, $packages)
                && in_array($package->branch, $packages[$packageName]->branches, true)
            ) {
                $mergedRequires[$packageName] = $preferredVersion;
            }
        }
        return $mergedRequires;
    }
}
?>
