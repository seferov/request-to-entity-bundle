<?php

namespace Seferov\Bundle\RequestToEntityBundle\EventListener;

use Doctrine\Common\Annotations\Reader;
use Seferov\Bundle\RequestToEntityBundle\Annotation\Entity;
use Seferov\Bundle\RequestToEntityBundle\RequestToEntityManager;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;

/**
 * Class EntityAnnotationListener.
 *
 * @author Farhad Safarov <farhad.safarov@gmail.com>
 */
class EntityAnnotationListener
{
    /**
     * @var Reader
     */
    protected $reader;

    /**
     * @var RequestToEntityManager
     */
    private $manager;

    public function __construct(Reader $reader, RequestToEntityManager $manager)
    {
        $this->reader = $reader;
        $this->manager = $manager;
    }

    public function onKernelController(FilterControllerEvent $event)
    {
        if (!is_array($controller = $event->getController())) {
            return;
        }

        $object = new \ReflectionObject($controller[0]);
        $method = $object->getMethod($controller[1]);

        $entityAnnotation = $this->reader->getMethodAnnotation($method, Entity::class);

        if (!$entityAnnotation) {
            return;
        }

        $class = $entityAnnotation->getClass();
        if (!class_exists($class)) {
            throw new \Exception(sprintf('No class as %s', $class));
        }

        // Create object and set attributes from request
        $object = new $class();
        $this->manager->handle($object);

        $request = $this->manager->getRequest();
        $request->attributes->set(lcfirst((new \ReflectionClass($object))->getShortName()), $object);
    }
}
