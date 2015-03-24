<?php

use Doh\FindUnusedBundlesBundle\Command\FindUnusedBundlesCommand;
use Symfony\Component\HttpKernel\Tests\Bundle\BundleTest;

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
            ->will($this->returnValue(new Symfony\Component\HttpKernel\Tests\Fixtures\KernelForTest('test', true)));

        $this->invokeMethod($command, 'execute', array($input, $output));
    }
}