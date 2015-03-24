<?php

use Doh\FindUnusedBundlesBundle\Command\FindUnusedBundlesCommand;
use Symfony\Component\HttpKernel\Tests\Bundle\BundleTest;

class FindUnusedBundlesCommandTest extends \PHPUnit_Framework_TestCase
{
    public function testRemoveItSelf()
    {
        $command = new FindUnusedBundlesCommand();

        $bundle = new BundleTest();
        $bundle->setName('foo');

        $bundle2 = new BundleTest();
        $bundle2->setName( FindUnusedBundlesCommand::DOH_FIND_UNUSED_BUNDLES_BUNDLE_NAME);
        $bundles = array($bundle, $bundle2);

        $command->setBundles($bundles);

        $this->assertEquals(2, count($command->getBundles()));
        $command->removeItSelf();
        $this->assertEquals(1, count($command->getBundles()));
    }
}