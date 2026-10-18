<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'secondary';

    public function up(): void
    {
        Schema::create('secondary_records', fn (Blueprint $table) => $table->id());
    }

    public function down(): void
    {
        Schema::drop('secondary_records');
    }
};
