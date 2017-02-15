<?php

namespace AppBundle\Rest;

class JsonApiValidator {
    
    protected static $instance;
    
    protected $topLevel = array(
                            'data', 
                            'errors', 
                            'meta', 
                            'jsonapi', 
                            'links', 
                            'included'
                        );
    
    protected $linksMembers = array(
                            'self',
                            'related',
                            'first',
                            'last',
                            'prev',
                            'next'
                        );
    
    protected $dataMembers = array(
                            'id',
                            'type',
                            'attributes',
                            'relationships',
                            'links',
                            'meta'
                        );
    protected $relationMembers = array(
                            'links',
                            'data',
                            'meta'
                        );
   
    protected $relationLinksMembers = array(
                            'self',
                            'related',
                            'first',
                            'last',
                            'prev',
                            'next'
                        );
    
    public static function getInstance()
    {
        if (!self::$instance)
        {
            self::$instance = new JsonApiValidator();
        }
        
        return self::$instance;
    }
    
    protected function __construct() {
    }
    
    public function validate($doc)
    {
        $atLeastOne = array_slice($this->topLevel, 0, 3);
        $keys = array_keys($doc);
        
        $topDiff = array_diff($keys, $this->topLevel);
        $intersect = array_intersect($keys, $atLeastOne);
        
        if (count($topDiff) > 0)
        {
            throw new Exception("Your request contains not valid top level JSONAPI fields: " . join(',', $topDiff) . "!");
        }
        
        if (count($intersect) == 0)
        {
            throw new Exception("At least one of data, errors, meta keys must be present!");
        }
        
        if (in_array('data', $intersect) and in_array('errors', $intersect))
        {
            throw new Exception("Data and errors field must not coexist in same request!");
        }
        
        if (!in_array('data', $intersect) and in_array('included', $keys))
        {
            throw new Exception("Included field must not be present when data field is missing!");
        }
        
        if (key_exists('links', $keys))
        {
            $linksDiff = array_diff($keys, $this->linksMembers);
            
            if (count($linksDiff) > 0)
            {
                throw new Exception("Links top level member can contain only these fields: " . join(",", $this->linksMembers) . "!");
            }
        }

        if (key_exists('data', $doc))
        {
            $this->validateDataField($doc);
        }
    }
    
    protected function isValidMemberName()
    {
        return function($name)
        {
            if (!strlen($name))
            {
                throw new Exception("Member name must contain at least one character!");
            }
            
            if (preg_match('/[^a-zA-Z0-9][^a-zA-Z0-9_-]+/', $name) === 0)
            {
                throw new Exception("Member name $name contains not allowed character!");
            }
        };
    }
    
    protected function validateDataMembers()
    {
        return function($data) 
        {
            $keys = array_keys($data);
            $dataDiff = array_diff($keys, $this->dataMembers);

            if (count($dataDiff) > 0)
            {
                throw new Exception("Data top level member can contain only these fields: " . join(",", $this->dataMembers) . "!");
            }
            
            if (!in_array('type', $keys))
            {
                throw new Exception("Field type must be present in data of object!");
            }
        };
    }
    
    protected function validataDataAttributes()
    {
        return function($data) {
            $depth = 1;
            $maxDepth = 5;
            
            $callback = function($item, $itemKey, &$depth) use ($maxDepth) {
                if ($depth > $maxDepth) {
                    throw new Exception("Recursion limit exceeded!");
                }
                
                if ($itemKey == 'relationships' or $itemKey == 'links')
                {
                    throw new Exception("Relationships and links fields not allowed in attributes field!");
                }
                
                $depth++;
            };
            
            array_walk_recursive($data['attributes'], $callback, $depth);
        };
    }
    
    protected function validateDataRelations()
    {
        return function($data) {
            if (!$this->isHash($data))
            {
                throw new Exception("Relationships must be object!");
            }
                
            foreach ($data['relationships'] as $relName => $relation)
            {
                $mandatory = array_slice($this->relationMembers, 0, 2);
                
                $relInter = array_intersect($mandatory, $relation);
                $relDiff = array_diff($relation, $this->relationMembers);
                
                if (empty($relInter))
                {
                    throw new Exception("Relationships object must contain at least one of " . join(',', $this->relationMembers) . "!");
                }

                if (count($relDiff) > 0)
                {
                    throw new Exception("Relationships object may contain only fields: " . join(',', $this->relationMembers) . "!");
                }
                
                if (in_array('links', $relation))
                {
                    $linkRelInter = array_intersect($this->relationLinksMembers, $relation['links']);
                    if (empty($linkRelInter))
                    {
                        throw new Exception("Relationships links object must contain at least one of " . join(',', $this->relationLinksMembers) . "!" );
                    }
                }
            
            }
        };
    }
    
    protected function validateDataField($dataFieldData)
    {            
        $validateMembers = $this->validateDataMembers();
        $validateAttributes = $this->validataDataAttributes();        
        $validateRelations = $this->validateDataRelations();

        if ($this->isHash($dataFieldData)) {
            $validateMembers($dataFieldData['data']);
            $validateAttributes($dataFieldData['data']);
            $validateRelations($dataFieldData['data']);
        } else {
            foreach ($dataFieldData['data'] as $member) {
                $validateMembers($member);
                $validateAttributes($member);
                $validateRelations($member);
            }
        }

    }
    
    protected function isHash($resource)
    {
        $return = 0;
        
        if (array_keys($resource) !== range(0, count($resource) - 1)) {
            $return = 1;
        } else {
            $return = 0;
        }
        
        return $return;
    }
}
