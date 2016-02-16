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
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Output the message
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage Task
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */
class OutputTask extends \Netresearch\Kite\Task
{
    /**
     * Configure the variables
     *
     * @return array
     */
    protected function configureVariables()
    {
        return array(
            'severity' => array(
                'type' => 'int',
                'label' => 'Severity of message (use OutputInterface::VERBOSITY_* constants)',
                'default' => OutputInterface::VERBOSITY_NORMAL
            ),
            'newLine' => array(
                'type' => 'bool',
                'default' => true,
                'label' => 'Whether to print a new line after message'
            ),
        );
    }

    /**
     * Don't do anything
     *
     * @return void
     */
    public function run()
    {
        $this->console->output($this->get('message'), $this->get('severity'), $this->get('newLine'));
    }
}
?>
