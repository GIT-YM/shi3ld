<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BuilderWorkflow;
use Illuminate\Console\Command;

class ProcessBuilderSchedule extends Command
{
    protected $signature = 'shi3ld:builder:process {--user= : Staff email when managing builder permissions} {--designer= : 1 or 0} {--publisher= : 1 or 0}';

    protected $description = 'Publish due saved builder drafts, or manage existing staff builder permissions';

    public function handle(BuilderWorkflow $workflow): int
    {
        if ($email = $this->option('user')) {
            $user = User::where('email', $email)->where('services_staff', true)->firstOrFail();
            $values = [];
            foreach (['designer', 'publisher'] as $name) {
                if ($this->option($name) !== null) {
                    if (! in_array($this->option($name), ['0', '1'], true)) {
                        $this->error('Permission values must be 1 or 0.');

                        return self::FAILURE;
                    }
                    $values['builder_'.$name] = $this->option($name) === '1';
                }
            }
            $user->forceFill($values)->save();
            $this->info('Staff builder permissions updated.');
        } else {
            $workflow->run();
        }

        return self::SUCCESS;
    }
}
