<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class HerdTenantLinkCommand extends Command
{
    protected $signature = 'herd:link-tenant {slug : The tenant slug (e.g. my-company creates my-company.namain.test)}
                                             {--unlink : Remove the link instead of creating it}';

    protected $description = 'Link (or unlink) a tenant subdomain in Laravel Herd. Local environment only.';

    public function handle(): int
    {
        if (! app()->isLocal()) {
            $this->error('This command is only available in the local environment.');

            return self::FAILURE;
        }

        $slug = $this->argument('slug');
        $siteName = "{$slug}.namain";
        $action = $this->option('unlink') ? 'unlink' : 'link';

        $herd = $this->resolveHerdBinary();

        if (! $herd) {
            $this->error('Could not locate the Herd CLI. Ensure Herd is installed.');

            return self::FAILURE;
        }

        $process = new Process(
            [$herd, $action, $siteName],
            base_path()
        );

        $process->run();

        if (! $process->isSuccessful() && $action === 'link') {
            $this->error("herd {$action} failed: ".$process->getErrorOutput());

            return self::FAILURE;
        }

        $verb = $action === 'link' ? 'Linked' : 'Unlinked';
        $this->info("{$verb} → https://{$siteName}.test");

        return self::SUCCESS;
    }

    private function resolveHerdBinary(): string|false
    {
        $candidates = [
            getenv('USERPROFILE').'\\.config\\herd\\bin\\herd.bat',
            '/usr/local/bin/herd',
            '/opt/homebrew/bin/herd',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate && file_exists($candidate)) {
                return $candidate;
            }
        }

        $result = trim((string) shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where herd' : 'which herd'));

        return $result ?: false;
    }
}
