<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

class AppCreateUserCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this
            ->setName('app:create:user')
            ->setDescription('...')
            ->addArgument('user', InputArgument::REQUIRED, 'User to create')
            ->addArgument('password', InputArgument::REQUIRED, 'Password of the user')
            ->AddArgument('role', InputArgument::OPTIONAL, 'Wheter user is admin')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $userProvider = $this->getContainer()->get('app.elastic_user_provider');

        $username = $input->getArgument('user');
        $password = $input->getArgument('password');
        $role     = $input->getArgument('role');

        try {
            $userProvider->loadUserByUsername($username);
            $output->writeln("User $username already exists, exiting...");
        } catch (UsernameNotFoundException $e) {
            try {
                $userProvider->createUser($username, $password, $role);
            } catch (Exception $e) {
                $output->writeln("User creation failed with message: ".$e->getMessage());
            }

            $output->writeln("User $username successfully created");
        }
    }
}