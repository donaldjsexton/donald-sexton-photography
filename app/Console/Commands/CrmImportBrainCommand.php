<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Services\Crm\ContactDirectory;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('crm:import-brain
    {file : JSON export of the agent-side clients table}
    {--dry-run : Report what would change without writing}
')]
#[Description('Import agent-side clients (contacts + do-not-contact) from a JSON export. Safe to re-run: matches on email.')]
class CrmImportBrainCommand extends Command
{
    public function handle(ContactDirectory $directory): int
    {
        $path = (string) $this->argument('file');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $rows = json_decode((string) file_get_contents($path), true);
        if (! is_array($rows) || ! isset($rows['clients']) || ! is_array($rows['clients'])) {
            $this->error('Expected JSON with a "clients" array.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $stats = ['created' => 0, 'existing' => 0, 'suppressed' => 0, 'skipped' => 0];

        foreach ($rows['clients'] as $row) {
            $email = Contact::normalizeEmail((string) ($row['email'] ?? ''));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $stats['skipped']++;

                continue;
            }

            $exists = Contact::query()->where('email', $email)->exists();
            $exists ? $stats['existing']++ : $stats['created']++;

            $isDnc = (bool) ($row['dnc'] ?? false);
            if ($isDnc) {
                $stats['suppressed']++;
            }

            if ($dry) {
                continue;
            }

            $sources = array_values(array_filter((array) ($row['sources'] ?? [])));
            $contact = $directory->findOrCreate($email, $this->sourceSlug($sources[0] ?? 'agent_import'), [
                'type' => 'prospect',
                'name' => $row['name'] ?? null,
                'shoot_type' => ($row['shoot_type'] ?? null) ?: null,
                'notes' => ($row['notes'] ?? null) ?: null,
                'source_detail' => $sources ? implode(', ', $sources) : null,
                'first_contact_at' => $this->date($row['first_contact'] ?? null),
                'last_contact_at' => $this->date($row['last_contact'] ?? null),
            ]);

            if ($isDnc) {
                $reason = (string) ($row['dnc_reason'] ?? '');
                $directory->suppress(
                    $email,
                    str_contains(strtolower($reason), 'bounce') ? 'bounce' : 'imported',
                    $reason ?: null,
                );
            }
        }

        $this->table(['Result', 'Count'], collect($stats)->map(fn ($n, $k) => [$k, $n])->values()->all());
        $this->info($dry ? 'Dry run: nothing written.' : 'Import complete.');

        return self::SUCCESS;
    }

    private function sourceSlug(string $source): string
    {
        return substr((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($source)), 0, 64) ?: 'agent_import';
    }

    private function date(?string $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }
}
