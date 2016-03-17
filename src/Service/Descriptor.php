<?php
/**
 * See class comment
 *
 * PHP Version 5
 *
 * @category Netresearch
 * @package  Netresearch\Kite\Service
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */

namespace Netresearch\Kite\Service;
use Netresearch\Kite\Exception;
use Netresearch\Kite\Task;

/**
 * Class TaskDescriptor
 *
 * @category Netresearch
 * @package  Netresearch\Kite\Service
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */
class Descriptor
{
    /**
     * Describe a task
     *
     * @param Task $task The task
     *
     * @return mixed|string
     */
    public function describeTask(Task $task)
    {
        $description = $task->get('description', null);

        if (!$description) {
            $reflection = new \ReflectionClass($task);

            if ($reflection->getNamespaceName() === 'Netresearch\\Kite') {
                $taskProperty = new \ReflectionProperty('Netresearch\\Kite\\Tasks', 'tasks');
                $taskProperty->setAccessible(true);
                foreach ($taskProperty->getValue($task) as $subTask) {
                    $description .= "\n\n" . $this->describeTask($subTask);
                }
                $description = trim($description);
                if (!$description) {
                    $description = 'Generic ' . $reflection->getName();
                }
            } elseif (preg_match_all('/^ \* ([^@ \n].+|)$/mU', $reflection->getDocComment(), $matches, PREG_PATTERN_ORDER)) {
                $description = trim(implode("\n", $matches[1]));
            }
        }

        return $description;
    }
}
?>
