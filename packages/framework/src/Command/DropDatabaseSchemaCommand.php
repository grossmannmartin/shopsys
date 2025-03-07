<?php

namespace Shopsys\FrameworkBundle\Command;

use Shopsys\FrameworkBundle\Component\Doctrine\DatabaseSchemaFacade;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DropDatabaseSchemaCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'shopsys:schema:drop';

    /**
     * @var \Shopsys\FrameworkBundle\Component\Doctrine\DatabaseSchemaFacade
     */
    private $databaseSchemaFacade;

    /**
     * @param \Shopsys\FrameworkBundle\Component\Doctrine\DatabaseSchemaFacade $databaseSchemaFacade
     */
    public function __construct(DatabaseSchemaFacade $databaseSchemaFacade)
    {
        $this->databaseSchemaFacade = $databaseSchemaFacade;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Drop database public schema');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Dropping database schema...');
        $this->databaseSchemaFacade->dropSchemaIfExists('public');
        $output->writeln('Database schema dropped successfully!');

        return CommandResultCodes::RESULT_OK;
    }
}
