<?php

namespace Doh\FindUnusedBundlesBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Routing\Route;
use Symfony\Component\Yaml\Yaml;

/**
 * Class FindUnusedBundlesCommand
 *
 * @package Acme\DemoBundle\Command
 */
class FindUnusedBundlesCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName( 'doh:unusedblundles:find' )
            ->setDescription( 'Find unused bundles in your application' );
    }

    protected function execute( InputInterface $input, OutputInterface $output )
    {
        $this->bundles          = $this->getContainer()->get('kernel')->getBundles();
        $this->loadedBundles    = $this->getContainer()->get('kernel')->getBundles();

        $this->checkComposer( $output );

        $this->checkRouting( $output );

        $this->checkConfig( $output );

        $this->checkSecurity( $output );

        $this->checkCode( $output );

        $this->checkTwigExtension( $output );

        $this->outputResult( $output );
    }

    protected function checkSecurity( OutputInterface $output )
    {
        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $output->writeln( '----- Check Security -----' );
        }

        $configDirectories = array( $this->getContainer()->get( 'kernel' )->getRootDir() . '/config' );

        $locator          = new FileLocator( $configDirectories );
        $yamlSecurityFile = $locator->locate( 'security.yml', null, false );

        $configValues        = Yaml::parse( file_get_contents( $yamlSecurityFile[0] ) );
        $bundlesUsedInConfig = array();

        foreach ($this->loadedBundles as $bundleKey => $bundle) {
            $configClassName = sprintf( '%s\DependencyInjection\Configuration', $bundle->getNamespace() );

            if (false === class_exists( $configClassName )) {
                $configClassName = sprintf( '%s\DependencyInjection\MainConfiguration', $bundle->getNamespace() );
                $config          = new $configClassName( array(), array() );
            } else {
                if ($bundle->getName() === 'AsseticBundle') {
                    $config = new $configClassName( array() );
                } else {
                    $config = new $configClassName( false );
                }
            }

            /** @var TreeBuilder $tree */
            $tree           = $config->getConfigTreeBuilder();
            $configNodeName = $tree->buildTree()->getName();

            if (isset( $configValues[$configNodeName] )) {
                $bundlesUsedInConfig[] = $bundle;
                unset( $this->bundles[$bundleKey] );
            }
        }

        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $output->writeln( sprintf( '%d bundles are loaded in Kernel, and used in security',
                count( $bundlesUsedInConfig ) ) );
        }
    }

    protected function checkConfig( OutputInterface $output )
    {
        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $output->writeln( '----- Check Config -----' );
        }

        $configDirectories = array( $this->getContainer()->get( 'kernel' )->getRootDir() . '/config' );

        $locator            = new FileLocator( $configDirectories );
        $yamlEnvConfigFile  = $locator->locate( sprintf('config_%s.yml', $this->getContainer()->get('kernel')->getEnvironment()), null, false );
        $yamlConfigFile     = $locator->locate( 'config.yml', null, false );

        $configEnvValues     = Yaml::parse( file_get_contents( $yamlEnvConfigFile[0] ) );
        $configValues        = Yaml::parse( file_get_contents( $yamlConfigFile[0] ) );

        $configValues = array_merge($configValues, $configEnvValues);
        $bundlesUsedInConfig = array();

        foreach ($this->loadedBundles as $bundleKey => $bundle) {
            $configClassName = sprintf( '%s\DependencyInjection\Configuration', $bundle->getNamespace() );

            if (false === class_exists( $configClassName )) {
                $configClassName = sprintf( '%s\DependencyInjection\MainConfiguration', $bundle->getNamespace() );
                $config          = new $configClassName( array(), array() );
            } else {
                if ($bundle->getName() === 'AsseticBundle') {
                    $config = new $configClassName( array() );
                } else {
                    $config = new $configClassName( false );
                }
            }

            /** @var TreeBuilder $tree */
            $tree           = $config->getConfigTreeBuilder();
            $configNodeName = $tree->buildTree()->getName();

            if (isset( $configValues[$configNodeName] )) {
                $bundlesUsedInConfig[] = $bundle;
                unset( $this->bundles[$bundleKey] );
            }
        }

        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $output->writeln( sprintf( '%d bundles are loaded in Kernel, and used in config',
                count( $bundlesUsedInConfig ) ) );
        }
    }

    protected function checkRouting( OutputInterface $output )
    {
        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $output->writeln( '----- Check Routing -----' );
        }

        $bundlesUsedInRouting = array();
        $router               = $this->getContainer()->get( 'router' );

        foreach ($this->loadedBundles as $bundleKey => $bundle) {

            /** @var Route $route */
            foreach ($router->getRouteCollection()->all() as $route) {
                $defaults = $route->getDefaults();
                if (isset( $defaults['_controller'] ) && preg_match( sprintf( '#%s#',
                        addslashes( $bundle->getNamespace() ) ), $defaults['_controller'] )
                ) {
                    $bundlesUsedInRouting[] = $bundle;
                    unset( $this->bundles[$bundleKey] );
                    continue 2;
                }
            }
        }

        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $output->writeln( sprintf( '%d bundles loaded in Kernel are used in routing',
                count( $bundlesUsedInRouting ) ) );
        }
    }

    /**
     * @param OutputInterface $output
     */
    protected function checkCode( OutputInterface $output )
    {
        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $output->writeln( '----- Check Code -----' );
        }

        $bundlesUsedInCode = array();
        foreach ($this->loadedBundles as $bundleKey => $bundle) {
            $grepUsage = exec( sprintf( 'grep -R "%s" %s', addslashes( addslashes( $bundle->getNamespace() ) ),
                $this->getContainer()->get( 'kernel' )->getRootDir() . '/../src' ) );

            if (strlen( $grepUsage ) > 0) {
                $bundlesUsedInCode[] = $bundle;
                unset( $this->bundles[$bundleKey] );
            }
        }

        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $output->writeln( sprintf( '%d bundles are loaded in Kernel, and used in code',
                count( $bundlesUsedInCode ) ) );
        }
    }

    /**
     * @param OutputInterface $output
     */
    protected function checkTwigExtension( OutputInterface $output )
    {
        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $output->writeln( '----- Check Twig Extension -----' );
        }
        $bundlesUsedInTwigExtension = array();

        foreach ($this->loadedBundles as $bundleKey => $bundle) {

            $grepTwig =  exec(sprintf( 'grep -R Twig_Extension %s | grep php | grep -v FindUnusedBundlesCommand', $bundle->getPath() ));

            if (strlen($grepTwig) == 0) {
                continue;
            }

            if (OutputInterface::VERBOSITY_VERY_VERBOSE <= $output->getVerbosity()) {
                $output->writeln( 'GrepTwig: ' . $grepTwig );
            }

            $twigExtension = explode( ' ', $grepTwig );

            $twigExtension = explode( ':', $twigExtension[0] );
            $extensionPath = $twigExtension[0];

            $namespace     = exec( sprintf( 'grep namespace %s', $extensionPath ) );

            $twigExtension = explode( '.', $twigExtension[0] );
            $twigExtension = explode( '/', $twigExtension[0] );

            $extensionClassName = array_pop( $twigExtension );
            $namespace          = explode( ' ', $namespace );

            if (OutputInterface::VERBOSITY_VERY_VERBOSE <= $output->getVerbosity()) {
                $output->writeln( 'Namespace: ' . $namespace[1] );
            }

            $namespace          = explode( ';', $namespace[1] );
            $className          = $namespace[0] . '\\' . $extensionClassName;

            if (OutputInterface::VERBOSITY_VERY_VERBOSE <= $output->getVerbosity()) {
                $output->writeln( 'Classname: ' . $className );
            }

            $extension = unserialize(
                sprintf(
                    'O:%d:"%s":0:{}',
                    strlen( $className ), $className
                )
            );

            $extensionUsage = exec( sprintf( 'grep -R %s %s', $extension->getName(),
                $this->getContainer()->get( 'kernel' )->getRootDir() . '/../src' ) );

            if (strlen( $extensionUsage ) > 0) {
                $bundlesUsedInTwigExtension[] = $bundle;
                unset( $this->bundles[$bundleKey] );
                unset($extension);
            }
        }

        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $output->writeln( sprintf( '%d bundles are loaded in Kernel, and used in twig extension',
                count( $bundlesUsedInTwigExtension ) ) );
        }

    }

    /**
     * @param OutputInterface $output
     *
     * @return mixed
     */
    protected function checkComposer( OutputInterface $output )
    {
        $rootDir = $this->getContainer()->get('kernel')->getRootDir() . '/../';
        $finder = new Finder();
        $finder->files()->in($rootDir);
        $finder->depth('== 0');
        $finder->name('composer.lock');

        if ($finder->count() == 0) {
            $formatter = $this->getHelperSet()->get('formatter');
            $errorMessages = array('', 'No composer.lock found', '');
            $formattedBlock = $formatter->formatBlock($errorMessages, 'error');
            $output->writeln($formattedBlock);
            exit(1);
        }

        foreach ($finder as $file) {
            $composerLockContent = json_decode($file->getContents(), true);
        }

        $finder = new Finder();
        $finder->files()->in($rootDir);
        $finder->depth('== 0');
        $finder->name('composer.json');
        foreach ($finder as $file) {
            $composerContent = json_decode($file->getContents(), true);
        }

        unset($composerContent['require']['php']);

        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $output->writeln( sprintf( '%d packages declared in composer.json',
                count( $composerContent['require'] ) ) );

            $output->writeln( 'Find packages in code' );
        }

        $this->packages = $composerContent['require'];

        foreach ($this->packages as $packageName => $packageVersion) {
            $grep = null;
            foreach ($composerLockContent['packages'] as $packageKey => $package) {
                if ($package['name'] == $packageName) {

                    if (isset( $package['autoload']['psr-0'] )) {
                        foreach ($package['autoload']['psr-0'] as $key => $value) {
                            $grep = exec(sprintf("grep -R '%s' %s", addslashes($key), $this->getContainer()->get( 'kernel' )->getRootDir() . '/../src'));
                        }
                    } elseif (isset( $package['autoload']['psr-1'] )) {
                        foreach ($package['autoload']['psr-1'] as $key => $value) {
                            $grep = exec(sprintf("grep -R '%s' %s", addslashes($key), $this->getContainer()->get( 'kernel' )->getRootDir() . '/../src'));
                        }
                    } elseif (isset( $package['autoload']['psr-4'] )) {
                        foreach ($package['autoload']['psr-4'] as $key => $value) {
                            $grep = exec(sprintf("grep -R '%s' %s", addslashes($key), $this->getContainer()->get( 'kernel' )->getRootDir() . '/../src'));
                        }
                    }
                }

                if (strlen($grep) > 0) {
                    unset($this->packages[$packageName]);
                }
            }
        }

        foreach ($this->packages as $packageName => $packageVersion) {
            $grep = null;
            foreach ($composerLockContent['packages'] as $packageKey => $package) {
                if ($package['name'] == $packageName) {
                    if (isset( $package['autoload']['psr-0'] )) {
                        foreach ($package['autoload']['psr-0'] as $key => $value) {

                            foreach ($this->loadedBundles as $bundle) {
                                if (preg_match(sprintf('#%s#', str_replace('\\', '', $bundle->getNamespace())), str_replace('\\', '', $key))) {
                                    unset($this->packages[$packageName]);
                                }
                            }
                        }
                    } elseif (isset( $package['autoload']['psr-1'] )) {
                        foreach ($package['autoload']['psr-1'] as $key => $value) {

                            foreach ($this->loadedBundles as $bundle) {
                                if (preg_match(sprintf('#%s#', str_replace('\\', '', $bundle->getNamespace())), str_replace('\\', '', $key))) {
                                    unset($this->packages[$packageName]);
                                }
                            }
                        }
                    } elseif (isset( $package['autoload']['psr-4'] )) {
                        foreach ($package['autoload']['psr-4'] as $key => $value) {

                            foreach ($this->loadedBundles as $bundle) {
                                if (preg_match(sprintf('#%s#', str_replace('\\', '', $bundle->getNamespace())), str_replace('\\', '', $key))) {
                                    unset($this->packages[$packageName]);
                                }
                            }
                        }
                    }
                }
            }
        }

        foreach ($this->packages as $packageName => $packageVersion) {

            foreach ($composerLockContent['packages'] as $packageKey => $package) {
                if ($package['name'] == $packageName) {

                    if (isset( $package['autoload']['psr-0'] )) {
                        foreach ($package['autoload']['psr-0'] as $key => $value) {

                            $cleanedKey = str_replace( '\\', '', $key );
                            foreach ($composerContent['scripts']['post-install-cmd'] as $script) {
                                $cleanedScript = str_replace( '\\', '', $script );
                                if (preg_match( sprintf( '#%s#', $cleanedKey ), $cleanedScript )) {
                                    unset( $this->packages[$packageName] );
                                }
                            }
                        }
                    } elseif (isset( $package['autoload']['psr-1'] )) {
                        foreach ($package['autoload']['psr-1'] as $key => $value) {

                            $cleanedKey = str_replace( '\\', '', $key );
                            foreach ($composerContent['scripts']['post-install-cmd'] as $script) {
                                $cleanedScript = str_replace( '\\', '', $script );
                                if (preg_match( sprintf( '#%s#', $cleanedKey ), $cleanedScript )) {
                                    unset( $this->packages[$packageName] );
                                }
                            }
                        }
                    } elseif (isset( $package['autoload']['psr-4'] )) {
                        foreach ($package['autoload']['psr-4'] as $key => $value) {

                            $cleanedKey = str_replace( '\\', '', $key );
                            foreach ($composerContent['scripts']['post-install-cmd'] as $script) {
                                $cleanedScript = str_replace( '\\', '', $script );
                                if (preg_match( sprintf( '#%s#', $cleanedKey ), $cleanedScript )) {
                                    unset( $this->packages[$packageName] );
                                }
                            }
                        }
                    }
                }
            }

        }
    }

    /**
     * @param OutputInterface $output
     */
    protected function outputResult( OutputInterface $output )
    {
        $formatter = $this->getHelperSet()->get('formatter');

        $unusedBundles  = count( $this->bundles );
        $unusedPackages = count( $this->packages );

        if ($unusedBundles == 0 && $unusedPackages == 0) {
            $messages       = array(
                '',
                'Congrats! No bundles loaded in Kernel nor packages declared in composer.json seems unused.',
                ''
            );
            $formattedBlock = $formatter->formatBlock( $messages, 'info' );
            $output->writeln( $formattedBlock );
        } else {
            if ($unusedBundles > 0) {
                $messages = array( '', sprintf( '%d bundle(s) loaded in kernel seems unused', $unusedBundles ), '' );

                foreach ($this->bundles as $bundle) {
                    $messages[] = '- ' . $bundle->getName();
                }

                $messages[]     = '';
                $formattedBlock = $formatter->formatBlock( $messages, 'error' );
                $output->writeln( $formattedBlock );
            }

            if ($unusedPackages > 0) {
                $messages = array(
                    '',
                    sprintf( '%d package(s) declared in composer.json seems unused', $unusedPackages ),
                    ''
                );

                foreach ($this->packages as $packageName => $packageValue) {
                    $messages[] = '- ' . $packageName;
                }

                $messages[]     = '';
                $formattedBlock = $formatter->formatBlock( $messages, 'error' );
                $output->writeln( $formattedBlock );
            }
        }
    }
}