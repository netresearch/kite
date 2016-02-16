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
use Netresearch\Kite\Task;
use Netresearch\Kite\Workflow;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Go through all packages and merge the given branch into the current, when it exists
 *
 * @category   Netresearch
 * @package    Netresearch\Kite\Workflow
 * @subpackage Composer
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */
class Merge extends Base
{
    /**
     * Configures the arguments/options
     *
     * @return array
     */
    protected function configureVariables()
    {
        return array(
            'branch' => array(
                'type' => 'string',
                'label' => 'The branch to merge in',
                'argument' => true,
                'required' => true
            ),
            'squash' => array(
                'type' => 'bool',
                'label' => 'Whether to merge with --squash',
                'option' => true,
            ),
            'delete' => array(
                'type' => 'bool',
                'label' => 'Whether to delete the branch after merge',
                'option' => true,
            ),
            'message' => array(
                'type' => 'bool',
                'label' => 'Message for commits (if any)',
                'option' => true,
                'shortcut' => 'm'
            ),
            'no-diagnose' => array(
                'type' => 'bool',
                'label' => 'Don\'t do a diagnose upfront',
                'option' => true,
            ),
            '--'
        ) + parent::configureVariables();
    }

    /**
     * Assemble the tasks
     *
     * @return void
     */
    public function assemble()
    {
        $this->callback(
            function () {
                $mergeBranch = $this->get('branch');
                $diagnose = !$this->get('no-diagnose');
                $delete = $this->get('delete', false);
                $squash = $this->get('squash', false);
                $optArg[$mergeBranch == 'master' ? 'ff' : 'no-ff'] = true;
                $message = $this->get('message', '');

                if ($diagnose) {
                    $this->sub('Netresearch\Kite\Workflow\Composer\Diagnose', array('fix' => true));
                }

                $mergePackages = $this->getMergePackages($mergeBranch, !$diagnose);
                if (!$mergePackages) {
                    $this->console->output("<warning>Could not find branch $mergeBranch in any installed package</warning>");
                    return;
                }

                foreach ($mergePackages as $package) {
                    $this->mergePackage($package, $mergeBranch, $message, $squash);

                    if ($delete) {
                        $this->git('branch', $package->path, array('d' => $mergeBranch));
                        $this->git('push', $package->path, array('origin', ':' . $mergeBranch));
                    }
                }

                $this->rewriteRequirements($mergePackages, true);
                $this->pushPackages();

                // Each package containing the branch should now be at the tip of it's
                // current branch. Anyway we do a composer update in order to update lock file
                // and eventually changed dependencies
                $this->doComposerUpdate();
            }
        );
    }

    /**
     * Get the packages which have the branch to merge
     *
     * @param string $mergeBranch mergeBranch
     * @param bool   $pull        Whether to pull when branch exists
     *
     * @return array
     */
    protected function getMergePackages($mergeBranch, $pull)
    {
        $mergePackages = array();
        foreach ($this->get('composer.packages') as $package) {
            if ($mergeBranch === $package->branch) {
                $checkout = $this->confirm("{$package->name} is checked out at {$mergeBranch} - do you want to checkout another branch from master and merge into that?");
                if ($checkout) {
                    $choices = array('Create new branch');
                    foreach ($package->branches as $choiceBranch) {
                        if ($choiceBranch !== $mergeBranch) {
                            $choices[] = $choiceBranch;
                        }
                    }
                    $checkoutBranch = $inferFromBranch = $this->choose('Select branch:', $choices, 0);
                    if ($checkoutBranch == $choices[0]) {
                        $checkoutBranch = $this->answer('Branch name:');
                        $package->branches[] = $checkoutBranch;
                        $inferFromBranch = 'master';
                    }
                    $this->git('fetch', $package->path, array('force' => true, 'origin', $inferFromBranch . ':' . $checkoutBranch));
                    $this->git('checkout', $package->path, array($checkoutBranch));
                    $package->branch = $checkoutBranch;
                    $package->version = 'dev-' . $checkoutBranch;
                } else {
                    continue;
                }
            } elseif (in_array($mergeBranch, $package->branches, true)) {
                // Pull is actually not needed here, as we have the current state
                // from composer service - but we don't know if we should rebase or
                // merge - thus we simply pull here
                $this->git('pull', $package->path);
            } else {
                continue;
            }
            $mergePackages[$package->name] = $package;
        }
        return $mergePackages;
    }
}
?>
