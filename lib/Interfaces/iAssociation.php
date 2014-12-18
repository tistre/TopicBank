<?php

namespace TopicBank\Interfaces;


interface iAssociation extends iPersistent, iReified, iScoped, iTyped
{
    public function getRoles(array $filters = [ ]);
    public function setRoles(array $roles);
}
