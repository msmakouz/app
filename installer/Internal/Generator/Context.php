<?php

declare(strict_types=1);

namespace Installer\Internal\Generator;

use Installer\Internal\ApplicationInterface;
use Installer\Internal\Configurator\ResourceQueue;

/**
 * The current state of the application files that are available for modification by the package.
 */
final class Context
{
    public function __construct(
        public readonly ApplicationInterface $application,
        public readonly KernelConfigurator $kernel,
        public readonly ExceptionHandlerBootloaderConfigurator $exceptionHandlerBootloader,
        public readonly EnvConfigurator $envConfigurator,
        public readonly string $applicationRoot,
        public ResourceQueue $resource
    ) {
    }
}
