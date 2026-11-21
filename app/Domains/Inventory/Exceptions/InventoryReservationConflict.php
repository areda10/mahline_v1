<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Exceptions;

use RuntimeException;

final class InventoryReservationConflict extends RuntimeException {}
