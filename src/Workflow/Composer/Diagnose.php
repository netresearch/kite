<?php
/**
 * See class comment
 *
 * PHP Version 5
 *
 * @category Netresearch
 * @package  Netresearch\Kite\Workflow\Composer
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */

namespace Netresearch\Kite\Workflow\Composer;
use Netresearch\Kite\Exception;

use Netresearch\Kite\Workflow;

/**
 * Workflow to diagnose packages and fix found problems
 *
 * @category Netresearch
 * @package  Netresearch\Kite\Workflow\Composer
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */
class Diagnose extends Base
{
    /**
     * @var array
     */
    protected $checks = array();

    /**
     * @var array
     */
    protected $fixes = array();

    /**
     * @var bool
     */
    protected $composerUpdateRequired = false;

    /**
     * @var bool
     */
    protected $dontCheckCurrentPackageAgain = false;

    /**
     * Configure the options
     *
     * @return array
     */
    protected function configureVariables()
    {
        foreach (get_class_methods($this) as $method) {
            if (substr($method, 0, 5) === 'check' && $method[5] === strtoupper($method[5])) {
                $check = substr($method, 5);
                $this->checks[] = $check;
                if (method_exists($this, 'fix' . $check)) {
                    $this->fixes[] = $check;
                }
            }
        }

        return array(
            'check' => array(
                'type' => 'array',
                'option' => true,
                'label' => 'Only execute these checks - available checks are ' . implode(', ', $this->checks),
            ),
            'fix' => array(
                'type' => 'boolean|array',
                'option' => true,
                'label' => 'Enable fixes and optionally reduce to certain fixes - available fixes are ' . implode(', ', $this->fixes),
            ),
        ) + parent::configureVariables();
    }

    /**
     * Override to assemble the tasks
     *
     * @return void
     */
    public function assemble()
    {
        $this->callback(
            function () {
                $fix = $this->get('fix');
                $fixes = ($fix === true) ? $this->fixes : (array) $fix;
                $checks = $this->get('check') ?: $this->checks;
                foreach ($checks as $check) {
                    $this->doCheck($check, in_array($check, $fixes, true));
                }
            }
        );
    }

    /**
     * Run the checks
     *
     * @param string  $check        The check to execute
     * @param boolean $fix          Whether to fix found problems
     * @param array   $packageNames Package names to filter this one
     *                              (for recursive calls only)
     *
     * @return void
     */
    public function doCheck($check, $fix, array $packageNames = array())
    {
        $packages = $this->get('composer.packages');
        $errors = 0;
        if (!$packageNames) {
            $this->console->output($check, false);
            $this->console->indent();
        }
        $rerunForPackages = array();
        foreach ($packages as $package) {
            if ($packageNames && !in_array($package->name, $packageNames, true)) {
                continue;
            }
            if (is_string($message = $this->{'check' . $check}($package))) {
                if (!$packageNames && !$errors) {
                    $this->console->output(
                        str_repeat(chr(8), strlen($check))
                        . '<fg=red;bg=black>' . $check . '</>'
                    );
                }
                $message = sprintf($message, "package <comment>$package->name</>");
                $this->console->output(ucfirst($message));
                $errors++;
                if ($fix) {
                    $this->dontCheckCurrentPackageAgain = false;
                    $this->console->indent();
                    $this->{'fix' . $check}($package);
                    $this->console->outdent();
                    if (!$this->dontCheckCurrentPackageAgain) {
                        $rerunForPackages[] = $package->name;
                    }
                }
            }
            $this->pushPackages();
            if ($this->composerUpdateRequired) {
                $this->composerUpdateRequired = false;
                $this->doComposerUpdate();
                $this->doCheck($check, $fix);
                return;
            }
        }
        if ($rerunForPackages) {
            $this->doCheck($check, $fix, $rerunForPackages);
        }
        if (!$packageNames) {
            if (!$errors) {
                $this->console->output(
                    str_repeat(chr(8), strlen($check))
                    . '<fg=green;bg=black>' . $check . '</>'
                );
            }
            $this->console->outdent();
        }
    }

    /**
     * Check for unstaged changes
     *
     * @param object $package The package
     *
     * @return null|string message string on problems, null otherwise
     */
    protected function checkUnstagedChanges($package)
    {
        if ($package->git) {
            $status = $this->git('status', $package->path, array('porcelain' => true));
            if (trim($status)) {
                return '%s has uncommited changes';
            }
        }
        return null;
    }

    /**
     * Fix unstaged changes
     *
     * @param object $package The package
     *
     * @return void
     */
    protected function fixUnstagedChanges($package)
    {
        $fix = $this->selectFixes(
            array(
                1 => 'Show diff (and ask again)',
                2 => 'Withdraw changes',
                3 => 'Stash changes',
            )
        );
        switch ($fix) {
        case 1:
            $this->git('add', $package->path, array('N' => true, 'A' => true));
            $this->console->output('');
            $this->git('diff', $package->path, 'HEAD', array('tty' => true));
            $this->console->output('');
            $this->fixUnstagedChanges($package);
            break;
        case 2:
            $this->git('reset', $package->path, array('hard' => true));
            $this->git('clean', $package->path, array('i' => true, 'd' => true), array('tty' => true));
            break;
        case 3:
            $this->git('reset', $package->path);
            $args = 'save -u';
            if ($message = $this->answer('Message for stash:')) {
                $args .= ' ' . escapeshellarg($message);
            }
            $this->git('stash', $package->path, $args);
            break;
        }
    }

    /**
     * Check if package is ahead and/or behind remote tracking branch
     *
     * @param object $package The package
     *
     * @return null|string message string on problems, null otherwise
     */
    protected function checkRemoteSynchronicity($package)
    {
        if ($package->git) {
            $status = $this->git('status', $package->path, '--branch --porcelain -u no');
            list($branchInfo) = explode("\n", $status, 2);
            preg_match('/ \[(ahead|behind) [0-9]+(?:, (ahead|behind) [0-9]+)?\]$/', $branchInfo, $matches);
            array_shift($matches);
            $package->ahead = in_array('ahead', $matches, true);
            $package->behind = in_array('behind', $matches, true);
            if ($package->ahead && $package->behind) {
                $type = 'has diverged from';
            } elseif ($package->ahead) {
                $type = 'is ahead of';
            } elseif ($package->behind) {
                $type = 'is behind';
            } else {
                return null;
            }
            return "%s $type remote tracking branch";
        }
        return null;
    }

    /**
     * Push and/or pull or show the differences
     *
     * @param object $package The package
     *
     * @return void
     */
    protected function fixRemoteSynchronicity($package)
    {
        $commands = array();
        if ($package->behind) {
            $commands[] = 'pull';
        }
        if ($package->ahead) {
            $commands[] = 'push';
        }
        $fixes = array(
            1 => 'Show incoming/outgoing commits (and ask again)',
            2 => ucfirst(implode(' and ', $commands)),
        );
        if (count($commands) > 1 && $package->branch !== 'master') {
            $fixes[3] = 'Push with <comment>--force</comment>';
        }
        switch ($this->selectFixes($fixes)) {
        case 1:
            $this->gitRevDiff($package, '@\{u\}', 'Remote', 'Local');
            $this->fixRemoteSynchronicity($package);
            break;
        case 3:
            $commands = array('push');
            $options = '--force';
        case 2:
            foreach ($commands as $command) {
                $pck = "<comment>{$package->name}</comment>";
                $this->console->output($msg = ucfirst($command) . "ing $pck...", false);
                $this->git($command, $package->path, isset($options) ? $options : null);
                $this->console->output(
                    str_repeat(chr(8), strlen(strip_tags($msg)))
                    . "<fg=green>Sucessfully {$command}ed $pck</>"
                );
            }
            break;
        }
    }

    /**
     * Checks for packages that require the package in another branch than the
     * current - or for packages that require the package in a version when
     * the package is checked out at a branch
     *
     * Further requirements checks are left to composer
     *
     * @param object $package The package
     *
     * @return null|string message string on problems, null otherwise
     */
    protected function checkRequirementsMatch($package)
    {
        if ($package->git && !$package->isRoot) {
            $package->requiredBranch = null;
            $package->unsatisfiedDependentPackages = array();
            $package->invalidRequirements = false;
            $dependentPackages = [];
            foreach ($this->get('composer.packages') as $dependentPackage) {
                if (!isset($dependentPackage->requiresUpToDate)) {
                    $this->reloadRequires($dependentPackage);
                    $dependentPackage->requiresUpToDate = true;
                }
                if (array_key_exists($package->name, $dependentPackage->requires)) {
                    if (substr($dependentPackage->requires[$package->name], 0, 4) === 'dev-') {
                        $dependentPackages[] = $dependentPackage->name;
                        $requiredBranch = substr($dependentPackage->requires[$package->name], 4);
                        if (strpos($requiredBranch, '#')) {
                            $otherHash = isset($hash) ? $hash : null;
                            list($requiredBranch, $hash) = explode('#', $requiredBranch);
                            if ($otherHash && $otherHash !== $hash) {
                                return '<error>Two or more packages require %s in different commits</error>';
                            }
                        }
                        if ($package->requiredBranch && $package->requiredBranch !== $requiredBranch) {
                            $package->invalidRequirements = true;
                            return '<error>' . array_pop($dependentPackages)
                                . ' and ' . array_pop($dependentPackages)
                                . ' require %s in different branches</error>';
                        }
                        $package->requiredBranch = $requiredBranch;
                        if ($requiredBranch !== $package->branch) {
                            $package->unsatisfiedDependentPackages[$package->name] = $dependentPackage;
                        }
                    } elseif ($package->branch) {
                        $package->unsatisfiedDependentPackages[$package->name] = $dependentPackage;
                    }
                }
            }
            $constraint = $package->tag ?: 'dev-' . $package->branch;
            if ($package->requiredBranch && $package->requiredBranch !== $package->branch) {
                return "%s is at <comment>$constraint</comment> but is required at <comment>dev-{$package->requiredBranch}</comment>";
            }
            if (isset($hash) && substr($package->source->reference, 0, strlen($hash)) !== $hash) {
                return "%s is at <comment>{$constraint}#{$package->source->reference}</comment> but is required at <comment>dev-{$package->requiredBranch}#{$hash}</comment>";
            }
        }
        return null;
    }

    /**
     * Checkout package at required branch or make dependent packages require the
     * current branch of the package.
     *
     * @param object $package The package
     *
     * @return void
     */
    protected function fixRequirementsMatch($package)
    {
        if ($package->invalidRequirements) {
            $this->doExit('Can not fix that', 1);
        }
        $currentConstraint = $package->tag ?: 'dev-' . $package->branch;
        $requiredConstraint = 'dev-' . $package->requiredBranch;
        if ($package->requiredBranch) {
            $actions = array(
                1 => "Show divergent commits between <comment>$currentConstraint</comment> and <comment>$requiredConstraint</comment> (and ask again)",
                2 => "Checkout package at <comment>$requiredConstraint</comment>"
            );
        } else {
            $actions = array();
        }
        if ($count = count($package->unsatisfiedDependentPackages)) {
            $actions[3] = "Make ";
            $git = true;
            foreach ($package->unsatisfiedDependentPackages as $i => $requirePackage) {
                $git &= $requirePackage->git;
                if ($i === $count - 1 && $i > 0) {
                    $actions[3] .= 'and ';
                }
                $actions[3] .= "<comment>{$requirePackage->name}</comment> ";
            }
            $actions[3] .= "require <comment>{$currentConstraint}</comment>";
            if (!$git) {
                unset($actions[3]);
            }
        }
        switch ($this->selectFixes($actions)) {
        case 1:
            $this->gitRevDiff($package, $package->requiredBranch, $requiredConstraint, $currentConstraint);
            $this->fixRequirementsMatch($package);
            break;
        case 2:
            $this->checkoutPackage($package, $package->requiredBranch);
            $this->reloadRequires($package);
            break;
        case 3:
            foreach ($package->unsatisfiedDependentPackages as $dependentPackage) {
                $this->rewriteRequirement($dependentPackage, $package->name, $currentConstraint);
            }
            break;
        }
    }

    /**
     * Check if package is on another branch/tag or another commit than locked
     *
     * @param object $package The package
     *
     * @return null|string message string on problems, null otherwise
     */
    protected function checkDivergeFromLock($package)
    {
        if ($package->git && !$package->isRoot) {
            $constraint = $package->tag ? ltrim($package->tag, 'v') : 'dev-' . $package->branch;
            if (($package->tag || $package->branch) && $package->version !== $constraint) {
                if ($this->git('rev-parse', $package->path, 'HEAD') === $package->source->reference) {
                    // HEAD is tip of branch and tag - so only branch was detected
                    return;
                }
                return "%s is at <comment>$constraint</comment> but is locked at <comment>{$package->version}</comment>";
            }
            $rawCounts = $this->git('rev-list', $package->path, "--count --left-right --cherry-pick {$package->source->reference}...");
            $counts = explode("\t", $rawCounts);
            if ($counts[0] || $counts[1]) {
                $num = $counts[0] ?: $counts[1];
                $type = $counts[0] ? 'behind</>' : 'ahead</> of';
                return '%s is <comment>' . $num . ' commit' . ($num > 1 ? 's ' : ' ')
                    . $type . ' locked commit <comment>'
                    . substr($package->source->reference, 0, 7) . '</>';
            }
        }
        return null;
    }

    /**
     * Run composer update or checkout the package at locked state
     *
     * @param object $package The package
     *
     * @return void
     */
    protected function fixDivergeFromLock($package)
    {
        $fix = $this->selectFixes(
            array(
                1 => 'Show commits between locked and current commit (and ask again)',
                2 => 'Run <info>composer update</info> (you may loose local changes)',
                3 => 'Checkout package at locked commit <comment>'
                    . substr($package->source->reference, 0, 7)
                    . "</comment> (<comment>{$package->version}</comment>)",
            )
        );

        switch ($fix) {
        case 1:
            $this->gitRevDiff($package, $package->source->reference, 'Locked', 'Current');
            $this->fixDivergeFromLock($package);
            break;
        case 2:
            $this->composerUpdateRequired = true;
            break;
        case 3:
            $this->git('checkout', $package->path, $package->source->reference);
            $this->reloadRequires($package);
            break;
        }
    }

    /**
     * Check if composer lock is up to date
     *
     * @param object $package The package
     *
     * @return null|string message string on problems, null otherwise
     */
    protected function checkComposerLockActuality($package)
    {
        if ($package->isRoot) {
            $lock = json_decode(file_get_contents('composer.lock'));
            if (md5_file('composer.json') !== $lock->{'hash'}) {
                return 'The lock file is not up to date with the latest changes in root composer.json';
            }
        }
        return null;
    }

    /**
     * Run composer update (--lock)
     *
     * @param object $package The package
     *
     * @return void
     */
    protected function fixComposerLockActuality($package)
    {
        $fix = $this->selectFixes(
            array(
                1 => 'Run <info>composer update</info> (you may loose local changes)',
            )
        );
        if ($fix === 1) {
            $this->composerUpdateRequired = true;
        }
    }

    /**
     * Select from the available fixes
     *
     * @param array $fixes The fixes
     *
     * @return int|null
     */
    protected function selectFixes($fixes)
    {
        $this->console->outdent();
        $fixes['n'] = 'Nothing for now, ask again later';
        $fixes['i'] = 'Ignore';
        $fixes['x'] = 'Exit';
        $result = $this->choose('What do you want to do?', $fixes);
        $this->console->indent();
        if ($result == 'i' || $result == 'n') {
            $this->dontCheckCurrentPackageAgain = ($result === 'i');
            return null;
        }
        if ($result == 'x') {
            $this->doExit();
        }
        return (int) $result;
    }

    /**
     * Show commits that are not in the HEAD but in ref and vice versa
     *
     * @param object $package    The package
     * @param string $ref        The ref name
     * @param string $leftTitle  The title of the ref name
     * @param string $rightTitle The title of HEAD
     *
     * @return void
     */
    protected function gitRevDiff($package, $ref, $leftTitle, $rightTitle)
    {
        for ($i = 0; $i <= 1; $i++) {
            $side = $i ? 'left' : 'right';
            $otherSide = $i ? 'right' : 'left';
            $args = "--$side-only --cherry-pick --pretty=format:'%C(yellow)%h %Cgreen%cd %an%Creset%n  %s' --abbrev-commit --date=local ";
            $args .= $ref . '...';
            $log = $this->git('log', $package->path, $args, array('shy' => true));
            if ($log) {
                $this->console->output('');

                $title = "<info>{${$side . 'Title'}}</info> > <info>{${$otherSide . 'Title'}}</info>";
                $this->output($title);
                $this->console->output(str_repeat('-', strlen(strip_tags($title))));

                $this->console->output($log);

                $this->console->output('');
            }
        }
    }
}
?>
