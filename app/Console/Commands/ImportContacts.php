<?php

namespace App\Console\Commands;

use App\Services\ContactImportPlanner;
use App\Services\ContactImportService;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class ImportContacts extends Command
{
    protected $signature = 'contacts:import
        {source : Local UTF-8 contact list}
        {--include-general : Admit unambiguous PARTNER rows as general contacts}
        {--decisions= : Local private JSON containing owner-reviewed source-line identity decisions}
        {--apply= : Apply eligible groups using the reviewed preview fingerprint}
        {--json : Print the private report as JSON}';

    protected $description = 'Preview contact matches or apply a reviewed contact list';

    public function handle(ContactImportService $import): int
    {
        try {
            $apply = $this->input->hasParameterOption('--apply', true);
            $fingerprint = $this->option('apply');
            if ($apply && (! is_string($fingerprint) || ! preg_match('/^[0-9a-f]{64}$/D', $fingerprint))) {
                throw new InvalidArgumentException('Supply the fingerprint from a fresh preview with --apply.');
            }
            $source = $this->readSource($this->argument('source'));
            $includeGeneral = (bool) $this->option('include-general');
            $decisions = null;
            if ($this->input->hasParameterOption('--decisions', true)) {
                $json = $this->readSource((string) $this->option('decisions'));
                $decisions = json_decode(str_starts_with($json, "\xEF\xBB\xBF") ? substr($json, 3) : $json, true);
                if (! is_array($decisions)) {
                    throw new InvalidArgumentException('Expected a private JSON identity decision file.');
                }
            }
            $report = $apply ? $import->apply($source, $fingerprint, $includeGeneral, $decisions) : $import->preview($source, $includeGeneral, $decisions);
        } catch (InvalidArgumentException $error) {
            return $this->failImport($error->getMessage());
        } catch (Throwable) {
            // Database error messages may contain SQL bindings with private names.
            return $this->failImport('Contact import failed. Check the configured database connection and migrations, then preview again.');
        }

        if ($this->option('json')) {
            $this->output->writeln(json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), OutputInterface::OUTPUT_RAW);
        } else {
            $this->renderReport($report);
        }

        return self::SUCCESS;
    }

    private function readSource(string $path): string
    {
        if (str_contains($path, "\0") || str_contains($path, '://') || str_starts_with(str_replace('\\', '/', $path), '//') || ! is_file($path)) {
            throw new InvalidArgumentException('Supply a readable local contact file.');
        }
        $source = @file_get_contents($path, length: ContactImportPlanner::MAX_BYTES + 1);
        if ($source === false) {
            throw new InvalidArgumentException('The contact file could not be read.');
        }
        if (strlen($source) > ContactImportPlanner::MAX_BYTES) {
            throw new InvalidArgumentException('The contact file exceeds the 1 MiB limit.');
        }

        return $source;
    }

    private function failImport(string $message): int
    {
        $this->output->writeln($this->option('json')
            ? json_encode(['error' => $message], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
            : $message, OutputInterface::OUTPUT_RAW);

        return self::FAILURE;
    }

    private function renderReport(array $report): void
    {
        $this->info($report['mode'] === 'applied' ? 'Applied contact import' : 'Contact import preview');
        $this->line($report['include_general'] ? 'General contacts: include eligible PARTNER rows' : 'General contacts: hold PARTNER rows for review');
        foreach ($report['groups'] as $group) {
            $lines = implode(', ', $group['lines']);
            $categories = implode(', ', $group['categories']);
            $entry = strtoupper($group['action'])." | line(s) {$lines} | {$categories} | ".$group['name'];
            if ($group['partner_id'] !== null) {
                $entry .= ' | partner #'.$group['partner_id'];
            }
            $this->writeReportLine($entry);
            if (isset($group['decision'])) {
                $this->writeReportLine('  Reviewed identity: '.$group['decision'].' | role: '.($group['role'] ?? 'pending'));
                foreach ($group['source_entries'] as $sourceEntry) {
                    $this->writeReportLine('  Source line '.$sourceEntry['line'].': '.$sourceEntry['category'].' | '.$sourceEntry['name']);
                }
            }
            if ($group['reasons']) {
                $this->writeReportLine('  Reasons: '.implode(', ', $group['reasons']));
            }
            if ($group['candidate_lines']) {
                $this->writeReportLine('  Compare source line(s): '.implode(', ', $group['candidate_lines']));
            }
            foreach ($group['candidate_ids'] as $id) {
                $candidate = $report['candidates'][$id];
                $this->writeReportLine("  Compare partner #{$id}: ".$candidate['name'].' | '.$candidate['state'].' | '.implode(', ', $candidate['roles']));
            }
        }
        $counts = $report['counts'];
        $this->line("Rows {$counts['source_rows']} | Groups {$counts['groups']} | Create {$counts['create']} | Reuse {$counts['reuse']} | Held for review {$counts['review']}");
        $this->line('Fingerprint: '.$report['fingerprint']);
    }

    private function writeReportLine(string $line): void
    {
        $this->output->writeln(preg_replace('/[\p{Cc}\x{2028}\x{2029}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', ' ', $line), OutputInterface::OUTPUT_RAW);
    }
}
