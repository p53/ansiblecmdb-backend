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
use Elastica\Request as ElasticaRequest;

class ApiItemController extends FOSRestController
{
    public function itemsAction(Request $request)
    {
        $results = [];
        $hostQuery = new Query();
                
        
        $filterByTerm = $request->query->get('filterByTerm');
        $sortField = $request->query->get('sortField');
        $sort = $request->query->get('sort');
        $page = $request->query->get('page');
        $size = $request->query->get('size');
        $from = ($page-1) * $size;  
        
        $size = 10000;
        $from = 0;
        
        $sort = $sort ? $sort : 'asc';
        $sortField = $sortField ? $sortField : 'ansible_hostname';
        
        $client = $this->get('fos_elastica.client.default');
        
        $elasticIndashIndex = $client->getIndex('indash');
        $elasticType = $elasticIndashIndex->getType('host');

        if (!$filterByTerm) {
            $filterByTerm = "*";
        }

        $query = 
        [
            "size" => 0,
            "from" => $from,
            "aggs" =>
            [
                "narrow" => [

                    "filter" =>
                    [
                        "query_string" =>
                        [
                            "query" => "$filterByTerm"
                        ]
                    ],
                    "aggs" => [
                        "product" =>
                        [
                            "terms" =>
                            [
                                "field" => "$sortField",
                                "size" => $size
                            ],
                            "aggs" =>
                            [
                                "machine" =>
                                [
                                    "terms" =>
                                    [
                                        "field" => "ansible_product_uuid"
                                    ],
                                    "aggs" =>
                                    [
                                        "hosts" =>
                                        [
                                            "top_hits" =>
                                            [
                                                "_source" =>
                                                [
                                                    "include" =>
                                                    [
                                                        "*"
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $path = $elasticIndashIndex->getName() . '/' . $elasticType->getName() . '/_search';

        $response = $client->request($path, ElasticaRequest::GET, $query);
        $results = $response->getData();

        foreach($results['aggregations']['narrow']['product']['buckets'] as $item)
        {
            $result = $item['machine']['buckets'][0]['hosts']['hits']['hits'][0];
            $resultArray = [];

            $resultArray['type'] = 'item';
            $resultArray['_id'] = $result['_source']['ansible_product_uuid'];
            $resultArray['attributes'] = $result['_source'];

            $resultsArray['data'][] = $resultArray;
        }

        $view = $this->view($resultsArray, 200)->setFormat("jsonapi_long");

        return $this->handleView($view);
    }
}
