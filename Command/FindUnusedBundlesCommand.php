<?php

namespace Doh\FindUnusedBundlesBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\Routing\Route;
use Symfony\Component\Yaml\Yaml;

/**
 * Class FindUnusedBundlesCommand
 *
 * @package Acme\DemoBundle\Command
 */
class FindUnusedBundlesCommand extends ContainerAwareCommand
{

    protected $bundles          = array();
    protected $loadedBundles    = array();
    protected $packages         = array();

    const DOH_FIND_UNUSED_BUNDLES_BUNDLE_NAME = 'DohFindUnusedBundlesBundle';

    protected function configure()
    {
        $this
            ->setName('doh:unusedblundles:find')
            ->setDescription('Find unused bundles in your application');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $kernel = $this->getKernel();
        $this->setBundles($kernel->getBundles());
        $this->setLoadedBundles($kernel->getBundles());

        $this->removeItSelf();

        $this->checkComposer($output);

        $env = $kernel->getEnvironment();
        $this->checkYamlFile('config.yml', $output);
        $this->checkYamlFile(sprintf('config_%s.yml', $env), $output);

        $this->checkYamlFile('security.yml', $output);
        $this->checkYamlFile(sprintf('security_%s.yml', $env), $output);

        $this->checkRouting($output);

        $this->checkCode($output);

        $this->checkTwigExtension($output);

        $this->outputResult($output);
    }

    /**
     * @param string $file
     */
    protected function checkYamlFile($file, OutputInterface $output)
    {
        $configDirectories = array($this->getKernel()->getRootDir() . '/config');

        $locator = new FileLocator($configDirectories);
        try {
            $yamlFile = $locator->locate($file, null, false);
        } catch (\InvalidArgumentException $exc) {
            return;
        }

        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $output->writeln(sprintf('----- Check %s -----', $file));
        }

        $configValues        = Yaml::parse(file_get_contents($yamlFile[0]));
        $bundlesUsedInConfig = array();

        /** @var Bundle $bundle */
        foreach ($this->loadedBundles as $bundleKey => $bundle) {
            $configClassName = sprintf('%s\DependencyInjection\Configuration', $bundle->getNamespace());

            if (false === class_exists($configClassName)) {
                $configClassName = sprintf('%s\DependencyInjection\MainConfiguration', $bundle->getNamespace());
                if (false === class_exists($configClassName)) {
                    continue;
                }
                $config = new $configClassName(array(), array());
            } else {
                if ($bundle->getName() === 'AsseticBundle') {
                    $config = new $configClassName(array());
                } else {
                    $config = new $configClassName(false);
                }
            }

            /** @var TreeBuilder $tree */
            $tree           = $config->getConfigTreeBuilder();
            $configNodeName = $tree->buildTree()->getName();

            if (isset($configValues[$configNodeName])) {
                $bundlesUsedInConfig[] = $bundle;
                unset($this->bundles[$bundleKey]);
            }
        }

        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $output->writeln(sprintf('%d bundles are loaded in Kernel, and used in %s',
                count($bundlesUsedInConfig), $file));
        }
    }

    protected function checkRouting(OutputInterface $output)
    {
        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $output->writeln('----- Check Routing -----');
        }

        $bundlesUsedInRouting = array();
        $router               = $this->getContainer()->get('router');

        /** @var Bundle $bundle */
        foreach ($this->loadedBundles as $bundleKey => $bundle) {
            /** @var Route $route */
            foreach ($router->getRouteCollection()->all() as $route) {
                $defaults = $route->getDefaults();
                if (isset($defaults['_controller']) && preg_match(sprintf('#%s#',
                        addslashes($bundle->getNamespace())), $defaults['_controller'])) {
                    $bundlesUsedInRouting[] = $bundle;
                    unset($this->bundles[$bundleKey]);
                    continue 2;
                }
            }
        }

        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $output->writeln(sprintf('%d bundles loaded in Kernel are used in routing',
                count($bundlesUsedInRouting)));
        }
    }

    /**
     * @param OutputInterface $output
     */
    protected function checkCode(OutputInterface $output)
    {
        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $output->writeln('----- Check Code -----');
        }

        $bundlesUsedInCode = array();
        /** @var Bundle $bundle */
        foreach ($this->loadedBundles as $bundleKey => $bundle) {
            $grepUsage = exec(sprintf('grep -R "%s" %s', addslashes(addslashes($bundle->getNamespace())),
                $this->getKernel()->getRootDir() . '/../src'));

            if (strlen($grepUsage) > 0) {
                $bundlesUsedInCode[] = $bundle;
                unset($this->bundles[$bundleKey]);
            }
        }

        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $output->writeln(sprintf('%d bundles are loaded in Kernel, and used in code',
                count($bundlesUsedInCode)));
        }
    }

    /**
     * @param OutputInterface $output
     */
    protected function checkTwigExtension(OutputInterface $output)
    {
        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $output->writeln('----- Check Twig Extension -----');
        }
        $bundlesUsedInTwigExtension = array();

        foreach ($this->loadedBundles as $bundleKey => $bundle) {

            $grepTwig = exec(sprintf('grep -R Twig_Extension %s | grep php | grep -v FindUnusedBundlesCommand', $bundle->getPath()));

            if (strlen($grepTwig) == 0) {
                continue;
            }

            if (OutputInterface::VERBOSITY_VERY_VERBOSE <= $output->getVerbosity()) {
                $output->writeln('GrepTwig: ' . $grepTwig);
            }

            $twigExtension = explode(' ', $grepTwig);

            $twigExtension = explode(':', $twigExtension[0]);
            $extensionPath = $twigExtension[0];

            $namespace     = exec(sprintf('grep namespace %s', $extensionPath));

            $twigExtension = explode('.', $twigExtension[0]);
            $twigExtension = explode('/', $twigExtension[0]);

            $extensionClassName = array_pop($twigExtension);
            $namespace          = explode(' ', $namespace);

            if (OutputInterface::VERBOSITY_VERY_VERBOSE <= $output->getVerbosity()) {
                $output->writeln('Namespace: ' . $namespace[1]);
            }

            $namespace = explode(';', $namespace[1]);
            $className = $namespace[0] . '\\' . $extensionClassName;

            if (OutputInterface::VERBOSITY_VERY_VERBOSE <= $output->getVerbosity()) {
                $output->writeln('Classname: ' . $className);
            }

            $extension = unserialize(
                sprintf(
                    'O:%d:"%s":0:{}',
                    strlen($className), $className
                )
            );

            $extensionUsage = exec(sprintf('grep -R %s %s', $extension->getName(),
                $this->getKernel()->getRootDir() . '/../src'));

            if (strlen($extensionUsage) > 0) {
                $bundlesUsedInTwigExtension[] = $bundle;
                unset($this->bundles[$bundleKey]);
                unset($extension);
            }
        }

        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $output->writeln(sprintf('%d bundles are loaded in Kernel, and used in twig extension',
                count($bundlesUsedInTwigExtension)));
        }

    }

    /**
     * @param OutputInterface $output
     *
     * @return mixed
     */
    protected function checkComposer(OutputInterface $output)
    {
        $composerLockContent    = $this->getFileContent('composer.lock');
        $composerContent        = $this->getFileContent('composer.json');

        unset($composerContent['require']['php']);

        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $output->writeln('----- Check Composer -----');
        }

        $this->packages = $composerContent['require'];

        foreach ($this->packages as $packageName => $packageVersion) {
            foreach ($composerLockContent['packages'] as $packageKey => $package) {

                if ($package['name'] == $packageName) {
                    if (isset($package['autoload']['psr-0'])) {
                        foreach ($package['autoload']['psr-0'] as $key => $value) {

                            $this->findUsage($key, $packageName, $composerContent);
                        }
                    } elseif (isset($package['autoload']['psr-1'])) {
                        foreach ($package['autoload']['psr-1'] as $key => $value) {
                            $this->findUsage($key, $packageName, $composerContent);
                        }
                    } elseif (isset($package['autoload']['psr-4'])) {
                        foreach ($package['autoload']['psr-4'] as $key => $value) {
                            $this->findUsage($key, $packageName, $composerContent);
                        }
                    }
                }
            }
        }

        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $output->writeln(sprintf('%d/%d packages are used in code',
                count($composerContent['require']) - count($this->packages), count($composerContent['require'])));
        }

    }

    /**
     * @param OutputInterface $output
     */
    protected function outputResult(OutputInterface $output)
    {
        $formatter = $this->getHelperSet()->get('formatter');

        $unusedBundles  = count($this->bundles);
        $unusedPackages = count($this->packages);

        if ($unusedBundles == 0 && $unusedPackages == 0) {
            $messages = array(
                '',
                'Congrats! No bundles loaded in Kernel nor packages declared in composer.json seems unused.',
                ''
            );
            $formattedBlock = $formatter->formatBlock($messages, 'info');
            $output->writeln($formattedBlock);
        } else {
            if ($unusedBundles > 0) {
                $messages = array('', sprintf('%d bundle(s) loaded in kernel seems unused', $unusedBundles), '');

                foreach ($this->bundles as $bundle) {
                    $messages[] = '- ' . $bundle->getName();
                }

                $messages[]     = '';
                $formattedBlock = $formatter->formatBlock($messages, 'error');
                $output->writeln($formattedBlock);
            }

            if ($unusedPackages > 0) {
                if ($unusedBundles > 0) {
                    $output->writeln('');
                }

                $messages = array();
                $messages[] = '';
                $messages[] = sprintf('%d package(s) declared in composer.json seems unused', $unusedPackages);
                $messages[] = '';

                foreach ($this->packages as $packageName => $packageValue) {
                    $messages[] = '- ' . $packageName;
                }

                $messages[]     = '';
                $formattedBlock = $formatter->formatBlock($messages, 'error');
                $output->writeln($formattedBlock);
            }
        }
    }

    /**
     * @param $key
     * @param $packageName
     * @param $composerContent
     */
    protected function findUsageUsingAutoload($key, $packageName, $composerContent)
    {
        foreach ($this->loadedBundles as $bundle) {
            if (preg_match(sprintf('#%s#', str_replace('\\', '', $bundle->getNamespace())),
                str_replace('\\', '', $key))) {
                unset($this->packages[$packageName]);
            } else {
                $cleanedKey = str_replace('\\', '', $key);
                foreach ($composerContent['scripts']['post-install-cmd'] as $script) {
                    $cleanedScript = str_replace('\\', '', $script);
                    if (preg_match(sprintf('#%s#', $cleanedKey), $cleanedScript)) {
                        unset($this->packages[$packageName]);
                    }
                }
            }
        }
    }

    /**
     * @param string $filename
     *
     * @return array
     */
    protected function getFileContent($filename)
    {
        $fileContent    = null;
        $rootDir        = $this->getKernel()->getRootDir() . '/../';
        $finder         = new Finder();

        $finder->files()->in($rootDir);
        $finder->depth('== 0');
        $finder->name($filename);

        if ($finder->count() == 0) {
            throw new \RuntimeException(sprintf('No %s found in %s', $filename, $rootDir));
        }

        foreach ($finder as $file) {
            $fileContent = json_decode($file->getContents(), true);
        }

        return $fileContent;
    }

    public function setBundles($bundles)
    {
        $this->bundles = $bundles;
    }

    public function getBundles()
    {
        return $this->bundles;
    }

    public function setPackages($packages)
    {
        $this->packages = $packages;
    }

    public function getPackages()
    {
        return $this->packages;
    }

    public function setLoadedBundles($bundles)
    {
        $this->loadedBundles = $bundles;
    }

    public function removeItSelf()
    {
        foreach ($this->bundles as $key => $bundle) {
            if ($bundle->getName() == self::DOH_FIND_UNUSED_BUNDLES_BUNDLE_NAME) {
                unset($this->bundles[$key]);
            }
        }
    }

    /**
     * @return object
     */
    protected function getKernel()
    {
        return $this->getContainer()->get('kernel');
    }

    /**
     * @param $key
     *
     * @return string
     */
    protected function findUsageInCode($key)
    {
        return $this->exec(sprintf("grep -R '%s' %s", addslashes($key), $this->getKernel()->getRootDir() . '/../src'));
    }

    protected function exec($command)
    {
        return exec($command);
    }

    /**
     * @param $key
     * @param $packageName
     * @param $composerContent
     */
    protected function findUsage($key, $packageName, $composerContent)
    {
        if (strlen($this->findUsageInCode($key)) > 0) {
            unset($this->packages[$packageName]);
        } else {
            $this->findUsageUsingAutoload($key, $packageName, $composerContent);
        }
    }

}
