<?php

namespace App\Modules\Ops\Http\Controllers;

use App\Models\User;
use App\Modules\Ops\Installer\InstallerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The browser installer (gate 2.2). Sealed = every route 404s, so a rerun can
 * never touch the first administrator or rewrite production configuration.
 */
final class SetupController extends Controller
{
    public function index(InstallerService $installer): Response
    {
        if ($installer->isSealed()) {
            abort(404);
        }

        return Inertia::render('Setup/Index', [
            'steps' => [
                'requirementsMet' => $installer->allRequirementsMet(),
                'requirements' => $installer->requirements(),
                'migrated' => $installer->schemaMigrated(),
                'accountCreated' => $this->accountExists($installer),
            ],
            'envExists' => $installer->envEditor()->exists(),
        ]);
    }

    public function database(Request $request, InstallerService $installer): RedirectResponse
    {
        if ($installer->isSealed()) {
            abort(404);
        }

        $validated = $request->validate([
            'driver' => ['required', 'in:sqlite,mysql,mariadb'],
            'database' => ['nullable', 'string', 'max:255'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer'],
            'name' => ['required_if:driver,mysql,mariadb', 'nullable', 'string', 'max:64'],
            'user' => ['required_if:driver,mysql,mariadb', 'nullable', 'string', 'max:64'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $installer->configureDatabase($validated);
            $installer->runMigrations();
        } catch (\RuntimeException $e) {
            return back()->withErrors(['database' => $e->getMessage()]);
        }

        return redirect()->route('setup.index')->with('success', 'Database configured and migrated.');
    }

    public function account(Request $request, InstallerService $installer): RedirectResponse
    {
        if ($installer->isSealed()) {
            abort(404);
        }

        if (! $installer->schemaMigrated()) {
            return back()->withErrors(['account' => 'Run the database step first.']);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        try {
            $installer->createFirstAccount($validated['name'], $validated['email'], $validated['password']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['account' => $e->getMessage()]);
        }

        return redirect()->route('setup.index')->with('success', 'Operator account created.');
    }

    public function finish(InstallerService $installer): RedirectResponse
    {
        if ($installer->isSealed()) {
            abort(404);
        }

        if (! $this->accountExists($installer)) {
            return back()->withErrors(['finish' => 'Create the first operator account first.']);
        }

        $verification = $installer->selfVerify();
        $installer->seal();

        return redirect()->route('setup.index')->with('success', 'Installer sealed.');
    }

    private function accountExists(InstallerService $installer): bool
    {
        return $installer->schemaMigrated() && User::query()->exists();
    }
}
