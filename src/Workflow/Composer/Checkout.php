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
use Netresearch\Kite\Exception;

/**
 * Checkout a branch and eventually merge it with the previously checked out branch
 *
 * @category   Netresearch
 * @package    Netresearch\Kite\Workflow
 * @subpackage Composer
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */
class Checkout extends Base
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
                'type' => 'string|array',
                'label' => 'The branch',
                'argument' => true,
                'required' => true
            ),
            'merge' => array(
                'type' => 'bool',
                'label' => 'Whether to merge the checked out branch with the previously checked out branch',
                'option' => true,
                'shortcut' => 'm'
            ),
            '--'
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
                $this->checkoutPackages(
                    (array) $this->get('branch'),
                    $this->get('merge')
                );
            }
        );
    }

    /**
     * Go through all packages and check it out in the first matching branch
     *
     * @param array $branches The branches to try
     * @param bool  $merge    Whether to merge the new branch with the previously
     *                        checked out branch
     *
     * @return void
     */
    protected function checkoutPackages(array $branches, $merge = false)
    {
        $packages = array();
        foreach ($this->get('composer.packages') as $package) {
            foreach ($branches as $branch) {
                $oldBranch = $package->branch;
                if ($oldBranch === $branch) {
                    break;
                }
                if ($this->checkoutPackage($package, $branch)) {
                    $packages[$package->name] = $package;
                    if ($merge && $oldBranch !== $branch) {
                        $this->mergePackage($package, $oldBranch);
                    }
                    break;
                }
            }
        }

        if (!$packages) {
            $lastBranch = array_pop($branches);
            $message = 'Could not find branch ';
            if ($branches) {
                $message .= implode(', ', $branches) . ' or ';
            }
            $message .= $lastBranch . ' in any installed package';
            $this->console->output("<warning>$message</warning>");
            return;
        }

        $this->rewriteRequirements($packages, $merge);
        $this->pushPackages();

        // Each package containing one of the branches should now be checked out in
        // this branch. Anyway we do a composer update in order to update lock file
        // and eventually changed dependencies
        $this->doComposerUpdate();
    }
}

?>
