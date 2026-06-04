<p align="center">
    <a href="https://croct.com">
        <img src="https://cdn.croct.io/brand/logo/repo-icon-green.svg" alt="Croct" height="80"/>
    </a>
    <br/>
    <strong>Croct Symfony Bundle</strong><br/>
    Plug your Symfony applications into Croct.
</p>
<div align="center">
    <strong>📘 <a href="https://docs.croct.com/reference/sdk/php/installation">Quick start &rarr;</a></strong>
</div>
<br/>
<p align="center">
    <a href="https://packagist.org/packages/croct/plug-symfony"><img alt="Version" src="https://img.shields.io/packagist/v/croct/plug-symfony"/></a>
    <a href="https://packagist.org/packages/croct/plug-symfony"><img alt="PHP version" src="https://img.shields.io/packagist/dependency-v/croct/plug-symfony/php"/></a>
</p>

## Introduction

This bundle plugs [`croct/plug-php`](https://github.com/croct-tech/plug-php) into Symfony with no
glue code: install it, set your credentials, and inject `Croct\Plug\Plug` anywhere.

Out of the box it builds a request-scoped client from the current request, keeps the session cookies
and cache headers safe automatically, detects the visitor's **locale** and authenticated **identity**,
and serves Croct content inside [Storyblok](https://www.storyblok.com) stories transparently — each
feature configurable and switchable. See the [documentation](https://docs.croct.com/reference/sdk/php/installation)
for the full configuration reference.

## Installation

Install the bundle with Composer:

```sh
composer require croct/plug-symfony
```

With [Symfony Flex](https://symfony.com/doc/current/setup/flex.html) the bundle is registered and a
`config/packages/croct.yaml` skeleton is created automatically. Set your credentials in `.env`:

```dotenv
CROCT_APP_ID=<your-application-id>
CROCT_API_KEY=<your-api-key>
```

Then inject `Croct\Plug\Plug` into any controller or service.

## Documentation

Visit our [official documentation](https://docs.croct.com/reference/sdk/php/installation).

## Support

Join our official [Slack channel](https://croct.link/community) to get help from the Croct team and
other developers.

## Contribution

Contributions are always welcome!

- Report any bugs or issues on the [issue tracker](https://github.com/croct-tech/plug-symfony/issues).
- For major changes, please [open an issue](https://github.com/croct-tech/plug-symfony/issues) first
  to discuss what you would like to change.
- Please make sure to update tests as appropriate. Run tests with `composer test`.

## License

This bundle is licensed under the [MIT license](LICENSE).
