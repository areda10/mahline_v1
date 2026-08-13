<?php

declare(strict_types=1);

namespace App\Core\Foundation\Http\Controllers;

use Illuminate\Routing\Controller;

/**
 * Base controller for the MAHLINE application.
 *
 * This class provides the common HTTP foundation for all
 * application controllers.
 *
 * Domain controllers must extend this class instead of
 * depending directly on Laravel's base Controller.
 *
 * Responsibilities:
 *
 * - Provide a common controller foundation.
 * - Keep the application architecture consistent.
 * - Provide a stable extension point for future HTTP concerns.
 *
 * The BaseController must remain lightweight.
 *
 * Business logic must NOT be implemented here.
 */
abstract class BaseController extends Controller
{
    /*
     * Intentionally empty.
     *
     * Shared controller behavior can be added here later
     * if it is genuinely transversal to the application.
     */
}