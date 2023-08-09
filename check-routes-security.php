<?php
const PROJECT_PATH = 'app/';
const CONTROLLERS_PATH = PROJECT_PATH . 'src/';
const EXCLUDE_ALL_ROUTES_THAT_START_WITH = [
    'web_profiler',
    'twig',
];
const EXCLUDE_FULL_ROUTES = [
    'Controller\MyController::register',
    'Controller\MyOtherController::login',
];

function isRouteExcluded(string $route): bool {
    foreach (EXCLUDE_ALL_ROUTES_THAT_START_WITH as $exclude) {
        if (strncmp($route, $exclude, strlen($exclude)) === 0 || in_array($route, EXCLUDE_FULL_ROUTES, true)) {
            return true;
        }
    }
    return false;
}

echo "Creating list of routes ...\n";
$rawJsonRoutes = shell_exec("cd " . PROJECT_PATH . " && php bin/console debug:router --show-controllers --format=json");
$jsonRoutes = json_decode($rawJsonRoutes, true);

// Get all valid routes
$controllers = [];
foreach ($jsonRoutes as $route) {
    if(isset($route['defaults']['_controller'])) {
        $controller = $route['defaults']['_controller'];

        if (!in_array($controller, $controllers, true) && strpos($controller, '::') !== false && !isRouteExcluded($controller)) {
            $controllers[] = $controller;
        }
    }
}

// Check if all valid routes have a security check
$noSecurityCounter = 0;
$checkedCounter = 0;
foreach ($controllers as $controller) {
    $parts = explode('::', $controller);
    if (count($parts) !== 2) {
        echo "'$controller' is not a valid route with function format\n";
    }

    $route = $parts[0];
    $function = $parts[1];

    $file_name = CONTROLLERS_PATH . str_replace('\\', '/', $route) . '.php';

    if (!file_exists($file_name)) {
        echo "The file '$file_name' does not exist. ($controller).\n";
        continue;
    }

    $fileContent = file_get_contents($file_name);

    // start of regular expression
    $regex = '(';
    // find "public function myFunction"
    $regex .= '(public function '.$function.')';
    // then everything until "{"
    $regex .= '(\([^{]*\{)';
    // then everything on next line
    $regex .= '(\s.*)';
    // until "$this->denyAccessUnlessGranted" ou "!$this->isGranted"
    $regex .= '(\$this->denyAccessUnlessGranted|!\$this->isGranted)';
    // end of regular expression
    $regex .= ')';

    if (!preg_match($regex, $fileContent)) {
        echo "No security check for function '$controller'\n";
        $noSecurityCounter++;
    }
    $checkedCounter++;
}

echo "\n$checkedCounter functions have been checked.\n";
echo "\n$noSecurityCounter functions without security check have been found.\n";
exit(0);
