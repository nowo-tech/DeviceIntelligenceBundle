<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Command;

use Nowo\DeviceIntelligence\AnalysisInput;
use Nowo\DeviceIntelligence\Signal\SignalFactory;
use Nowo\DeviceIntelligenceBundle\Event\AnalyzeService;

use const STDIN;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Runs analyze() against a JSON signals payload (file or stdin).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsCommand(name: 'device-intelligence:risk:test', description: 'Run risk analysis against a JSON signals file')]
final class RiskTestCommand extends Command
{
    public function __construct(private AnalyzeService $analyze)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('file', 'f', InputOption::VALUE_OPTIONAL, 'JSON file with a signals map');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = $input->getOption('file');
        $raw = \is_string($file) && '' !== $file ? file_get_contents($file) : stream_get_contents(\STDIN);
        if (!\is_string($raw) || '' === $raw) {
            $raw = '{"signals":{}}';
        }
        $decoded = json_decode($raw, true);
        if (!\is_array($decoded)) {
            $io->error('Invalid JSON.');

            return Command::FAILURE;
        }
        $signals = isset($decoded['signals']) && \is_array($decoded['signals']) ? $decoded['signals'] : $decoded;
        $now = new \DateTimeImmutable();
        $analysis = $this->analyze->analyze(new AnalysisInput(
            $now,
            SignalFactory::bagFromClient($signals, $now),
        ));

        $io->definitionList(
            ['Device' => $analysis->device()->id->value],
            ['New' => $analysis->match()->isNewDevice() ? 'yes' : 'no'],
            ['Confidence' => (string) $analysis->matchConfidence()],
            ['Risk' => (string) $analysis->riskScore()],
            ['Level' => $analysis->riskLevel()],
        );
        $io->listing($analysis->riskReasons());

        return Command::SUCCESS;
    }
}
