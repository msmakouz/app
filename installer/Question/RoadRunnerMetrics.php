<?php

declare(strict_types=1);

namespace Installer\Question;

use Installer\Package\Generator\RoadRunnerBridge\MetricsBootloader;
use Installer\Package\Package;
use Installer\Package\Packages;
use Installer\Question\Option\Option;

final class RoadRunnerMetrics extends AbstractQuestion
{
    /**
     * @param Option[] $options
     */
    public function __construct(
        string $question = 'Do you need the RoadRunner Metrics?',
        bool $required = false,
        array $options = [
            new Option(name: 'Yes', packages: [
                new Package(
                    package: Packages::RoadRunnerBridge,
                    generators: [
                        new MetricsBootloader(),
                    ]
                ),
            ]),
        ]
    ) {
        parent::__construct($question, $required, $options);
    }
}