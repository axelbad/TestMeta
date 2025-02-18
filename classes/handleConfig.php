<?php

namespace App;

use Exception;
use SimpleXMLElement;

class handleConfig
{
    private array $config = [];
    private string $cacheFile = __DIR__ . '/../cache/configCache.php';
    private string $mainCache = __DIR__ . '/../cache/mainCache.xml';
    private string $configFile;
    private bool $skipIfNotExists = true;

    /**
     * Constructor for the handleConfig class.
     *
     * @param string $configFile The path to the configuration file.
     *
     * This constructor initializes the configuration file path and checks if the cache is valid.
     * If the cache is valid, it loads the configuration from the cache file.
     * Otherwise, it loads the configuration from the provided configuration file and saves it to the cache.
     */
    public function __construct(string $configFile)
    {
        $this->configFile = $configFile;

        if ($this->isCacheValid()) {
            $this->config = include $this->cacheFile;
        } else {
            $this->loadConfig($this->configFile);
            $this->saveCache();
        }
    }

    /**
     * Checks if the cache is valid by comparing the contents of the main cache file and the configuration file.
     *
     * @return bool Returns true if both files exist and their contents are identical, false otherwise.
     */
    private function isCacheValid(): bool
    {
        return file_exists($this->mainCache) && file_exists($this->configFile) && file_get_contents($this->mainCache) === file_get_contents($this->configFile);
    }

    /**
     * Saves the current configuration to cache files.
     *
     * This method writes the current configuration array to a PHP file
     * and also copies the contents of the main configuration file to another cache file.
     *
     * @return void
     */
    private function saveCache(): void
    {
        file_put_contents($this->cacheFile, '<?php return ' . var_export($this->config, true) . ';');
        file_put_contents($this->mainCache, file_get_contents($this->configFile));
    }

    /**
     * Loads and parses an XML configuration file.
     *
     * @param string $filePath The path to the XML configuration file.
     * @param string $prefix An optional prefix to be used during parsing.
     * 
     * @throws Exception If the file does not exist and $this->skipIfNotExists is false.
     */
    private function loadConfig(string $filePath, string $prefix = ''): void
    {
        if (!file_exists($filePath)) {
            if ($this->skipIfNotExists) {
                return;
            }
            throw new Exception("File not found: $filePath");
        }
        
        $xml = simplexml_load_file($filePath, 'SimpleXMLElement', LIBXML_NOCDATA);
        
        $this->parseXml($xml, $prefix, dirname($filePath));
    }

    /**
     * Parses an XML configuration file and processes its nodes.
     *
     * @param SimpleXMLElement $xml The XML element to parse.
     * @param string $prefix The prefix to use for parameter names.
     * @param string $basePath The base path for resolving import paths.
     *
     * The function processes the following node types:
     * - Import: Loads additional configuration from the specified source.
     * - Group: Recursively parses child nodes with an updated prefix.
     * - Param: Stores a parameter with the specified name and value.
     */
    private function parseXml(SimpleXMLElement $xml, string $prefix, string $basePath): void
    {
        foreach ($xml->children('http://www.glizy.org/dtd/1.0/') as $node) {
            switch ($node->getName()) {
                case 'Import':
                    $importPath = $basePath . '/' . (string) $node->attributes()->src;
                    $this->loadConfig($importPath, $prefix);
                    break;
                case 'Group':
                    $newPrefix = $prefix . (string) $node->attributes()->name . '/';
                    $this->parseXml($node, $newPrefix, $basePath);
                    break;
                case 'Param':
                    $value = (string) ($node->attributes()->value ?? $node);
                    $this->storeParam($prefix . (string) $node->attributes()->name, $value);
                    break;
            }
        }
    }

    /**
     * Stores a parameter in the configuration array.
     *
     * If the value is numeric, it will be converted to an integer or float.
     * If the value is a string representation of a boolean ('true' or 'false'),
     * it will be converted to a boolean.
     * If the parameter name ends with '[]', the value will be appended to an array
     * under the base name (without '[]'). Otherwise, the value will be stored
     * directly under the given name.
     *
     * @param string $name The name of the parameter.
     * @param string $value The value of the parameter.
     * @return void
     */
    private function storeParam(string $name, string $value): void
    {
        if (is_numeric($value)) {
            $value += 0;
        } elseif (in_array(strtolower($value), ['true', 'false'], true)) {
            $value = strtolower($value) === 'true';
        }

        if (substr($name, -2) === '[]') {
            $key = substr($name, 0, -2);
            $this->config[$key][] = $value;
        } else {
            $this->config[$name] = $value;
        }
    }

    /**
     * Retrieves the entire configuration array.
     *
     * @return array The configuration array.
     */
    public function getConfig(): array
    {
        return $this->config;
    }
    
    /**
     * Retrieves a value from the configuration array by key.
     *
     * If the key does not exist, the default value will be returned.
     *
     * @param string $key The key of the configuration parameter.
     * @param mixed $default The default value to return if the key does not exist.
     * @return mixed The value of the configuration parameter or the default value.
     */
    public function get(string $key, mixed $default = null)
    {
        return $this->config[$key] ?? $default;
    }
}