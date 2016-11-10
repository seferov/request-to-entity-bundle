<?php

namespace Seferov\Bundle\RequestToEntityBundle\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class EntityNotFoundEvent.
 */
class EntityNotFoundEvent extends Event
{
    const NAME = 'request_to_entity.entity_not_found';

    /**
     * @var string
     */
    private $entity;

    public function __construct($entity)
    {
        $this->entity = $entity;
    }

    /**
     * @return string
     */
    public function getEntity()
    {
        return $this->entity;
    }

    public function getEntityShortName()
    {
        $rf = new \ReflectionClass($this->entity);

        return $rf->getShortName();
    }
}
