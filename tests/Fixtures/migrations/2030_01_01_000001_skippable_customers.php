<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function shouldRun(): bool
    {
        return is_file(__DIR__.'/enable_customers');
    }

    public function up(): void
    {
        Schema::create('customers', fn (Blueprint $table) => $table->id());
    }

    public function down(): void
    {
        Schema::drop('customers');
    }
};
