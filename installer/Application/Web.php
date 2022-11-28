<?php

declare(strict_types=1);

namespace Installer\Application;

use App\Application\Kernel;
use Installer\Package\Packages;
use Installer\Question\QuestionInterface;

final class Web extends AbstractApplication
{
    /**
     * @param Packages[] $packages
     * @param array{
     *     psr-0?: array<string, string>,
     *     psr-4?: array<string, string>,
     *     classmap?: array<string>,
     *     files?: array<string>,
     *     exclude-from-classmap?: array<string>
     * } $autoload
     * @param array{
     *     psr-0?: array<string, string>,
     *     psr-4?: array<string, string>,
     *     classmap?: array<string>,
     *     files?: array<string>,
     *     exclude-from-classmap?: array<string>
     * } $autoloadDev
     * @param QuestionInterface[] $questions
     */
    public function __construct(
        string $name = 'Web',
        array $packages = [
            Packages::ExtMbString,
            Packages::RoadRunnerBridge,
            Packages::NyholmBridge,
            Packages::SapiBridge,
        ],
        array $autoload = [
            'psr-4' => [
                'App\\' => 'app/src',
            ],
            'files' => [
                'app/src/Application/helpers.php',
            ],
        ],
        array $autoloadDev = [
            'psr-4' => [
                'Tests\\' => 'tests',
            ],
        ],
        array $questions = [],
        array $resources = [],
    ) {
        parent::__construct($name, $packages, $autoload, $autoloadDev, $questions, $resources);
    }

    public function getKernelClass(): string
    {
        return Kernel::class;
    }
}