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

namespace Netresearch\Kite\Service\Event;

/**
 * Class EventDispatcher
 *
 * @category Netresearch
 * @package  Netresearch\Kite\Service
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */
trait Dispatcher
{
    /**
     * @var array
     */
    private $listeners = [];

    /**
     * Dispatch an event
     *
     * @param Event|string $event The event or the name
     *
     * @return $this
     */
    public function trigger($event)
    {
        $args = func_get_args();
        if (!$event instanceof Event) {
            $event = $args[0] = new Event($event);
        }
        if (array_key_exists($event->getName(), $this->listeners)) {
            foreach ($this->listeners[$event->getName()] as $listener) {
                call_user_func_array($listener, $args);
                if ($event->isPropagationStopped()) {
                    break;
                }
            }
        }
        return $this;
    }

    /**
     * Register an event handler
     *
     * @param string   $event    The event name
     * @param callable $callback The callback to call
     *
     * @return $this
     */
    public function on($event, $callback)
    {
        if (!array_key_exists($event, $this->listeners)) {
            $this->listeners[$event] = [];
        }
        $this->listeners[$event][] = $callback;
        return $this;
    }
}

?>
