<p align="center">
    <a href="https://croct.com">
        <img src="https://cdn.croct.io/brand/logo/repo-icon-green.svg" alt="Croct" height="80"/>
    </a>
    <br />
    <strong>PHP Project Title</strong>
    <br />
    A brief description about the project.
</p>
<p align="center">
    <img alt="Language" src="https://img.shields.io/badge/language-PHP-blue" />
    <img alt="Build" src="https://img.shields.io/badge/build-passing-green" />
    <img alt="License" src="https://img.shields.io/badge/license-proprietary-lightgrey" />
    <br />
    <br />
    <a href="https://github.com/croct-tech/repository-template-php/releases">📦 Releases</a>
    ·
    <a href="https://github.com/croct-tech/repository-template-php/issues">🐞 Report Bug</a>
    ·
    <a href="https://github.com/croct-tech/repository-template-php/issues">✨ Request Feature</a>
</p>

# Instructions

Follow the steps below to create a new repository:

1. Customize the repository
   1. Click on the _Use this template_ button at the top of this page
   2. Clone the repository locally 
   3. Update the `README.md` and `composer.json` with the new package information 
2. Setup Code Climate
   1. Add the project to [Croct's code climate organization](https://codeclimate.com/accounts/5e714648faaa9c00fb000081/dashboard)
   2. Go to **Repo Settings > Badges** and copy the maintainability and coverage badges to the `README.md` 
   3. Go to **Repo Settings > Test coverage** and copy the "_TEST REPORTER ID_"
   4. On the Github repository page, go to **Settings > Secrets** and add a secret with name `CODECLIMATE_TESTREPORTER_ID` and the ID from the previous step as value
3. Setup Repman
   1. If you are a Repman admin, you need to generate a token for each member. Go to [**Organizations > Croct > Tokens > New Token**](https://app.repman.io/organization/croct/token/new) and click on Generate New Token button.
   2. If you are a member, you need to configure global authentication to access this organization's packages. With the token in hand, you can authorize Composer with the following command (replace `TOKEN_VALUE` with the actual token):

        ```sh
        composer config --global --auth http-basic.croct.repo.repman.io token TOKEN_VALUE
        ```

## Installation

We recommend using the package manager [Composer](https://getcomposer.org) to install the package:

```sh
composer require croct/project-php
```

## Basic usage

```php
use Croct\Project\Example;

$example = new Example();
$example->displayBasicUsage();
```

## Contributing

Contributions to the package are always welcome! 

- Report any bugs or issues on the [issue tracker](https://github.com/croct-tech/project-php/issues).
- For major changes, please [open an issue](https://github.com/croct-tech/project-php/issues) first to discuss what you would like to change.
- Please make sure to update tests as appropriate.

## Testing

Before running the test suites, the development dependencies must be installed:

```sh
composer install
```

Then, to run all tests:

```sh
composer test
```

## Copyright Notice

Copyright © 2015-2020 Croct Limited, All Rights Reserved.

All information contained herein is, and remains the property of Croct Limited. The intellectual, design and technical concepts contained herein are proprietary to Croct Limited s and may be covered by U.S. and Foreign Patents, patents in process, and are protected by trade secret or copyright law. Dissemination of this information or reproduction of this material is strictly forbidden unless prior written permission is obtained from Croct Limited.
