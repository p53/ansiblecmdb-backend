<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use FOS\RestBundle\Controller\FOSRestController;
use Elastica\Query\Range;
use Elastica\Query;
use Elastica\Query\QueryString;
use Elastica\Filter;
use Elastica\QueryBuilder;
use Elastica\Document;
use DateTime;

class ApiUserController extends FOSRestController
{

    public function userAction(Request $request, $userId)
    {
        $userProvider = $this->get('app.elastic_user_provider');
        $query        = new Query();

        $search = $this->get('indash.user.search');

        if ($userId) {
            if ($userId == 'me' && $request->headers->get('X-AUTH-TOKEN')) {
                $token  = $request->headers->get('X-AUTH-TOKEN');
                $user   = $userProvider->loadUserByToken($token);
                $userId = $user->getElasticDocId();
            }

            $queryString = new QueryString("_id: $userId");

            $query->setQuery($queryString);
        }

        $results = $search->search($query)->getResults();

        $resultsArray = [];

        foreach ($results as $result) {
            $resultArray = [];

            $resultArray['type']       = $result->getType();
            $resultArray['_id']        = $result->getId();
            $resultArray['attributes'] = $result->getSource();
            unset($resultArray['attributes']['password']);

            $resultsArray['data'][] = $resultArray;
        }

        $view = $this->view($resultsArray, 200)->setFormat("jsonapi");
        return $this->handleView($view);
    }

    public function usersAction(Request $request)
    {
        #$elasticHostType = $this->get('fos_elastica.index.indash.host');
        $results   = [];
        $hostQuery = new Query();

        $filterByDate = $request->query->get('filterByDate');
        $filterByTerm = $request->query->get('filterByTerm');
        $sortField    = $request->query->get('sortField');
        $sort         = $request->query->get('sort');
        $page         = $request->query->get('page');
        $size         = $request->query->get('size');
        $from         = ($page - 1) * $size;

        $size = 10000;
        $from = 0;

        $search = $this->get('indash.host.search');

        if ($filterByDate) {
            $dateFilter = new Filter\Range();

            $dateFilter->addField(
                "indash_timestamp_utc",
                array(
                "gte" => $filterByDate,
                "lte" => $filterByDate
                )
            );

            $hostQuery->setPostFilter($dateFilter);
        }

        if ($hostId) {
            $queryString = new QueryString("_id: $hostId");
            $hostQuery->setQuery($queryString);
        }

        if ($filterByTerm) {
            $queryString = new QueryString($filterByTerm);
            $hostQuery->setQuery($queryString);
        }
        !$from ? : $hostQuery->setFrom($from);
        !$size ? : $hostQuery->setSize($size);
        !($sortField && $sort) ? : $hostQuery->setSort([$sortField => $sort]);

        $results = $search->search($hostQuery)->getResults();

        $view = $this->view($results, 200)->setFormat("jsonapi");

        return $this->handleView($view);
    }

    public function updateAction(Request $request, $userId)
    {
        $userProvider = $this->get('app.elastic_user_provider');
        $data         = json_decode($request->getContent(), true);

        $user   = $data['data']['attributes'];
        $userId = $data['data']['_id'];

        $client = $this->get('fos_elastica.client.default');

        $elasticIndashIndex = $client->getIndex('indash');
        $elasticUserType    = $elasticIndashIndex->getType('user');

        $oldUser           = $userProvider->loadUserByUsername($user['username']);
        $oldPassword       = $oldUser->getPassword();
        $user['token']     = $oldUser->getToken();
        $user['tokenDate'] = $oldUser->getTokenDate();
        $newPassword       = $userProvider->encryptPassword($user['password']);

        if ($oldPassword != $newPassword && $user['password'] != '') {
            $user['password'] = $newPassword;
        } else {
            $user['password'] = $oldPassword;
        }

        $doc      = new Document($userId, $user);
        $response = $elasticUserType->addDocument($doc);
        $elasticIndashIndex->refresh();

        $userResults['data'][] = $data['data'];

        $view = $this->view($userResults, 200)->setFormat("jsonapi");

        return $this->handleView($view);
    }

    public function createAction(Request $request)
    {
        $allDocs     = [];
        $data        = json_decode($request->getContent(), true);
        #$hostId = $data['attributes']['hostId'];
        $productUuid = $data['data']['attributes']['ansible_product_uuid'];
        $machineId   = $data['data']['attributes']['ansible_machine_id'];
        $user        = $data['data']['attributes']['user'];

        $client = $this->get('fos_elastica.client.default');

        $elasticIndashIndex = $client->getIndex('indash');
        $elasticUserType    = $elasticIndashIndex->getType('user');
        $elasticHostType    = $elasticIndashIndex->getType('host');

        $userData = [
            'user' => $user
        ];

        $doc      = new Document('', $userData);
        $response = $elasticUserType->addDocument($doc);
        $elasticIndashIndex->refresh();

        $newUserId = $response->getData()['_id'];

        $search     = $this->get('indash.host.search');
        $query      = new Query\Filtered();
        $boolFilter = new Filter\BoolFilter();

        $productUuidFilter = new Filter\Term(['ansible_product_uuid' => $productUuid]);
        $machineIdFilter   = new Filter\Term(['ansible_machine_id' => $machineId]);

        $boolFilter->addMust(array($productUuidFilter, $machineIdFilter));

        $query->setFilter($boolFilter);

        $results = $search->search($query)->getResults();

        foreach ($results as $hostDoc) {
            $dataOfHost         = $hostDoc->getSource();
            $dataOfHost['user'] = $newUserId;

            $allDocs[] = new \Elastica\Document(
                $hostDoc->getId(), $dataOfHost
            );
        }

        $elasticHostType->addDocuments($allDocs);
        $elasticIndashIndex->refresh();

        $data['data']['_id'] = $newUserId;

        $userResults['data'][] = $data['data'];

        $view = $this->view($userResults, 201)->setFormat("jsonapi");

        return $this->handleView($view);
    }
}