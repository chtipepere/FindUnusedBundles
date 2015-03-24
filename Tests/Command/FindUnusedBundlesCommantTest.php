<?php

namespace Doh\FindUnusedBundlesBundle\Tests;

use Doh\FindUnusedBundlesBundle\Command\FindUnusedBundlesCommand;
use Symfony\Component\HttpKernel\Tests\Bundle\BundleTest;
use Symfony\Component\HttpKernel\Tests\Fixtures\ExtensionPresentBundle\ExtensionPresentBundle;
use Symfony\Component\HttpKernel\Tests\Fixtures\KernelForTest;

class FindUnusedBundlesCommandTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param mixed  $object
     * @param string $methodName
     * @param array  $parameters
     *
     * @return mixed
     */
    public function invokeMethod($object, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    public function testRemoveItSelf()
    {
        $command = new FindUnusedBundlesCommand();

        $bundle = new BundleTest();
        $bundle->setName('foo');

        $bundle2 = new BundleTest();
        $bundle2->setName(FindUnusedBundlesCommand::DOH_FIND_UNUSED_BUNDLES_BUNDLE_NAME);
        $bundles = array($bundle, $bundle2);

        $command->setBundles($bundles);

        $this->assertEquals(2, count($command->getBundles()));
        $command->removeItSelf();
        $this->assertEquals(1, count($command->getBundles()));
    }

    public function testExecuteCallEveryMethods()
    {
        $output = $this->getMockBuilder('Symfony\Component\Console\Output\OutputInterface')->getMock();
        $input = $this->getMockBuilder('Symfony\Component\Console\Input\InputInterface')->getMock();
        $command = $this->getMockBuilder('Doh\FindUnusedBundlesBundle\Command\FindUnusedBundlesCommand')
            ->setMethods(array('setBundles', 'setLoadedBundles', 'removeItSelf', 'checkComposer', 'getContainer', 'checkYamlFile', 'checkRouting', 'checkCode', 'checkTwigExtension', 'outputResult', 'getKernel'))
            ->getMock();

        $command->expects($this->once())
            ->method('setBundles');

        $command->expects($this->once())
            ->method('removeItSelf');

        $command->expects($this->once())
            ->method('checkComposer')
            ->with($output);

        $command->expects($this->exactly(4))
            ->method('checkYamlFile');

        $command->expects($this->once())
            ->method('checkRouting')
            ->with($output);

        $command->expects($this->once())
            ->method('checkCode')
            ->with($output);

        $command->expects($this->once())
            ->method('checkTwigExtension')
            ->with($output);

        $command->expects($this->once())
            ->method('outputResult')
            ->with($output);

        $command->expects($this->once())
            ->method('getKernel')
            ->will($this->returnValue(new KernelForTest('test', true)));

        $this->invokeMethod($command, 'execute', array($input, $output));
    }

    public function testFindUsageWhenUsageIsInCode()
    {
        $command = $this->getMockBuilder('Doh\FindUnusedBundlesBundle\Command\FindUnusedBundlesCommand')
                        ->setMethods(array('findUsageInCode', 'findUsageUsingAutoload'))
                        ->getMock();

        $key                = 'key';
        $packageName        = 'packageName';
        $composerContent    = 'composerContent';

        $command->setPackages(array('foo' => 'bar', 'packageName' => 'package'));

        $command->expects($this->once())
            ->method('findUsageInCode')
            ->with($key)
            ->will($this->returnValue('grep'));

        $command->expects($this->never())
            ->method('findUsageUsingAutoload');

        $this->assertEquals(2, count($command->getPackages()));
        $this->invokeMethod($command, 'findUsage', array($key, $packageName, $composerContent));
        $this->assertEquals(1, count($command->getPackages()));
    }

    public function testFindUsageWhenUsageIsInAutoload()
    {
        $command = $this->getMockBuilder('Doh\FindUnusedBundlesBundle\Command\FindUnusedBundlesCommand')
                        ->setMethods(array('findUsageInCode', 'findUsageUsingAutoload'))
                        ->getMock();

        $key                = 'key';
        $packageName        = 'packageName';
        $composerContent    = 'composerContent';

        $command->expects($this->once())
            ->method('findUsageInCode')
            ->with($key)
            ->will($this->returnValue(''));

        $command->expects($this->once())
            ->method('findUsageUsingAutoload')
            ->with($key, $packageName, $composerContent);

        $this->invokeMethod($command, 'findUsage', array($key, $packageName, $composerContent));
    }

    public function testFindUsageInCode()
    {
        $key = 'key';
        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\Tests\Fixtures\KernelForTest')
            ->disableOriginalConstructor()
            ->setMethods(array('getRootDir'))
            ->getMock();

        $kernel->expects($this->once())
            ->method('getRootDir')
            ->will($this->returnValue('/foo'));

        $command = $this->getMockBuilder('Doh\FindUnusedBundlesBundle\Command\FindUnusedBundlesCommand')
                        ->setMethods(array('getKernel', 'exec'))
                        ->getMock();

        $command->expects($this->once())
            ->method('getKernel')
            ->will($this->returnValue($kernel));

        $command->expects($this->once())
            ->method('exec')
            ->with("grep -R 'key' /foo/../src");

        $this->invokeMethod($command, 'findUsageInCode', array($key));
    }

    public function testGetFileContentThrowException()
    {
        $filename = 'README.md';
        $command = $this->getMockBuilder('Doh\FindUnusedBundlesBundle\Command\FindUnusedBundlesCommand')
                        ->setMethods(array('getKernel'))
                        ->getMock();

        $command->expects($this->once())
                ->method('getKernel')
                ->will($this->returnValue(new KernelForTest('test', true)));

        $this->setExpectedException('RuntimeException');

        $this->invokeMethod($command, 'getFileContent', array($filename));
    }

    public function testGetFileContentReturnContents()
    {
        $filename = 'composer.json';

        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\Tests\Fixtures\KernelForTest')
                        ->disableOriginalConstructor()
                        ->setMethods(array('getRootDir'))
                        ->getMock();

        $kernel->expects($this->once())
                ->method('getRootDir')
                ->will($this->returnValue(__DIR__ . '/..'));

        $command = $this->getMockBuilder('Doh\FindUnusedBundlesBundle\Command\FindUnusedBundlesCommand')
                        ->setMethods(array('getKernel'))
                        ->getMock();

        $command->expects($this->once())
                ->method('getKernel')
                ->will($this->returnValue($kernel));

        $fileContent = $this->invokeMethod($command, 'getFileContent', array($filename));
        $this->assertNotEmpty($fileContent);
    }

    public function testGetKernel()
    {
        $command = $this->getMockBuilder('Doh\FindUnusedBundlesBundle\Command\FindUnusedBundlesCommand')
                        ->setMethods(array('container'))
                        ->getMock();

        $container = $this->getMockForAbstractClass('Symfony\Component\DependencyInjection\ContainerInterface');

        $container->expects($this->once())
            ->method('get')
            ->with('kernel')
            ->will($this->returnValue(new KernelForTest('test', true)));

        $command->setContainer($container);

        $this->invokeMethod($command, 'getKernel');
    }

    public function testSetLoadedBundles()
    {
        $bundles = 'foo';
        $command = new FindUnusedBundlesCommand();
        $command->setLoadedBundles($bundles);
        $this->assertEquals('foo', $command->getLoadedBundles());
    }

    public function testFindUsageUsingAutoload()
    {
        $key = 'Symfony\Component\HttpKernel\Tests\Fixtures\ExtensionPresentBundle';
        $packageName = 'PackageName';
        $composerContent = '';

        $command = new FindUnusedBundlesCommand();
        $command->setLoadedBundles(array(new ExtensionPresentBundle()));
        $command->setPackages(array('PackageName' => 'package', 'foo' => 'bar'));

        $this->assertEquals(2, count($command->getPackages()));
        $this->invokeMethod($command, 'findUsageUsingAutoload', array($key, $packageName, $composerContent));
        $this->assertEquals(1, count($command->getPackages()));
    }

    public function testFindUsageUsingAutoloadAndComposer()
    {
        $key = 'foo\bar';
        $packageName = 'PackageName';
        $composerContent = array();
        $composerContent['scripts']['post-install-cmd'] = array(
            'foo\bar'
        );

        $command = new FindUnusedBundlesCommand();
        $command->setLoadedBundles(array(new ExtensionPresentBundle()));
        $command->setPackages(array('PackageName' => 'package', 'foo' => 'bar'));

        $this->assertEquals(2, count($command->getPackages()));
        $this->invokeMethod($command, 'findUsageUsingAutoload', array($key, $packageName, $composerContent));
        $this->assertEquals(1, count($command->getPackages()));
    }
}