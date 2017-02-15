<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use FOS\RestBundle\Controller\FOSRestController;
use Elastica\Query;
use Elastica\Filter;
use Elastica\QueryBuilder;

class ApiHostStatsController extends FOSRestController
{
    public function datehistogramsAction(Request $request)
    {
        #$elasticHostType = $this->get('fos_elastica.index.indash.host');
        
        $search = $this->get('indash.host.search');

        $recentDateQuery = new Query();
        $qb = new QueryBuilder();
        
        $recentDateQuery->addAggregation(
            $qb->aggregation()
                ->date_histogram("DateAgg", "indash_timestamp_utc", "second")
                ->setFormat("yyyy-MM-dd HH:mm:ss")
                ->setMinimumDocumentCount(1)
        );
        
        $resultSet = $search->search($recentDateQuery);

        $aggResults = $resultSet->getAggregation('DateAgg');

        $results = $aggResults['buckets'];
        
        $view = $this->view($results, 200)
                ->setFormat("json");
        
        return $this->handleView($view);
    }
    
    public function propertyhistogramsAction(Request $request)
    {
        $search = $this->get('indash.host.search');
        $aggregatedTerm = $request->query->get('property');
        $filterByDate = $request->query->get('filterByDate'); 
        
        $query = new Query();
        $constantScore = new Query\ConstantScore();
        
        if ($filterByDate) {
            $dateFilter = new Filter\Range();

            $dateFilter->addField(
                "indash_timestamp_utc", 
                array(
                    "gte" => $filterByDate,
                    "lte" => $filterByDate
                )
            );

            $constantScore->setFilter($dateFilter);    
                    
            $query->setQuery($constantScore);            
        }
        
        if ($aggregatedTerm)
        {
            $qb = new QueryBuilder();

            $query->addAggregation(
                $qb->aggregation()
                    ->terms("PropAgg")
                    ->setField($aggregatedTerm)
                    ->setMinimumDocumentCount(1)
            );

            $resultSet = $search->search($query);

            $aggResults = $resultSet->getAggregation('PropAgg');

            $results = $aggResults['buckets'];
        } else {
            $results = [];
        }
        
        $view = $this->view($results, 200)
                ->setFormat("json");
        
        return $this->handleView($view);        
    }            
}
