<?php

namespace Seferov\Bundle\RequestToEntityBundle;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
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
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

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
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AuthorizationCheckerInterface
     */
    private $authorizationChecker;

    public function __construct(RequestStack $requestStack, Reader $reader, EntityManagerInterface $entityManager, EventDispatcherInterface $eventDispatcher, AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->request = $requestStack->getCurrentRequest();
        $this->reader = $reader;
        $this->entityManager = $entityManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->authorizationChecker = $authorizationChecker;
    }

    /**
     * @return Request|null
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

        $rf = ClassUtils::newReflectionObject($object);

        foreach ($rf->getProperties() as $prop) {
            if (!$this->request->request->has($prop->getName())) {
                continue;
            }
            
            /** @var RequestOptions $requestOptions */
            $requestOptions = $this->reader->getPropertyAnnotation($prop, RequestOptions::class);

            // Skip readonly properties
            if ($requestOptions && $requestOptions->readOnly) {
                continue;
            }

            // Skip properties with has no authorization to change for current user
            if ($requestOptions && count($requestOptions->roles) && !$this->authorizationChecker->isGranted($requestOptions->roles)) {
                continue;
            }

            $value = $this->request->get($prop->getName());
            if (is_object($value)) {
                continue;
            }

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

            try {
                $reflectionProperty = $rf->getProperty($prop->getName());
                $reflectionProperty->setAccessible(true);
                $reflectionProperty->setValue($object, $value);
            } catch (\ReflectionException $e) {
            }
        }

        return $object;
    }
}
