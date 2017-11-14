<?php

namespace Modules\Search\Exceptions;

use Exception;
use Throwable;

class InvalidSearchableModelException extends Exception
{
    /**
     * InvalidSearchableModelException constructor.
     *
     * @param string $className
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $className, $message = "", $code = 0, Throwable $previous = null)
    {
        $message = 'Invalid searchable model given: "' . $className. '"';

        parent::__construct($message, $code, $previous);
    }
}