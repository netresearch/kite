.. header::

   .. image:: ../res/logo/logo.png
      :width: 200 px
      :alt: Kite

**************************************************************
Kite: Yet another build automation tool inspired by TYPO3.Surf
**************************************************************

===========================
Task and Workflow reference
===========================

.. sidebar:: Navigation

   `Back to manual <../README.rst>`_

   .. contents::
      :depth: 2

Common options
==============
The following options are available on the most tasks and workflows (unless they deactivated them):

.. list-table::
   :header-rows: 1
   :widths: 5 5 5 5 80

   * - Name
     - Type
     - Default
     - Required
     - Label
   * - 

       .. |common-name| replace:: name

       .. _common-name:

       name

     - string
     - \-
     - \-
     - Name of the task
   * - 

       .. |common-after| replace:: after

       .. _common-after:

       after

     - string
     - \-
     - \-
     - Name of the task to execute this task after
   * - 

       .. |common-before| replace:: before

       .. _common-before:

       before

     - string
     - \-
     - \-
     - Name of the task to execute this task before
   * - 

       .. |common-message| replace:: message

       .. _common-message:

       message

     - string
     - \-
     - \-
     - Message to output when job is run with --dry-run or prior to execution
   * - 

       .. |common-if| replace:: if

       .. _common-if:

       if

     - 

       .. code::php

           Array

           (

               [0] => string

               [1] => callback

               [2] => bool

           )

           


     - \-
     - \-
     - Expression string, callback returning true or false or boolean. Depending of that the task will be executed or not
   * - 

       .. |common-executeInPreview| replace:: executeInPreview

       .. _common-executeInPreview:

       executeInPreview

     - bool
     - `false`
     - \-
     - Whether to execute this task even when job is run with --dry-run
   * - 

       .. |common-force| replace:: force

       .. _common-force:

       force

     - bool
     - `false`
     - \-
     - Whether this task should be run even when prior tasks (inside the current workflow) failed, exited or broke execution.
   * - 

       .. |common-toVar| replace:: toVar

       .. _common-toVar:

       toVar

     - string
     - \-
     - \-
     - The variable to save the return value of the execute method of the task to.


Tasks
=====


answer
------

Ask a question and return the answer

Options
```````

.. list-table::
   :header-rows: 1
   :widths: 5 5 5 5 80

   * - Name
     - Type
     - Default
     - Required
     - Label
   * - 

       .. |task-answer-question| replace:: question

       .. _task-answer-question:

       question

     - string
     - \-
     - X
     - The question to ask
   * - 

       .. |task-answer-default| replace:: default

       .. _task-answer-default:

       default

     - string|numeric
     - \-
     - \-
     - Default value (shown to the user as well)

Common options
``````````````
|common-name|_, |common-after|_, |common-before|_, |common-message|_, |common-if|_, |common-executeInPreview|_, |common-force|_, |common-toVar|_


break
-----

Break the current iteration (of Tasks chain f.i.)

Common options
``````````````
|common-name|_, |common-after|_, |common-before|_, |common-message|_, |common-if|_, |common-executeInPreview|_, |common-force|_, |common-toVar|_


callback
--------

Execute a callback

Options
```````

.. list-table::
   :header-rows: 1
   :widths: 5 5 5 5 80

   * - Name
     - Type
     - Default
     - Required
     - Label
   * - 

       .. |task-callback-callback| replace:: callback

       .. _task-callback-callback:

       callback

     - callable|string
     - \-
     - X
     - The callback or user function to run (@see GeneralUtility::callUserFunction())

Common options
``````````````
|common-name|_, |common-after|_, |common-before|_, |common-message|_, |common-if|_, |common-executeInPreview|_, |common-force|_, |common-toVar|_


choose
------

Ask a selection question and return the answer

Options
```````

.. list-table::
   :header-rows: 1
   :widths: 5 5 5 5 80

   * - Name
     - Type
     - Default
     - Required
     - Label
   * - 

       .. |task-choose-choices| replace:: choices

       .. _task-choose-choices:

       choices

     - array
     - \-
     - X
     - The choices, the user can choose from
   * - 

       .. |task-choose-question| replace:: question

       .. _task-choose-question:

       question

     - string
     - \-
     - X
     - The question to ask
   * - 

       .. |task-choose-default| replace:: default

       .. _task-choose-default:

       default

     - string|numeric
     - \-
     - \-
     - Default value (shown to the user as well)

Common options
``````````````
|common-name|_, |common-after|_, |common-before|_, |common-message|_, |common-if|_, |common-executeInPreview|_, |common-force|_, |common-toVar|_


composer
--------

Run a composer command

Options
```````

.. list-table::
   :header-rows: 1
   :widths: 5 5 5 5 80

   * - Name
     - Type
     - Default
     - Required
     - Label
   * - 

       .. |task-composer-processSettings| replace:: processSettings

       .. _task-composer-processSettings:

       processSettings

     - array
     - 

       .. code::php

           Array

           (

               [pt] => 1

           )

           


     - \-
     - Settings for symfony process class
   * - 

       .. |task-composer-command| replace:: command

       .. _task-composer-command:

       command

     - string|array
     - \-
     - X
     - Command(s) to execute
   * - 

       .. |task-composer-cwd| replace:: cwd

       .. _task-composer-cwd:

       cwd

     - string
     - \-
     - \-
     - The directory to change to before running the command
   * - 

       .. |task-composer-argv| replace:: argv

       .. _task-composer-argv:

       argv

     - array|string
     - \-
     - \-
     - String with all options and arguments for the command or an array in the same format as $argv. Attention: Values won't be escaped!
   * - 

       .. |task-composer-options| replace:: options

       .. _task-composer-options:

       options

     - array
     - 

       .. code::php

           Array

           (

           )

           


     - \-
     - Array with options: Elements with numeric keys or bool true values will be --switches.
   * - 

       .. |task-composer-arguments| replace:: arguments

       .. _task-composer-arguments:

       arguments

     - array
     - 

       .. code::php

           Array

           (

           )

           


     - \-
     - Arguments to pass to the cmd
   * - 

       .. |task-composer-optArg| replace:: optArg

       .. _task-composer-optArg:

       optArg

     - array|string
     - \-
     - \-
     - Arguments and options in one array. When array, elements with numeric keys will be added as |task-composer-arguments|_ and elements with string keys will be added as |task-composer-options|_. When string, |task-composer-argv|_ will be set to this value
   * - 

       .. |task-composer-errorMessage| replace:: errorMessage

       .. _task-composer-errorMessage:

       errorMessage

     - string
     - \-
     - \-
     - Message to display when the command failed

Common options
``````````````
|common-name|_, |common-after|_, |common-before|_, |common-message|_, |common-if|_, |common-executeInPreview|_, |common-force|_, |common-toVar|_


confirm
-------

Ask a confirmation question and return the answer

Options
```````

.. list-table::
   :header-rows: 1
   :widths: 5 5 5 5 80

   * - Name
     - Type
     - Default
     - Required
     - Label
   * - 

       .. |task-confirm-question| replace:: question

       .. _task-confirm-question:

       question

     - string
     - \-
     - X
     - The question to ask
   * - 

       .. |task-confirm-default| replace:: default

       .. _task-confirm-default:

       default

     - string|numeric
     - \-
     - \-
     - Default value (shown to the user as well)

Common options
``````````````
|common-name|_, |common-after|_, |common-before|_, |common-message|_, |common-if|_, |common-executeInPreview|_, |common-force|_, |common-toVar|_


evaluate
--------

Evaluate an expression and return the result

Options
```````

.. list-table::
   :header-rows: 1
   :widths: 5 5 5 5 80

   * - Name
     - Type
     - Default
     - Required
     - Label
   * - 

       .. |task-evaluate-expression| replace:: expression

       .. _task-evaluate-expression:

       expression

     - string
     - \-
     - X
     - The question to ask

Common options
``````````````
|common-name|_, |common-after|_, |common-before|_, |common-message|_, |common-if|_, |common-executeInPreview|_, |common-force|_, |common-toVar|_


exit
----

Exit

Options
```````

.. list-table::
   :header-rows: 1
   :widths: 5 5 5 5 80

   * - Name
     - Type
     - Default
     - Required
     - Label
   * - 

       .. |task-exit-code| replace:: code

       .. _task-exit-code:

       code

     - int
     - 0
     - \-
     - Code to exit with

Common options
``````````````
|common-name|_, |common-after|_, |common-before|_, |common-message|_, |common-if|_, |common-executeInPreview|_, |common-force|_, |common-toVar|_


fs
--

Filesystem task - calls methods on {@see \Netresearch\Kite\Service\Filesystem}

Options
```````

.. list-table::
   :header-rows: 1
   :widths: 5 5 5 5 80

   * - Name
     - Type
     - Default
     - Required
     - Label
   * - 

       .. |task-fs-action| replace:: action

       .. _task-fs-action:

       action

     - string
     - \-
     - X
     - Method of \Netresearch\Kite\Service\Filesystem to execute
   * - 

       .. |task-fs-arguments| replace:: arguments

       .. _task-fs-arguments:

       arguments

     - array
     - 

       .. code::php

           Array

           (

           )

           


     - \-
     - Arguments for action method

Common options
``````````````
|common-name|_, |common-after|_, |common-before|_, |common-message|_, |common-if|_, |common-executeInPreview|_, |common-force|_, |common-toVar|_


git
---

Execute a git command and return the result

Options
```````

.. list-table::
   :header-rows: 1
   :widths: 5 5 5 5 80

   * - Name
     - Type
     - Default
     - Required
     - Label
   * - 

       .. |task-git-command| replace:: command

       .. _task-git-command:

       command

     - string|array
     - \-
     - X
     - Command(s) to execute
   * - 

       .. |task-git-cwd| replace:: cwd

       .. _task-git-cwd:

       cwd

     - string
     - \-
     - \-
     - The directory to change to before running the command
   * - 

       .. |task-git-argv| replace:: argv

       .. _task-git-argv:

       argv

     - array|string
     - \-
     - \-
     - String with all options and arguments for the command or an array in the same format as $argv. Attention: Values won't be escaped!
   * - 

       .. |task-git-options| replace:: options

       .. _task-git-options:

       options

     - array
     - 

       .. code::php

           Array

           (

           )

           


     - \-
     - Array with options: Elements with numeric keys or bool true values will be --switches.
   * - 

       .. |task-git-arguments| replace:: arguments

       .. _task-git-arguments:

       arguments

     - array
     - 

       .. code::php

           Array

           (

           )

           


     - \-
     - Arguments to pass to the cmd
   * - 

       .. |task-git-optArg| replace:: optArg

       .. _task-git-optArg:

       optArg

     - array|string
     - \-
     - \-
     - Arguments and options in one array. When array, elements with numeric keys will be added as |task-git-arguments|_ and elements with string keys will be added as |task-git-options|_. When string, |task-git-argv|_ will be set to this value
   * - 

       .. |task-git-errorMessage| replace:: errorMessage

       .. _task-git-errorMessage:

       errorMessage

     - string
     - \-
     - \-
     - Message to display when the command failed
   * - 

       .. |task-git-processSettings| replace:: processSettings

       .. _task-git-processSettings:

       processSettings

     - array
     - 

       .. code::php

           Array

           (

           )

           


     - \-
     - Settings for symfony process class

Common options
``````````````
|common-name|_, |common-after|_, |common-before|_, |common-message|_, |common-if|_, |common-executeInPreview|_, |common-force|_, |common-toVar|_


include
-------

Include a file

Options
```````

.. list-table::
   :header-rows: 1
   :widths: 5 5 5 5 80

   * - Name
     - Type
     - Default
     - Required
     - Label
   * - 

       .. |task-include-file| replace:: file

       .. _task-include-file:

       file

     - string
     - \-
     - true
     - The file to include

Common options
``````````````
|common-name|_, |common-after|_, |common-before|_, |common-message|_, |common-if|_, |common-executeInPreview|_, |common-force|_, |common-toVar|_


iterate
-------

Run each task for each of an arrays element

Options
```````

.. list-table::
   :header-rows: 1
   :widths: 5 5 5 5 80

   * - Name
     - Type
     - Default
     - Required
     - Label
   * - 

       .. |task-iterate-array| replace:: array

       .. _task-iterate-array:

       array

     - array
     - \-
     - X
     - The array to iterate over
   * - 

       .. |task-iterate-as| replace:: as

       .. _task-iterate-as:

       as

     - string|array
     - `null`
     - \-
     - String with variable name to set the VALUEs to or array which's key to set the KEYs  and which's value to set the VALUEs to
   * - 

       .. |task-iterate-key| replace:: key

       .. _task-iterate-key:

       key

     - string
     - `null`
     - \-
     - Variable name to set the KEYs to (ignored when "as" doesn't provide both
   * - 

       .. |task-iterate-tasks| replace:: tasks

       .. _task-iterate-tasks:

       tasks

     - array
     - \-
     - \-
     - Array of tasks to add to the subTask
   * - 

       .. |task-iterate-task| replace:: task

       .. _task-iterate-task:

       task

     - mixed
     - \-
     - \-
     - Task to run as a sub task
   * - 

       .. |task-iterate-workflow| replace:: workflow

       .. _task-iterate-workflow:

       workflow

     - array
     - \-
     - \-
     - Workflow to run as a subtask
   * - 

       .. |task-iterate-script| replace:: script

       .. _task-iterate-script:

       script

     - string
     - \-
     - \-
     - Script to include which configures the tasks

Common options
``````````````
|common-name|_, |common-after|_, |common-before|_, |common-message|_, |common-if|_, |common-executeInPreview|_, |common-force|_, |common-toVar|_


output
------

Output the message

Options
```````

.. list-table::
   :header-rows: 1
   :widths: 5 5 5 5 80

   * - Name
     - Type
     - Default
     - Required
     - Label
   * - 

       .. |task-output-severity| replace:: severity

       .. _task-output-severity:

       severity

     - int
     - 32
     - \-
     - Severity of message (use OutputInterface::VERBOSITY_* constants)
   * - 

       .. |task-output-newLine| replace:: newLine

       .. _task-output-newLine:

       newLine

     - bool
     - `true`
     - \-
     - Whether to print a new line after message


phar
----

Class PharTask

Options
```````

.. list-table::
   :header-rows: 1
   :widths: 5 5 5 5 80

   * - Name
     - Type
     - Default
     - Required
     - Label
   * - 

       .. |task-phar-from| replace:: from

       .. _task-phar-from:

       from

     - string
     - \-
     - X
     - The path to the directory to create the phar from
   * - 

       .. |task-phar-to| replace:: to

       .. _task-phar-to:

       to

     - string
     - \-
     - X
     - Path and filename of the resulting phar file
   * - 

       .. |task-phar-filter| replace:: filter

       .. _task-phar-filter:

       filter

     - string
     - \-
     - \-
     - Only file paths matching this pcre regular expression will be included in the archive
   * - 

       .. |task-phar-cliStub| replace:: cliStub

       .. _task-phar-cliStub:

       cliStub

     - string
     - \-
     - \-
     - Path to cli index file, relative to <info>comment</info>
   * - 

       .. |task-phar-webStub| replace:: webStub

       .. _task-phar-webStub:

       webStub

     - string
     - \-
     - \-
     - Path to web index file, relative to <info>comment</info>
   * - 

       .. |task-phar-alias| replace:: alias

       .. _task-phar-alias:

       alias

     - string
     - \-
     - \-
     - Alias with which this Phar archive should be referred to in calls to stream functionality
   * - 

       .. |task-phar-metadata| replace:: metadata

       .. _task-phar-metadata:

       metadata

     - mixed
     - \-
     - \-
     - Anything containing information to store that describes the phar archive

Common options
``````````````
|common-name|_, |common-after|_, |common-before|_, |common-message|_, |common-if|_, |common-executeInPreview|_, |common-force|_, |common-toVar|_


remoteShell
-----------

Execute a shell command on either the current node or all nodes

Options
```````

.. list-table::
   :header-rows: 1
   :widths: 5 5 5 5 80

   * - Name
     - Type
     - Default
     - Required
     - Label
   * - 

       .. |task-remoteShell-command| replace:: command

       .. _task-remoteShell-command:

       command

     - string|array
     - \-
     - X
     - Command(s) to execute
   * - 

       .. |task-remoteShell-cwd| replace:: cwd

       .. _task-remoteShell-cwd:

       cwd

     - string
     - \-
     - \-
     - The directory to change to before running the command
   * - 

       .. |task-remoteShell-argv| replace:: argv

       .. _task-remoteShell-argv:

       argv

     - array|string
     - \-
     - \-
     - String with all options and arguments for the command or an array in the same format as $argv. Attention: Values won't be escaped!
   * - 

       .. |task-remoteShell-options| replace:: options

       .. _task-remoteShell-options:

       options

     - array
     - 

       .. code::php

           Array

           (

           )

           


     - \-
     - Array with options: Elements with numeric keys or bool true values will be --switches.
   * - 

       .. |task-remoteShell-arguments| replace:: arguments

       .. _task-remoteShell-arguments:

       arguments

     - array
     - 

       .. code::php

           Array

           (

           )

           


     - \-
     - Arguments to pass to the cmd
   * - 

       .. |task-remoteShell-optArg| replace:: optArg

       .. _task-remoteShell-optArg:

       optArg

     - array|string
     - \-
     - \-
     - Arguments and options in one array. When array, elements with numeric keys will be added as |task-remoteShell-arguments|_ and elements with string keys will be added as |task-remoteShell-options|_. When string, |task-remoteShell-argv|_ will be set to this value
   * - 

       .. |task-remoteShell-errorMessage| replace:: errorMessage

       .. _task-remoteShell-errorMessage:

       errorMessage

     - string
     - \-
     - \-
     - Message to display when the command failed
   * - 

       .. |task-remoteShell-processSettings| replace:: processSettings

       .. _task-remoteShell-processSettings:

       processSettings

     - array
     - 

       .. code::php

           Array

           (

           )

           


     - \-
     - Settings for symfony process class

Common options
``````````````
|common-name|_, |common-after|_, |common-before|_, |common-message|_, |common-if|_, |common-executeInPreview|_, |common-force|_, |common-toVar|_


rsync
-----

RSync from/to the current node or all nodes if no current

Options
```````

.. list-table::
   :header-rows: 1
   :widths: 5 5 5 5 80

   * - Name
     - Type
     - Default
     - Required
     - Label
   * - 

       .. |task-rsync-exclude| replace:: exclude

       .. _task-rsync-exclude:

       exclude

     - array
     - 

       .. code::php

           Array

           (

           )

           


     - \-
     - Array with files/dirs to explicitely exclude
   * - 

       .. |task-rsync-include| replace:: include

       .. _task-rsync-include:

       include

     - array
     - 

       .. code::php

           Array

           (

           )

           


     - \-
     - Array with files/dirs to explicitely include
   * - 

       .. |task-rsync-options| replace:: options

       .. _task-rsync-options:

       options

     - array
     - 

       .. code::php

           Array

           (

           )

           


     - \-
     - Array with options for rsync: Elements with numeric keys or bool true values will be --switches.
   * - 

       .. |task-rsync-from| replace:: from

       .. _task-rsync-from:

       from

     - string
     - \-
     - X
     - Path to the source (prefix with {node}: to download from a node)
   * - 

       .. |task-rsync-to| replace:: to

       .. _task-rsync-to:

       to

     - string
     - \-
     - X
     - Path to the target (prefix with {node}: to upload to a node)
   * - 

       .. |task-rsync-command| replace:: command

       .. _task-rsync-command:

       command

     - string|array
     - \-
     - X
     - Command(s) to execute
   * - 

       .. |task-rsync-cwd| replace:: cwd

       .. _task-rsync-cwd:

       cwd

     - string
     - \-
     - \-
     - The directory to change to before running the command
   * - 

       .. |task-rsync-argv| replace:: argv

       .. _task-rsync-argv:

       argv

     - array|string
     - \-
     - \-
     - String with all options and arguments for the command or an array in the same format as $argv. Attention: Values won't be escaped!
   * - 

       .. |task-rsync-errorMessage| replace:: errorMessage

       .. _task-rsync-errorMessage:

       errorMessage

     - string
     - \-
     - \-
     - Message to display when the command failed
   * - 

       .. |task-rsync-processSettings| replace:: processSettings

       .. _task-rsync-processSettings:

       processSettings

     - array
     - 

       .. code::php

           Array

           (

           )

           


     - \-
     - Settings for symfony process class

Common options
``````````````
|common-name|_, |common-after|_, |common-before|_, |common-message|_, |common-if|_, |common-executeInPreview|_, |common-force|_, |common-toVar|_


scp
---

Up/download via SCP

Options
```````

.. list-table::
   :header-rows: 1
   :widths: 5 5 5 5 80

   * - Name
     - Type
     - Default
     - Required
     - Label
   * - 

       .. |task-scp-from| replace:: from

       .. _task-scp-from:

       from

     - string
     - \-
     - X
     - Path to the source (prefix with {node}: to download from a node)
   * - 

       .. |task-scp-to| replace:: to

       .. _task-scp-to:

       to

     - string
     - \-
     - X
     - Path to the target (prefix with {node}: to upload to a node)
   * - 

       .. |task-scp-command| replace:: command

       .. _task-scp-command:

       command

     - string|array
     - \-
     - X
     - Command(s) to execute
   * - 

       .. |task-scp-cwd| replace:: cwd

       .. _task-scp-cwd:

       cwd

     - string
     - \-
     - \-
     - The directory to change to before running the command
   * - 

       .. |task-scp-argv| replace:: argv

       .. _task-scp-argv:

       argv

     - array|string
     - \-
     - \-
     - String with all options and arguments for the command or an array in the same format as $argv. Attention: Values won't be escaped!
   * - 

       .. |task-scp-errorMessage| replace:: errorMessage

       .. _task-scp-errorMessage:

       errorMessage

     - string
     - \-
     - \-
     - Message to display when the command failed
   * - 

       .. |task-scp-processSettings| replace:: processSettings

       .. _task-scp-processSettings:

       processSettings

     - array
     - 

       .. code::php

           Array

           (

           )

           


     - \-
     - Settings for symfony process class

Common options
``````````````
|common-name|_, |common-after|_, |common-before|_, |common-message|_, |common-if|_, |common-executeInPreview|_, |common-force|_, |common-toVar|_


shell
-----

Executes a command locally and returns the output

Options
```````

.. list-table::
   :header-rows: 1
   :widths: 5 5 5 5 80

   * - Name
     - Type
     - Default
     - Required
     - Label
   * - 

       .. |task-shell-command| replace:: command

       .. _task-shell-command:

       command

     - string|array
     - \-
     - X
     - Command(s) to execute
   * - 

       .. |task-shell-cwd| replace:: cwd

       .. _task-shell-cwd:

       cwd

     - string
     - \-
     - \-
     - The directory to change to before running the command
   * - 

       .. |task-shell-argv| replace:: argv

       .. _task-shell-argv:

       argv

     - array|string
     - \-
     - \-
     - String with all options and arguments for the command or an array in the same format as $argv. Attention: Values won't be escaped!
   * - 

       .. |task-shell-options| replace:: options

       .. _task-shell-options:

       options

     - array
     - 

       .. code::php

           Array

           (

           )

           


     - \-
     - Array with options: Elements with numeric keys or bool true values will be --switches.
   * - 

       .. |task-shell-arguments| replace:: arguments

       .. _task-shell-arguments:

       arguments

     - array
     - 

       .. code::php

           Array

           (

           )

           


     - \-
     - Arguments to pass to the cmd
   * - 

       .. |task-shell-optArg| replace:: optArg

       .. _task-shell-optArg:

       optArg

     - array|string
     - \-
     - \-
     - Arguments and options in one array. When array, elements with numeric keys will be added as |task-shell-arguments|_ and elements with string keys will be added as |task-shell-options|_. When string, |task-shell-argv|_ will be set to this value
   * - 

       .. |task-shell-errorMessage| replace:: errorMessage

       .. _task-shell-errorMessage:

       errorMessage

     - string
     - \-
     - \-
     - Message to display when the command failed
   * - 

       .. |task-shell-processSettings| replace:: processSettings

       .. _task-shell-processSettings:

       processSettings

     - array
     - 

       .. code::php

           Array

           (

           )

           


     - \-
     - Settings for symfony process class

Common options
``````````````
|common-name|_, |common-after|_, |common-before|_, |common-message|_, |common-if|_, |common-executeInPreview|_, |common-force|_, |common-toVar|_


sub
---

Run tasks or a workflow within a task

Options
```````

.. list-table::
   :header-rows: 1
   :widths: 5 5 5 5 80

   * - Name
     - Type
     - Default
     - Required
     - Label
   * - 

       .. |task-sub-tasks| replace:: tasks

       .. _task-sub-tasks:

       tasks

     - array
     - \-
     - \-
     - Array of tasks to add to the subTask
   * - 

       .. |task-sub-task| replace:: task

       .. _task-sub-task:

       task

     - mixed
     - \-
     - \-
     - Task to run as a sub task
   * - 

       .. |task-sub-workflow| replace:: workflow

       .. _task-sub-workflow:

       workflow

     - array
     - \-
     - \-
     - Workflow to run as a subtask
   * - 

       .. |task-sub-script| replace:: script

       .. _task-sub-script:

       script

     - string
     - \-
     - \-
     - Script to include which configures the tasks

Common options
``````````````
|common-name|_, |common-after|_, |common-before|_, |common-message|_, |common-if|_, |common-executeInPreview|_, |common-force|_, |common-toVar|_


tar
---

Create a tar archive a set of files

Options
```````

.. list-table::
   :header-rows: 1
   :widths: 5 5 5 5 80

   * - Name
     - Type
     - Default
     - Required
     - Label
   * - 

       .. |task-tar-command| replace:: command

       .. _task-tar-command:

       command

     - string
     - \-
     - X
     - Name of the task
   * - 

       .. |task-tar-cwd| replace:: cwd

       .. _task-tar-cwd:

       cwd

     - string
     - \-
     - \-
     - The directory to change to before running the command
   * - 

       .. |task-tar-options| replace:: options

       .. _task-tar-options:

       options

     - array
     - 

       .. code::php

           Array

           (

           )

           


     - \-
     - Array with options: Elements with numeric keys or bool true values will be --switches.
   * - 

       .. |task-tar-arguments| replace:: arguments

       .. _task-tar-arguments:

       arguments

     - array
     - 

       .. code::php

           Array

           (

           )

           


     - \-
     - Arguments to pass to the cmd
   * - 

       .. |task-tar-optArg| replace:: optArg

       .. _task-tar-optArg:

       optArg

     - array
     - \-
     - \-
     - Arguments and options in one array. Elements with numeric keys will be arguments, elems. with bool true values will be --switches, all other options

Common options
``````````````
|common-name|_, |common-after|_, |common-before|_, |common-message|_, |common-if|_, |common-executeInPreview|_, |common-force|_, |common-toVar|_


tryCatch
--------

Catch exceptions while executing tasks

Options
```````

.. list-table::
   :header-rows: 1
   :widths: 5 5 5 5 80

   * - Name
     - Type
     - Default
     - Required
     - Label
   * - 

       .. |task-tryCatch-onCatch| replace:: onCatch

       .. _task-tryCatch-onCatch:

       onCatch

     - array
     - \-
     - \-
     - Task to execute when an exception was catched
   * - 

       .. |task-tryCatch-errorMessage| replace:: errorMessage

       .. _task-tryCatch-errorMessage:

       errorMessage

     - string
     - \-
     - \-
     - Message to display on error
   * - 

       .. |task-tryCatch-tasks| replace:: tasks

       .. _task-tryCatch-tasks:

       tasks

     - array
     - \-
     - \-
     - Array of tasks to add to the subTask
   * - 

       .. |task-tryCatch-task| replace:: task

       .. _task-tryCatch-task:

       task

     - mixed
     - \-
     - \-
     - Task to run as a sub task
   * - 

       .. |task-tryCatch-workflow| replace:: workflow

       .. _task-tryCatch-workflow:

       workflow

     - array
     - \-
     - \-
     - Workflow to run as a subtask
   * - 

       .. |task-tryCatch-script| replace:: script

       .. _task-tryCatch-script:

       script

     - string
     - \-
     - \-
     - Script to include which configures the tasks

Common options
``````````````
|common-name|_, |common-after|_, |common-before|_, |common-message|_, |common-if|_, |common-executeInPreview|_, |common-force|_, |common-toVar|_


Workflows
=========


clearCodeCaches
---------------

Clears code caches not available from shell and calls (statcache, opcache and apc).

Creates a PHP script on the nodes or locally and calls it via the webUrl or node.webUrl

Options
```````

.. list-table::
   :header-rows: 1
   :widths: 5 5 5 5 80

   * - Name
     - Type
     - Default
     - Required
     - Label
   * - 

       .. |workflow-clearCodeCaches-webUrl| replace:: webUrl

       .. _workflow-clearCodeCaches-webUrl:

       webUrl

     - string
     - \-
     - \-
     - URL to the current web root. Set this if you want to clear caches locally - otherwise this WF will clear the node(s) caches
   * - 

       .. |workflow-clearCodeCaches-baseDir| replace:: baseDir

       .. _workflow-clearCodeCaches-baseDir:

       baseDir

     - string
     - `{config["workspace"]}`
     - \-
     - Path relative to current application root and webUrl, where the temp script will be stored

Common options
``````````````
|common-name|_, |common-after|_, |common-before|_, |common-message|_, |common-if|_, |common-executeInPreview|_, |common-force|_, |common-toVar|_


composer-checkout
-----------------

Checkout a branch and eventually merge it with the previously checked out branch

Options
```````

.. list-table::
   :header-rows: 1
   :widths: 5 5 5 5 80

   * - Name
     - Type
     - Default
     - Required
     - Label
   * - 

       .. |workflow-composer-checkout-branch| replace:: branch

       .. _workflow-composer-checkout-branch:

       branch

     - string|array
     - \-
     - X
     - The branch
   * - 

       .. |workflow-composer-checkout-merge| replace:: merge

       .. _workflow-composer-checkout-merge:

       merge

     - bool
     - \-
     - \-
     - Whether to merge the checked out branch with the previously checked out branch
   * - 

       .. |workflow-composer-checkout-create| replace:: create

       .. _workflow-composer-checkout-create:

       create

     - bool
     - \-
     - \-
     - Create branch if not exists
   * - 

       .. |workflow-composer-checkout-whitelistNames| replace:: whitelistNames

       .. _workflow-composer-checkout-whitelistNames:

       whitelistNames

     - string
     - `{config["composer"]["whitelistNames"]}`
     - \-
     - Regular expression for package names, to limit this operation to
   * - 

       .. |workflow-composer-checkout-whitelistPaths| replace:: whitelistPaths

       .. _workflow-composer-checkout-whitelistPaths:

       whitelistPaths

     - string
     - `{config["composer"]["whitelistPaths"]}`
     - \-
     - Regular expression for package paths, to limit this operation to
   * - 

       .. |workflow-composer-checkout-whitelistRemotes| replace:: whitelistRemotes

       .. _workflow-composer-checkout-whitelistRemotes:

       whitelistRemotes

     - string
     - `{config["composer"]["whitelistRemotes"]}`
     - \-
     - Regular expression for package remote urls, to limit this operation to

Common options
``````````````
|common-name|_, |common-after|_, |common-before|_, |common-message|_, |common-if|_, |common-executeInPreview|_, |common-force|_, |common-toVar|_


composer-diagnose
-----------------

Workflow to diagnose packages and fix found problems

Options
```````

.. list-table::
   :header-rows: 1
   :widths: 5 5 5 5 80

   * - Name
     - Type
     - Default
     - Required
     - Label
   * - 

       .. |workflow-composer-diagnose-check| replace:: check

       .. _workflow-composer-diagnose-check:

       check

     - array
     - \-
     - \-
     - Only execute these checks - available checks are UnstagedChanges, RemoteSynchronicity, RequirementsMatch, DivergeFromLock, ComposerLockActuality
   * - 

       .. |workflow-composer-diagnose-fix| replace:: fix

       .. _workflow-composer-diagnose-fix:

       fix

     - boolean|array
     - \-
     - \-
     - Enable fixes and optionally reduce to certain fixes - available fixes are UnstagedChanges, RemoteSynchronicity, RequirementsMatch, DivergeFromLock, ComposerLockActuality
   * - 

       .. |workflow-composer-diagnose-whitelistNames| replace:: whitelistNames

       .. _workflow-composer-diagnose-whitelistNames:

       whitelistNames

     - string
     - `{config["composer"]["whitelistNames"]}`
     - \-
     - Regular expression for package names, to limit this operation to
   * - 

       .. |workflow-composer-diagnose-whitelistPaths| replace:: whitelistPaths

       .. _workflow-composer-diagnose-whitelistPaths:

       whitelistPaths

     - string
     - `{config["composer"]["whitelistPaths"]}`
     - \-
     - Regular expression for package paths, to limit this operation to
   * - 

       .. |workflow-composer-diagnose-whitelistRemotes| replace:: whitelistRemotes

       .. _workflow-composer-diagnose-whitelistRemotes:

       whitelistRemotes

     - string
     - `{config["composer"]["whitelistRemotes"]}`
     - \-
     - Regular expression for package remote urls, to limit this operation to

Common options
``````````````
|common-name|_, |common-after|_, |common-before|_, |common-message|_, |common-if|_, |common-executeInPreview|_, |common-force|_, |common-toVar|_


composer-merge
--------------

Go through all packages and merge the given branch into the current, when it exists

Options
```````

.. list-table::
   :header-rows: 1
   :widths: 5 5 5 5 80

   * - Name
     - Type
     - Default
     - Required
     - Label
   * - 

       .. |workflow-composer-merge-branch| replace:: branch

       .. _workflow-composer-merge-branch:

       branch

     - string
     - \-
     - X
     - The branch to merge in
   * - 

       .. |workflow-composer-merge-squash| replace:: squash

       .. _workflow-composer-merge-squash:

       squash

     - bool
     - \-
     - \-
     - Whether to merge with --squash
   * - 

       .. |workflow-composer-merge-delete| replace:: delete

       .. _workflow-composer-merge-delete:

       delete

     - bool
     - \-
     - \-
     - Whether to delete the branch after merge
   * - 

       .. |workflow-composer-merge-message| replace:: message

       .. _workflow-composer-merge-message:

       message

     - bool
     - \-
     - \-
     - Message for commits (if any)
   * - 

       .. |workflow-composer-merge-no-diagnose| replace:: no-diagnose

       .. _workflow-composer-merge-no-diagnose:

       no-diagnose

     - bool
     - \-
     - \-
     - Don't do a diagnose upfront
   * - 

       .. |workflow-composer-merge-whitelistNames| replace:: whitelistNames

       .. _workflow-composer-merge-whitelistNames:

       whitelistNames

     - string
     - `{config["composer"]["whitelistNames"]}`
     - \-
     - Regular expression for package names, to limit this operation to
   * - 

       .. |workflow-composer-merge-whitelistPaths| replace:: whitelistPaths

       .. _workflow-composer-merge-whitelistPaths:

       whitelistPaths

     - string
     - `{config["composer"]["whitelistPaths"]}`
     - \-
     - Regular expression for package paths, to limit this operation to
   * - 

       .. |workflow-composer-merge-whitelistRemotes| replace:: whitelistRemotes

       .. _workflow-composer-merge-whitelistRemotes:

       whitelistRemotes

     - string
     - `{config["composer"]["whitelistRemotes"]}`
     - \-
     - Regular expression for package remote urls, to limit this operation to

Common options
``````````````
|common-name|_, |common-after|_, |common-before|_, |common-if|_, |common-executeInPreview|_, |common-force|_, |common-toVar|_


deployment
----------

Deploy the current application to a certain stage

Options
```````

.. list-table::
   :header-rows: 1
   :widths: 5 5 5 5 80

   * - Name
     - Type
     - Default
     - Required
     - Label
   * - 

       .. |workflow-deployment-rollback| replace:: rollback

       .. _workflow-deployment-rollback:

       rollback

     - bool
     - \-
     - \-
     - Makes previous release current and current release next
   * - 

       .. |workflow-deployment-activate| replace:: activate

       .. _workflow-deployment-activate:

       activate

     - bool
     - \-
     - \-
     - Makes next release current and current release previous
   * - 

       .. |workflow-deployment-rsync| replace:: rsync

       .. _workflow-deployment-rsync:

       rsync

     - array
     - \-
     - \-
     - Options for the rsync task (can contain keys options, exclude, and include - see rsync task for their descriptions)
   * - 

       .. |workflow-deployment-shared| replace:: shared

       .. _workflow-deployment-shared:

       shared

     - array
     - 

       .. code::php

           Array

           (

           )

           


     - \-
     - Array of files (in key "files") and directories (in key "dirs") to share between releases - share directory is in node.deployDir/shared
   * - 

       .. |workflow-deployment-onReady| replace:: onReady

       .. _workflow-deployment-onReady:

       onReady

     - array
     - \-
     - \-
     - \-

Common options
``````````````
|common-name|_, |common-after|_, |common-before|_, |common-message|_, |common-if|_, |common-executeInPreview|_, |common-force|_, |common-toVar|_


git-assertUnchanged
-------------------

Workflow to assert a git repo has no uncommited and unpushed changes

Options
```````

.. list-table::
   :header-rows: 1
   :widths: 5 5 5 5 80

   * - Name
     - Type
     - Default
     - Required
     - Label
   * - 

       .. |workflow-git-assertUnchanged-cwd| replace:: cwd

       .. _workflow-git-assertUnchanged-cwd:

       cwd

     - string
     - \-
     - \-
     - The directory to change into

Common options
``````````````
|common-name|_, |common-after|_, |common-before|_, |common-message|_, |common-if|_, |common-executeInPreview|_, |common-force|_, |common-toVar|_


stageSelect
-----------

Run a task for each stage until the selected stage

Options
```````

.. list-table::
   :header-rows: 1
   :widths: 5 5 5 5 80

   * - Name
     - Type
     - Default
     - Required
     - Label
   * - 

       .. |workflow-stageSelect-stage| replace:: stage

       .. _workflow-stageSelect-stage:

       stage

     - string
     - \-
     - \-
     - Preselect a stage - otherwise you'll be asked
   * - 

       .. |workflow-stageSelect-stages| replace:: stages

       .. _workflow-stageSelect-stages:

       stages

     - array
     - \-
     - X
     - Array of stages - keys are the stages names and the values are arrays which's contain variables that will be set when the according stage was selected
   * - 

       .. |workflow-stageSelect-sliding| replace:: sliding

       .. _workflow-stageSelect-sliding:

       sliding

     - bool
     - \-
     - \-
     - Whether all stages until the selected should be used
   * - 

       .. |workflow-stageSelect-task| replace:: task

       .. _workflow-stageSelect-task:

       task

     - array
     - \-
     - X
     - The task to invoke for each selected stage
   * - 

       .. |workflow-stageSelect-message| replace:: message

       .. _workflow-stageSelect-message:

       message

     - string
     - \-
     - \-
     - Message to output before each executed stage - %s will be replaced with stage name
   * - 

       .. |workflow-stageSelect-question| replace:: question

       .. _workflow-stageSelect-question:

       question

     - string
     - `Select stage`
     - \-
     - Question to ask before stage select

Common options
``````````````
|common-name|_, |common-after|_, |common-before|_, |common-if|_, |common-executeInPreview|_, |common-force|_, |common-toVar|_
