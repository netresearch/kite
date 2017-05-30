<?php
/**
 * See class comment.
 *
 * PHP Version 5
 *
 * @category   Netresearch
 *
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 *
 * @link       http://www.netresearch.de
 */

namespace Netresearch\Kite\Workflow\Git;

use Netresearch\Kite\Workflow;

/**
 * Workflow to assert a git repo has no uncommited and unpushed changes.
 *
 * @category   Netresearch
 *
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 *
 * @link       http://www.netresearch.de
 */
class AssertUnchanged extends Workflow
{
    /**
     * Variable configuration.
     *
     * @return array
     */
    protected function configureVariables()
    {
        return [
            'cwd' => [
                'type'  => 'string',
                'label' => 'The directory to change into',
            ],
            '--',
        ] + parent::configureVariables();
    }

    /**
     * Assemble the tasks.
     *
     * @return void
     */
    public function assemble()
    {
        $cwd = $this->get('cwd');

        $this
            ->tryCatch('Detected unstaged changes - please commit or stash them first')
            ->git('diff-index', $cwd, ['quiet' => true, 'HEAD', '--']);

        $this
            ->tryCatch('Your branch is not in sync with the remote tracking branch')
            ->shell("test -z \"$(git status -u no | grep -E '[^'\''](ahead|behind|diverged)[^'\'']')\"", $cwd);
    }
}
