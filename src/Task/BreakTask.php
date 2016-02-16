<?php
/**
 * See class comment
 *
 * PHP Version 5
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage Task
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */

namespace Netresearch\Kite\Task;
use Netresearch\Kite\Task;
use Netresearch\Kite\Exception\BreakException;

/**
 * Break the current iteration (of Tasks chain f.i.)
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage Task
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */
class BreakTask extends Task
{
    /**
     * Run the task
     *
     * @throws BreakException
     * @throws \Netresearch\Kite\Exception
     *
     * @return void
     */
    public function run()
    {
        if ($this->has('message')) {
            $this->console->output('<info>' . $this->get('message') . '</info>');
        }
        throw new BreakException('Execution stopped');
    }
}

?>
