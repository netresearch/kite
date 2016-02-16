<?php
/**
 * See class comment
 *
 * PHP Version 5
 *
 * @category Netresearch
 * @package  Netresearch\Kite\Console\Output
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */

namespace Netresearch\Kite\Console\Output;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;

use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

/**
 * Class Output
 *
 * @category Netresearch
 * @package  Netresearch\Kite\Console\Output
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */
class Output extends StreamOutput
{
    /**
     * @var int
     */
    protected $indention = 0;

    protected $actualIndention = 0;

    protected $lastIndention = null;

    /**
     * @var bool Whether previous, actually output line had newline ending
     */
    protected $previousWasNewLine = true;

    protected $outputOnCurrentIndention = false;

    /**
     * @var array
     */
    protected $terminalDimensions;

    /**
     * Constructor.
     *
     * @param mixed                         $stream    A stream resource
     * @param int                           $verbosity The verbosity level (one of the VERBOSITY constants in OutputInterface)
     * @param bool|null                     $decorated Whether to decorate messages (null for auto-guessing)
     * @param OutputFormatterInterface|null $formatter Output formatter instance (null to use default OutputFormatter)
     *
     * @throws \InvalidArgumentException When first argument is not a real stream
     *
     * @api
     */
    public function __construct($stream, $verbosity = self::VERBOSITY_NORMAL, $decorated = null, OutputFormatterInterface $formatter = null)
    {
        parent::__construct($stream, $verbosity, $decorated, $formatter);
        $formatter = $this->getFormatter();
        $formatter->setStyle('cmd', new OutputFormatterStyle('black', null, array('bold')));
        $formatter->setStyle('warning', new OutputFormatterStyle('black', 'yellow'));
        $formatter->setStyle('step', new OutputFormatterStyle('green', 'black'));
    }

    /**
     * Set terminal dimensions
     *
     * @param array $terminalDimensions Dimensions
     *
     * @return void
     */
    public function setTerminalDimensions($terminalDimensions)
    {
        $this->terminalDimensions = $terminalDimensions;
    }

    /**
     * Increase indention for all following lines
     *
     * @param int $tabs The tabs
     *
     * @return void
     */
    public function indent($tabs = 1)
    {
        $this->indention += $tabs;
    }

    /**
     * Decrease indention for all following lines
     *
     * @param int $tabs The tabs
     *
     * @return void
     */
    public function outdent($tabs = 1)
    {
        $this->indention -= $tabs;
        if ($this->indention < 0) {
            $this->indention = 0;
        }
    }

    /**
     * Get the current indention (this actually indents further/back when required)
     *
     * @return string
     */
    protected function getIndention()
    {
        if ($this->lastIndention !== null) {
            if ($this->indention > $this->lastIndention) {
                $this->actualIndention++;
            } elseif ($this->indention < $this->lastIndention) {
                $this->actualIndention = max(0, min($this->actualIndention - 1, $this->indention));
            }
        }
        $this->lastIndention = $this->indention;
        return str_repeat(" ", $this->actualIndention * 2);
    }

    /**
     * Write to output
     *
     * @param array|string $messages Messages
     * @param bool         $newline  Whether to append a newline
     * @param int          $type     The output type
     *
     * @return void
     */
    public function write($messages, $newline = false, $type = \Symfony\Component\Console\Output\Output::OUTPUT_NORMAL)
    {
        $messages = (array) $messages;

        foreach ($messages as &$message) {
            $l = strlen($message) - 1;
            if ($l >= 0) {
                if ($message[$l] === "\n") {
                    $message = substr($message, 0, $l);
                    $l--;
                    $newline = true;
                }
                if ($this->previousWasNewLine && $l >= 0 && $message[0] !== "\n") {
                    $message = $this->getIndention() . $message;
                }
                if (strpos($message, "\n") !== false) {
                    $message = str_replace("\n", "\n" . $this->getIndention(), $message);
                }

                // TODO: Indent wrapped lines - that's just not that easy because of the ANSI color escape codes
            }
        }

        parent::write($messages, $newline, $type);

        $this->previousWasNewLine = $newline;
    }


}

?>
