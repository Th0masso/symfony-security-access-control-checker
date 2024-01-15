# SSACC - Symfony Security Access Control Checker

SSACC is a Symfony bundle that checks the access control for each route of your application.

## Installation

### Require the bundle

```bash
composer require --dev theodo/accent-bundle
```

## Configuration and usage

1. Create a config file in your project directory (e.g. `ssacc-config.yaml`)
2. You can copy the [default config file](./ssacc-config.example.yaml) and adapt it to your needs
3. run the command with the `--configFile` option, followed by the path to your config file
```bash
bin/console security:check-access-control --configFile=ssacc-config.yaml
```
