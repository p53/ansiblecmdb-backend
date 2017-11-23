<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use AppBundle\Security\User\ElasticUser;

class AppDeleteUserCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this
            ->setName('app:delete:user')
            ->setDescription('...')
            ->addArgument('user', InputArgument::REQUIRED, 'User to create')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $userProvider = $this->getContainer()->get('app.elastic_user_provider');

        $username = $input->getArgument('user');

        try {
            $user = $userProvider->loadUserByUsername($username);
            $output->writeln("User $username sucessfully deleted.");
            $userProvider->deleteUser($user);
        } catch (UsernameNotFoundException $e) {
            $output->writeln("User $username does not exist, exiting...");
        } catch (Exception $e) {
            $output->writeln("User deletion failed with message: ".$e->getMessage());
        }
    }
}