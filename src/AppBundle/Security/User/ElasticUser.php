<?php

namespace AppBundle\Security\User;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\EquatableInterface;

class ElasticUser implements UserInterface, EquatableInterface
{
    private $elasticDocId;
    private $username;
    private $password;
    private $salt;
    private $roles;
    private $settings;
    
    public function __construct($id, $username, $password, $salt, $settings, array $roles, $token = null)
    {
        $this->elasticDocId = $id;
        $this->username = $username;
        $this->password = $password;
        $this->salt = $salt;
        $this->roles = $roles;
        $this->settings = $settings;
        $this->token = $token;
    }

    public function getElasticDocId()
    {
        return $this->elasticDocId;
    }
    
    public function getRoles()
    {
        return $this->roles;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function getSalt()
    {
        return $this->salt;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function eraseCredentials()
    {
    }

    public function getSettings()
    {
        return $this->settings;
    }
    
    public function isEqualTo(UserInterface $user)
    {
        if (!$user instanceof ElasticUser) {
            return false;
        }

        if ($this->password !== $user->getPassword()) {
            return false;
        }

        if ($this->salt !== $user->getSalt()) {
            return false;
        }

        if ($this->username !== $user->getUsername()) {
            return false;
        }

        return true;
    }
}
