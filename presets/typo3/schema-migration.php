<?php
/**
 * See class comment
 *
 * PHP Version 5
 *
 * @category Netresearch
 * @package  Kite
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */

require_once __DIR__ . '/bootstrap.php';

use TYPO3\CMS\Core\Utility\GeneralUtility;

$bootstrap = GeneralUtility::makeInstance('TYPO3\CMS\Extbase\Core\Bootstrap');
$bootstrap->initialize(array());

$objectManager = GeneralUtility::makeInstance('TYPO3\CMS\Extbase\Object\ObjectManager');
$schemaMigrationService = $objectManager->get('TYPO3\CMS\Install\Service\SqlSchemaMigrationService');
$expectedSchemaService = $objectManager->get('TYPO3\CMS\Install\Service\SqlExpectedSchemaService');

// @codingStandardsIgnoreStart
$updateStatements = $schemaMigrationService->getUpdateSuggestions(
    $schemaMigrationService->getDatabaseExtra(
        $expectedSchemaService->getExpectedDatabaseSchema(),
        $schemaMigrationService->getFieldDefinitions_database()
    )
);
// @codingStandardsIgnoreEnd

$count = 0;
$execute = true;
echo  "Migrating database:\n";
foreach (array('add', 'change', 'create_table') as $type) {
    foreach ((array) $updateStatements[$type] as $query) {
        echo  "  $query\n";
        $count++;
        if ($execute) {
            $GLOBALS['TYPO3_DB']->admin_query($query);
            if ($GLOBALS['TYPO3_DB']->sql_error()) {
                throw new \Exception('SQL-Error: ' . $GLOBALS['TYPO3_DB']->sql_error());
            }
        }
    }
}
if (!$count) {
    echo "  <info>No changes</info>\n";
}
?>
