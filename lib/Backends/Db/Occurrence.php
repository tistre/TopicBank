<?php

namespace TopicBank\Backends\Db;


class Occurrence extends Core implements \TopicBank\Interfaces\iOccurrence
{
    use Id, Reified, Scoped, Typed, OccurrenceDbAdapter;
    
    protected $value = false;
    protected $datatype = false;
    
    
    public function getValue()
    {
        return $this->value;
    }
    
    
    public function setValue($str)
    {
        $this->value = $str;

        return 1;
    }
    
    
    public function getDatatype()
    {
        return $this->datatype;
    }
    

    public function setDatatype($topic_id)
    {
        $this->datatype = $topic_id;
        
        return 1;
    }
    

    public function getDatatypeSubject()
    {
        return $this->getTopicMap()->getTopicSubject($this->getDatatype());
    }
    
    
    public function setDatatypeSubject($topic_subject)
    {
        $topic_id = $this->getTopicMap()->getTopicBySubject($topic_subject);
        
        if (strlen($topic_id) === 0)
            return -1;
            
        return $this->setDatatype($topic_id);
    }

    
    public function validate(&$msg_html)
    {
        $ok = \TopicBank\Utils\DatatypeUtils::validate
        (
            $this->value, 
            $this->getDatatypeSubject(), 
            $msg_txt
        );
        
        $msg_html = htmlspecialchars($msg_txt);
        
        return $ok;
    }

    
    public function getAll()
    {
        $result = 
        [
            'value' => $this->getValue(),
            'datatype' => $this->getDatatype()
        ];

        $result = array_merge($result, $this->getAllId());

        $result = array_merge($result, $this->getAllTyped());

        $result = array_merge($result, $this->getAllReified());

        $result = array_merge($result, $this->getAllScoped());
            
        return $result;
    }
    
    
    public function setAll(array $data)
    {
        $data = array_merge(
        [
            'value' => false,
            'datatype' => false
        ], $data);
        
        $ok = $this->setValue($data[ 'value' ]);
        $ok = $this->setDatatype($data[ 'datatype' ]);
        
        if ($ok >= 0)
            $ok = $this->setAllId($data);
            
        if ($ok >= 0)
            $ok = $this->setAllTyped($data);
            
        if ($ok >= 0)
            $ok = $this->setAllReified($data);
            
        if ($ok >= 0)
            $ok = $this->setAllScoped($data);
            
        return $ok;
    }
}
