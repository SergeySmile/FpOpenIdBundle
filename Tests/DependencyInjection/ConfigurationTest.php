<?php
namespace Fp\OpenIdBundle\Tests\DependencyInjection;

use Symfony\Component\Config\Definition\Processor;
use PHPUnit\Framework\TestCase;
use Fp\OpenIdBundle\DependencyInjection\Configuration;

class ConfigurationTest extends TestCase
{
    /**
     * @test
     */
    public function shouldAllowToUseWithoutAnyRequiredConfiguration()
    {
        $emptyConfig = array();

        $this->processConfiguration($emptyConfig);
    }

    /**
     * @test
     */
    public function shouldAllowToSetScalarDbDriver()
    {
        $config = array('fp_open_id' => array(
            'db_driver' => 'foo'
        ));

        $this->processConfiguration($config);
    }

    /**
     * @test
     */
    public function shouldSetNullAsDefaultDbDriver()
    {
        $config = array('fp_open_id' => array());

        $processedConfig = $this->processConfiguration($config);

        $this->assertArrayHasKey('db_driver', $processedConfig);
        $this->assertNull($processedConfig['db_driver']);
    }

    /**
     * @test
     *
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidTypeException
     * @expectedExceptionMessage Invalid type for path "fp_open_id.db_driver". Expected scalar, but got array.
     */
    public function throwIfDbDriverNotScalar()
    {
        $config = array('fp_open_id' => array(
            'db_driver' => array()
        ));

        $this->processConfiguration($config);
    }

    /**
     * @test
     */
    public function shouldAllowToSetIdentityClass()
    {
        $config = array('fp_open_id' => array(
            'identity_class' => 'foo'
        ));

        $this->processConfiguration($config);
    }

    /**
     * @test
     */
    public function shouldSetNullAsDefaultIdentityClass()
    {
        $config = array('fp_open_id' => array());

        $processedConfig = $this->processConfiguration($config);

        $this->assertArrayHasKey('identity_class', $processedConfig);
        $this->assertNull($processedConfig['identity_class']);
    }

    /**
     * @test
     *
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidTypeException
     * @expectedExceptionMessage Invalid type for path "fp_open_id.identity_class". Expected scalar, but got array.
     */
    public function throwIfIdentityClassNotScalar()
    {
        $config = array('fp_open_id' => array(
            'identity_class' => array()
        ));

        $this->processConfiguration($config);
    }

    /**
     * @test
     */
    public function shouldAllowToSetTemplateEngine()
    {
        $config = array('fp_open_id' => array(
            'template' => array('engine' => 'foo')
        ));

        $this->processConfiguration($config);
    }

    /**
     * @test
     */
    public function shouldAddTwgiAsDefaultTemplateEngine()
    {
        $config = array('fp_open_id' => array());

        $processedConfig = $this->processConfiguration($config);

        $this->assertArrayHasKey('template', $processedConfig);
        $this->assertArrayHasKey('engine', $processedConfig['template']);
        $this->assertEquals('twig', $processedConfig['template']['engine']);
    }

    /**
     * @test
     *
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidTypeException
     * @expectedExceptionMessage Invalid type for path "fp_open_id.template.engine". Expected scalar, but got array.
     */
    public function throwIfTemplateEngineNotScalar()
    {
        $config = array('fp_open_id' => array(
            'template' => array('engine' => array())
        ));

        $this->processConfiguration($config);
    }

    protected function processConfiguration(array $configs)
    {
        $configuration = new Configuration();
        $processor = new Processor();

        return $processor->processConfiguration($configuration, $configs);
    }
}