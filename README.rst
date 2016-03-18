.. header::

    .. image:: res/logo/logo.png
       :width: 200 px
       :alt: Kite

**************************************************************
Kite: Yet another build automation tool inspired by TYPO3.Surf
**************************************************************

.. image:: http://img.shields.io/travis/netresearch/kite.svg?style=flat-square
    :target: https://travis-ci.org/netresearch/kite
.. image:: https://img.shields.io/packagist/v/netresearch/kite.svg?style=flat-square
    :target: https://packagist.org/packages/netresearch/kite
.. image:: https://img.shields.io/scrutinizer/g/netresearch/kite.svg?style=flat-square
    :target: https://scrutinizer-ci.com/g/netresearch/kite/?branch=master

.. contents::
    :backlinks: top

.. topic:: Appendix

    - `Task and workflow reference <docs/reference.rst>`_

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
    - Smallest, predefined steps
    - See the `task reference <docs/reference.rst#tasks>`_ for tasks shipped with kite
- Workflows
    - Special kind of task that allows to composer it's subtasks in a class
    - Top level workflows can expose command line arguments and options
    - See the `workflow reference <docs/reference.rst#workflows>`_ for workflows shipped with kite
- Jobs
    - Outermost kind of task
    - Available as commands on command line
    - Set of tasks and/or workflows defined in arrays (in arbitrary depth)
    - Configurable command line arguments and options
- Presets
    - Configuration presets (including f.i. common jobs)
- Configuration file (typo3conf/Kite.php, app/etc/kite.php, kite.php)
    - Defines the jobs; can load and override presets

Variables
=========
The fact that all of the configured tasks are to be ran automated, introduces the
need for a variable system that allows you to read from dynamic configurations or
change it. Kite provides a basic syntax to access those variables from within
strings (all options of tasks, nodes etc.):

Each string *inside curly braces* inside an option string are evaluated as
`Symfony Expression Language <http://symfony.com/doc/current/components/expression_language/index.html>`_
expressions - f.i.

.. code:: php

    <?php
    $this['messages'] = (object) ['default' => 'Hello world'];
    $this['jobs']['echo'] = [
        'description' => 'Output the default message from (\{config["messages"].default\})',
        'task' => [
            'type' => 'output',
            'message' => '{config["messages"].default}'
        ]
    ];

As you saw above, by quoting the braces, you can avoid that the expression is evaluated.
Please see the `Symfony Expression Language Syntax <http://symfony.com/doc/current/components/expression_language/syntax.html>`_
for help on how to use the expressions.

Variable scopes
---------------
The variable scopes are very similar to those in JavaScript. This means that you can
access all variables from the parent scope within the current scope unless you have
a variable in the current scope that's name is the same. To disambiguate you can use
the special variables `this` and `parent`.

.. topic:: `Task or workflow options <docs/reference.rst>`_ are always bound to the scope of the task

    This means, that they have to explicitly be set for the task or workflow and can not be read
    from parent tasks (like jobs or workflows). However sub tasks of those tasks can
    access those options without prefix when they don't have an option with the same
    name or with `parent` prefix otherwise.

Global variables
----------------
Additionally to the options from the current and parent tasks there are some global variables available:

- `job`
    - The current job object (instance of `\Netresearch\Kite\Job`)
- `kite`
    - Object with some information about the kite package (`path` and relative `dir`)
- `config`
    - The config array object (as in configuration file)
- `composer`
    - Composer service object providing keys `packages` and `rootPackage`

Special variables
-----------------
- `this`
    - By using `this` you can force the variable to be not looked up in the parent scopes
    - but only within the current.
- `parent`
    - Points to the parent object

Available functions
-------------------
Kite ships with the following functions:

- `isset(variable)` and `empty(variable)`
    - Behave just like their PHP equivalents. Only available for variable objects, such as
    - `tasks`, `nodes`, `workflows` or `jobs` and their objects (f.i. not for configuration
    - arrays)
- `get(variableName, variableValue)` and `set(variableName, variableValue)`
    - Get or set the variables (f.i. `set('job.owner', node.user)`
- `answer(question)`
    - Let the (command line) user answer a question and return the result
- `answer(question)`
    - Let the (command line) user answer a confirmation question and return the result
- `select(question, options)`
    - Let the (command line) select from an array of options
- `replace(search, replace, subject, regex)`
    - Replace the string `search` with `replace` in `subject`. Behaves like preg_replace
      when `regex` is true - like string_replace otherwise.


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
            'message' => '{job.message}'
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

Deployment configuration
========================

Stages
------
As you saw in the example in `Kite configuration file`_, there is a top level configuration
element named `stages`. They are set by the `common` preset and hold configuration
only used for each of it's keys (such as `staging` and `production` by default). They
are evaluated by workflows based on the `stage-select` workflow, which takes the
stage(s) to use from either command line or a select question. After a stage was
selected ALL of it's values are set to the corresponding task (such as `deploy`).

The stages have no special meaning and are not handled in a special way - they only
play together with the stage based tasks (`deploy` and `rollout` from the `common`
preset and `ccr` from the `typo3` preset) because those are configured so.

Deployment
----------
The `deployment` workflow deploys your application to exactly one stage (whereas the
`rollout` just runs the `deployment` workflow for each until the selected stage).
Thereby it does the following steps:

#. Run `kite composer diagnose` to assert that your application is at a defined state (nothing uncommited, unpushed, unpulled, lock file up to date etc.)
#. Run `composer checkout` with the parameters you provided for the stage:
    #. `branch` - The branch to checkout. In `common` preset they are configured as follows:

        .. code:: php

            <?php
            $this['stages'] = [
                'staging' => [
                    'branch' => '{replace("/", "-", composer.rootPackage.name)}-staging',
                    'merge' => true,
                    'createBranch' => '{config["composer"]["whitelistNames"] || config["composer"]["whitelistRemotes"] || config["composer"]["whitelistPaths"]}'
                    // add nodes or node in your config
                ],
                'production' => [
                    'branch' => 'master',
                    // add nodes or node in your config
                ]
            ];

    #. `merge` - Whether to merge the currently checked out branch into the branch to checkout
    #. `createBranch` - Whether to create the branch if it doesn't exist. This is by
       default set to true for the staging stage, when no whitelists for composer tasks
       are configured. You can configure whitelists for composer like that

        .. code:: php

            <?php
            // The following whitelist types are available (evaluated by OR)
            // ... for the package names
            $this['composer']['whitelistNames'] = 'netresearch/.*';
            // ... for the git remote urls
            $this['composer']['whitelistRemotes'] = 'git@github.com:netresearch/.*';
            // ... for the package paths
            $this['composer']['whitelistPaths'] = 'vendor/netresearch/.*';

    #. `rsync` - configuration for rsync task invoked (f.i. with `excludes` option)
#. Creates a new release from the current release on each `node` `{deployPath}/releases`
#. Rsync the current local state to the new release dir on each `node`
#. Symlink shared directories and files (shared means shared between the releases) -
   the shared directories and files are expected to be at `{deployPath}/shared`. They
   can be configured as seen in the typo3 preset:

    .. code:: php

        <?php
        $this->merge(
            $this['jobs']['deploy']['task'],
            [
                'shared' => [
                    'dirs' => ['fileadmin', 'uploads', 'typo3temp']
                ]
            ]
        );

    To illustrate the behaviour of the stage configuration here's an example setting
    the shared directories differently for each `stage`:

    .. code:: php

        <?php
        $this->merge(
            $this['stages'],
            [
                'staging' => [
                    'shared' => [
                        'dirs' => ['shared_dir_1', 'shared_dir_2'],
                        'files' => ['file1', 'file2']
                    ]
                ],
                'production' => [
                    'shared' => [
                        'dirs' => '{config["stages"]["staging"]["shared"]["dirs"]}',
                        'file' => 'file'
                    ]
                ]
            ]
        );

#. Switch the previous release pointer (`{deploypath}/previous`) to the current release.
#. Switch the current release pointer (`{deploypath}/current`) to the new release.
#. Invoke the `onReady` task if any. F.i.:

    .. code:: php

        <?php
        $this->merge(
            $this['jobs']['deploy']['task'],
            [
                'onReady' => [
                    'type' => 'shell',
                    'command' => 'mail',
                    'optArg' => ['s' => 'Deployed to {stage}', 'user@example.com']
                ],
            ]
        );

    And to once again demonstrate that each of the `stages` can override any option on
    the deployment workflow:

    .. code:: php

        <?php
        $this->merge(
            $this['stages']['production'],
            [
                'onReady' => [
                    'type' => 'shell',
                    'command' => 'mail',
                    'optArg' => ['s' => '[IMPORTANT] Deployed to {stage}', 'user@example.com']
                ],
            ]
        );

When you invoke the `deployment` or `rollout` jobs with the rollback (`--rollback` or `-r`)
option, it

#. switches the next release pointer (`{deploypath}/next`) to the current release
#. switches the current release pointer (`{deploypath}/current`) to the previous release
#. invokes the `onReady` task if any.

When you invoke the `deployment` or `rollout` jobs with the rollback (`--activate` or `-a`)
option, it invokes the last three steps of the deployment (switch symlinks, and invoke `onReady`).

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
