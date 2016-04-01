<?php
/**
 * Bootstrap TYPO3
 *
 * PHP Version 5
 *
 * @category Netresearch
 * @package  Kite
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @author   Torsten Fink <torsten.fink@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */

define('TYPO3_MODE', 'BE');
define('TYPO3_cliMode', true);

$GLOBALS['MCONF']['name'] = '_CLI_lowlevel';

if (PHP_SAPI !== 'cli') {
    die('Access denied');
}

define('PATH_site', getcwd() . DIRECTORY_SEPARATOR);

$typo3VersionIsMinimum7 = true;

$cliBootstrapFile = 'typo3/sysext/core/Classes/Core/CliBootstrap.php';
if (file_exists($cliBootstrapFile)) {
    $typo3VersionIsMinimum7 = false;
    include $cliBootstrapFile;
    \TYPO3\CMS\Core\Core\CliBootstrap::checkEnvironmentOrDie();
}

if ($typo3VersionIsMinimum7) {
    $classLoader = include getcwd() . '/typo3_src/vendor/autoload.php';
    \TYPO3\CMS\Core\Core\Bootstrap::getInstance()
        ->initializeClassLoader($classLoader)
        ->baseSetup('')
        ->startOutputBuffering()
        ->loadConfigurationAndInitialize()
        ->loadTypo3LoadedExtAndExtLocalconf(true)
        ->setFinalCachingFrameworkCacheConfiguration()
        ->defineLoggingAndExceptionConstants()
        ->unsetReservedGlobalVariables()
        ->initializeTypo3DbGlobal();
} else {
    include 'typo3/sysext/core/Classes/Core/Bootstrap.php';
    \TYPO3\CMS\Core\Core\Bootstrap::getInstance()
        ->baseSetup()
        ->loadConfigurationAndInitialize()
        ->loadTypo3LoadedExtAndExtLocalconf(true)
        ->applyAdditionalConfigurationSettings()
        ->initializeTypo3DbGlobal()
        ->loadExtensionTables(true)
        ->initializeBackendUser()
        ->initializeBackendAuthentication()
        ->initializeBackendUserMounts()
        ->initializeLanguageObject();
}
// Make sure output is not buffered, so command-line output and interaction can take place
\TYPO3\CMS\Core\Utility\GeneralUtility::flushOutputBuffers();
?>
