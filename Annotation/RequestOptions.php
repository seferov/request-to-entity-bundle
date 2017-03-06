<?php

namespace Seferov\Bundle\RequestToEntityBundle\Annotation;

/**
 * Class RequestOptions.
 *
 * @Annotation
 * @Target({"PROPERTY", "METHOD"})
 *
 * @author Farhad Safarov <farhad.safarov@gmail.com>
 */
class RequestOptions
{
    /**
     * @var bool
     */
    public $readOnly = false;

    public $transformer;

    /**
     * @var array
     */
    public $roles = [];
}
