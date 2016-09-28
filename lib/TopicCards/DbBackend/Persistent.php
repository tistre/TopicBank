<?php

namespace TopicCards\DbBackend;


trait Persistent
{
    protected $created = false;
    protected $updated = false;
    protected $version = 0;
    protected $loaded = false;
    protected $index_related = false;
    
    /** @var array Copy of the data as it was on load (needed for label removal) */
    protected $previous_data = [ ];
    
    
    public function getCreated()
    {
        return $this->created;
    }
    
    
    public function setCreated($date)
    {
        $this->created = $date;
        return 1;
    }
    
    
    public function getUpdated()
    {
        return $this->updated;
    }
    
    
    public function setUpdated($date)
    {
        $this->updated = $date;
        return 1;
    }
    
    
    public function getVersion()
    {
        return $this->version;
    }
    
    
    public function setVersion($version)
    {
        $this->version = intval($version);
        return 1;
    }
    
    
    public function getAllPersistent()
    {   
        return
        [
            'created' => $this->getCreated(), 
            'updated' => $this->getUpdated(), 
            'version' => $this->getVersion()
        ];
    }
    
        
    public function setAllPersistent(array $data)
    {
        $data = array_merge(
        [
            'created' => false,
            'updated' => false,
            'version' => 0
        ], $data);
        
        $this->setCreated($data[ 'created' ]);
        $this->setUpdated($data[ 'updated' ]);
        $this->setVersion($data[ 'version' ]);
        
        return 1;
    }
    
    
    public function load($id)
    {
        $this->previous_data = [ ];
        
        $rows = $this->selectAll([ 'id' => $id ]);
        
        if (! is_array($rows))
        {
            return $rows;
        }
            
        if (count($rows) === 0)
        {
            return -1;
        }
            
        $ok = $this->setAll($rows[ 0 ]);
        
        if ($ok >= 0)
        {
            $this->previous_data = $this->getAll();
            $this->loaded = true;
        }
            
        return $ok;
    }
    
    
    public function isLoaded()
    {
        return $this->loaded;
    }


    public function save()
    {
        $ok = $this->validate($dummy);
        
        if ($ok < 0)
        {
            return $ok;
        }

        $this->resetIndexRelated();
        
        if ($this->getVersion() === 0)
        {
            if (strlen($this->getId()) === 0)
            {
                $this->setId($this->getTopicmap()->createId());
            }
                
            $ok = $this->insertAll($this->getAll());
        }
        else
        {
            $ok = $this->updateAll($this->getAll());
        }

        if ($ok >= 0)
        {
            $this->setVersion($this->getVersion() + 1);
            $this->previous_data = $this->getAll();
            
            $this->index();
            $this->indexRelated();
            $this->addHistoryItem(($this->getVersion() <= 1 ? 'i' : 'u'));
        }
                
        return $ok;
    }
    
    
    public function delete()
    {
        if ($this->getVersion() === 0)
            return 0;

        $this->removeFromIndex();
        
        $this->resetIndexRelated();
        
        $ok = $this->deleteById($this->getId(), $this->getVersion());
        
        // Sort of manual rollback: If deletion failed, re-add to index
        
        if ($ok < 0)
        {
            $this->index();
        }
        else
        {
            $this->indexRelated();
            $this->addHistoryItem('d');
        }        
           
        return $ok;
    }
    
    
    protected function resetIndexRelated()
    {
        $this->index_related = [ 'topic_id' => [ ], 'association_id' => [ ] ];
    }
    
    
    protected function addIndexRelated($add)
    {
        if (! is_array($this->index_related))
            $this->resetIndexReleated();
            
        if (! is_array($add))
            return 0;
        
        foreach ([ 'topic_id', 'association_id' ] as $key)
        {   
            if (isset($add[ $key ]) && is_array($add[ $key ]))
            {
                $this->index_related[ $key ] = array_merge
                (
                    $this->index_related[ $key ], 
                    $add[ $key ]
                );
            }
        }
        
        return 1;
    }
    
    
    protected function indexRelated()
    {
        // TODO to be implemented
        $cnt = 0;
        
        if (count($this->index_related[ 'topic_id' ]) > 0)
        {
            $topic = $this->getTopicMap()->newTopic();
            
            $topic_ids = array_unique($this->index_related[ 'topic_id' ]);
            
            foreach ($topic_ids as $topic_id)
            {
                $topic->load($topic_id);
                $topic->index();
                
                $cnt++;
            }
        }
        /* TODO implement associations
        if (count($this->index_related[ 'association_id' ]) > 0)
        {
            $association = $this->getTopicMap()->newAssociation();
            
            $association_ids = array_unique($this->index_related[ 'association_id' ]);

            foreach ($association_ids as $association_id)
            {
                $association->load($association_id);
                $association->index();
                
                $cnt++;
            }
        }
        */
        
        return $cnt;
    }


    protected function addHistoryItem($dml_type)
    {
        $this->services->search->index($this->topicmap,
            [
                'type' => 'history',
                'body' => 
                    [
                        'type' => $this->getSearchType(),
                        'id' => $this->getId(),
                        'when' => date('c'),
                        'dml' => $dml_type
                    ]
            ]);
        
        return 1;
    }


    public function getHistoryItems()
    {
        $result = [ ];
        
        $query =
            [
                'query' => [ 'filtered' => [ 'filter' => [ 'term' => [ 'id' => $this->getId() ] ] ] ],
                'size' => 50,
                'from' => 0
            ];
        
        $response = $this->services->search->search($this->topicmap,
            [
                'type' => 'history',
                'body' => $query
            ]);

        if (empty($response[ 'hits' ][ 'hits' ]))
        {
            return $result;
        }
        
        foreach ($response[ 'hits' ][ 'hits' ] as $hit)
        {
            $result[ ] = $hit[ '_source' ];
        }
        
        return $result;
    }
}