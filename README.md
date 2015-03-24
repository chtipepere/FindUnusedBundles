Find unused bundles or packages
=================================

[![Build Status](https://travis-ci.org/chtipepere/FindUnusedBundles.svg?branch=master)](https://travis-ci.org/chtipepere/FindUnusedBundles)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/chtipepere/FindUnusedBundles/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/chtipepere/FindUnusedBundles/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/chtipepere/FindUnusedBundles/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/chtipepere/FindUnusedBundles/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/doh/find-unused-bundles-bundle/v/stable.svg)](https://packagist.org/packages/doh/find-unused-bundles-bundle)
[![Total Downloads](https://poser.pugx.org/doh/find-unused-bundles-bundle/downloads.svg)](https://packagist.org/packages/doh/find-unused-bundles-bundle)
[![Latest Unstable Version](https://poser.pugx.org/doh/find-unused-bundles-bundle/v/unstable.svg)](https://packagist.org/packages/doh/find-unused-bundles-bundle)
[![License](https://poser.pugx.org/doh/find-unused-bundles-bundle/license.svg)](https://packagist.org/packages/doh/find-unused-bundles-bundle)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/1ac42dd7-7d59-41c9-8f0b-653353ddfd59/mini.png)](https://insight.sensiolabs.com/projects/1ac42dd7-7d59-41c9-8f0b-653353ddfd59)

Repository address: https://github.com/chtipepere/FindUnusedBundles

----------
INSTALLATION
------------
Add the bundle to your composer.json
```
"require-dev": {
    ...,
    "doh/find-unused-bundles-bundle": "dev-master"
}
```
Add the bundle to your AppKernel
```
if (in_array($this->getEnvironment(), ['dev', 'test'])) {
    $bundles[] = new Doh\FindUnusedBundlesBundle\DohFindUnusedBundlesBundle();
}
```
----------
Usage
------------
Scan your app
```
php app/console doh:unusedblundles:find
php app/console d:u:f
```

----------
Known bugs
-----
* n/a

----------
Todo
-----
* Remove and/or delete unused bundles/packages.
* Add tests
