<?php

namespace AppBundle\Controller;

use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpFoundation\Request;
//use Elastica\Query;
//use Elastica\Filter;
//use Elastica\Document;
use DateTime;

class AuthenticationController extends FOSRestController
{
    public function getTokenAction(Request $request)
    {          
        if (($request->request->has('username') && $request->request->has('password')) || $request->headers->get('X-AUTH-TOKEN'))
        {
            $userProvider = $this->get('app.elastic_user_provider');
            $user = null;
            
            if ($username = $request->request->get('username')) {
                $user = $userProvider->loadUserByUsername($username);
            } elseif ($token = $request->headers->get('X-AUTH-TOKEN')) {
                $user = $userProvider->loadUserByToken($token);
            }

            $newToken = $user->generateToken();
            $user->setToken($newToken);

            $currentTime = new DateTime();
            $currentTime->setTimezone('UTC');

            $firstPart = $currentTime->format("Y-m-d");
            $secPart   = $currentTime->format("H:i:s.u");

            $user->setTokenDate($firstPart."T".$secPart);
            $userProvider->saveUser($user);
        
            $responseData = [
                'token' => $newToken
            ];
            
            $view = $this->view($responseData, 200)->setFormat("json");
        } else {
            $responseData = [
                'message' => "Authentication Required, token won't be granted!"
            ];
            
            $view = $this->view($responseData, 401)->setFormat("json");
        }
        
        return $this->handleView($view);
    }
}
