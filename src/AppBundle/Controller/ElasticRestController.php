<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use FOS\RestBundle\Controller\FOSRestController;
use AppBundle\Rest\JsonApiValidator;

class ElasticRestController extends FOSRestController {
    
    public function createApiAction(Request $request, $type)
    {
        #$validator = $this->get('appbundle.rest.jsonapivalidator');
        
        $content = $request->getContent();

        if (!empty($content))
        {
            $resource = json_decode($content, true);
            
            if (!$resource)
            {
                throw new Exception("Not valid JSON or no data!");
            }
        }
        
        if (array_keys($resource) === range(0, count($resource) - 1)) {
            throw new Exception("Create action can receive only one object!");
        }
                
        $validator = JsonApiValidator::getInstance();
        
        $validator->validate($resource);
        
        $elasticDoc = $this->jsonapiToElasticDoc();
        $hostModel = $this->get('appbundle.elastic.models.host');
        $hostModel->create($elasticDoc);
        
    }
    
    public function getApiAction(Request $request, $type, $id)
    {
        
    }
    
    public function deleteApiAction(Request $request, $type, $id)
    {
        
    }
    
    public function updateApiAction(Request $request, $type, $id)
    {
        
    }
    
    public function updateRelApiAction(Request $request, $type, $id, $relatedType)
    {
        
    }
    
    public function deleteRelApiAction(Request $request, $type, $id, $relatedType)
    {
        
    }
}
