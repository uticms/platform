<?php

namespace Uticms\Platform\Exceptions;

use RuntimeException;

final class PlatformException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
    ) {
        parent::__construct($message);
    }
}
