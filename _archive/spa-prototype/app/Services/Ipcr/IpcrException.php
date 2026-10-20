<?php

namespace App\Services\Ipcr;

use RuntimeException;

/** Domain error whose message is shown to the SPA exactly like the prototype's thrown Errors. */
class IpcrException extends RuntimeException
{
}
