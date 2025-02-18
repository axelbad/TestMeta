<?php

use PHPUnit\Framework\TestCase;
use App\handleConfig;

class HandleConfigTest extends TestCase
{
    private string $testConfigFile;
    private string $importFile;
    private string $cacheFile;
    private string $mainCache;

    protected function setUp(): void
    {
        $this->testConfigFile = __DIR__ . '/testConfig.xml';
        $this->importFile = __DIR__ . '/import1.xml';
        $this->cacheFile = __DIR__ . '/../cache/configCache.php';
        $this->mainCache = __DIR__ . '/../cache/mainCache.xml';

        // Create a mock imported XML file
        $importContent = <<<XML
        <?xml version="1.0" encoding="utf-8"?>
        <glz:Config xmlns:glz="http://www.glizy.org/dtd/1.0/">
            <glz:Group name="imported">
                <glz:Param name="importedValue" value="xyz" />
            </glz:Group>
        </glz:Config>
        XML;
        file_put_contents($this->importFile, $importContent);

        // Create a test XML configuration
        $xmlContent = <<<XML
        <?xml version="1.0" encoding="utf-8"?>
        <glz:Config xmlns:glz="http://www.glizy.org/dtd/1.0/">
            <glz:Import src="import1.xml" />

            <glz:Group name="thumbnail">
                <glz:Param name="width" value="400" />
                <glz:Param name="height" value="400" />
            </glz:Group>

            <glz:Param name="arrayvalue[]" value="abc" />
            <glz:Param name="arrayvalue[]" value="def" />

            <glz:Group name="group">
                <glz:Group name="innergroup">
                    <glz:Param name="value1" value="abc" />
                    <glz:Param name="value2" value="def" />
                </glz:Group>
            </glz:Group>

            <glz:Param name="longtext"><![CDATA[
                <p>Lorem ipsum dolor sit amet.</p>
            ]]></glz:Param>
        </glz:Config>
        XML;
        file_put_contents($this->testConfigFile, $xmlContent);
    }

    protected function tearDown(): void
    {
        // Cleanup test files
        if (file_exists($this->testConfigFile)) {
            unlink($this->testConfigFile);
        }
        if (file_exists($this->importFile)) {
            unlink($this->importFile);
        }
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
        if (file_exists($this->mainCache)) {
            unlink($this->mainCache);
        }
    }

    /**
     * Test loading values from the XML configuration.
     */
    public function testConfigLoading(): void
    {
        $config = new handleConfig($this->testConfigFile);

        // Check simple values
        $this->assertEquals('400', $config->get('thumbnail/width'));
        $this->assertEquals('400', $config->get('thumbnail/height'));

        // Check nested values
        $this->assertEquals('abc', $config->get('group/innergroup/value1'));
        $this->assertEquals('def', $config->get('group/innergroup/value2'));

        // Check long text with CDATA
        $this->assertStringContainsString('<p>Lorem ipsum dolor sit amet.</p>', $config->get('longtext'));
    }

    /**
     * Test array handling from XML.
     */
    public function testArrayHandling(): void
    {
        $config = new handleConfig($this->testConfigFile);
        $this->assertIsArray($config->get('arrayvalue'));
        $this->assertEquals(['abc', 'def'], $config->get('arrayvalue'));
    }

    /**
     * Test XML import functionality.
     */
    public function testXmlImport(): void
    {
        $config = new handleConfig($this->testConfigFile);
        $this->assertEquals('xyz', $config->get('imported/importedValue'));
    }
}