<?php

declare(strict_types=1);

namespace Th0masso\SsaccBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

#[AsCommand('security:check-access-control', 'Check access control for each route.')]
class AccessControlCheckerCommand extends Command
{
    public const PROJECT_PATH_DEFAULT = './';
    public const CONTROLLERS_PATH_DEFAULT = 'src/';
    public const EXCLUDE_ALL_ROUTES_THAT_START_WITH_DEFAULT = [
        'web_profiler',
        'twig',
    ];
    public const EXCLUDE_FULL_ROUTES_DEFAULT = [
        'error_controller::preview',
    ];
    public const SECURITY_REQUIREMENT_DEFAULT = [
        '$this->denyAccessUnlessGranted',
        '!$this->isGranted',
    ];

    /**
     * @param string[] $securityRequirement
     * @param string[] $routesToCheck
     *
     * @return array{noSecurityCounter: int, checkedCounter: int}
     */
    public function checkSecurityForRoutes(
        SymfonyStyle $io,
        OutputInterface $output,
        string $controllersPath,
        array $routesToCheck,
        array $securityRequirement
    ): array {
        $noSecurityCounter = 0;
        $checkedCounter = 0;
        foreach ($io->progressIterate($routesToCheck) as $route) {
            $parts = explode('::', $route);
            if (2 !== \count($parts)) {
                $output->write("'$route' is not a valid route with function format\n");
            }

            $file_path = str_replace('App\\', $controllersPath, $parts[0]);
            $file_path = str_replace('\\', '/', $file_path).'.php';
            $function = $parts[1];

            if (!file_exists($file_path)) {
                $output->write("\n");
                $io->warning("The route '$route' have not been checked.\nBecause the file '$file_path' does not exist.");
                continue;
            }

            $fileContent = file_get_contents($file_path);

            if (false === $fileContent) {
                $output->write("\n");
                $io->warning("The route '$route' have not been checked.\nBecause the file '$file_path' could not be read.");
                continue;
            }

            $functionsToSearch = implode('|', $securityRequirement);
            $functionsToSearch = str_replace('$', '\$', $functionsToSearch);

            // start of regular expression
            $regex = '(';
            // find "public function myFunction"
            $regex .= "(public function $function)";
            // then everything until "{"
            $regex .= '(\([^{]*\{)';
            // then everything on next line
            $regex .= '(\s.*)';
            // until "$this->denyAccessUnlessGranted" or "!$this->isGranted" is found
            $regex .= "($functionsToSearch)";
            // end of regular expression
            $regex .= ')';

            if (!preg_match($regex, $fileContent)) {
                $io->error("No security check for function '$route'");
                ++$noSecurityCounter;
            }
            ++$checkedCounter;
        }

        return [
            'noSecurityCounter' => $noSecurityCounter,
            'checkedCounter' => $checkedCounter,
        ];
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Check if all routes have a security check')
            ->addArgument('configFile', InputArgument::OPTIONAL, 'Relative path to the config file', 'ssacc-config.yaml');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->showTitle($output);

        $configFile = $input->getArgument('configFile');
        if (!\is_string($configFile) || !file_exists($configFile)) {
            $configFileMessage = \is_string($configFile) ? "'$configFile' " : '';
            $io->error('The config file '.$configFileMessage.'does not exist.');

            return Command::FAILURE;
        }

        $config = Yaml::parseFile($configFile);
        if (!\is_array($config) || !\array_key_exists('ssacc-config', $config)) {
            $io->error("The config file '$configFile' does not contain the key 'ssacc-config'.");

            return Command::FAILURE;
        }
        $projectConfig = $config['ssacc-config'];
        $projectPath = $projectConfig['project_path'] ?? self::PROJECT_PATH_DEFAULT;
        $controllersPath = $projectConfig['controllers_path'] ?? self::CONTROLLERS_PATH_DEFAULT;
        $excludeAllRoutesThatStartWith = $projectConfig['exclude_all_routes_that_start_with'] ?? self::EXCLUDE_ALL_ROUTES_THAT_START_WITH_DEFAULT;
        $excludeFullRoutes = $projectConfig['exclude_full_routes'] ?? self::EXCLUDE_FULL_ROUTES_DEFAULT;
        $securityRequirement = $projectConfig['security_requirement'] ?? self::SECURITY_REQUIREMENT_DEFAULT;

        if (!$this->validateCommandArguments($io, $projectPath, $controllersPath, $excludeAllRoutesThatStartWith, $excludeFullRoutes, $securityRequirement)) {
            return Command::FAILURE;
        }

        $routesToCheck = $this->getRoutesToCheck($io, $output, $projectPath, $excludeAllRoutesThatStartWith, $excludeFullRoutes);
        $output->write(\count($routesToCheck)." routes will be checked.\n\n");
        $output->write("Checking routes ...\n");

        [
            'noSecurityCounter' => $noSecurityCounter,
            'checkedCounter' => $checkedCounter,
        ] = $this->checkSecurityForRoutes($io, $output, $controllersPath, $routesToCheck, $securityRequirement);

        $output->write("Finished, $checkedCounter routes have been checked.\n\n");
        $message = "$noSecurityCounter routes without security check have been found.\n";
        if ($noSecurityCounter > 0) {
            $io->error($message);

            return Command::FAILURE;
        }
        $io->success($message);

        return Command::SUCCESS;
    }

    private function showTitle(OutputInterface $output): void
    {
        $output->writeln([
            '<fg=black;bg=white;>',
            '      ____     ____     ___      _____    _____  ',
            '     / __/    / __/    / _ |    / ___/   / ___/  ',
            '    _\ \     _\ \     / __ |   / /__    / /__    ',
            '   /___/    /___/    /_/ |_|   \___/    \___/    ',
            '                                                 ',
            '  Symfony  Security  Access   Control  Checker   ',
            '                                                 ',
            '</>',
        ]);
    }

    /**
     * @param string[] $excludeAllRoutesThatStartWith
     * @param string[] $excludeFullRoutes
     * @param string[] $securityRequirement
     */
    private function validateCommandArguments(
        SymfonyStyle $io,
        string $projectPath,
        string $controllersPath,
        array $excludeAllRoutesThatStartWith,
        array $excludeFullRoutes,
        array $securityRequirement
    ): bool {
        if (!file_exists($projectPath)) {
            $io->error("The project path '$projectPath' does not exist.");

            return false;
        }
        if (!file_exists($projectPath.$controllersPath)) {
            $io->error("The controllers path '$projectPath$controllersPath' does not exist.");

            return false;
        }
        if (!\is_array($excludeAllRoutesThatStartWith)) {
            $io->error('The exclude_all_routes_that_start_with config is not an array.');

            return false;
        }
        if (!\is_array($excludeFullRoutes)) {
            $io->error('The exclude_full_routes config is not an array.');

            return false;
        }
        if (!\is_array($securityRequirement)) {
            $io->error('The security_requirement config is not an array.');

            return false;
        }

        return true;
    }

    /**
     * @param string[] $excludeAllRoutesThatStartWith
     * @param string[] $excludeFullRoutes
     *
     * @return string[]
     */
    private function getRoutesToCheck(StyleInterface $io, OutputInterface $output, string $projectPath, array $excludeAllRoutesThatStartWith, array $excludeFullRoutes): array
    {
        $output->write("Generating list of routes ...\n");

        $rawJsonRoutes = shell_exec('cd '.$projectPath.' && php bin/console debug:router --show-controllers --format=json');

        if (!\is_string($rawJsonRoutes)) {
            $output->write("\n");
            throw new \RuntimeException("The command 'php bin/console debug:router --show-controllers --format=json' failed.");
        }

        /** @var array{defaults: array{_controller?: string}}[] */
        $jsonRoutes = json_decode($rawJsonRoutes, true);

        $output->write(\count($jsonRoutes)." total routes found on the project.\n\n");

        $output->write("Filtering and formatting routes ...\n");
        // Get all valid routes
        $routes = [];
        foreach ($jsonRoutes as $route) {
            if (isset($route['defaults']['_controller'])) {
                $route = $route['defaults']['_controller'];

                if (!\in_array($route, $routes, true) && str_contains($route, '::') && !$this->isRouteExcluded($route, $excludeAllRoutesThatStartWith, $excludeFullRoutes)) {
                    $routes[] = $route;
                }
            }
        }

        return $routes;
    }

    /**
     * @param string[] $excludeAllRoutesThatStartWith
     * @param string[] $excludeFullRoutes
     */
    private function isRouteExcluded(string $route, array $excludeAllRoutesThatStartWith, array $excludeFullRoutes): bool
    {
        if (\in_array($route, $excludeFullRoutes, true)) {
            return true;
        }
        foreach ($excludeAllRoutesThatStartWith as $exclude) {
            if (0 === strncmp($route, $exclude, mb_strlen($exclude))) {
                return true;
            }
        }

        return false;
    }
}
