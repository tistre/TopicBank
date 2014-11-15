<?php

namespace TopicBank\Backends\Db;


trait OccurrenceDbAdapter
{
    public function selectAll(array $filters)
    {
        $ok = $this->services->db_utils->connect();
        
        if ($ok < 0)
            return $ok;

        if (isset($filters[ 'topic' ]))
        {
            $where = 'occurrence_topic = :topic_id';
        }
        elseif (isset($filters[ 'reifier' ]))
        {
            $where = 'occurrence_reifier = :reifier_id';
        }
        
        $prefix = $this->topicmap->getDbTablePrefix();
        
        $sql = $this->services->db->prepare(sprintf
        (
            'select * from %soccurrence'
            . ' where ' . $where, 
            $prefix
        ));

        if (isset($filters[ 'topic' ]))
        {
            $sql->bindValue(':topic_id', $filters[ 'topic' ], \PDO::PARAM_STR);
        }
        elseif (isset($filters[ 'reifier' ]))
        {
            $sql->bindValue(':reifier_id', $filters[ 'reifier' ], \PDO::PARAM_STR);
        }
        
        $ok = $sql->execute();
        
        if ($ok === false)
            return -1;

        $result = [ ];
        $occurrence_ids = [ ];
        
        foreach ($sql->fetchAll() as $row)
        {
            $row = $this->services->db_utils->stripColumnPrefix('occurrence_', $row);
            $row[ 'scope' ] = $this->selectScopes([ 'occurrence' => intval($row[ 'id' ]) ]);
            
            $result[ ] = $row;
            $occurrence_ids[ ] = intval($row[ 'id' ]) ;
        }
                    
        return $result;
    }


    public function insertAll($topic_id, array $data)
    {
        $ok = $this->services->db_utils->connect();
        
        if ($ok < 0)
            return $ok;
        
        foreach ($data as $name_data)
        {
            $values = [ ];
        
            $name_data[ 'topic' ] = $topic_id;

            foreach ($name_data as $key => $value)
            {
                if ($key === 'scope')
                    continue;
                    
                // PostgreSQL "serial" does not kick in if we provide an empty value
                
                if (($key === 'id') && (strlen($value) === 0))
                    continue;
                    
                $values[ ] =
                [
                    'column' => 'occurrence_' . $key,
                    'value' => $value
                ];
            }
        
            $sql = $this->services->db_utils->prepareInsertSql
            (
                $this->topicmap->getDbTablePrefix() . 'occurrence', 
                $values
            );
        
            $ok = $sql->execute();
        
            if ($ok === false)
                return -1;

            $name_id = $this->services->db->lastInsertId();
            
            $ok = $this->insertScopes('occurrence', $name_id, $name_data[ 'scope' ]);
            
            if ($ok < 0)
                return $ok;
        }
        
        return 1;
    }
    
    
    public function updateAll($topic_id, array $data)
    {
        $ok = $this->services->db_utils->connect();
        
        if ($ok < 0)
            return $ok;

        $sql = $this->services->db_utils->prepareDeleteSql
        (
            $this->topicmap->getDbTablePrefix() . 'occurrence', 
            [ 
                [ 'column' => 'occurrence_topic', 'value' => $topic_id ]
            ]
        );
    
        $ok = $sql->execute();
    
        if ($ok === false)
            return -1;

        return $this->insertAll($topic_id, $data);        
    }
}
