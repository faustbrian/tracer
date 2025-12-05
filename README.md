[![GitHub Workflow Status][ico-tests]][link-tests]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

------

A Laravel package for model revision tracking and staged change management with approval workflows. Track every change to your models, revert to previous states, and implement maker-checker patterns with configurable approval strategies.

## Requirements

> **Requires [PHP 8.4+](https://php.net/releases/)** and Laravel 11+

## Installation

```bash
composer require cline/tracer
```

## Documentation

- **[Getting Started](cookbook/getting-started.md)** - Installation, configuration, and first steps
- **[Basic Usage](cookbook/basic-usage.md)** - Revision tracking and querying history
- **[Staged Changes](cookbook/staged-changes.md)** - Queue changes for approval before persisting
- **[Approval Workflows](cookbook/approval-workflows.md)** - Simple, quorum, and custom approval strategies
- **[Strategies](cookbook/strategies.md)** - Customize how changes are calculated and stored
- **[Advanced Usage](cookbook/advanced-usage.md)** - Events, performance, and integration patterns

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please use the [GitHub security reporting form][link-security] rather than the issue queue.

## Credits

- [Brian Faust][link-maintainer]
- [All Contributors][link-contributors]

## License

The MIT License. Please see [License File](LICENSE.md) for more information.

[ico-tests]: https://github.com/faustbrian/tracer/actions/workflows/quality-assurance.yaml/badge.svg
[ico-version]: https://img.shields.io/packagist/v/cline/tracer.svg
[ico-license]: https://img.shields.io/badge/License-MIT-green.svg
[ico-downloads]: https://img.shields.io/packagist/dt/cline/tracer.svg

[link-tests]: https://github.com/faustbrian/tracer/actions
[link-packagist]: https://packagist.org/packages/cline/tracer
[link-downloads]: https://packagist.org/packages/cline/tracer
[link-security]: https://github.com/faustbrian/tracer/security
[link-maintainer]: https://github.com/faustbrian
[link-contributors]: ../../contributors
