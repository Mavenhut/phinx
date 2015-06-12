<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2015 Rob Morgan
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated * documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * @package    Phinx
 * @subpackage Phinx\Config
 */
namespace Phinx\Config;

/**
 * Phinx configuration class.
 *
 * @package Phinx
 * @author Rob Morgan
 */
class Config implements ConfigInterface
{
    /**
     * @var array
     */
    private $values = array();

    /**
     * Migration table
     */
    private $migrationTable = 'migrations';

    /**
     * Migration folder
     */
    private $migrationPath = __DIR__;

    /**
     * @var string
     */
    protected $configFilePath;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $configArray, $configFilePath = null)
    {
        $this->values = $configArray;
    }

    /**
     * Create config instance from array
     * @param {Array} $cfgArray
     * @return Config
     */
    public static function fromArray($cfgArray)
    {
        /**
         * Default template for config
         */
        $configArray = array();

        $configArray['paths']['migrations'] = getcwd() . '/migrations';
        $configArray['environments']['default_migration_table'] = 'migrations_phinx';
        $configArray['environments']['default_database'] = 'db';
        $configArray['environments']['db']['adapter'] = 'mysql';
        $configArray['environments']['db']['port'] = '3306';
        $configArray['environments']['db']['charset'] = 'utf8';

        $configArray['environments']['db']['host'] = $cfgArray['host'];
        $configArray['environments']['db']['user'] = $cfgArray['user'];
        $configArray['environments']['db']['pass'] = $cfgArray['pass'];
        $configArray['environments']['db']['name'] = $cfgArray['db'];


        return new static($configArray);
    }

    /**
     * {@inheritdoc}
     */
    public function getEnvironments()
    {
        if (isset($this->values) && isset($this->values['environments'])) {
            $environments = array();
            foreach ($this->values['environments'] as $key => $value) {
                if (is_array($value)) {
                    $environments[$key] = $value;
                }
            }

            return $environments;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getEnvironment($name)
    {
        $environments = $this->getEnvironments();

        if (isset($environments[$name])) {
            if (isset($this->values['environments']['default_migration_table'])) {
                $environments[$name]['default_migration_table'] =
                    $this->values['environments']['default_migration_table'];
            }

            return $environments[$name];
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function hasEnvironment($name)
    {
        return (!(null === $this->getEnvironment($name)));
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultEnvironment()
    {
        // The $PHINX_ENVIRONMENT variable overrides all other default settings
        $env = getenv('PHINX_ENVIRONMENT');
        if (!empty($env)) {
            if ($this->hasEnvironment($env)) {
                return $env;
            }

            throw new \RuntimeException(sprintf(
                'The environment configuration (read from $PHINX_ENVIRONMENT) for \'%s\' is missing',
                $env
            ));
        }

        // if the user has configured a default database then use it,
        // providing it actually exists!
        if (isset($this->values['environments']['default_database'])) {
            if ($this->getEnvironment($this->values['environments']['default_database'])) {
                return $this->values['environments']['default_database'];
            }

            throw new \RuntimeException(sprintf(
                'The environment configuration for \'%s\' is missing',
                $this->values['environments']['default_database']
            ));
        }

        // else default to the first available one
        if (is_array($this->getEnvironments()) && count($this->getEnvironments()) > 0) {
            $names = array_keys($this->getEnvironments());
            return $names[0];
        }

        throw new \RuntimeException('Could not find a default environment');
    }

    /**
     * {@inheritdoc}
     */
    public function getMigrationPath()
    {
        if (!isset($this->values['paths']['migrations'])) {
            throw new \UnexpectedValueException('Migrations path missing from config file');
        }

        return $this->values['paths']['migrations'];
    }

    /**
     * Gets the base class name for migrations.
     *
     * @param boolean $dropNamespace Return the base migration class name without the namespace.
     * @return string
     */
    public function getMigrationBaseClassName($dropNamespace = true)
    {
        $className = !isset($this->values['migration_base_class']) ? 'Phinx\Migration\AbstractMigration' : $this->values['migration_base_class'];

        return $dropNamespace ? substr(strrchr($className, '\\'), 1) : $className;
    }

    /**
     * Recurse an array for the specified tokens and replace them.
     *
     * @param array $arr Array to recurse
     * @param array $tokens Array of tokens to search for
     * @return array
     */
    protected function recurseArrayForTokens($arr, $tokens)
    {
        $out = array();
        foreach ($arr as $name => $value) {
            if (is_array($value)) {
                $out[$name] = $this->recurseArrayForTokens($value, $tokens);
                continue;
            }
            if (is_string($value)) {
                foreach ($tokens as $token => $tval) {
                    $value = str_replace($token, $tval, $value);
                }
                $out[$name] = $value;
                continue;
            }
            $out[$name] = $value;
        }
        return $out;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($id, $value)
    {
        $this->values[$id] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($id)
    {
        if (!array_key_exists($id, $this->values)) {
            throw new \InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
        }

        return $this->values[$id] instanceof \Closure ? $this->values[$id]($this) : $this->values[$id];
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($id)
    {
        return isset($this->values[$id]);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($id)
    {
        unset($this->values[$id]);
    }
}
