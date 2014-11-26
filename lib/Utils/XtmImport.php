<?php

namespace TopicBank\Utils;


class XtmImport
{
    protected $topicmap;
    protected $generated_guids = [ ];
    
    
    public function __construct(\TopicBank\Interfaces\iTopicMap $topicmap)
    {
        $this->topicmap = $topicmap;
    }
    
    
    public function importObjects($xml)
    {        
        $dom = new \DOMDocument();
        
        $ok = $dom->loadXML($xml);
        
        if ($ok === false)
            return false;

        $result = [ ];
        
        foreach ($dom->documentElement->childNodes as $node)
        {
            if ($node->nodeType != XML_ELEMENT_NODE)
                continue;
                
            if ($node->tagName === 'topic')
            {
                $result[ ] = $this->importTopic($node);
            }
            elseif ($node->tagName === 'association')
            {
                $result[ ] = $this->importAssociation($node);
            }
        }
        
        return $result;
    }
    
    
    public function importTopic(\DOMElement $context_node)
    {
        $topic = $this->topicmap->newTopic();

        if ($context_node->hasAttribute('id'))
            $topic->setId($this->generateGuid($context_node->getAttribute('id')));

        $this->importTypes($context_node, $topic);
        $this->importSubjectIdentifiers($context_node, $topic);
        $this->importSubjectLocators($context_node, $topic);
        $this->importNames($context_node, $topic);
        $this->importOccurrences($context_node, $topic);
        
        return $topic;
    }
    
    
    public function importAssociation(\DOMElement $context_node)
    {
        $association = $this->topicmap->newAssociation();

        if ($context_node->hasAttribute('id'))
            $association->setId($this->generateGuid($context_node->getAttribute('id')));

        if ($context_node->hasAttribute('reifier'))
            $association->setReifier($this->topicRefToId($context_node->getAttribute('reifier')));

        $association->setType($this->getType($context_node));
        $association->setScope($this->getScope($context_node));

        $this->importRoles($context_node, $association);
        
        return $association;
    }
    
    
    protected function importTypes(\DOMElement $context_node, \TopicBank\Interfaces\iTopic $topic)
    {
        $topic_refs = [ ];
        
        foreach ($context_node->getElementsByTagName('instanceOf') as $node)
        {
            $topic_ref = $this->getTopicRef($node);
            
            if (strlen($topic_ref) > 0)
                $topic_refs[ ] = $topic_ref;
        }
        
        $topic->setTypes($topic_refs);
    }
    
    
    protected function importSubjectIdentifiers(\DOMElement $context_node, \TopicBank\Interfaces\iTopic $topic)
    {
        $this->importSubjects('subjectIdentifier', $context_node, $topic);
    }
    
    
    protected function importSubjectLocators(\DOMElement $context_node, \TopicBank\Interfaces\iTopic $topic)
    {
        $this->importSubjects('subjectLocator', $context_node, $topic);
    }
    
    
    protected function importSubjects($what, \DOMElement $context_node, \TopicBank\Interfaces\iTopic $topic)
    {
        $hrefs = [ ];
        
        foreach ($context_node->getElementsByTagName($what) as $node)
        {
            if (! $node->hasAttribute('href'))
                continue;
                
            $hrefs[ ] = $node->getAttribute('href');
        }
        
        $method = sprintf('set%ss', $what);
        
        $topic->$method($hrefs);
    }


    protected function importNames(\DOMElement $context_node, \TopicBank\Interfaces\iTopic $topic)
    {
        $names = [ ];
        
        foreach ($context_node->getElementsByTagName('name') as $node)
        {
            $name = $topic->newName();
            
            $name->setType($this->getType($node));
            $name->setScope($this->getScope($node));
            
            foreach ($node->getElementsByTagName('value') as $subnode)
                $name->setValue($subnode->nodeValue);

            if ($node->hasAttribute('reifier'))
                $name->setReifier($this->topicRefToId($node->getAttribute('reifier')));
                
            $names[ ] = $name;
        }
        
        $topic->setNames($names);
    }
    
    
    protected function importOccurrences(\DOMElement $context_node, \TopicBank\Interfaces\iTopic $topic)
    {
        $occurrences = [ ];
        
        foreach ($context_node->getElementsByTagName('occurrence') as $node)
        {
            $occurrence = $topic->newOccurrence();
            
            $occurrence->setType($this->getType($node));
            $occurrence->setScope($this->getScope($node));
            
            foreach ($node->getElementsByTagName('resourceData') as $subnode)
            {
                $datatype = $subnode->getAttribute('datatype');
                
                $occurrence->setValue(\TopicBank\Utils\DatatypeUtils::getValueFromDomNode($subnode, $datatype));
                
                $occurrence->setDataType
                (
                    $topic->getTopicMap()->getTopicBySubjectIdentifier($datatype)
                );
            }
                
            if ($node->hasAttribute('reifier'))
                $occurrence->setReifier($this->topicRefToId($node->getAttribute('reifier')));
                
            $occurrences[ ] = $occurrence;
        }
        
        $topic->setOccurrences($occurrences);
    }
    
    
    protected function importRoles(\DOMElement $context_node, \TopicBank\Interfaces\iAssociation $association)
    {
        $roles = [ ];
        
        foreach ($context_node->getElementsByTagName('role') as $node)
        {
            $role = $association->newRole();
            
            $role->setType($this->getType($node));
            $role->setPlayer($this->getTopicRef($node));
            
            if ($node->hasAttribute('reifier'))
                $role->setReifier($this->topicRefToId($node->getAttribute('reifier')));
                
            $roles[ ] = $role;
        }
        
        $association->setRoles($roles);
    }
    
    
    protected function getType(\DOMElement $node)
    {
        foreach ($node->getElementsByTagName('type') as $subnode)
            return $this->getTopicRef($subnode);
        
        return false;
    }


    protected function getScope(\DOMElement $node)
    {
        $result = [ ];
        
        foreach ($node->getElementsByTagName('scope') as $subnode)
        {
            $scope = $this->getTopicRef($subnode);
            
            if (strlen($scope) > 0)
                $result[ ] = $scope;
        }
        
        return $result;
    }


    protected function getTopicRef(\DOMElement $node)
    {
        foreach ($node->childNodes as $subnode)
        {
            if ($subnode->nodeType != XML_ELEMENT_NODE)
                continue;
                
            if ($subnode->tagName !== 'topicRef')
                continue;

            if (! $subnode->hasAttribute('href'))
                continue;

            // XXX return an error if subject does not exist!
            
            $topic_ref = $subnode->getAttribute('href');
            
            if (strlen($topic_ref) === 0)
                continue;
            
            return $this->topicRefToId($topic_ref);
        }
        
        return false;
    }
    
    
    protected function topicRefToId($topic_ref)
    {
        if (strlen($topic_ref) === 0)
            continue;
            
        // Local IDs are prefixed with "#"
        // XXX does this conform to the XTM 2.0 spec?
        
        if ($topic_ref[ 0 ] === '#')
        {
            $topic_ref = $this->generateGuid($topic_ref);
            return substr($topic_ref, 1);
        }
        else
        {
            return $this->topicmap->getTopicBySubjectIdentifier($topic_ref);
        }
    }
    
    
    protected function generateGuid($id)
    {
        // #topicbank-generate-uuid:idm38524599744 => 7b1931ef-d101-4a7b-81de-b174ab7872df
        // topicbank-generate-uuid:idm38524599744 => 7b1931ef-d101-4a7b-81de-b174ab7872df

        if (strlen($id) === 0)
            return $id;
        
        $prefix = '';
        
        if ($id[ 0 ] === '#')
        {
            $prefix = '#';
            $id = substr($id, 1);
        }
        
        if (substr($id, 0, 24) !== 'topicbank-generate-uuid:')
            return $prefix . $id;

        $key = substr($id, 24);
        
        if (! isset($this->generated_guids[ $key ]))
            $this->generated_guids[ $key ] = $this->topicmap->createId();

        return $prefix . $this->generated_guids[ $key ];
    }    
}
