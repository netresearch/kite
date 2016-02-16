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

/**
 * Execute a git command and return the result
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage Task
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */
class GitTask extends ShellTask
{
    /**
     * Infere command to gitCommand
     *
     * @param string $name  Name
     * @param mixed  $value Value
     *
     * @return void
     */
    public function offsetSet($name, $value)
    {
        if ($name === 'command') {
            if (is_array($value)) {
                foreach ($value as &$item) {
                    $item = 'git ' . $item;
                }
            } else {
                $value = 'git ' .  $value;
            }
        }
        parent::offsetSet($name, $value);
    }
}
?>
