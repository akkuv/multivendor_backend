<?php

use App\Enums\SupportConversationKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_conversations', function (Blueprint $table): void {
            // What this thread is about. Order queries are admin-initiated,
            // order-linked conversations with the vendor; everything else is a
            // requester's everyday chat with the admin team.
            $table->string('kind', 20)->default(SupportConversationKind::General->value);

            // The order an admin is asking about; set only for order queries.
            $table->foreignId('order_id')->nullable()->after('kind')
                ->constrained('orders')->nullOnDelete();

            // The inbox now reads (kind, requester_type, is_closed) so order
            // queries and everyday threads stay in their own queues.
            $table->index(['kind', 'requester_type', 'is_closed']);
        });
    }

    public function down(): void
    {
        Schema::table('support_conversations', function (Blueprint $table): void {
            $table->dropIndex(['kind', 'requester_type', 'is_closed']);
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn('kind');
        });
    }
};
