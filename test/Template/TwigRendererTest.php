<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Template;

use ArrayObject;
use PHPUnit_Framework_TestCase as TestCase;
use Twig_Environment;
use Twig_Loader_Filesystem;
use Zend\Expressive\Exception;
use Zend\Expressive\Template\TwigRenderer;

class TwigRendererTest extends TestCase
{
    /** @var  Twig_Loader_Filesystem */
    private $twigFilesystem;
    /** @var Twig_Environment */
    private $twigEnvironment;

    use TemplatePathAssertionsTrait;

    public function setUp()
    {
        $this->twigFilesystem  = new Twig_Loader_Filesystem;
        $this->twigEnvironment = new Twig_Environment($this->twigFilesystem);
    }

    public function testShouldInjectDefaultLoaderIfProvidedEnvironmentDoesNotComposeOne()
    {
        $twigEnvironment  = new Twig_Environment();
        $renderer = new TwigRenderer($twigEnvironment);
        $loader           = $twigEnvironment->getLoader();
        $this->assertInstanceOf('Twig_Loader_Filesystem', $loader);
    }

    public function testCanPassEngineToConstructor()
    {
        $renderer = new TwigRenderer($this->twigEnvironment);
        $this->assertInstanceOf(TwigRenderer::class, $renderer);
        $this->assertEmpty($renderer->getPaths());
    }

    public function testInstantiatingWithoutEngineLazyLoadsOne()
    {
        $renderer = new TwigRenderer();
        $this->assertInstanceOf(TwigRenderer::class, $renderer);
        $this->assertEmpty($renderer->getPaths());
    }

    public function testCanAddPathWithEmptyNamespace()
    {
        $renderer = new TwigRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset');
        $paths = $renderer->getPaths();
        $this->assertInternalType('array', $paths);
        $this->assertEquals(1, count($paths));
        $this->assertTemplatePath(__DIR__ . '/TestAsset', $paths[0]);
        $this->assertTemplatePathString(__DIR__ . '/TestAsset', $paths[0]);
        $this->assertEmptyTemplatePathNamespace($paths[0]);
    }

    public function testCanAddPathWithNamespace()
    {
        $renderer = new TwigRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset', 'test');
        $paths = $renderer->getPaths();
        $this->assertInternalType('array', $paths);
        $this->assertEquals(1, count($paths));
        $this->assertTemplatePath(__DIR__ . '/TestAsset', $paths[0]);
        $this->assertTemplatePathString(__DIR__ . '/TestAsset', $paths[0]);
        $this->assertTemplatePathNamespace('test', $paths[0]);
    }

    public function testDelegatesRenderingToUnderlyingImplementation()
    {
        $renderer = new TwigRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset');
        $name = 'Twig';
        $result = $renderer->render('twig.html', [ 'name' => $name ]);
        $this->assertContains($name, $result);
        $content = file_get_contents(__DIR__ . '/TestAsset/twig.html');
        $content = str_replace('{{ name }}', $name, $content);
        $this->assertEquals($content, $result);
    }

    public function invalidParameterValues()
    {
        return [
            'true'       => [true],
            'false'      => [false],
            'zero'       => [0],
            'int'        => [1],
            'zero-float' => [0.0],
            'float'      => [1.1],
            'string'     => ['value'],
        ];
    }

    /**
     * @dataProvider invalidParameterValues
     */
    public function testRenderRaisesExceptionForInvalidParameterTypes($params)
    {
        $renderer = new TwigRenderer();
        $this->setExpectedException(Exception\InvalidArgumentException::class);
        $renderer->render('foo', $params);
    }

    public function testCanRenderWithNullParams()
    {
        $renderer = new TwigRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset');
        $result = $renderer->render('twig-null.html', null);
        $content = file_get_contents(__DIR__ . '/TestAsset/twig-null.html');
        $this->assertEquals($content, $result);
    }

    public function objectParameterValues()
    {
        $names = [
            'stdClass'    => uniqid(),
            'ArrayObject' => uniqid(),
        ];

        return [
            'stdClass'    => [(object) ['name' => $names['stdClass']], $names['stdClass']],
            'ArrayObject' => [new ArrayObject(['name' => $names['ArrayObject']]), $names['ArrayObject']],
        ];
    }

    /**
     * @dataProvider objectParameterValues
     */
    public function testCanRenderWithParameterObjects($params, $search)
    {
        $renderer = new TwigRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset');
        $result = $renderer->render('twig.html', $params);
        $this->assertContains($search, $result);
        $content = file_get_contents(__DIR__ . '/TestAsset/twig.html');
        $content = str_replace('{{ name }}', $search, $content);
        $this->assertEquals($content, $result);
    }

    /**
     * @group namespacing
     */
    public function testProperlyResolvesNamespacedTemplate()
    {
        $renderer = new TwigRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset/test', 'test');

        $expected = file_get_contents(__DIR__ . '/TestAsset/test/test.html');
        $test     = $renderer->render('test::test');

        $this->assertSame($expected, $test);
    }

    /**
     * @group namespacing
     */
    public function testResolvesNamespacedTemplateWithSuffix()
    {
        $renderer = new TwigRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset/test', 'test');

        $expected = file_get_contents(__DIR__ . '/TestAsset/test/test.js');
        $test     = $renderer->render('test::test.js');

        $this->assertSame($expected, $test);
    }

    public function testAddParameterToOneTemplate()
    {
        $renderer = new TwigRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset');
        $name = 'Twig';
        $renderer->addDefaultParam('twig', 'name', $name);
        $result = $renderer->render('twig');

        $content = file_get_contents(__DIR__ . '/TestAsset/twig.html');
        $content = str_replace('{{ name }}', $name, $content);
        $this->assertEquals($content, $result);
    }

    public function testAddSharedParameters()
    {
        $renderer = new TwigRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset');
        $name = 'Twig';
        $renderer->addDefaultParam($renderer::TEMPLATE_ALL, 'name', $name);
        $result = $renderer->render('twig');
        $content = file_get_contents(__DIR__ . '/TestAsset/twig.html');
        $content = str_replace('{{ name }}', $name, $content);
        $this->assertEquals($content, $result);

        $result = $renderer->render('twig-2');
        $content = file_get_contents(__DIR__ . '/TestAsset/twig-2.html');
        $content = str_replace('{{ name }}', $name, $content);
        $this->assertEquals($content, $result);
    }

    public function testOverrideSharedParametersPerTemplate()
    {
        $renderer = new TwigRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset');
        $name = 'Twig';
        $name2 = 'Template';
        $renderer->addDefaultParam($renderer::TEMPLATE_ALL, 'name', $name);
        $renderer->addDefaultParam('twig-2', 'name', $name2);
        $result = $renderer->render('twig');
        $content = file_get_contents(__DIR__ . '/TestAsset/twig.html');
        $content = str_replace('{{ name }}', $name, $content);
        $this->assertEquals($content, $result);

        $result = $renderer->render('twig-2');
        $content = file_get_contents(__DIR__ . '/TestAsset/twig-2.html');
        $content = str_replace('{{ name }}', $name2, $content);
        $this->assertEquals($content, $result);
    }

    public function testOverrideSharedParametersAtRender()
    {
        $renderer = new TwigRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset');
        $name = 'Twig';
        $name2 = 'Template';
        $renderer->addDefaultParam($renderer::TEMPLATE_ALL, 'name', $name);
        $result = $renderer->render('twig', ['name' => $name2]);
        $content = file_get_contents(__DIR__ . '/TestAsset/twig.html');
        $content = str_replace('{{ name }}', $name2, $content);
        $this->assertEquals($content, $result);
    }
}
