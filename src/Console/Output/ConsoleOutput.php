<?php
/**
 * See class comment.
 *
 * PHP Version 5
 *
 * @category Netresearch
 *
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 *
 * @link     http://www.netresearch.de
 */

namespace Netresearch\Kite\Console\Output;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;

/**
 * Class ConsoleOutput.
 *
 * @category Netresearch
 *
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 *
 * @link     http://www.netresearch.de
 */
class ConsoleOutput extends Output
{
    /**
     * ConsoleOutput constructor.
     *
     * @param int|mixed                     $verbosity The verbosity
     * @param bool                          $decorated If output should be decorated
     * @param OutputFormatterInterface|null $formatter Formatter
     */
    public function __construct($verbosity = self::VERBOSITY_NORMAL, $decorated = null, OutputFormatterInterface $formatter = null)
    {
        $outputStream = 'php://stdout';
        if (!$this->hasStdoutSupport()) {
            $outputStream = 'php://output';
        }

        parent::__construct(fopen($outputStream, 'w'), $verbosity, $decorated, $formatter);
    }

    /**
     * Returns true if current environment supports writing console output to
     * STDOUT.
     *
     * IBM iSeries (OS400) exhibits character-encoding issues when writing to
     * STDOUT and doesn't properly convert ASCII to EBCDIC, resulting in garbage
     * output.
     *
     * @return bool
     */
    protected function hasStdoutSupport()
    {
        return 'OS400' != php_uname('s');
    }
}
