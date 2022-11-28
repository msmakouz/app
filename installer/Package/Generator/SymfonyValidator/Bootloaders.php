<?php

declare(strict_types=1);

namespace Installer\Package\Generator\SymfonyValidator;

use Installer\Package\Generator\Context;
use Installer\Package\Generator\GeneratorInterface;
use Spiral\Bootloader\Security\FiltersBootloader;
use Spiral\Validation\Symfony\Bootloader\ValidatorBootloader;
use Spiral\Validation\Bootloader\ValidationBootloader;

final class Bootloaders implements GeneratorInterface
{
    public function process(Context $context): void
    {
        $context->kernel->loadAppend(ValidationBootloader::class, FiltersBootloader::class);
        $context->kernel->loadAppend(ValidatorBootloader::class, ValidationBootloader::class);
    }
}