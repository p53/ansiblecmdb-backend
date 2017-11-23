<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use DateTime;

class AppRedisToElasticCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this
            ->setName('app:redistoelastic:index')
            ->setDescription('Index all entries from redis to elasticsearch '
                .'and add timestamp of indexing')
            ->addArgument(
                'name', InputArgument::REQUIRED, 'Name of elasticsearch index'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $utcTimestamp = time();
        $allDocs      = array();
        $text         = '';

        $date      = new DateTime();
        $date->setTimestamp($utcTimestamp);
        $firstPart = $date->format("Y-m-d");
        $secPart   = $date->format("H:i:s.u");

        $name = $input->getArgument('name');

        $redis  = $this->getContainer()->get('snc_redis.default');
        $client = $this->getContainer()->get('fos_elastica.client.default');

        $elasticIndashIndex = $client->getIndex('indash');
        $elasticHostType    = $elasticIndashIndex->getType($name);

        $allKeys = $redis->keys('*');

        $output->writeln("\x0B\x0B");

        $progressBar = new ProgressBar($output, count($allKeys));
        $progressBar->setFormat(" \n%message%\n %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%\n");

        if (!empty($allKeys)) {
            $progressBar->setMessage('Converting redis data to elastic documents...');

            foreach ($allKeys as $item) {
                if ($item != 'ansible_cache_keys') {
                    $doc = $redis->get($item);

                    $docHash = json_decode($doc, true);
                    unset($docHash['ansible_python']['version_info']);
                    unset($docHash['ansible_selinux']);

                    $docHash['indash_timestamp_utc'] = $firstPart."T".$secPart;

                    $cleandDocHash = $this->recurseDotKeyReplace($docHash);

                    $allDocs[] = new \Elastica\Document(
                        '', $cleandDocHash
                    );
                }

                $progressBar->advance();
            }

            $progressBar->setMessage('Indexing elastic documents...');
            $progressBar->start();

            $elasticHostType->addDocuments($allDocs);
            $elasticIndashIndex->refresh();

            $progressBar->advance(100);

            $text = '<info>Redis documents indexed in Elasticsearch.</info>';
        } else {
            $test = '<warning>No keys present in Redis, nothing to index.</warning>';
        }

        $progressBar->finish();
        $output->writeln("\x0D");

        $output->writeln($text);
    }

    protected function recurseDotKeyReplace(array $data, $level = 1, $startAtLevel = 1)
    {
        $keys = array_keys($data);

        foreach ($keys as $keyItem) {
            $value = $data[$keyItem];

            if ($level >= $startAtLevel) {
                if (strpos($keyItem, '.')) {
                    unset($data[$keyItem]);

                    $keyItem = str_replace('.', '_', $keyItem);

                    $data[$keyItem] = $value;
                }
            }

            if (is_array($value)) {
                $keyLevel       = $level + 1;
                $data[$keyItem] = $this->recurseDotKeyReplace($data[$keyItem], $keyLevel, $startAtLevel);
            }
        }

        return $data;
    }
}