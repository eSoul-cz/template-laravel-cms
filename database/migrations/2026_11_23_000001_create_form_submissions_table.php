<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->string('form', 40)->default('contact');
            $table->string('name', 120);
            $table->string('email', 254);
            $table->text('message');
            // HMAC-SHA256 of the client address with the application key; the address itself is never stored.
            $table->string('client_hash', 64);
            $table->decimal('recaptcha_score', 3, 2)->nullable();
            $table->string('mail_status', 16)->default('pending')->index();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
