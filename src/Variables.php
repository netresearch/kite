<?php
/**
 * See class comment
 *
 * PHP Version 5
 *
 * @category Netresearch
 * @package  Netresearch\Kite
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */

namespace Netresearch\Kite;

use Netresearch\Kite\Exception;
use Netresearch\Kite\ExpressionLanguage\ExpressionLanguage;

/**
 * Variable container class
 *
 * @category Netresearch
 * @package  Netresearch\Kite
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch Copyright
 * @link     http://www.netresearch.de
 */
class Variables implements \ArrayAccess
{
    /**
     * @var array
     */
    private $variables = array();

    /**
     * @var Variables
     */
    private $parent;

    /**
     * @var Variables[]
     */
    private $children = array();

    /**
     * Variables constructor.
     *
     * @param Variables|null $parent   Parent variables container
     * @param array          $defaults Array with default values
     */
    public function __construct(Variables $parent = null, array $defaults = array())
    {
        $this->parent = $parent === $this ? null : $parent;
        if ($this->parent) {
            $this->parent->children[] = $this;
        }
        $variableConfiguration = $this->configureVariables();
        foreach ($variableConfiguration as $variable => $config) {
            if (is_array($config)) {
                if (!array_key_exists($variable, $defaults) && (!array_key_exists('required', $config) || !$config['required'])) {
                    $defaults[$variable] = array_key_exists('default', $config) ? $config['default'] : null;
                }
            } elseif ($config === null) {
                $defaults[$variable] = null;
            } elseif (!is_numeric($variable) && $config !== false) {
                throw new Exception('Invalid variable configuration');
            }
        }
        $this->variables += $defaults;
        $this->variables['_variableConfiguration'] = $variableConfiguration;
    }

    /**
     * Clone the children with this step as well, cause otherwise they'll lose
     * connection to parent
     *
     * @return void
     */
    function __clone()
    {
        $children = array();
        foreach ($this->children as $child) {
            $children[] = $clone = clone $child;
            $clone->parent = $this;
        }
        $this->children = $children;
    }

    /**
     * Bind to another parent
     *
     * @param Variables $newParent The new parent
     *
     * @return void
     */
    public function bindTo(Variables $newParent)
    {
        if ($this->parent) {
            foreach ($this->parent->children as $i => $child) {
                if ($child === $this) {
                    unset($this->parent->children[$i]);
                    break;
                }
            }
        }
        $this->parent = $newParent;
        $this->parent->children[] = $this;
    }

    /**
     * Get the parent variables object, if any
     *
     * @return Variables
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Provide an array of variable configurations:
     * - Keys are the variable names
     * - Values can be
     * -- an array with the configuration - following keys are interpreted:
     * --- required (defaults to false)
     * --- default (defaults to null, ignored when required)
     * -- null: The default value will be set to null
     * -- false: No default value will be set
     *
     * Also you can add a "--" with numeric key, to state the boundaries of
     * current and parent variables configuration
     *
     * In either case variables in this configuration will always be fetched
     * from and saved to the very scope of this variables object (this)
     *
     * @return array
     */
    protected function configureVariables()
    {
        return array();
    }

    /**
     * Get a variable from this very object (unexpanded)
     *
     * @param mixed $offset Variable name
     *
     * @internal Required set/get/has/remove in order to access entries of Variables
     *           without looking up parents.
     *           You can however override this to do your own logic on plain offsets
     *           (no dot paths as in set/get/has/remove).
     *
     * @return mixed
     */
    public function &offsetGet($offset)
    {
        return $this->variables[$offset];
    }

    /**
     * Determine if a variable is available on this very object
     *
     * @param mixed $offset Variable name
     *
     * @internal See {@see Variables::offsetGet()}
     *
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->variables);
    }

    /**
     * Set a variable on this very object
     *
     * @param mixed $offset Variable name
     * @param mixed $value  The value
     *
     * @internal See {@see Variables::offsetGet()}
     *
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->variables[$offset] = $value;
    }

    /**
     * Unset a variable from this very object
     *
     * @param mixed $offset Variable name
     *
     * @internal See {@see Variables::offsetGet()}
     *
     * @return mixed
     */
    public function offsetUnset($offset)
    {
        unset($this->variables[$offset]);
    }


    /**
     * Find the first context that contains the first part of the variable
     * (this will always return current context - configured variables as well)
     *
     * @param array $variableParts The path split by dot
     *
     * @return Variables
     */
    private function findContext(&$variableParts)
    {
        if (!$variableParts) {
            return $this;
        }
        if (array_key_exists($variableParts[0], $this->variables['_variableConfiguration'])) {
            // Configured variables always belong to the current scope
            return $this;
        }
        if ($variableParts[0] === 'this') {
            array_shift($variableParts);
        } elseif ($variableParts[0] === 'parent') {
            if (!$this->parent) {
                throw new Exception('No parent object available');
            }
            array_shift($variableParts);
            return $this->parent;
        } else {
            // Look up this and all parents, if they have the variable
            $parent = $this;
            do {
                if ($parent->offsetExists($variableParts[0])) {
                    return $parent;
                }
            } while ($parent = $parent->parent);
        }
        return $this;
    }

    /**
     * Get an expanded variable by it's path (second argument can be a default value)
     *
     * @param string $name The variable name
     *
     * @final This method is not to be overwritten as logic is to complex to deal
     *        with overrides because callee detection might be introduced later on
     *        and the expansion logic of dot path's is mostly internal.
     *        If you need to intercept the variable handling rather override
     *        offsetSet, offsetGet, offsetExists or offsetUnset.
     *
     * @return mixed
     */
    final public function get($name)
    {
        $parts = explode('.', $name);

        $value = $this->findContext($parts);
        while ($parts) {
            $part = array_shift($parts);
            if ($this->arrayKeyExists($value, $part)) {
                $value = $this->expand($value[$part]);
            } elseif ($this->propertyExists($value, $part)) {
                $value = $this->expand($value->$part);
            } else {
                if (func_num_args() > 1) {
                    return func_get_arg(1);
                } else {
                    throw new Exception\MissingVariableException('Missing variable ' . $name);
                }
            }
            if ($parts && $value instanceof self && $value !== $this) {
                try {
                    $args = func_get_args();
                    $args[0] = implode('.', $parts);
                    return call_user_func_array(array($value, 'get'), $args);
                } catch (Exception\MissingVariableException $e) {
                    throw new Exception\MissingVariableException('Missing variable ' . $name);
                }
            }
        }

        return $value;
    }

    /**
     * Determine if a variable path is available
     *
     * @param string $name The variable name
     *
     * @final See {@see Variables::get()}
     *
     * @return boolean
     */
    final public function has($name)
    {
        $parts = explode('.', $name);
        $value = $this->findContext($parts);
        while ($parts) {
            $part = array_shift($parts);
            if ($this->arrayKeyExists($value, $part)) {
                $value = $this->expand($value[$part]);
            } elseif ($this->propertyExists($value, $part)) {
                $value = $this->expand($value->$part);
            } else {
                return false;
            }
            if ($parts && $value instanceof self && $value !== $this) {
                return $value->has(implode('.', $parts));
            }
        }

        return true;
    }

    /**
     * Set a variable by it's path
     *
     * @param string $name  The variable name
     * @param mixed  $value The value
     *
     * @final See {@see Variables::get()}
     *
     * @return $this
     */
    final public function set($name, $value)
    {
        $parts = explode('.', $name);
        if (in_array($name, array('this', 'parent'), true)) {
            throw new Exception('this and parent are variable names you may not override');
        }

        $finalPart = array_pop($parts);
        $parent = $this->findContext($parts);
        while ($parts) {
            $part = array_shift($parts);
            if ($this->arrayKeyExists($parent, $part)) {
                $parent = &$parent[$part];
            } elseif ($this->propertyExists($parent, $part)) {
                $parent = &$parent->$part;
            } else {
                $parent = $this->expand($parent);
                if (is_object($parent) || is_array($parent)) {
                    array_unshift($parts, $part);
                    continue;
                } else {
                    throw new Exception('Can not set values on primitives or null');
                }
            }
            if ($parts && $parent instanceof self && $parent !== $this) {
                $parent->set(implode('.', $parts), $value);
                return $this;
            }
        }
        $this->filterSpecialNames($finalPart, $value);
        if (is_array($parent) || $parent instanceof \ArrayAccess) {
            $parent[$finalPart] = $value;
        } elseif (is_object($parent)) {
            $parent->$finalPart = $value;
        }
        return $this;
    }

    /**
     * Unset a variable by it's path
     *
     * @param string $name Variable name
     *
     * @final See {@see Variables::get()}
     *
     * @return $this
     */
    final public function remove($name)
    {
        $parts = explode('.', $name);
        $finalPart = array_pop($parts);
        $parent = $this->findContext($parts);
        while ($parts) {
            $part = array_shift($parts);
            if ($this->arrayKeyExists($parent, $part)) {
                $parent = &$parent[$part];
            } elseif ($this->propertyExists($parent, $part)) {
                $parent = &$parent->$part;
            } else {
                $parent = $this->expand($parent);
                if (is_object($parent) || is_array($parent)) {
                    array_unshift($parts, $part);
                    continue;
                } else {
                    return $this;
                }
            }
            if ($parts && $parent instanceof self && $parent !== $this) {
                $parent->remove(implode('.', $parts));
                return $this;
            }
        }

        if ($this->arrayKeyExists($parent, $finalPart)) {
            unset($parent[$finalPart]);
        } elseif ($this->propertyExists($parent, $finalPart)) {
            unset($parent->$finalPart);
        }

        return $this;
    }

    /**
     * Determine if a variable is an array or accessible as array and has the key
     *
     * This method is required as isset returns false on existing keys with null
     * values and array_key_exists doesn't invoke {@see \ArrayAccess::offsetExists()}
     *
     * Use this BEFORE {@see Variables::propertyExists()} in according checks, as
     * it will match {@see Variables} objects as well. This will likely speed things
     * up a little but more importantly it will avoid that private or protected
     * properties are detected by {@see Variables::propertyExists()}.
     *
     * @param array|\ArrayAccess $array Array
     * @param string             $key   The key
     *
     * @return bool
     */
    private function arrayKeyExists($array, $key)
    {
        if (is_array($array)) {
            return array_key_exists($key, $array);
        } elseif ($array instanceof \ArrayAccess) {
            return $array->offsetExists($key);
        }
        return false;
    }

    /**
     * Determine if a variable is an object and has a property
     *
     * This method is required as isset returns false on existing properties with
     * null values and property_exists doesn't invoke {@see __isset()}
     *
     * @param object $object   The object
     * @param string $property The property
     *
     * @return bool
     */
    private function propertyExists($object, $property)
    {
        if (is_object($object)) {
            if (property_exists($object, $property)) {
                return true;
            }
            if (method_exists($object, '__isset') && $object->__isset($property)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Set from array
     *
     * @param array $values Array with key value pairs
     *
     * @return $this
     */
    public function setFromArray($values)
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
        return $this;
    }

    /**
     * Expand expressions within the $value
     *
     * @param mixed $value The value
     *
     * @return string|mixed
     */
    public function expand($value)
    {
        static $expressionEngine;
        if (!$expressionEngine) {
            $expressionEngine = new ExpressionLanguage();
        }
        return $expressionEngine->evaluate($value, [ExpressionLanguage::VARIABLES_KEY => $this]);
    }

    /**
     * Handles special variable names
     *
     * @param string $key   Current part of the complete variable name
     * @param mixed  $value The value
     *
     * @return void
     */
    private function filterSpecialNames(&$key, &$value)
    {
        if ($key === 'node' && is_array($value)) {
            $key = 'nodes';
            $value = array($value);
        }
        if ($key === 'nodes') {
            $nodes = array();
            foreach ($value as $id => $options) {
                if ($options instanceof Node) {
                    $node = $options;
                } else {
                    $node = new Node($this);
                    $node->setFromArray($options);
                }
                $node->set('id', $id);
                $nodes[$id] = $node;
            }
            $value = $nodes;
        }
    }
}
?>
