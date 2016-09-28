<?php

namespace TopicCards\DbBackend;


use TopicCards\iTopicMap;

trait RoleDbAdapter
{
    public function selectAll(array $filters)
    {
        $ok = $this->services->db_utils->connect();

        if ($ok < 0)
            return $ok;

        if (! empty($filters[ 'reifier' ]))
        {
            // TODO to be implemented
            return -1;
        }

        if (! isset($filters[ 'association' ]))
        {
            return -1;
        }

        $query = 'MATCH (assoc:Association { id: {id} })-[rel]-(node:Topic) RETURN assoc, rel, node';
        $bind = [ 'id' => $filters[ 'association' ] ];

        $this->logger->addInfo($query, $bind);
        
        try
        {
            $qresult = $this->services->db->run($query, $bind);
        }
        catch (\GraphAware\Neo4j\Client\Exception\Neo4jException $exception)
        {
            $this->logger->addError($exception->getMessage());
            // TODO: Error handling
            return -1;
        }

        $result = [ ];

        foreach ($qresult->getRecords() as $record)
        {
            $rel = $record->get('rel');
            // TODO: Only fetch the topic ID, not the whole topic
            $node = $record->get('node');
            // TODO: Only fetch the association ID, not the whole association
            $assoc = $record->get('assoc');

            $row =
                [
                    'id' => ($rel->hasValue('id') ? $rel->value('id') : false),
                    'association' => ($assoc->hasValue('id') ? $assoc->value('id') : false),
                    'player' => ($node->hasValue('id') ? $node->value('id') : false)
                ];

            // Type

            $row[ 'type' ] = $rel->type();

            $result[ ] = $row;
        }

        return $result;
    }


    public function insertAll($association_id, array $data, \GraphAware\Neo4j\Client\Transaction\Transaction $transaction)
    {
        foreach ($data as $role_data)
        {
            $this->insertRole($association_id, $role_data, $transaction);
        }

        // TODO: error handling

        return 1;
    }
    
    
    public function updateAll($association_id, array $data, array $previous_data, \GraphAware\Neo4j\Client\Transaction\Transaction $transaction)
    {
        foreach ($data as $role_data)
        {
            // No ID? Must be a new role

            if (empty($role_data[ 'id' ]))
            {
                $ok = $this->insertRole($association_id, $role_data, $transaction);

                if ($ok < 0)
                {
                    return $ok;
                }

                continue;
            }

            // If the ID is not in $previous_data, it's a new role

            $found = false;

            foreach ($previous_data as $previous_role_data)
            {
                if ($previous_role_data[ 'id' ] === $role_data[ 'id' ])
                {
                    $found = true;
                    break;
                }
            }

            if (! $found)
            {
                $ok = $this->insertRole($association_id, $role_data, $transaction);

                if ($ok < 0)
                {
                    return $ok;
                }

                continue;
            }

            // It's an updated role...

            $ok = $this->updateRole($association_id, $role_data, $previous_role_data, $transaction);

            if ($ok < 0)
            {
                return $ok;
            }

            // TODO: handle role deletion, or empty value
        }

        // TODO: error handling
        return $ok;
    }
    
    
    protected function insertRole($association_id, array $data, \GraphAware\Neo4j\Client\Transaction\Transaction $transaction)
    {
        if ((! isset($data[ 'player' ])) || (strlen($data[ 'player' ]) === 0))
        {
            return 0;
        }

        if (empty($data[ 'type' ]))
        {
            return -1;
        }
        
        if (empty($data[ 'id' ]))
        {
            $data[ 'id' ] = $this->getTopicMap()->createId();
        }

        $property_data =
            [
                'id' => $data[ 'id' ]
            ];

        $bind = 
            [ 
                'association_id' => $association_id,
                'topic_id' => $data[ 'player' ]
            ];

        $property_query = $this->services->db_utils->propertiesString($property_data, $bind);

        $classes = [ $data[ 'type' ] ];

        $query = sprintf
        (
            'MATCH (a:Association), (t:Topic)'
            . ' WHERE a.id = {association_id} AND t.id = {topic_id}'
            . ' CREATE (a)-[r%s { %s }]->(t)',
            $this->services->db_utils->labelsString($classes),
            $property_query
        );

        $this->logger->addInfo($query, $bind);

        $transaction->push($query, $bind);

        // Mark type topics

        $type_queries = $this->services->db_utils->tmConstructLabelQueries
        (
            $this->topicmap,
            [ $data[ 'type' ] ],
            iTopicMap::SUBJECT_ASSOCIATION_ROLE_TYPE
        );

        foreach ($type_queries as $type_query)
        {
            $this->logger->addInfo($type_query['query'], $type_query['bind']);
            $transaction->push($type_query['query'], $type_query['bind']);
        }
        
        // TODO: error handling
        return 1;
    }


    protected function updateRole($association_id, array $data, array $previous_data, \GraphAware\Neo4j\Client\Transaction\Transaction $transaction)
    {
        $do_delete = $do_insert = false;
        $ok = 0;
        
        if ((! isset($data[ 'player' ])) || (strlen($data[ 'player' ]) === 0))
        {
            $do_delete = true;
        }
        elseif (($previous_data[ 'player' ] !== $data[ 'player' ]) || ($previous_data[ 'type' ] !== $data[ 'type' ]))
        {
            $do_delete = $do_insert = true;
        }
        
        if ($do_delete)
        {
            $bind = 
                [ 
                    'id' => $data[ 'id' ],
                    'association_id' => $association_id,
                    'player_id' => $previous_data[ 'player' ]
                ];
            
            $query = 'MATCH (a:Association { id: {association_id} })-[r { id: {id} }]-(t:Topic { id: {player_id} }) DELETE r';

            $this->logger->addInfo($query, $bind);
            
            $transaction->push($query, $bind);

            // TODO: error handling
            $ok = 1;
        }

        if ($do_insert)
        {
            $ok = $this->insertRole($association_id, $data, $transaction);
        }

        return $ok;
    }
}