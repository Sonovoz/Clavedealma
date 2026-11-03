<?php

use App\Modules\Identity\Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => $this->seed(RolesSeeder::class))
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');
