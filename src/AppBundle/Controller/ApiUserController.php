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

class ApiNoteController extends FOSRestController
{
    public function noteAction(Request $request, $noteId)
    {
        $query = new Query();
        
        $search = $this->get('indash.note.search');
 
        if ($noteId)
        {
            $queryString= new QueryString("_id: $noteId");
            $query->setQuery($queryString);
        }
        
        $results = $search->search($query)->getResults();
        
        $resultsArray = [];
        
        foreach($results as $result)
        {
            $resultArray = [];

            $resultArray['type'] = $result->getType();
            $resultArray['_id'] = $result->getId();
            $resultArray['attributes'] = $result->getSource();

            $resultsArray['data'][] = $resultArray;
        }
        
        $view = $this->view($resultsArray, 200)->setFormat("jsonapi");
        return $this->handleView($view); 
    }
    
    public function notesAction(Request $request)
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
                    
        $view = $this->view($results, 200)->setFormat("jsonapi");
       
        return $this->handleView($view);
    }
    
    public function updateAction(Request $request, $noteId = null)
    {
        $data = json_decode($request->getContent(), true);

        $productUuid = $data['data']['attributes']['ansible_product_uuid'];
        $machineId = $data['data']['attributes']['ansible_machine_id'];
        $note = $data['data']['attributes']['note'];
        $noteId = $data['data']['_id'];
        
        $client = $this->get('fos_elastica.client.default');
        
        $elasticIndashIndex = $client->getIndex('indash');
        $elasticNoteType = $elasticIndashIndex->getType('note');
        $elasticHostType = $elasticIndashIndex->getType('host');

        $noteData = [
            'note' => $note
        ];
                
        $doc = new Document($noteId, $noteData);
        $response = $elasticNoteType->addDocument($doc);
        $elasticIndashIndex->refresh();
        
        $noteResults['data'][] = $data['data'];
        
        $view = $this->view($noteResults, 200)->setFormat("jsonapi");
       
        return $this->handleView($view);        
    }
    
    public function createAction(Request $request)
    {
        $allDocs = [];
        $data = json_decode($request->getContent(), true);
        #$hostId = $data['attributes']['hostId'];
        $productUuid = $data['data']['attributes']['ansible_product_uuid'];
        $machineId = $data['data']['attributes']['ansible_machine_id'];
        $note = $data['data']['attributes']['note'];
        
        $client = $this->get('fos_elastica.client.default');
        
        $elasticIndashIndex = $client->getIndex('indash');
        $elasticNoteType = $elasticIndashIndex->getType('note');
        $elasticHostType = $elasticIndashIndex->getType('host');

        $noteData = [
            'note' => $note
        ];
                
        $doc = new Document('', $noteData);
        $response = $elasticNoteType->addDocument($doc);
        $elasticIndashIndex->refresh();
        
        $newNoteId = $response->getData()['_id'];

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
            $dataOfHost = $hostDoc->getSource();
            $dataOfHost['note'] = $newNoteId;
            
            $allDocs[] = new \Elastica\Document(
                            $hostDoc->getId(), 
                            $dataOfHost
                        );
        }
        
        $elasticHostType->addDocuments($allDocs);
        $elasticIndashIndex->refresh();

        $data['data']['_id'] = $newNoteId;
        
        $noteResults['data'][] = $data['data'];
        
        $view = $this->view($noteResults, 201)->setFormat("jsonapi");
       
        return $this->handleView($view);        
    }
}
