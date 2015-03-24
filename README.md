Find unused bundles or packages
=================================

[![Build Status](https://travis-ci.org/chtipepere/FindUnusedBundles.svg?branch=master)](https://travis-ci.org/chtipepere/FindUnusedBundles)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/chtipepere/FindUnusedBundles/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/chtipepere/FindUnusedBundles/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/chtipepere/FindUnusedBundles/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/chtipepere/FindUnusedBundles/?branch=master)

Repository address: https://github.com/chtipepere/FindUnusedBundles

----------
INSTALLATION
------------
Add the bundle to your composer.json
```
"doh/find-unused-bundles-bundle": "dev-master"
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
