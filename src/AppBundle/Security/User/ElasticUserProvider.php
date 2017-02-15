<?php

namespace AppBundle\Security\User;

use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use AppBundle\Security\User\ElasticUser;
use Elastica\Query;
use Elastica\Filter;
use Elastica\Document;
use Elastica\Search;
use \FOS\ElasticaBundle\Elastica\Client;

class ElasticUserProvider implements UserProviderInterface
{
    protected $elasticaClient;
    protected $defaultUser;
    
    public function __construct(Client $elasticaClient, $defaultUser)
    {
        $this->elasticaClient = $elasticaClient;
        $this->defaultUser = $defaultUser;
    }
    
    public function loadUserByToken($token)
    {
        $client = $this->elasticaClient;
        
        $elasticIndashIndex = $client->getIndex('indash');
        $elasticUserType = $elasticIndashIndex->getType('user');
        
        $userQuery = new Query\ConstantScore();
        $userFilter = new Filter\Term(['token' => $token]);
        $userQuery->setFilter($userFilter);
                
        $search = new Search($client);
        $search->addIndex($elasticIndashIndex);
        $search->addType($elasticUserType);
        
        $results = $search->search($userQuery)->getResults();

        if ($results) {
            $userData = $results[0]->getSource();
            
            return new ElasticUser(
                $userData['_id'],
                $userData['username'],
                $userData['password'],
                $userData['salt'],
                $userData['settings'],
                $userData['roles'],
                $userData['token']
            );
        }

        throw new UsernameNotFoundException(
            sprintf('User with "%s" token does not exist.', $token)
        );
    }
    
    public function loadUserByUsername($username)
    { 
        $client = $this->elasticaClient;
        
        $elasticIndashIndex = $client->getIndex('indash');
        $elasticUserType = $elasticIndashIndex->getType('user');

        $userQuery = new Query\ConstantScore();

        $userFilter = new Filter\Term(['username' => $username]);
        $userQuery->setFilter($userFilter);
        
        $search = new Search($client);
        $search->addIndex($elasticIndashIndex);
        $search->addType($elasticUserType);

        $results = $search->search($userQuery)->getResults();

        if (!$elasticUserType->exists() || !$results)
        {
            $doc = new Document('', $this->defaultUser);
            $elasticUserType->addDocument($doc);
            $elasticIndashIndex->refresh();
        }

        $results = $search->search($userQuery)->getResults();

        if ($results) {
            $userData = $results[0]->getSource();
            
            return new ElasticUser(
                $userData['_id'],    
                $userData['username'],
                $userData['password'],
                $userData['salt'],
                $userData['settings'],
                $userData['roles']
            );
        }

        throw new UsernameNotFoundException(
            sprintf('Username "%s" does not exist.', $username)
        );
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof ElasticUser) {
            throw new UnsupportedUserException(
                sprintf('Instances of "%s" are not supported.', get_class($user))
            );
        }

        return $this->loadUserByUsername($user->getUsername());
    }

    public function supportsClass($class)
    {
        return $class === 'AppBundle\Security\User\ElasticUser';
    }
}


