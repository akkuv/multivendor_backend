<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_messages', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('support_conversation_id')
                ->constrained('support_conversations')
                ->cascadeOnDelete();

            // Kept denormalised so a message renders its author without loading
            // the parent conversation, and so history survives a user deletion.
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_role', 20);

            $table->text('body');

            // Set for messages the rule-based chatbot produced.
            $table->boolean('is_bot')->default(false);

            // Null until the recipient opens the conversation.
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index(['support_conversation_id', 'created_at']);
            $table->index(['support_conversation_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
    }
};
