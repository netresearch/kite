<?php
/**
 * See class comment
 *
 * PHP Version 5
 *
 * @category Netresearch
 * @package  Netresearch\Kite\Test\Workflow\Composer
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */

namespace Netresearch\Kite\Test\Workflow\Composer;
use Netresearch\Kite\Test\TestCase;

/**
 * Tests for the Base Class
 *
 * @category Netresearch
 * @package  Netresearch\Kite\Test\Workflow\Composer
 * @author   Alexander Gunkel <alexander.gunkel@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */

class BaseTest extends TestCase
{
    /**
     * Test the correct new name of version in composer.json
     *
     * @dataProvider versionNameProvider
     */
    public function testGetNewVersionName($new, $old, $result)
    {
        $base = $this->getMockForAbstractClass(
            '\\Netresearch\\Kite\\Workflow\\Composer\\Base',
            array(
                $this->getJobMock()
            )
        );

        $compVersion = $base->getNewVersionName($new, $old);

        $this->assertEquals($compVersion, $result);
    }

    /**
     * Gives combinations of branch names with ans without aliases and the respective
     * result of getNewVersionName
     *
     * @return array
     */
    public function versionNameProvider()
    {
        return array(
            array(
                'newversion',
                'oldversion',
                'newversion as oldversion'
            ),
            array(
                'dev-NEW-123',
                'master',
                'dev-NEW-123 as master'
            ),
            array(
                'newestversion',
                'newversion as oldversion',
                'newestversion as oldversion'
            )
        );
    }
}
