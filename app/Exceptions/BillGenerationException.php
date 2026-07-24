<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by BillCalculationService when a bill cannot be previewed or
 * generated for the given retailer/date - e.g. there are no pending
 * give-stock records, so a bill would otherwise be created with zero
 * line items and a grand total of Rs. 0.
 */
class BillGenerationException extends RuntimeException
{
}