<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', fn (Blueprint $table) => $table->id());
    }

    public function down(): never
    {
        throw new RuntimeException('Customer deletion cannot be undone.');
    }
};
