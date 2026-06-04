<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests;

/**
 * Restores the error and exception handlers the kernel registers on boot, so booting it in a test
 * is not flagged as risky by PHPUnit.
 */
trait RestoresErrorHandlers
{
    protected function tearDown(): void
    {
        parent::tearDown();

        \restore_exception_handler();
    }
}
