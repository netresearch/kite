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
use Netresearch\Kite\Exception\ExitException;

/**
 * Exit
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage Task
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */
class ExitTask extends Task
{
    /**
     * Configure the options
     *
     * @return array
     */
    protected function configureVariables()
    {
        return array(
            'code' => array(
                'type' => 'int',
                'default' => 0,
                'label' => 'Code to exit with'
            ),
            '--'
        ) + parent::configureVariables();
    }

    /**
     * Run the task
     *
     * @return void
     */
    public function run()
    {
        throw new ExitException($this->get('message'), $this->get('code'));
    }
}
?>
