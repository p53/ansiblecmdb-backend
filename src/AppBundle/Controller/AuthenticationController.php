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
    
//    public function refreshTokenAction(Request $request)
//    {
//        if ($token = $request->headers->get('X-AUTH-TOKEN'))
//        {
//            $client = $this->get('fos_elastica.client.default');
//            $search = $this->get('indash.user.search');
//            $elasticIndashIndex = $client->getIndex('indash');
//            $elasticUserType = $elasticIndashIndex->getType('user');
//
//            $userQuery = new Query\ConstantScore();
//            $userFilter = new Filter\Term(['token' => $token]);
//            $userQuery->setFilter($userFilter);
//
//            $results = $search->search($userQuery)->getResults();
//
//            $docId = $results[0]->getId();
//            $docToken = $results[0]->getSource()['token'];
//
//            if ($results)
//            {
//                $token = base64_encode(random_bytes(50));
//
//                $data = [
//                    'token' => $token
//                ];
//
//                $doc = new Document($docId, $data);
//
//                $elasticUserType->updateDocument($doc);
//                $elasticIndashIndex->refresh();
//
//                $responseData = [
//                    'token' => $token
//                ];
//
//                $view = $this->view($responseData, 200)->setFormat("json");
//            }
//            else
//            {
//                $responseData = [
//                    'message' => 'Authentication Required, bad token!'
//                ];
//
//                $view = $this->view($responseData, 401)->setFormat("json");
//            }
//        }
//        else
//        {
//            $responseData = [
//                'message' => "Authentication Required, token missing, token won't be refreshed!"
//            ];
//
//            $view = $this->view($responseData, 401)->setFormat("json");
//        }
//
//        return $this->handleView($view);
//    }

}
