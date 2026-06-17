<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests;

/**
 * Restores the exception handler the kernel leaves registered after boot.
 */
trait RestoresExceptionHandler
{
    protected function tearDown(): void
    {
        parent::tearDown();

        \restore_exception_handler();
    }
}
