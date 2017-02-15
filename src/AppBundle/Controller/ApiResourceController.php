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

class ApiResourceController extends FOSRestController
{
    public function updateAction(Request $request, $resource, $resourceId)
    {
        $data = json_decode($request->getContent(), true);
        $resourceName = preg_replace('/^(.*)s$/', '$1', $resource);
                
        $client = $this->get('fos_elastica.client.default');
        
        $elasticIndashIndex = $client->getIndex('indash');
        $elasticType = $elasticIndashIndex->getType($resourceName);

        $allDocs[] = new \Elastica\Document(
                        $resourceId, 
                        $data['data']['attributes']
                    );

        $elasticType->addDocuments($allDocs);
        $elasticIndashIndex->refresh();
               
        $results = [];
        
        $view = $this->view($results, 200)->setFormat("jsonapi");
       
        return $this->handleView($view);
    }    
}
