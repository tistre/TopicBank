<?php

namespace TopicBank\Backends\Db;

use \TopicBank\Interfaces\iTopic;


trait TopicDbAdapter
{
    public function selectAll(array $filters)
    {
        $ok = $this->services->db_utils->connect();
        
        if ($ok < 0)
            return $ok;
        
        $prefix = $this->topicmap->getDbTablePrefix();
        
        $sql = $this->services->db->prepare(sprintf
        (
            'select * from %stopic'
            . ' where topic_id = :topic_id', 
            $prefix
        ));

        $sql->bindValue(':topic_id', $filters[ 'id' ], \PDO::PARAM_STR);
        
        $ok = $sql->execute();
        
        if ($ok === false)
            return -1;

        $result = [ ];
        
        $name = new Name($this->services, $this->topicmap);
        $occurrence = new Occurrence($this->services, $this->topicmap);
        
        foreach ($sql->fetchAll() as $row)
        {
            $row = $this->services->db_utils->stripColumnPrefix('topic_', $row);
            
            $row[ 'types' ] = $this->selectTypes([ 'topic' => $row[ 'id' ] ]);
            
            $row[ 'subject_identifiers' ] = $this->selectSubjectIdentifiers([ 'topic' => $row[ 'id' ] ]);

            $row[ 'subject_locators' ] = $this->selectSubjectLocators([ 'topic' => $row[ 'id' ] ]);

            $row[ 'names' ] = $name->selectAll([ 'topic' => $row[ 'id' ] ]);

            $row[ 'occurrences' ] = $occurrence->selectAll([ 'topic' => $row[ 'id' ] ]);

            $result[ ] = $row;
        }

        return $result;        
    }
    

    protected function selectTypes(array $filters)
    {
        $ok = $this->services->db_utils->connect();
        
        if ($ok < 0)
            return $ok;
        
        $prefix = $this->topicmap->getDbTablePrefix();

        $sql = $this->services->db->prepare(sprintf
        (
            'select type_type from %stype'
            . ' where type_topic = :type_topic', 
            $prefix
        ));
        
        $sql->bindValue(':type_topic', $filters[ 'topic' ], \PDO::PARAM_STR);
        
        $ok = $sql->execute();
        
        if ($ok === false)
            return -1;

        $rows = $sql->fetchAll();
        
        $result = [ ];
        
        foreach ($rows as $row)
            $result[ ] = $row[ 'type_type' ];
            
        return $result;
    }    


    protected function selectSubjectIdentifiers(array $filters)
    {
        return $this->selectSubjects($filters, 0);
    }
    

    protected function selectSubjectLocators(array $filters)
    {
        return $this->selectSubjects($filters, 1);
    }
    

    protected function selectSubjects(array $filters, $islocator)
    {
        $ok = $this->services->db_utils->connect();
        
        if ($ok < 0)
            return $ok;
        
        $prefix = $this->topicmap->getDbTablePrefix();
        
        $sql = $this->services->db->prepare(sprintf
        (
            'select subject_value from %ssubject'
            . ' where subject_topic = :subject_topic'
            . ' and subject_islocator = :subject_islocator'
            . ' order by subject_id', 
            $prefix
        ));
        
        $sql->bindValue(':subject_topic', $filters[ 'topic' ], \PDO::PARAM_STR);
        $sql->bindValue(':subject_islocator', $islocator, \PDO::PARAM_INT);
        
        $ok = $sql->execute();
        
        if ($ok === false)
            return -1;

        $rows = $sql->fetchAll();
        
        $result = [ ];
        
        foreach ($rows as $row)
            $result[ ] = $row[ 'subject_value' ];
            
        return $result;
    }


    public function selectIsReifier(&$reifies_what, &$reifies_id)
    {
        $result = $reifies_what = $reifies_id = false;
        
        $prefix = $this->topicmap->getDbTablePrefix();

        // PostgreSQL needs a cast() in a union query that joins text and number,
        // MySQL doesn't
        
        $do_cast = ($this->services->db->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql');
        
        $sql = $this->services->db->prepare(sprintf
        (
            "select %s as reifies_id, '%d' as reifies_what"
            . " from %sname where name_reifier = :topic_id"
            . " union select %s as reifies_id, '%d' as reifies_what"
            . " from %soccurrence where occurrence_reifier = :topic_id"
            . " union select association_id as reifies_id, '%d' as reifies_what"
            . " from %sassociation where association_reifier = :topic_id"
            . " union select %s as reifies_id, '%d' as reifies_what"
            . " from %srole where role_reifier = :topic_id",
            ($do_cast ? 'cast(name_id as text)' : 'name_id'),
            iTopic::REIFIES_NAME, 
            $prefix,
            ($do_cast ? 'cast(occurrence_id as text)' : 'occurrence_id'),
            iTopic::REIFIES_OCCURRENCE, 
            $prefix,
            iTopic::REIFIES_ASSOCIATION, 
            $prefix,
            ($do_cast ? 'cast(role_id as text)' : 'role_id'),
            iTopic::REIFIES_ROLE, 
            $prefix
        ));

        $sql->bindValue(':topic_id', $this->id, \PDO::PARAM_STR);
        
        $ok = $sql->execute();
        
        if ($ok === false)
            return $result;

        $rows = $sql->fetchAll();
        
        foreach ($rows as $row)
        {
            $reifies_what = intval($row[ 'reifies_what' ]);
            $reifies_id = $row[ 'reifies_id' ];
            $result = true;
        }
            
        return $result;
    }
    
    
    public function selectReifiedObject($reifies_what)
    {
        $result = false;
        
        $map =
        [
            iTopic::REIFIES_NAME => 'Name',
            iTopic::REIFIES_OCCURRENCE => 'Occurrence',
            iTopic::REIFIES_ASSOCIATION => 'Association',
            iTopic::REIFIES_ROLE => 'Role'
        ];
        
        if (! isset($map[ $reifies_what ]))
            return false;
        
        $method = 'selectReifiedObject_' . $map[ $reifies_what ];
        
        return $this->$method();
    }
    
    
    protected function selectReifiedObject_Name()
    {
        $name = new Name($this->services, $this->topicmap);

        $rows = $name->selectAll([ 'reifier' => $this->id ]);
    
        if (count($rows) === 0)
            return false;

        $topic = new Topic($this->services, $this->topicmap);
        $ok = $topic->load($rows[ 0 ][ 'topic' ]);
        
        if ($ok < 0)
            return false;

        foreach ($topic->getNames([ 'reifier' => $this->id ]) as $name)
        {
            if ($name->getId() !== $rows[ 0 ][ 'id' ])
                continue;
                
            return
            [
                'topic' => $topic,
                'name' => $name
            ];
        }
        
        return false;
    }
    
    
    protected function selectReifiedObject_Occurrence()
    {
        $occurrence = new Occurrence($this->services, $this->topicmap);

        $rows = $occurrence->selectAll([ 'reifier' => $this->id ]);
    
        if (count($rows) === 0)
            return false;

        $topic = new Topic($this->services, $this->topicmap);
        $ok = $topic->load($rows[ 0 ][ 'topic' ]);
        
        if ($ok < 0)
            return false;

        foreach ($topic->getOccurrences([ ]) as $occurrence)
        {
            if ($occurrence->getId() !== $rows[ 0 ][ 'id' ])
                continue;
                
            return
            [
                'topic' => $topic,
                'occurrence' => $occurrence
            ];
        }
        
        return false;
    }
    
    
    protected function selectReifiedObject_Association()
    {
        $association = new Association($this->services, $this->topicmap);

        $rows = $association->selectAll([ 'reifier' => $this->id ]);
    
        if (count($rows) === 0)
            return false;

        $ok = $association->load($rows[ 0 ][ 'id' ]);
        
        if ($ok < 0)
            return false;

        return
        [
            'association' => $association,
        ];
    }
    
    
    protected function selectReifiedObject_Role()
    {
        $role = new Role($this->services, $this->topicmap);

        $rows = $role->selectAll([ 'reifier' => $this->id ]);
    
        if (count($rows) === 0)
            return false;

        $association = new Association($this->services, $this->topicmap);
        $ok = $association->load($rows[ 0 ][ 'association' ]);
        
        if ($ok < 0)
            return false;

        foreach ($association->getRoles() as $role)
        {
            if ($role->getId() !== $rows[ 0 ][ 'id' ])
                continue;
                
            return
            [
                'association' => $association,
                'role' => $role
            ];
        }
        
        return false;
    }
    
    
    public function insertAll(array $data)
    {
        $ok = $this->services->db_utils->connect();
        
        if ($ok < 0)
            return $ok;
        
        $this->services->db_utils->beginTransaction();
        
        $now = date('c');        
        $data[ 'created' ] = $data[ 'updated' ] = $now;
        
        $data[ 'version' ] = 1;
        
        $values = [ ];
        
        foreach ($data as $key => $value)
        {
            $ignore = [ 'types', 'subject_identifiers', 'subject_locators', 'names', 'occurrences' ];
            
            if (in_array($key, $ignore))
                continue;
            
            if (($key === 'created') || ($key === 'updated'))
                $value = $this->services->db_utils->datetimeToDb($value);
            
            $datatype = \PDO::PARAM_STR;
            
            if ($key === 'version')
                $datatype = \PDO::PARAM_INT;
                
            $values[ ] =
            [
                'column' => 'topic_' . $key,
                'value' => $value,
                'datatype' => $datatype
            ];
        }
        
        $sql = $this->services->db_utils->prepareInsertSql
        (
            $this->topicmap->getDbTablePrefix() . 'topic', 
            $values
        );
        
        $ok = $sql->execute();
        
        $ok = ($ok === false ? -1 : 1);
        
        if ($ok >= 0)
            $ok = $this->insertTypes($data[ 'id' ], $data[ 'types' ]);
        
        if ($ok >= 0)
            $ok = $this->insertSubjectIdentifiers($data[ 'id' ], $data[ 'subject_identifiers' ]);
        
        if ($ok >= 0)
            $ok = $this->insertSubjectLocators($data[ 'id' ], $data[ 'subject_locators' ]);
        
        if ($ok >= 0)
        {
            $name = new Name($this->services, $this->topicmap);
            $ok = $name->insertAll($data[ 'id' ], $data[ 'names' ]);
        }

        if ($ok >= 0)
        {
            $occurrence = new Occurrence($this->services, $this->topicmap);
            $ok = $occurrence->insertAll($data[ 'id' ], $data[ 'occurrences' ]);
        }

        if ($ok >= 0)
        {
            $callback_result = [ ];
            
            $ok = $this->topicmap->trigger
            (
                iTopic::EVENT_SAVING, 
                [ 'topic' => $this, 'dml' => 'insert' ],
                $callback_result
            );
            
            if (isset($callback_result[ 'index_related' ]))
                $this->addIndexRelated($callback_result[ 'index_related' ]);
        }
            
        if ($ok < 0)
        {
            $this->services->db_utils->rollBack();
            return $ok;
        }

        $this->services->db_utils->commit();

        return $ok;
    }


    protected function insertTypes($topic_id, array $types)
    {
        $ok = $this->services->db_utils->connect();
        
        if ($ok < 0)
            return $ok;
        
        foreach ($types as $type)
        {
            $values = [ ];

            $values[ ] =
            [
                'column' => 'type_topic',
                'value' => $topic_id
            ];
        
            $values[ ] =
            [
                'column' => 'type_type',
                'value' => $type
            ];
        
            $sql = $this->services->db_utils->prepareInsertSql
            (
                $this->topicmap->getDbTablePrefix() . 'type', 
                $values
            );
        
            $ok = $sql->execute();
        
            if ($ok === false)
                return -1;
        }
        
        return 1;
    }


    protected function insertSubjectIdentifiers($topic_id, array $subject_identifiers)
    {
        return $this->insertSubjects($topic_id, $subject_identifiers, 0);
    }
    

    protected function insertSubjectLocators($topic_id, array $subject_locators)
    {
        return $this->insertSubjects($topic_id, $subject_locators, 1);
    }
    

    protected function insertSubjects($topic_id, array $subjects, $islocator)
    {
        $ok = $this->services->db_utils->connect();
        
        if ($ok < 0)
            return $ok;
        
        foreach ($subjects as $subject)
        {
            $values = [ ];

            $values[ ] =
            [
                'column' => 'subject_topic',
                'value' => $topic_id
            ];
        
            $values[ ] =
            [
                'column' => 'subject_value',
                'value' => $subject
            ];
        
            $values[ ] =
            [
                'column' => 'subject_islocator',
                'value' => intval($islocator),
                'datatype' => \PDO::PARAM_INT
            ];
        
            $sql = $this->services->db_utils->prepareInsertSql
            (
                $this->topicmap->getDbTablePrefix() . 'subject', 
                $values
            );
        
            $ok = $sql->execute();
        
            if ($ok === false)
                return -1;
        }
        
        return 1;
    }


    public function updateAll(array $data)
    {
        $ok = $this->services->db_utils->connect();
        
        if ($ok < 0)
            return $ok;
        
        $this->services->db_utils->beginTransaction();
        
        $previous_version = $data[ 'version' ];
        
        $data[ 'updated' ] = date('c');        
        $data[ 'version' ]++;
        
        $values = [ ];
        
        foreach ($data as $key => $value)
        {
            $ignore = [ 'id', 'created', 'types', 'subject_identifiers', 'subject_locators', 'names', 'occurrences' ];
            
            if (in_array($key, $ignore))
                continue;
            
            if (($key === 'created') || ($key === 'updated'))
                $value = $this->services->db_utils->datetimeToDb($value);
            
            $datatype = \PDO::PARAM_STR;
            
            if ($key === 'version')
                $datatype = \PDO::PARAM_INT;
                
            $values[ ] =
            [
                'column' => 'topic_' . $key,
                'value' => $value,
                'datatype' => $datatype
            ];
        }
        
        $sql = $this->services->db_utils->prepareUpdateSql
        (
            $this->topicmap->getDbTablePrefix() . 'topic', 
            $values,
            [
                [
                    'column' => 'topic_id',
                    'value' => $data[ 'id' ]
                ],
                [
                    'column' => 'topic_version',
                    'value' => $previous_version,
                    'datatype' => \PDO::PARAM_INT
                ]
            ]
        );

        $ok = $sql->execute();
        
        $ok = ($ok === false ? -1 : 1);
        
        if (($ok >= 0) && ($sql->rowCount() !== 1))
            $ok = -2;

        if ($ok >= 0)
            $ok = $this->updateTypes($data[ 'id' ], $data[ 'types' ]);
        
        if ($ok >= 0)
            $ok = $this->updateSubjectIdentifiers($data[ 'id' ], $data[ 'subject_identifiers' ]);
        
        if ($ok >= 0)
            $ok = $this->updateSubjectLocators($data[ 'id' ], $data[ 'subject_locators' ]);
        
        if ($ok >= 0)
        {
            $name = new Name($this->services, $this->topicmap);
            $ok = $name->updateAll($data[ 'id' ], $data[ 'names' ]);
        }

        if ($ok >= 0)
        {
            $occurrence = new Occurrence($this->services, $this->topicmap);
            $ok = $occurrence->updateAll($data[ 'id' ], $data[ 'occurrences' ]);
        }

        if ($ok >= 0)
        {
            $callback_result = [ ];

            $ok = $this->topicmap->trigger
            (
                iTopic::EVENT_SAVING, 
                [ 'topic' => $this, 'dml' => 'update' ],
                $callback_result
            );

            if (isset($callback_result[ 'index_related' ]))
                $this->addIndexRelated($callback_result[ 'index_related' ]);
        }

        if ($ok < 0)
        {
            $this->services->db_utils->rollBack();
            return $ok;
        }

        $this->services->db_utils->commit();

        return $ok;
    }


    protected function updateTypes($topic_id, array $types)
    {
        $ok = $this->services->db_utils->connect();
        
        if ($ok < 0)
            return $ok;

        $sql = $this->services->db_utils->prepareDeleteSql
        (
            $this->topicmap->getDbTablePrefix() . 'type', 
            [ [ 'column' => 'type_topic', 'value' => $topic_id ] ]
        );
    
        $ok = $sql->execute();
    
        if ($ok === false)
            return -1;
        
        return $this->insertTypes($topic_id, $types);
    }


    protected function updateSubjectIdentifiers($topic_id, array $subject_identifiers)
    {
        return $this->updateSubjects($topic_id, $subject_identifiers, 0);
    }
    

    protected function updateSubjectLocators($topic_id, array $subject_locators)
    {
        return $this->updateSubjects($topic_id, $subject_locators, 1);
    }
    

    protected function updateSubjects($topic_id, array $subjects, $islocator)
    {
        $ok = $this->services->db_utils->connect();
        
        if ($ok < 0)
            return $ok;

        $sql = $this->services->db_utils->prepareDeleteSql
        (
            $this->topicmap->getDbTablePrefix() . 'subject', 
            [ 
                [ 'column' => 'subject_topic', 'value' => $topic_id ],
                [ 'column' => 'subject_islocator', 'value' => intval($islocator), 'datatype' => \PDO::PARAM_INT ]
            ]
        );
    
        $ok = $sql->execute();
    
        if ($ok === false)
            return -1;
        
        return $this->insertSubjects($topic_id, $subjects, $islocator);
    }
    
    
    public function deleteById($id, $version)
    {
        $ok = $this->services->db_utils->connect();
        
        if ($ok < 0)
            return $ok;

        $this->services->db_utils->beginTransaction();

        $prefix = $this->topicmap->getDbTablePrefix();

        $sql = $this->services->db_utils->prepareDeleteSql
        (
            $prefix . 'topic', 
            [ 
                [ 'column' => 'topic_id', 'value' => $id ],
                [ 'column' => 'topic_version', 'value' => $version ]
            ]
        );
    
        $ret = $sql->execute();
    
        if ($ret === false)
            $ok = -1;
            
        if ($ok >= 0)              
        {  
            $callback_result = [ ];

            $ok = $this->topicmap->trigger
            (
                iTopic::EVENT_DELETING, 
                [ 'topic_id' => $id ],
                $callback_result
            );

            if (isset($callback_result[ 'index_related' ]))
                $this->addIndexRelated($callback_result[ 'index_related' ]);
        }
            
        if ($ok < 0)
        {
            $this->services->db_utils->rollBack();
            return $ok;
        }

        $this->services->db_utils->commit();            

        return 1;
    }
}