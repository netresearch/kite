<?php
/**
 * TYPO3 v9 specific configuration
 *
 * @category Netresearch
 * @package  Kite
 * @author   André Lademann <andre.lademann@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */

/* @var $this \Netresearch\Kite\Service\Config */

$this->loadPreset('common');

$this['workspace'] = 'public/typo3temp/Kite';

$this['webUrl'] = null;

// Override this to use another php binary or set env vars
$this['php'] = 'php';

$this['jobs']['cc'] = [
    'description' => 'Clear caches',
    'tasks' => [
        [
            'type' => 'output',
            'message' =>
                '<comment>Set $this[\'webUrl\'] in your kite config to clear code caches</comment>',
            'if' => '!config["webUrl"]',
        ],
        [
            'workflow' => 'clearCodeCaches',
            'webUrl' => '{config["webUrl"]}',
            'if' => 'config["webUrl"]',
        ],
        [
            'type' => 'shell',
            'command' => [
                'rm -rf ./public/typo3temp/Cache/*',
                '{config["php"]} ' . __DIR__ . '/typo3/clear-cache.php',
                '{config["php"]} ' . __DIR__ . '/typo3/schema-migration.php',
            ],
            'processSettings' => ['pt' => true],
        ],
    ],
];
foreach (['update', 'checkout', 'merge'] as $job) {
    $this->merge($this['jobs'][$job], [
        'onAfter' => ['{config["jobs"]["cc"]}'],
    ]);
}

$this['jobs']['ccr'] = [
    'description' => 'Clear remote caches and migrate DB',
    'workflow' => 'stageSelect',
    'stages' => '{config["stages"]}',
    'task' => [
        'type' => 'sub',
        'tasks' => [
            ['workflow' => 'clearCodeCaches'],
            [
                'type' => 'scp',
                'from' => __DIR__ . '/typo3',
                'to' =>
                    '{node}:{node.deployPath}/current/{config["workspace"]}/typo3',
            ],
            [
                'type' => 'remoteShell',
                'command' => [
                    'rm -rf ./public/typo3temp/Cache/*',
                    '{node.php} {config["workspace"]}/typo3/clear-cache.php',
                    '{node.php} {config["workspace"]}/typo3/schema-migration.php',
                    'rm -rf {config["workspace"]}',
                ],
                'cwd' => '{node.webRoot}',
                'processSettings' => ['pt' => true],
            ],
        ],
    ],
];

$this->merge($this['jobs']['deploy']['task'], [
    'onAfter' => ['{config["jobs"]["ccr"]["task"]}'],
    'rsync' => [
        'exclude' => [
            'public/typo3temp',
            'public/fileadmin',
            'public/uploads',
            'build.xml',
            'dynamicReturnTypeMeta.json',
            '.git',
            'node_modules',
            'Gruntfile.js',
            'package.json',
            'composer.lock',
            // TYPO3 needs this file in the packages:
            // 'composer.json'
        ],
    ],
    'shared' => [
        'dirs' => ['public/fileadmin', 'public/uploads', 'public/typo3temp'],
    ],
]);
?>
