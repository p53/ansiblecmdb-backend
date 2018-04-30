<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use FOS\RestBundle\Controller\FOSRestController;
use Elastica\Query\Range;
use Elastica\Query;
use Elastica\Query\QueryString;
use Elastica\Filter;
use Elastica\QueryBuilder;
use DateTime;

class ApiHostController extends FOSRestController
{
    public function indexAction()
    {
        return true;
    }

    public function hostAction(Request $request, $hostId = null)
    {
        #$elasticHostType = $this->get('fos_elastica.index.indash.host');
        $results = [];
        $hostQuery = new Query();
                
        $filterByDate = $request->query->get('filterByDate'); 
        $filterByTerm = $request->query->get('filterByTerm');
        $sortField = $request->query->get('sortField');
        $sort = $request->query->get('sort');
        $page = $request->query->get('page');
        $size = $request->query->get('size');
        $from = ($page-1) * $size;  
        
        $size = 10000;
        $from = 0;
        
        $search = $this->get('indash.host.search');
        
        if ($filterByDate)
        {
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

        if ($hostId)
        {
            $queryString= new QueryString("_id: $hostId");
            $hostQuery->setQuery($queryString);
        }
        
        if ($filterByTerm) {
            $queryString= new QueryString($filterByTerm);
            $hostQuery->setQuery($queryString);
        }

        !$from ? :$hostQuery->setFrom($from);
        !$size ? :$hostQuery->setSize($size);
        !($sortField && $sort) ? :$hostQuery->setSort([$sortField => $sort]);
        
        $results = $search->search($hostQuery)->getResults();
        
        $resultsArray = [];
        
        foreach($results as $result)
        {
            $resultArray = [];

            $resultArray['type'] = $result->getType();
            $resultArray['_id'] = $result->getId();
            $resultArray['attributes'] = $result->getSource();
            $relationData = null;

            if( $resultArray['attributes']['note'] )
            {
                $relationData['id'] = $resultArray['attributes']['note'];
                $relationData['type'] = 'note';
            }
            
            $resultArray['relationships'] = [
                "note" => 
                [
                    "links" => 
                    [
                      "self" => "/hosts/" . $result->getId() . "/relationships/note",
                      "related" => "/hosts/" . $result->getId() . "/note"
                    ],
                    "data" => $relationData
                ]
            ];

            $resultsArray['data'][] = $resultArray;
        }
        
        $view = $this->view($resultsArray, 200)->setFormat("jsonapi");
       
        return $this->handleView($view);
    }
    
    public function hostsAction(Request $request)
    {
        #$elasticHostType = $this->get('fos_elastica.index.indash.host');
        $results = [];
        $hostQuery = new Query();
                
        $filterByDate = $request->query->get('filterByDate'); 
        $filterByTerm = $request->query->get('filterByTerm');
        $staticItem = $request->query->get('static');
        $sortField = $request->query->get('sortField');
        $sort = $request->query->get('sort');
        $page = $request->query->get('page');
        $size = $request->query->get('size');
        $from = ($page-1) * $size;  
        
        $size = 10000;
        $from = 0;
        
        $search = $this->get('indash.host.search');
        
        if ($filterByDate)
        {
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

        if ($staticItem)
        {
            $staticFilter = new Filter\Term([ 'static' => $staticItem ]);
            $hostQuery->setPostFilter($staticFilter);
        }
        
        if ($filterByTerm) 
        {
            $queryString= new QueryString($filterByTerm);
            $hostQuery->setQuery($queryString);
        }

        !$from ? :$hostQuery->setFrom($from);
        !$size ? :$hostQuery->setSize($size);
        !($sortField && $sort) ? :$hostQuery->setSort([$sortField => $sort]);
        
        $results = $search->search($hostQuery)->getResults();  

        $resultsArray['data'] = [];
        
        foreach($results as $result)
        {
            $resultArray = [];

            $resultArray['type'] = $result->getType();
            $resultArray['_id'] = $result->getId();
            $resultArray['attributes'] = $result->getSource();

            $resultsArray['data'][] = $resultArray;
        }

        $view = $this->view($resultsArray, 200)->setFormat("jsonapi_long");

        return $this->handleView($view);
    }
    
    protected function getMostRecentHosts($page, $size, $sortField, $sort)
    {
        $from = ($page-1) * $size;

        $search = $this->get('indash.host.search');

        $recentDateQuery = new Query();
        $qb = new QueryBuilder();
        
        $recentDateQuery->addAggregation(
            $qb->aggregation()
                ->date_histogram("DateAgg", "indash_timestamp_utc", "second")
                ->addAggregation(
                    $qb->aggregation()
                        ->max("MaxDateAgg")
                        ->setField("indash_timestamp_utc")
                )
        );
     
        $resultSet = $search->search($recentDateQuery);

        $dateAggResult = $resultSet->getAggregation('DateAgg');
 
        $maxDate = $dateAggResult['buckets'][0]['MaxDateAgg']['value'];

        $recentHostDataQuery = new Query();
        $recentHostDataQuery->setFrom($from);
        $recentHostDataQuery->setSize($size);
        $recentHostDataQuery->setSort([$sortField => $sort]);

        $recentFilter = new Filter\Term(array('indash_timestamp_utc' => $maxDate));
        
        $recentHostDataQuery->setPostFilter($recentFilter);

        $results = $search->search($recentHostDataQuery)->getResults();
        
        return $results;
    }
    
    public function updateAction(Request $request, $resourceId)
    {
        $data = json_decode($request->getContent(), true);
                
        $client = $this->get('fos_elastica.client.default');
        
        $elasticIndashIndex = $client->getIndex('indash');
        $elasticType = $elasticIndashIndex->getType('host');

        if(key_exists('note', $data['data']['relationships']))
        {
            $data['data']['attributes']['note'] = $data['data']['relationships']['note']['data']['id'];
        }
        
        $allDocs[] = new \Elastica\Document(
                        $resourceId, 
                        $data['data']['attributes']
                    );

        $elasticType->addDocuments($allDocs);
        $elasticIndashIndex->refresh();
               
        $results['data'][] = $data['data'];
        
        $view = $this->view($results, 200)->setFormat("jsonapi");
       
        return $this->handleView($view);
    }
    
    public function deleteAction(Request $request, $resourceId)
    {
        $data = json_decode($request->getContent(), true);
        
        $client = $this->get('fos_elastica.client.default');
        $elasticIndashIndex = $client->getIndex('indash');
        $elasticType = $elasticIndashIndex->getType('host');
            
        $all = $data['data']['attributes']['all'];
                
        if ($all) 
        {
            $productUuid = $data['data']['attributes']['ansible_product_uuid'];
            $machineId = $data['data']['attributes']['ansible_machine_id'];
            
            $search = $this->get('indash.host.search');
            $query = new Query\Filtered();
            $boolFilter = new Filter\BoolFilter();

            $productUuidFilter = new Filter\Term(['ansible_product_uuid' => $productUuid]);
            $machineIdFilter = new Filter\Term(['ansible_machine_id' => $machineId]);

            $boolFilter->addMust(array($productUuidFilter, $machineIdFilter));

            $query->setFilter($boolFilter);

            $results = $search->search($query)->getResults();
            
            foreach($results as $hostDoc)
            {
                $elasticType->deleteById($hostDoc->getId());
            }
            
            $elasticIndashIndex->refresh();
            
            $results['data'] = [];

            $view = $this->view($results, 204)->setFormat("jsonapi");
        }
        else
        {
            $elasticType->deleteById($resourceId);
            $elasticIndashIndex->refresh();

            $results['data'] = [];

            $view = $this->view($results, 204)->setFormat("jsonapi");
        }
        
        return $this->handleView($view);
    }
}
