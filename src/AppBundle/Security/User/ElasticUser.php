<?php

namespace AppBundle\Security\User;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Elastica\Document;

class ElasticUser implements UserInterface, EquatableInterface
{
    private $elasticDocId;
    private $username;
    private $password;
    private $salt;
    private $roles;
    private $settings;
    private $tokenDate;
    
    public function __construct($username, $password, array $settings, array $roles, $id = null, $token = null, $tokenDate = null)
    {
        $this->elasticDocId = $id;
        $this->username = $username;
        $this->password = $password;
        $this->roles = $roles;
        $this->settings = $settings;
        $this->token = $token;
        $this->tokenDate = $tokenDate;
    }

    public function toElasticDoc()
    {
        $props = [
            'username' => $this->username,
            'password' => $this->password,
            'roles' => $this->roles,
            'settings' => $this->settings,
            'token' => $this->token,
            'tokenDate' => $this->tokenDate
        ];

        if ($this->elasticDocId) {
            $doc = new Document($this->elasticDocId, $props);
        } else {
            $doc = new Document('', $props);
        }

        return $doc;
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

    public function getSalt()
    {
    }

    public function generateToken()
    {
        return base64_encode(random_bytes(50));
    }

    public function getTokenDate()
    {
        return $this->tokenDate;
    }

    public function getToken()
    {
        return $this->token;
    }


    public function setRoles(array $roles)
    {
        $this->roles = $roles;
    }

    public function setPassword($password)
    {
        $this->password = $password;
    }

    public function setUsername($username)
    {
        $this->username = $username;
    }

    public function setSettings(array $settings)
    {
        $this->settings = $settings;
    }

    public function setTokenDate($date)
    {
        $this->tokenDate = $date;
    }

    public function setToken($token)
    {
        $this->token = $token;
    }


    public function isEqualTo(UserInterface $user)
    {
        if (!$user instanceof ElasticUser) {
            return false;
        }

        if ($this->password !== $user->getPassword()) {
            return false;
        }

        if ($this->username !== $user->getUsername()) {
            return false;
        }

        return true;
    }
}
