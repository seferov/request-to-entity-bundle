<?php

namespace Seferov\Bundle\RequestToEntityBundle;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OneToOne;
use Seferov\Bundle\RequestToEntityBundle\Annotation\RequestOptions;
use Seferov\Bundle\RequestToEntityBundle\Event\EntityNotFoundEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Doctrine\Common\Annotations\Reader;

/**
 * Class RequestToEntityManager.
 *
 * @author Farhad Safarov <farhad.safarov@gmail.com>
 */
class RequestToEntityManager
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var Reader
     */
    private $reader;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(RequestStack $requestStack, Reader $reader, EntityManager $entityManager, EventDispatcherInterface $eventDispatcher)
    {
        $this->request = $requestStack->getCurrentRequest();
        $this->reader = $reader;
        $this->entityManager = $entityManager;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @return null|Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param mixed $object
     *
     * @return mixed
     *
     * @throws EntityNotFoundException
     */
    public function handle($object)
    {
        $accessor = PropertyAccess::createPropertyAccessor();

        $rf = new \ReflectionObject($object);

        foreach ($rf->getProperties() as $prop) {
            /** @var RequestOptions $requestOptions */
            $requestOptions = $this->reader->getPropertyAnnotation($prop, RequestOptions::class);

            if ($requestOptions && $requestOptions->readOnly) {
                continue;
            }

            $value = $this->request->get($prop->getName());
            if (isset($value['id'])) {
                $annotations = $this->reader->getPropertyAnnotations($prop);
                foreach ($annotations as $annotation) {
                    if ($annotation instanceof ManyToOne || $annotation instanceof OneToMany || $annotation instanceof ManyToMany || $annotation instanceof OneToOne) {
                        $targetEntity = class_exists($annotation->targetEntity)
                            ? $annotation->targetEntity
                            : $rf->getNamespaceName().'\\'.$annotation->targetEntity;
                        $o = $this->entityManager->getRepository($targetEntity)->find(intval($value['id']));

                        if (!$o) {
                            $this->eventDispatcher->dispatch(EntityNotFoundEvent::NAME, new EntityNotFoundEvent($targetEntity));
                        }
                        $accessor->setValue($object, $prop->getName(), $o);
                        continue 2;
                    }
                }
            }

            if ($value && $requestOptions && is_callable($requestOptions->transformer)) {
                $value = call_user_func($requestOptions->transformer, $value);
            }

            if ($value !== null && $accessor->isWritable($object, $prop->getName())) {
                $accessor->setValue($object, $prop->getName(), $value);
            }
        }

        return $object;
    }
}
