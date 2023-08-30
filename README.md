# symfony-access-control-script

Allows listing all project routes that do not have permission checks.
For a route to pass the check, the function called in the controller must have the following in its first line:
 `!$this->isGranted` or `$this->denyAccessUnlessGranted`.
 You can learn more about [how to ensure that all the routes on my Symfony app have access control with this article]().

## get started
1. Copy the file `check-routes-security.php` into your symfony project
2. Open the file and change those variables if needed :
   - `PROJECT_PATH`
   - `CONTROLLERS_PATH`
3. Run the script with :
```bash
> php check-routes-security.php
```
(might take 1-5 minutes if you have hundreds of routes)  

4. Check which routes have no access control

### add routes to ignore
To add routes to ignore open `check-routes-security.php`, and change th following arrays :
- EXCLUDE_ALL_ROUTES_THAT_START_WITH : excule all routes within the path
- EXCLUDE_FULL_ROUTES : exclude sepcific route
