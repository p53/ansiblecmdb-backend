<?php

namespace AppBundle\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use DateTime;

class TokenAuthenticator extends AbstractGuardAuthenticator
{

    public function __construct(UserProviderInterface $userProvider)
    {
        $this->userProvider = $userProvider;
    }

    /**
     * Called on every request. Return whatever credentials you want,
     * or null to stop authentication.
     */
    public function getCredentials(Request $request)
    {
        $credentials = [];
        
        if (!$token = $request->headers->get('X-AUTH-TOKEN')) {
            // no token? Return null and no other methods will be called
            $username = $request->get('username');
            $password = $request->get('password');

            if ($username && $password) {
                $credentials = [
                    'username' => $username,
                    'password' => $password
                ];
            } else {
                return;
            }
        } else {
            $credentials = ['token' => $token];
        }

        // What you return here will be passed to getUser() as $credentials
        return $credentials;
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $elasticUser = null;

        if (key_exists('token', $credentials)) {
            $token       = $credentials['token'];
            $elasticUser = $userProvider->loadUserByToken($token);
        } else {
            $username    = $credentials['username'];
            $elasticUser = $userProvider->loadUserByUsername($username);
        }

        // if null, authentication will fail
        // if a User object, checkCredentials() is called
        return $elasticUser;
    }

    public function checkCredentials($credentials, UserInterface $user)
    {
        $checkResult = false;

        // check credentials - e.g. make sure the password is valid
        // no credential check is needed in this case
        if (key_exists('token', $credentials)) {
            if (!$this->tokenIsExpired($user, $this->userProvider)) {
                if ($this->tokenNeedsRefresh($user, $this->userProvider)) {
                    $currentTime = new DateTime();
                    $currentTime->setTimezone('UTC');

                    $firstPart = $currentTime->format("Y-m-d");
                    $secPart   = $currentTime->format("H:i:s.u");

                    $user->setTokenDate($firstPart."T".$secPart);
                    $this->userProvider->saveUser($user);
                }
                
                $checkResult = true;
            }
        } elseif ($credentials['password']) {
            $sentPass   = $this->userProvider->encryptPassword($credentials['password']);
            $passFromDb = $user->getPassword();
            
            if ($sentPass == $passFromDb) {
                $checkResult = true;
            }
        }

        // return true to cause authentication success
        return $checkResult;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        // on success, let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $data = array(
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData())

            // or to translate this message
            // $this->translator->trans($exception->getMessageKey(), $exception->getMessageData())
        );

        return new JsonResponse($data, 401);
    }

    /**
     * Called when authentication is needed, but it's not sent
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        $data = array(
            // you might translate this message
            'message' => 'Authentication Required'
        );

        return new JsonResponse($data, 401);
    }

    public function supportsRememberMe()
    {
        return false;
    }

    public function tokenIsExpired(UserInterface $user, UserProviderInterface $userProvider)
    {
        $currentTime = new DateTime();
        $currentTime->setTimezone('UTC');

        if ($tokenDate = $user->getTokenDate()) {
            $tokenDateObj   = DateTime::createFromFormat('Y-m-d\TH:i:s.u', $tokenDate);
            $timeDiff       = $currentTime->diff($tokenDateObj);
            $inactivityTime = $userProvider->getDefaultUser()['inactivity_time'];

            $timeDiffMinutes = $this->toSeconds($timeDiff)/60;

            return ($timeDiffMinutes > $inactivityTime) ? true : false;
        } else {
            return true;
        }
    }

    public function tokenNeedsRefresh(UserInterface $user, UserProviderInterface $userProvider)
    {
        $currentTime = new DateTime();
        $currentTime->setTimezone('UTC');

        if ($tokenDate = $user->getTokenDate()) {
            $tokenDateObj   = DateTime::createFromFormat('Y-m-d\TH:i:s.u', $tokenDate);
            $timeDiff       = $currentTime->diff($tokenDateObj);
            $inactivityTime = $userProvider->getDefaultUser()['inactivity_time'];

            $timeDiffMinutes = $this->toSeconds($timeDiff)/60;

            return (($timeDiffMinutes < $inactivityTime) && $timeDiffMinutes > 1) ? true : false;
        } else {
            return true;
        }
    }

    public function toSeconds(\DateInterval $dateInterval)
    {
        return ($dateInterval->y * 365 * 24 * 60 * 60) +
            ($dateInterval->m * 30 * 24 * 60 * 60) +
            ($dateInterval->d * 24 * 60 * 60) +
            ($dateInterval->h * 60 * 60) +
            ($dateInterval->i * 60) +
            $dateInterval->s;
    }
}