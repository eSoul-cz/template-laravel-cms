<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_home')->default(false);
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('noindex')->default(false);
            $table->string('og_image')->nullable();
            $table->json('blocks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
