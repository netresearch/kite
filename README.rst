.. header::

    .. image:: res/logo/logo.png
       :width: 200 px
       :alt: Kite

**************************************************************
Kite: Yet another build automation tool inspired by TYPO3.Surf
**************************************************************

.. contents::
    :backlinks: top

Kite is build and automation tool, written in PHP and utilizing it for configuration.

- ECMA like variable access:
    - Sub tasks can access variables from parent but can set them on their own as well
    - Advanced logic during execution possible by using expressions (utilizing `Symfony Expression Language <http://symfony.com/doc/current/components/expression_language/index.html>`_)
- Node based:
    - Unlimited number of remote targets possible
    - Nodes can be set globally or only for specific (sub) tasks
    - Remote tasks operate on all current nodes
- Dry-Run available by design (yet the tasks to include need to be configured)
- Originally planned and built as TYPO3 extension but later on ported to generic composer package - installable globally or per project

    
============
Installation
============

You can install kite globally (recommended) or per project

Prerequesites
=============

Locally
-------
- PHP 5.4+ (for the composer installation, the application can have lower versions)
- Linux shell (windows might work but is untested)
- SSH/SCP installed (for working on nodes)
- git installed (if you use git tasks)
- composer installed (if you use composer tasks)

Remote
------
- PHP
- SSH access

Global installation
===================

.. code:: bash

    composer global require "kite=^1.2.0"
    ~/.composer/vendor/bin/kite -V

Per project installation
========================

.. code:: bash

    cd /var/www/project
    composer require "kite=^1.2.0"
    vendor/bin/kite -V

=============
Configuration
=============

Task organization
=================
- Tasks
    - Smallest, predefined steps (currently: answer, break, callback, choose, composer, confirm, evaluate, exit, fs, git, include, iterate, output, phar, remoteShell, sub, tar, tryCatch)
- Workflows
    -  Sets of tasks predefined in classes
    - Top level workflows can expose command line arguments and options
- Jobs
    - Available as commands on command line
    - Set of tasks and/or workflows defined in arrays (in arbitrary depth)
    - Configurable command line arguments and options
- Presets
    - Configuration presets (including f.i. common jobs)
- Configuration file (typo3conf/Kite.php, app/etc/kite.php, kite.php)
    - Defines the jobs; can load and override presets

Kite configuration file
=======================
You need a file called "Kite.php" to set up config (where to deploy).
For TYPO3 projects it should be placed here: `typo3conf/Kite.php`,
for Magento `app/etc/kite.php` and for all other applications just `kite.php`.
A basic example could be

.. code:: php

    <?php
    // Example for a project without a staging environment

    // This loads configuration with common jobs
    $this->loadPreset('common');

    // This configuration is loaded on execution of deploy or rollout job
    $this['stages']['staging']['node'] = array(
        'host' => 'set host here',
        'deployPath' => 'set path on host here',
        'webUrl' => 'set url here',
        'php' => 'php56',
    );

    // no staging is available
    unset($this['stages']['production']);

    ?>

Jobs
====
Jobs are to be configured in the key `jobs` in the configuration. They can contain
a single `task`, an array of `tasks` or a `workflow` (always only one of them).

.. code:: php

    <?php
    // Job, running a single task
    $this['jobs']['echo'] = [
        'description' => 'Output a message',
        'arguments' => [
            'message' => [
                'type' => 'string',
                'required' => true,
                'label' => 'The message to output'
            ]
        ],
        'task' => [
            'type' => 'output',
            'message' => '{message}'
        ]
    ];

    // Job, running a workflow
    $this['jobs']['diagnose'] = [
        'description' => 'Show status of packages',
        'workflow' => 'Netresearch\Kite\Workflow\Composer\Diagnose'
        // can written as follows also:
        // 'workflow' => 'composer-diagnose'
    ];

Nodes
=====
Whenever you set a key named `node` or `nodes` on a job, workflow or task
it's value will be mapped to an aggregate of node models. Those models have the
following default configuration:

.. code:: php

    <?php
    array(
        'user' => '',
        'pass' => '',
        'port' => '',
        'url' => '{(this.user ? this.user ~ "@" : "") ~ this.host}', // SCP/SSH URL
        'sshOptions' => ' -A{this.port ? " -p " ~ this.port : ""}{this.pass ? " -o PubkeyAuthentication=no" : ""}',
        'scpOptions' => '{this.port ? " -P " ~ this.port : ""}{this.pass ? " -o PubkeyAuthentication=no" : ""}',
        'php' => 'php', // PHP executable
        'webRoot' => '{this.deployPath}/current',
        // No default values, required to be set:
        // 'webUrl' => 'http://example.com',
        // 'host' => 'example.com',
        // 'deployPath' => '/var/www'
    );

=====
Usage
=====

As stated above, all jobs are available as kite sub commands (`kite job-name`). You can list the available commands by running

.. code:: bash

    kite [list]

By running

.. code:: bash

    kite help command
    #or
    kite command --help

you can show help for a specific job/command.

Common jobs
===========
- `kite [help command]`
    - Gives a list of all available commands (jobs) or shows help for the given one
- `kite checkout [--merge] branch`
    - Goes through all composer packages and checks out the branch there if it’s available
    - After checking out the branch on a package it goes through all packages requiring it and updates the version constraint to that branch
    - When `--merge` is passed, the currently checked out branch is merged into the branch to checkout
- `kite merge [--squash] [--message=”Message”] branch`
    - Goes through all composer packages and merges the branch into the currently checked out
- `kite package-foreach [--git] command`
    - Runs a command for each composer package (optionally only `--git` packages)
- `kite cc, kite ccr [stage]`
    - Clears caches locally (cc) or on all nodes of a specific stage

Deployment jobs
===============
- `kite deploy [stage]`
    - Runs the deployment for all nodes on the given or selected stage
- `kite rollout [stage]`
    - Runs the deployment for all nodes for each stage until (including) the given stage

.. topic:: Use public key authentication

    To prevent you to have to type your password several times during deployment you should set your public key on your server. Usually this is located here: "~/.ssh/authorized_keys".
