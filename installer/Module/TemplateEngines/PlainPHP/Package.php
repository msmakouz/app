<?php

declare(strict_types=1);

namespace Installer\Module\TemplateEngines\PlainPHP;

use Installer\Application\ComposerPackages;
use Installer\Internal\Package as BasePackage;
use Installer\Module\TemplateEngines\PlainPHP\Generator\Bootloaders;

final class Package extends BasePackage
{
    public function __construct()
    {
        parent::__construct(
            package: ComposerPackages::Views,
            resources: [
                'views' => 'app/views',
            ],
            generators: [
                new Bootloaders(),
            ],
            instructions: [
                'Read more about views in the Spiral Framework: <comment>https://spiral.dev/docs/views-configuration</comment>',
                'Documentation: <comment>https://spiral.dev/docs/views-plain</comment>',
            ]
        );
    }
}
