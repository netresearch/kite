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

/**
 * Migrate the schema changes of all extensions and return the update suggestions
 *
 * @category   Netresearch
 * @package    Netresearch\Kite
 * @subpackage Task
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */
class SchemaMigrationTask extends Task
{
    /**
     * @var \TYPO3\CMS\Install\Service\SqlSchemaMigrationService
     * @inject
     */
    protected $schemaMigrationService;

    /**
     * @var \TYPO3\CMS\Install\Service\SqlExpectedSchemaService
     * @inject
     */
    protected $expectedSchemaService;

    /**
     * Run the task
     *
     * @return array
     */
    public function run()
    {
        $bootstrap = GeneralUtility::makeInstance('TYPO3\CMS\Extbase\Core\Bootstrap');
        $bootstrap->initialize(array());

        $this->preview();

        // @codingStandardsIgnoreStart
        $updateStatements = $this->schemaMigrationService->getUpdateSuggestions(
            $this->schemaMigrationService->getDatabaseExtra(
                $this->expectedSchemaService->getExpectedDatabaseSchema(),
                $this->schemaMigrationService->getFieldDefinitions_database()
            )
        );
        // @codingStandardsIgnoreEnd

        $this->console->indent();
        $count = 0;
        $execute = $this->job->shouldExecute();
        foreach (array('add', 'change', 'create_table') as $type) {
            foreach ((array) $updateStatements[$type] as $query) {
                $this->console->output($query);
                $count++;
                if ($execute) {
                    $GLOBALS['TYPO3_DB']->admin_query($query);
                    if ($GLOBALS['TYPO3_DB']->sql_error()) {
                        $this->console->outdent();
                        throw new \Exception('SQL-Error: ' . $GLOBALS['TYPO3_DB']->sql_error());
                    }
                }
            }
        }
        if (!$count) {
            $this->console->output('> No changes');
        }
        $this->console->outdent();

        return $updateStatements;
    }
}
?>
