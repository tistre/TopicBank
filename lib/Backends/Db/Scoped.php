<?php

namespace TopicBank\Backends\Db;


trait Scoped
{
    use ScopedDbAdapter;
    
    protected $scope = [ ];
    
    
    public function getScope()
    {
        return $this->scope;
    }
    
    
    public function setScope(array $topic_ids)
    {
        $this->scope = $topic_ids;
        return 1;
    }


    public function getScopeSubjects()
    {
        $result = [ ];
        
        foreach ($this->getScope() as $topic_id)
            $result[ ] = $this->getTopicMap()->getTopicSubject($topic_id);
            
        return $result;
    }


    public function setScopeSubjects(array $topic_subjects)
    {
        $topic_ids = [ ];
        $result = 1;
        
        foreach ($topic_subjects as $topic_subject)
        {
            $topic_id = $this->getTopicMap()->getTopicBySubject($topic_subject);
            
            if (strlen($topic_id) === 0)
            {
                $result = -1;
            }
            else
            {
                $topic_ids[ ] = $topic_id;
            }   
        }
        
        $ok = $this->setScope($topic_ids);
        
        if ($ok < 0)
            $result = $ok;
        
        return $result;
    }


    public function getAllScoped()
    {
        return
        [
            'scope' => $this->getScope()
        ];
    }


    public function setAllScoped(array $data)
    {
        $data = array_merge(
        [
            'scope' => [ ]
        ], $data);
        
        return $this->setScope($data[ 'scope' ]);
    }
    
    
    public function matchesScope(array $match_topic_ids)
    {
        $my_topic_ids = $this->getScope();

        $my_count = count($my_topic_ids);
        $match_count = count($match_topic_ids);
        
        // Short cut: If counts differ, scopes cannot match
        
        if ($my_count !== $match_count)
            return false;
        
        // Exact match, independent of order
        
        return
        (
            (count(array_diff($my_topic_ids, $match_topic_ids)) === 0)
            && (count(array_diff($match_topic_ids, $my_topic_ids)) === 0)
        );
    }
}
