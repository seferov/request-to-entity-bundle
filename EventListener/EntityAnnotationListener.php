<?php

namespace Seferov\Bundle\RequestToEntityBundle\EventListener;

use Doctrine\Common\Annotations\Reader;
use Seferov\Bundle\RequestToEntityBundle\Annotation\Entity;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Class EntityAnnotationListener
 *
 * @author Farhad Safarov <farhad.safarov@gmail.com>
 */
class EntityAnnotationListener
{
    /**
     * @var Reader
     */
    protected $reader;

    public function __construct(Reader $reader)
    {
        $this->reader = $reader;
    }

    public function onKernelController(FilterControllerEvent $event)
    {
        if (!is_array($controller = $event->getController())) {
            return;
        }

        $request = $event->getRequest();

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

        $accessor = PropertyAccess::createPropertyAccessor();

        // Create object and set attributes from request
        $object = new $class;
        foreach ($request->request->all() as $name => $value) {
            if ($accessor->isWritable($object, $name)) {
                $accessor->setValue($object, $name, $value);
            }
        }

        $request->attributes->set(lcfirst((new \ReflectionClass($object))->getShortName()), $object);
    }
}
