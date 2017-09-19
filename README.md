This repository contains a wrapper written in PHP to access the public [ORCID API](https://members.orcid.org/api) in [JSKOS format](https://gbv.github.io/jskos/) via [Entity Lookup Microservice API (ELMA)](http://gbv.github.io/elma/).

# Background

The [Open Researcher and Contributor ID (ORCID)](https://orcid.org/) is a code to uniquely identify scientific and other academic authors and contributors. ORCID identifiers are a subset of the International Standard Name Identifier (ISNI) consisting of 16 digits in four groups. The final character may also be an `X`. The identifier is prefixed by `http://orcid.org/` to get an URI. For example:

    0000-0002-2997-7611
    http://orcid.org/0000-0002-2997-7611

ORCID organization provides [a public API](https://members.orcid.org/api) to access and search for ORCID profiles. Access to the API requires credentials in form of a "client id" and a "client secret" as decribed at <http://support.orcid.org/knowledgebase/articles/343182>.

# Requirements

Requires the [jskos-php](https://packagist.org/packages/gbv/jskos-php) PHP library.

You also need client credentials from your ORCID profile to access the ORIC API.

# Installation

~~~bash
composer require gbv/jskos-bartoc
~~~

This will automatically create `composer.json` for your project (unless it already exists) and add orcid-jskos as dependency. Composer also generates `vendor/autoload.php` to get autoloading of all dependencies.

# Usage

## Use as library

The wrapper can be used as instance of class `ORCIDService`, a subclass of `\JSKOS\Service`:

~~~php
require 'vendor/autoload.php';

$service = new ORCIDService($client_id, $client_secret);
~~~

See [jskos-php-examples](https://github.com/gbv/jskos-php-examples/) for an example how to use the wrapper as part of a larger PHP application.

## Local webservice for testing

To use as server you also need an implementation of `Http\Message\ResponseFactory`, e.g. 

    $ composer require php-http/guzzle6-adapter

Locally run the application on port 8080 as following:

    $ composer install
    $ ORCID_CLIENT_ID=... ORCID_CLIENT_SECRET=... php -S localhost:8080

You can also put credentials into `credentials.php` as described above.

Given valid credentials, ORCID profiles can be accessed in JSKOS like this:

* <http://localhost:8080/?notation=0000-0002-2997-7611>
* <http://localhost:8080/?uri=http://orcid.org/0000-0002-2997-7611>
* <http://localhost:8080/?search=Dawn%20Wright>

## Webservice via Apache webserver

3. Run `composer install` to download dependencies into directory `vendor`

4. Add a file `credentials.php` with client credentials as following:

    ~~~php
    <?php
    define('ORCID_CLIENT_ID', '...');
    define('ORCID_CLIENT_SECRET', '...');
    ~~~

You may add a rule to disallow direct access to all except `index.php`:

    Require all denied
    <Files index.php>
        Require all granted
    </Files>

## Webservice at Heroku

3. [Create an app](https://devcenter.heroku.com/articles/creating-apps)
4. [Configure the app](https://devcenter.heroku.com/articles/config-vars)
5. [Deploy the app](https://devcenter.heroku.com/articles/git)

In short:

    $ heroku create
    $ heroku config:set ORCID_CLIENT_ID=...
    $ heroku config:set ORCID_CLIENT_SECRET=...
    $ git push heroku master

# Contributung

Bugs and feature request are [tracked on GitHub](https://github.com/gbv/orcid-jskos/issues).

See `CONTRIBUTING.md` of repository [jskos-php](https://packagist.org/packages/gbv/jskos) for general guidelines.

# Author and License

Jakob Voß <jakob.voss@gbv.de>

orcid-jskos is licensed under the LGPL license (see `LICENSE` for details).

