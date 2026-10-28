<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_conversations', function (Blueprint $table): void {
            $table->id();

            // One conversation per vendor. Admins have no vendor profile, so the
            // vendor user is recorded separately to resolve the profile cheaply.
            $table->foreignId('vendor_id')->constrained('vendor_profiles')->cascadeOnDelete();
            $table->foreignId('vendor_user_id')->constrained('users')->cascadeOnDelete();

            $table->string('subject')->nullable();
            $table->timestamp('last_message_at')->nullable();

            // Set once an admin replies; cleared when the vendor replies again.
            $table->timestamp('last_admin_reply_at')->nullable();
            $table->timestamp('last_vendor_reply_at')->nullable();

            $table->boolean('is_closed')->default(false);
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            $table->index(['vendor_id', 'is_closed']);
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_conversations');
    }
};
