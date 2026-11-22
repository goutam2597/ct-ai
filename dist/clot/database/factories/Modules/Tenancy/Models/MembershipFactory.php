<?php

namespace Database\Factories\Modules\Tenancy\Models;

use App\Modules\Tenancy\Enums\BrandAccess;
use App\Modules\Tenancy\Enums\WorkspaceRole;
use App\Modules\Tenancy\Models\Membership;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Membership> */
class MembershipFactory extends Factory
{
    protected $model = Membership::class;

    public function definition(): array
    {
        return [
            'workspace_id' => WorkspaceFactory::new(),
            'user_id' => UserFactory::new(),
            'role' => WorkspaceRole::Member,
            'brand_access' => BrandAccess::All,
            'accepted_at' => now(),
        ];
    }

    public function owner(): self
    {
        return $this->state(fn (): array => ['role' => WorkspaceRole::Owner]);
    }

    public function admin(): self
    {
        return $this->state(fn (): array => ['role' => WorkspaceRole::Admin]);
    }

    public function viewer(): self
    {
        return $this->state(fn (): array => ['role' => WorkspaceRole::Viewer]);
    }

    public function teamsOnly(): self
    {
        return $this->state(fn (): array => ['brand_access' => BrandAccess::TeamsOnly]);
    }
}
