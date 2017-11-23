<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Elastica\Type\Mapping;

class AppIndexInitCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this
            ->setName('app:index:init')
            ->setDescription('Initialize index and types from app config');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $client         = $this->getContainer()->get('fos_elastica.client.default');
        $mappingBuilder = $this->getContainer()->get('fos_elastica.mapping_builder');
        $configManager  = $this->getContainer()->get('fos_elastica.config_manager');

        $indexes = $configManager->getIndexNames();

        foreach ($indexes as $index) {
            $indexConfig = $configManager->getIndexConfiguration($index);
            $indexName   = $indexConfig->getElasticSearchName();

            $elasticIndex = $client->getIndex($indexName);

            if (!$elasticIndex->exists()) {
                $output->writeln("Creating index $indexName");
                $elasticIndex->create(array(), false);
                $output->writeln("Creating index $indexName successfull");
            }

            foreach ($indexConfig->getTypes() as $type) {
                $typeName = $type->getName();

                $output->writeln("Creating type $typeName");

                $elasticHostType = $elasticIndex->getType($typeName);
                $typeConfig     = $configManager->getTypeConfiguration($index, $typeName);

                $mapping = new Mapping();

                foreach ($mappingBuilder->buildTypeMapping($typeConfig) as $name => $field) {
                    $mapping->setParam($name, $field);
                }

                $elasticHostType->setMapping($mapping);

                $output->writeln("Creting type $typeName successfull");
            }
        }
    }
}