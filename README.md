# SSACC - Symfony Security Access Control Checker

SSACC is a Symfony bundle that list all your project's routes that do not have permission checks.

The bundle will check if certain functions are called on the first line of the controller's action.

 You can learn more about [how to ensure that all the routes on my Symfony app have access control with this article](https://blog.theodo.com/2023/10/ensure-that-symfony-routes-have-access-control/).

# Installation

```bash
composer require --dev ssacc/ssacc-bundle
```

# Configuration

You should create a config file like this one:
```yaml
ssacc-config:
  project_path: "./"
  controllers_path: "src/"
  exclude_all_routes_that_start_with:
    - "web_profiler"
    - "twig"
  exclude_full_routes:
    - "error_controller::preview"
  security_requirement:
    - "$this->denyAccessUnlessGranted"
    - "!$this->isGranted"
```
Those are the default values, you can change them as you wish.

You can use `ssacc-config.dist.yaml` as a template.

The default config path is `./ssacc-config.yaml`, but you can change it in the next step.

## Description of the options
- `project_path`: The path to the root of your project.
- `controllers_path`: The path to the controllers directory.
- `exclude_all_routes_that_start_with`: An array of strings. All routes that start with any of those strings will be excluded.
- `exclude_full_routes`: An array of strings. All routes that match any of those strings will be excluded.
- `security_requirement`: An array of strings. All routes functions that do not have any of those strings on the first line of the controller's action will be listed.

# Usage

The only (optional) argument is the relative path to the config file you created in the previous step.

If the value is not specified, the default value is `ssacc-config.yaml` (root of your project).

```bash
php bin/console security:check-access-control myConfigDir/my-config-file.yaml
```