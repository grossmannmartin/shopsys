<?php

namespace Shopsys\MigrationBundle\Component\Generator;

use Shopsys\MigrationBundle\Component\Doctrine\Migrations\MigrationsLocation;
use SqlFormatter;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;

class MigrationsGenerator
{
    protected const LINE_LENGTH_LIMIT = 100;
    protected const HIGHLIGHT_OFF = false;
    protected const INDENT_CHARACTERS = '    ';
    protected const INDENT_TABULATOR_COUNT = 3;

    /**
     * @var \Twig\Environment
     */
    protected $twigEnvironment;

    /**
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $filesystem;

    /**
     * @param \Twig\Environment $twigEnvironment
     * @param \Symfony\Component\Filesystem\Filesystem $filesystem
     */
    public function __construct(
        Environment $twigEnvironment,
        Filesystem $filesystem
    ) {
        $this->twigEnvironment = $twigEnvironment;
        $this->filesystem = $filesystem;
    }

    /**
     * @param array $sqlCommands
     * @param \Shopsys\MigrationBundle\Component\Doctrine\Migrations\MigrationsLocation $migrationsLocation
     * @return \Shopsys\MigrationBundle\Component\Generator\GeneratorResult
     */
    public function generate(array $sqlCommands, MigrationsLocation $migrationsLocation)
    {
        $this->createMigrationLocationDirectoryIfNotExists($migrationsLocation);
        $formattedSqlCommands = $this->formatSqlCommandsIfLengthOverflow($sqlCommands);
        $escapedFormattedSqlCommands = $this->escapeSqlCommands($formattedSqlCommands);
        $migrationClassName = 'Version' . date('YmdHis');
        $migrationFileRawData = $this->twigEnvironment->render('@ShopsysMigration/Migration/migration.php.twig', [
            'sqlCommands' => $escapedFormattedSqlCommands,
            'migrationClassName' => $migrationClassName,
            'namespace' => $migrationsLocation->getNamespace(),
        ]);

        $migrationFilePath = $migrationsLocation->getDirectory() . '/' . $migrationClassName . '.php';
        $writtenBytes = file_put_contents($migrationFilePath, $migrationFileRawData);

        return new GeneratorResult($migrationFilePath, $writtenBytes);
    }

    /**
     * @param string[] $filteredSchemaDiffSqlCommands
     * @return string[]
     */
    protected function formatSqlCommandsIfLengthOverflow(array $filteredSchemaDiffSqlCommands)
    {
        $formattedSqlCommands = [];

        foreach ($filteredSchemaDiffSqlCommands as $filteredSchemaDiffSqlCommand) {
            if (strlen($filteredSchemaDiffSqlCommand) > static::LINE_LENGTH_LIMIT) {
                $formattedSqlCommands[] = $this->formatSqlCommand($filteredSchemaDiffSqlCommand);
            } else {
                $formattedSqlCommands[] = $filteredSchemaDiffSqlCommand;
            }
        }

        return $formattedSqlCommands;
    }

    /**
     * @param string $filteredSchemaDiffSqlCommand
     * @return string
     */
    protected function formatSqlCommand($filteredSchemaDiffSqlCommand)
    {
        $formattedQuery = $this->formatSqlQueryWithTabs($filteredSchemaDiffSqlCommand);
        $formattedQueryLines = array_map('rtrim', explode("\n", $formattedQuery));

        return "\n" . implode("\n", $this->indentSqlCommandLines($formattedQueryLines));
    }

    /**
     * @param string $query
     * @return string
     */
    protected function formatSqlQueryWithTabs($query)
    {
        $previousTab = SqlFormatter::$tab;
        SqlFormatter::$tab = static::INDENT_CHARACTERS;

        $formattedQuery = SqlFormatter::format($query, static::HIGHLIGHT_OFF);

        SqlFormatter::$tab = $previousTab;

        return $formattedQuery;
    }

    /**
     * @param string[] $queryLines
     * @return string[]
     */
    protected function indentSqlCommandLines(array $queryLines)
    {
        return array_map(function ($queryLine) {
            return str_repeat(static::INDENT_CHARACTERS, static::INDENT_TABULATOR_COUNT) . $queryLine;
        }, $queryLines);
    }

    /**
     * @param string[] $sqlCommands
     * @return string[]
     */
    protected function escapeSqlCommands(array $sqlCommands)
    {
        return array_map(function ($sqlCommand) {
            return str_replace('\'', "\\'", $sqlCommand);
        }, $sqlCommands);
    }

    /**
     * @param \Shopsys\MigrationBundle\Component\Doctrine\Migrations\MigrationsLocation $migrationLocation
     */
    protected function createMigrationLocationDirectoryIfNotExists(MigrationsLocation $migrationLocation)
    {
        if (!$this->filesystem->exists($migrationLocation->getDirectory())) {
            $this->filesystem->mkdir($migrationLocation->getDirectory());
        }
    }
}
