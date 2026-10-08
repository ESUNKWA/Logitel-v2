<?php

namespace App\Exceptions;

use DomainException;

/**
 * Thrown by a model's `deleting` event when deleting it would wipe financial history (stays,
 * invoices, payments) or orphan rooms. The message is shown to the user as is.
 */
class DeletionBlocked extends DomainException {}
