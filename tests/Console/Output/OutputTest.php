<?php
/**
 * See class comment
 *
 * PHP Version 5
 *
 * @category Netresearch
 * @package  Netresearch\Kite\Test\Console\Output
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */

namespace Netresearch\Kite\Test\Console\Output;
use Netresearch\Kite\Console\Output\Output;

/**
 * Class OutputTest
 *
 * @category Netresearch
 * @package  Netresearch\Kite\Test\Console\Output
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */
class OutputTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Assert that the indention only indents when there was actual output on the
     * previous indention level
     *
     * @return void
     */
    public function testIndention()
    {
        $n = PHP_EOL;
        $expected
            = 'Cosmological Constant' . $n
            . '  Einstein, the frizzy-haired,' . $n
            . '  said E equals MC squared.' . $n
            . '  Thus all mass decreases' . $n
            . '  as activity ceases?' . $n
            . '    Not my mass, my ass declared!' . $n
            . '- Michael R. Burch' . $n;

        ob_start();

        $stream = fopen('php://output', 'rw');
        $output = new Output($stream);
        $output->indent();
        $output->writeln('Cosmological Constant');
        $output->indent();
        $output->writeln('Einstein, the frizzy-haired,');
        $output->writeln('said E equals MC squared.');
        $output->indent();
        $output->outdent();
        $output->writeln('Thus all mass decreases');
        $output->writeln('as activity ceases?');
        $output->indent();
        $output->indent();
        $output->writeln('Not my mass, my ass declared!');
        $output->outdent();
        $output->outdent();
        $output->outdent();
        $output->outdent();
        $output->writeln('- Michael R. Burch');

        $this->assertEquals($expected, ob_get_clean());
    }
}
?>
