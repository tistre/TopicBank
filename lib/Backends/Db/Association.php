<?php

namespace TopicBank\Backends\Db;


class Association extends Core implements \TopicBank\Interfaces\iAssociation
{
    use Id, Persistent, Reified, Scoped, Typed, AssociationDbAdapter, AssociationSearchAdapter, Searchable;
    
    protected $roles = [ ];
    
    
    public function newRole()
    {   
        $role = new Role($this->services, $this->topicmap);
        
        $this->roles[ ] = $role;
        
        return $role;
    }


    public function getRoles(array $filters = [ ])
    {
        if (count($filters) === 0)            
            return $this->roles;
        
        $result = [ ];
        
        if (isset($filters[ 'type' ]))
            $filters[ 'type_id' ] = $this->getTopicMap()->getTopicIdBySubject($filters[ 'type' ]);

        if (isset($filters[ 'player' ]))
            $filters[ 'player_id' ] = $this->getTopicMap()->getTopicIdBySubject($filters[ 'player' ]);

        foreach ($this->roles as $role)
        {
            $match = true;
            
            if (isset($filters[ 'type_id' ]) && ($role->getTypeId() !== $filters[ 'type_id' ]))
                $match = false;
                
            if (isset($filters[ 'player_id' ]) && ($role->getPlayerId() !== $filters[ 'player_id' ]))
                $match = false;
                
            if ($match)
                $result[ ] = $role;
        }
        
        return $result;
    }
    
    
    public function setRoles(array $roles)
    {
        $this->roles = $roles;
        return 1;
    }
    

    public function getFirstRole(array $filters = [ ])
    {
        $roles = $this->getRoles($filters);
        
        if (count($roles) > 0)
            return $roles[ 0 ];

        $role = $this->newRole();
        
        if (isset($filters[ 'type' ]))
        {
            $role->setType($filters[ 'type' ]);
        }
        elseif (isset($filters[ 'type_id' ]))
        {
            $role->setTypeId($filters[ 'type_id' ]);
        }
        
        if (isset($filters[ 'player' ]))
        {
            $role->setPlayer($filters[ 'player' ]);
        }
        elseif (isset($filters[ 'player_id' ]))
        {
            $role->setTypeId($filters[ 'player_id' ]);
        }
        
        return $role;
    }

    
    public function validate(&$msg_html)
    {
        $result = 1;
        $msg_html = '';
        
        foreach ($this->getRoles([ ]) as $role)
        {
            $ok = $role->validate($msg);
            
            if ($ok < 0)
            {
                $result = $ok;
                $msg_html .= $msg;
            }
        }
        
        return $result;
    }
    
    
    public function getAll()
    {
        $result = 
        [
            'roles' => [ ]
        ];

        foreach ($this->getRoles() as $role)
            $result[ 'roles' ][ ] = $role->getAll();
            
        $result = array_merge($result, $this->getAllId());

        $result = array_merge($result, $this->getAllPersistent());

        $result = array_merge($result, $this->getAllTyped());

        $result = array_merge($result, $this->getAllReified());

        $result = array_merge($result, $this->getAllScoped());

        return $result;
    }
    
    
    public function setAll(array $data)
    {
        $data = array_merge(
        [
            'roles' => [ ]
        ], $data);
        
        $this->setAllId($data);
        
        $this->setAllPersistent($data);
        
        $this->setAllTyped($data);
            
        $this->setAllReified($data);
            
        $this->setAllScoped($data);
        
        $this->setRoles([ ]);
        
        foreach ($data[ 'roles' ] as $role_data)
        {
            $role = $this->newRole();
            $role->setAll($role_data);
        }
        
        return 1;
    }
}
