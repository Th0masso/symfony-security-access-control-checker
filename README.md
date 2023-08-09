# symfony-access-control-script

Allows listing all project routes that do not have permission checks.
For a route to pass the check, the function called in the controller must have the following in its first line:
 `!$this->isGranted` or `$this->denyAccessUnlessGranted`.

## get started
1. Copy the file `check-routes-security.php` into your symfony project
2. Run the script with :
```bash
> php check-routes-security.php
```
(might take 1-5 minutes if you have hundreds of routes)
3. Check which routes have no access control

## add files to ignore

## add directories to ignore
