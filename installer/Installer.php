<?php

declare(strict_types=1);

namespace Installer;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\BasePackage;
use Composer\Package\Link;
use Composer\Package\RootPackageInterface;
use Composer\Package\Version\VersionParser;
use Composer\Script\Event;
use Installer\Application\ApplicationInterface;
use Installer\Package\Package;
use Installer\Question\QuestionInterface;
use Seld\JsonLint\ParsingException;

final class Installer extends AbstractInstaller
{
    private ApplicationInterface $application;
    private JsonFile $composerJson;
    private RootPackageInterface $rootPackage;
    private string $installerSource;

    /** @var Link[] */
    private array $composerRequires = [];

    /** @var Link[] */
    private array $composerDevRequires = [];

    /** @var int[] */
    private array $stabilityFlags = [];

    /**
     * @throws ParsingException
     */
    public function __construct(
        IOInterface $io,
        Composer $composer,
        ?string $projectRoot = null
    ) {
        parent::__construct($io, $projectRoot);

        $this->parseComposerDefinition($composer, Factory::getComposerFile());

        $this->installerSource = \realpath(__DIR__) . '/Resources/';
    }

    public static function install(Event $event): void
    {
        $installer = new self($event->getIO(), $event->getComposer());

        $installer->io->write('<info>Setting up application preset</info>');
        $installer->setApplicationType($installer->requestApplicationType());

        $installer->io->write('<info>Setting up required packages</info>');
        $installer->setRequiredPackages();

        $installer->io->write('<info>Setting up optional packages</info>');
        $installer->promptForOptionalPackages();

        $installer->io->write('<info>Setting up application files</info>');
        $installer->setApplicationFiles();

        $installer->updateRootPackage();
        $installer->removeInstallerFromDefinition();
        $installer->finalize();
    }

    private function setRequiredPackages(): void
    {
        foreach ($this->application->getPackages() as $package) {
            $this->addPackage($package);
        }
    }

    private function setApplicationFiles(): void
    {
        foreach ($this->application->getResources() as $source => $target) {
            $this->copyResource($source, $target);
        }
    }

    private function promptForOptionalPackages(): void
    {
        foreach ($this->application->getQuestions() as $question) {
            if ($question->canAsk($this->composerDefinition)) {
                $this->promptForOptionalPackage($question);
            }
        }
    }

    private function promptForOptionalPackage(QuestionInterface $question): void
    {
        $answer = $this->askQuestion($question);
        if ($answer === 0) {
            return;
        }

        if (!$question->hasOption($answer)) {
            $this->io->write('<error>Invalid package</error>');
            exit;
        }

        // Add packages to install
        foreach ($question->getOption($answer)->getPackages() as $package) {
            $this->addPackage($package);
        }
    }

    private function requestApplicationType(): int
    {
        $query = [
            \sprintf(
                "\n  <question>%s</question>\n",
                'Which application preset do you want to install?'
            ),
        ];
        foreach ($this->config as $key => $app) {
            $query[] = \sprintf("  [<comment>%s</comment>] %s\n", $key + 1, $app->getName());
        }
        $query[] = \sprintf('  Make your selection <comment>(%s)</comment>: ', \array_key_first($this->config) + 1);

        $answer = (int) $this->io->ask(\implode($query), 1) - 1;

        if (!isset($this->config[$answer]) || !$this->config[$answer] instanceof ApplicationInterface) {
            $this->io->write('<error>Invalid application preset!</error>');
            exit;
        }

        return $answer;
    }

    private function setApplicationType(int $applicationType): void
    {
        $this->application = $this->config[$applicationType];

        $this->composerDefinition['extra']['spiral']['application-type'] = $applicationType;
    }

    private function askQuestion(QuestionInterface $question): int
    {
        $answer = $this->io->ask($question->getQuestion(), (string) $question->getDefault());

        // Handling "y", "Y", "n", "N"
        if (\strtolower((string) $answer) === 'n') {
            $answer = 0;
        }
        if (\strtolower((string) $answer) === 'y' && count($question->getOptions()) === 2) {
            $answer = 1;
        }

        if (!isset($question->getOptions()[(int) $answer])) {
            $this->io->write('<error>Invalid answer</error>');
            exit;
        }

        return (int) $answer;
    }

    private function addPackage(Package $package): void
    {
        $this->io->write(\sprintf(
            '  - Adding package <info>%s</info> (<comment>%s</comment>)',
            $package->getName(),
            $package->getVersion()
        ));

        $versionParser = new VersionParser();
        $constraint = $versionParser->parseConstraints($package->getVersion());

        $link = new Link('__root__', $package->getName(), $constraint, 'requires', $package->getVersion());

        // Add package to the root package and composer.json requirements
        if (\in_array($package->getName(), $this->config['require-dev'] ?? [], true)) {
            unset(
                $this->composerDefinition['require'][$package->getName()],
                $this->composerRequires[$package->getName()]
            );

            $this->composerDefinition['require-dev'][$package->getName()] = $package->getVersion();
            $this->composerDevRequires[$package->getName()] = $link;
        } else {
            unset(
                $this->composerDefinition['require-dev'][$package->getName()],
                $this->composerDevRequires[$package->getName()]
            );

            $this->composerDefinition['require'][$package->getName()] = $package->getVersion();
            $this->composerRequires[$package->getName()] = $link;
        }

        $stability = match (VersionParser::parseStability($package->getVersion())) {
            'dev' => BasePackage::STABILITY_DEV,
            'alpha' => BasePackage::STABILITY_ALPHA,
            'beta' => BasePackage::STABILITY_BETA,
            'RC' => BasePackage::STABILITY_RC,
            default => null
        };
        if ($stability !== null) {
            $this->stabilityFlags[$package->getName()] = $stability;
        }

        // Add package to the extra section
        if (!\in_array($package, $this->composerDefinition['extra']['spiral']['packages'] ?? [], true)) {
            $this->composerDefinition['extra']['spiral']['packages'][] = $package->getName();
        }

        // Package resources
        foreach ($package->getResources() as $source => $target) {
            $this->copyResource($source, $target);
        }
    }

    /**
     * @throws ParsingException
     */
    private function parseComposerDefinition(Composer $composer, string $composerFile): void
    {
        $this->composerJson = new JsonFile($composerFile);
        $this->rootPackage = $composer->getPackage();
        $this->composerRequires = $this->rootPackage->getRequires();
        $this->composerDevRequires = $this->rootPackage->getDevRequires();
        $this->stabilityFlags = $this->rootPackage->getStabilityFlags();
    }

    private function updateRootPackage(): void
    {
        $this->rootPackage->setRequires($this->composerRequires);
        $this->rootPackage->setDevRequires($this->composerDevRequires);
        $this->rootPackage->setStabilityFlags($this->stabilityFlags);
        $this->rootPackage->setAutoload($this->application->getAutoload());
        $this->rootPackage->setDevAutoload($this->application->getAutoloadDev());
        $this->rootPackage->setExtra($this->composerDefinition['extra'] ?? []);
    }

    private function removeInstallerFromDefinition(): void
    {
        $this->io->write('<info>Remove Installer from composer.json</info>');

        unset(
            $this->composerDefinition['autoload']['psr-4']['Installer\\'],
            $this->composerDefinition['scripts']['pre-update-cmd'],
            $this->composerDefinition['scripts']['pre-install-cmd']
        );
    }

    private function copyResource(string $resource, string $target): void
    {
        $copy = function (string $source, string $destination) use (&$copy): void {
            if (\is_dir($source)) {
                $handle = \opendir($source);
                while ($file = \readdir($handle)) {
                    if ($file !== '.' && $file !== '..') {
                        if (\is_dir($source . '/' . $file)) {
                            if (!\is_dir($destination . '/' . $file)) {
                                \mkdir($destination . '/' . $file, 0775, true);
                            }
                            $copy($source . '/' . $file, $destination . '/' . $file);
                        } else {
                            $this->io->write(
                                \sprintf(
                                '  - Copying <info>%s</info>',
                                \str_replace('\\', '/', $destination) . '/' . $file
                            )
                            );
                            if (!\is_dir($destination)) {
                                \mkdir($destination, 0775, true);
                            }
                            \copy($source . '/' . $file, $destination . '/' . $file);
                        }
                    }
                }
                \closedir($handle);
            } else {
                $this->io->write(\sprintf('  - Copying <info>%s</info>', \str_replace('\\', '/', $destination)));
                \copy($source, $destination);
            }
        };

        $copy($this->installerSource . $resource, $this->projectRoot . $target);
    }

    private function finalize(): void
    {
        $this->composerDefinition['autoload'] = $this->application->getAutoload();
        $this->composerDefinition['autoload-dev'] = $this->application->getAutoloadDev();

        $this->composerJson->write($this->composerDefinition);
    }
}