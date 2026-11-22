<?php

namespace App\Modules\Collection\Console;

use App\Models\User;
use App\Modules\Collection\Models\Brand;
use App\Modules\Collection\Models\Competitor;
use App\Modules\Collection\Models\Prompt;
use App\Modules\Collection\Services\Sweep\SweepRunner;
use App\Modules\Tenancy\Models\Workspace;
use Illuminate\Console\Command;

/**
 * Demo data for the public demo and for screenshots (build plan §8):
 * a fictional workspace ("Acme", Northwind CRM) with prompts, competitors
 * and real collected answers from the FakeCollector — free, deterministic,
 * labeled as demo by the owning user's @clot.test email.
 *
 * clot:demo --reset removes every workspace whose owner uses a @clot.test
 * email (demo-reset pattern).
 */
final class DemoCommand extends Command
{
    protected $signature = 'clot:demo {--reset : Remove all demo workspaces instead of seeding}';

    protected $description = 'Seed (or reset) the demo workspace with sample tracking data';

    public function handle(SweepRunner $runner): int
    {
        if ($this->option('reset')) {
            return $this->reset();
        }

        if (User::query()->where('email', 'demo-owner@clot.test')->exists()) {
            $this->warn('Demo workspace already exists. Use --reset first.');

            return self::FAILURE;
        }

        $owner = User::query()->create([
            'name' => 'Demo Owner',
            'email' => 'demo-owner@clot.test',
            'password' => 'demo-password',
        ]);

        $workspace = Workspace::createWithOwner($owner->id, 'Acme');

        // Demo mode: settings.is_sample = true drives the "Sample data" badge
        // in the UI and blocks writes to settings, keys and users.
        $workspace->forceFill(['settings' => ['is_sample' => true]])->save();

        $brand = Brand::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Northwind',
            'domains' => ['northwind.example'],
            'aliases' => ['Northwind CRM'],
            'engines' => ['chatgpt', 'claude', 'perplexity'],
            'runs_per_prompt' => 3,
            'frequency' => 'weekly',
            'is_active' => true,
        ]);

        $brand->markets()->create([
            'workspace_id' => $workspace->id,
            'country_code' => 'US',
            'language_code' => 'en',
            'is_primary' => true,
        ]);

        foreach (['Contoso', 'Rivalsoft', 'Fabrikam'] as $competitorName) {
            Competitor::query()->create([
                'workspace_id' => $workspace->id,
                'brand_id' => $brand->id,
                'name' => $competitorName,
                'domains' => [strtolower($competitorName).'.example'],
                'aliases' => [],
            ]);
        }

        foreach ([
            'What is the best CRM for a small agency?',
            'Northwind or Rivalsoft for a 10-person team?',
            'Which CRM has the best free plan?',
            'Top CRM tools for client services firms',
            'Which CRM has the easiest setup?',
        ] as $text) {
            Prompt::query()->create([
                'workspace_id' => $workspace->id,
                'brand_id' => $brand->id,
                'text' => $text,
                'topic' => 'CRM',
                'status' => 'active',
            ]);
        }

        $sweep = $runner->runForBrand($brand->refresh());

        $this->info('Demo workspace seeded: workspace #'.$workspace->id.', brand #'.$brand->id.'.');
        $this->info("Sweep {$sweep->id}: {$sweep->cells_collected} collected / {$sweep->cells_failed} failed.");
        $this->info('Sign in as demo-owner@clot.test / demo-password.');

        return self::SUCCESS;
    }

    private function reset(): int
    {
        // Only demo-prefixed accounts: the installer's first operator also
        // lives under @clot.test in dev and must NEVER be touched here.
        $count = User::query()
            ->where('email', 'like', 'demo-%@clot.test')
            ->count();

        User::query()->where('email', 'like', 'demo-%@clot.test')->get()->each->delete();

        $this->info("Removed {$count} demo user(s) (cascades workspaces, brands, answers).");

        return self::SUCCESS;
    }
}
