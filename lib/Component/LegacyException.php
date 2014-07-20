<?php
/**
 * Exception raised by Legacy
 */

namespace Lmc\Steward\Component;

use Exception;

class LegacyException extends \Exception
{
    public function __construct($message = "", $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
