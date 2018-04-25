<?php

namespace AppBundle\Security\User;

use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use AppBundle\Security\User\ElasticUser;
use Elastica\Query;
use Elastica\Filter;
use Elastica\Search;
use Elastica\Type;
use Elastica\Index;
use Elastica\Document;

class ElasticUserProvider implements UserProviderInterface
{
    protected $elasticaUserSearch;
    protected $defaultUser;

    public function __construct(Search $elasticaUserSearch, Index $elasticaIndex, Type $elasticaUserType, $defaultUser)
    {
        $this->elasticaUserSearch = $elasticaUserSearch;
        $this->elasticaUserType   = $elasticaUserType;
        $this->elasticaIndex      = $elasticaIndex;
        $this->defaultUser        = $defaultUser;
    }

    public function loadUserByToken($token)
    {
        $search = $this->elasticaUserSearch;

        $userQuery  = new Query\ConstantScore();
        $userFilter = new Filter\Term(['token' => $token]);
        $userQuery->setFilter($userFilter);

        $results = $search->search($userQuery)->getResults();

        if ($results) {
            $userData = $results[0]->getSource();

            return new ElasticUser(
                $userData['username'], $userData['password'], $userData['settings'], $userData['roles'],
                $results[0]->getId(), $userData['token'], $userData['tokenDate']
            );
        }

        throw new UsernameNotFoundException(
        sprintf('User with "%s" token does not exist.', $token)
        );
    }

    public function loadUserByUsername($username)
    {
        $search = $this->elasticaUserSearch;

        $userQuery = new Query\ConstantScore();

        $userFilter = new Filter\Term(['username' => $username]);
        $userQuery->setFilter($userFilter);

        $results = $search->search($userQuery)->getResults();

        if ($results) {
            $userData = $results[0]->getSource();

            return new ElasticUser(
                $userData['username'], $userData['password'], $userData['settings'], $userData['roles'],
                $results[0]->getId(), $userData['token'], $userData['tokenDate']
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

    public function saveUser(ElasticUser $user)
    {
        $userType = $this->elasticaUserType;
        $index    = $this->elasticaIndex;

        if ($user->getElasticDocId()) {
            $userType->updateDocument($user->toElasticDoc());
        } else {
            $userType->addDocument($elasticUser->toElasticDoc());
        }

        $index->refresh();
    }

    public function createUser($user, $pass, $role)
    {
        $userType      = $this->elasticaUserType;
        $index         = $this->elasticaIndex;
        $encryptedPass = $this->encryptPassword($pass);

        $elasticUser = new ElasticUser($user, $encryptedPass, [], []);

        if ($role == 'Admin') {
            $elasticUser->setRoles(['ROLE_ADMIN']);
        }

        $userType->addDocument($elasticUser->toElasticDoc());
        $index->refresh();
    }

    public function deleteUser(ElasticUser $user)
    {
        $index    = $this->elasticaIndex;
        $userType = $this->elasticaUserType;

        $userType->deleteById($user->getElasticDocId());
        $index->refresh();
    }

    public function encryptPassword($password)
    {
        return hash_hmac('sha256', $password, $this->defaultUser['salt']);
    }

    public function getDefaultUser()
    {
        return $this->defaultUser;
    }
}