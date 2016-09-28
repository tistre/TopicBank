<?php

namespace TopicCards;


interface iTopicMap
{    
    const SUBJECT_ASSOCIATION_ROLE_TYPE = 'http://psi.topicmaps.org/iso13250/glossary/association-role-type';
    const SUBJECT_ASSOCIATION_TYPE = 'http://psi.topicmaps.org/iso13250/glossary/association-type';
    const SUBJECT_OCCURRENCE_TYPE = 'http://psi.topicmaps.org/iso13250/glossary/occurrence-type';
    const SUBJECT_SCOPE = 'http://psi.topicmaps.org/iso13250/glossary/scope';
    const SUBJECT_TOPIC_NAME_TYPE = 'http://psi.topicmaps.org/iso13250/glossary/topic-name-type';
    const SUBJECT_TOPIC_TYPE = 'http://psi.topicmaps.org/iso13250/glossary/topic-type';
    const SUBJECT_DATATYPE = 'http://www.w3.org/2000/01/rdf-schema#Datatype';
    
    public function __construct(iServices $services);
    
    /** @var iServices */
    public function getServices();
    
    public function on($event, callable $callback);
    public function trigger($event, array $params, array &$result);

    public function setUrl($url);
    public function getUrl();

    public function getReifierId();
    
    public function createId();
    
    /** @var iTopic */
    public function newTopic();
    
    /** @var iAssociation */
    public function newAssociation();
    
    public function newFileTopic($filename);

    public function getTopicIds(array $filters);
    public function getTopicIdBySubject($uri);
    public function getTopicSubject($topic_id);
    public function getTopicSubjectIdentifier($topic_id);
    public function getTopicSubjectLocator($topic_id);
    public function getTopicLabel($topic_id);
    public function getAssociationIds(array $filters);
    public function getTopicTypeIds(array $filters);
    public function getNameTypeIds(array $filters);
    public function getNameScopeIds(array $filters);
    public function getOccurrenceTypeIds(array $filters);
    public function getOccurrenceDatatypeIds(array $filters);
    public function getOccurrenceScopeIds(array $filters);
    public function getAssociationTypeIds(array $filters);
    public function getAssociationScopeIds(array $filters);
    public function getRoleTypeIds(array $filters);
    public function getRolePlayerIds(array $filters);
}