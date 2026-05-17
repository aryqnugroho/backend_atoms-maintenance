<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an authenticated user attempts to sign a Work Order role
 * for which they are not the authorized signer (name mismatch).
 *
 * Mapped to HTTP 403 by WorkOrderController::sign().
 */
class SignerNotAuthorizedException extends RuntimeException
{
}
