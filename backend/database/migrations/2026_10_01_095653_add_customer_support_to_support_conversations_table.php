<?php

use App\Enums\SupportRequesterType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_conversations', function (Blueprint $table): void {
            // A conversation now belongs to either a vendor or a customer, so
            // exactly one of these two requester keys is set and
            // `requester_type` says which side it is.
            $table->foreignId('vendor_id')->nullable()->change();
            $table->foreignId('vendor_user_id')->nullable()->change();
            $table->foreignId('customer_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('requester_type', 20)->default(SupportRequesterType::Vendor->value);

            // The column has always meant "the non-admin side spoke last"; it
            // just was not called that while vendors were the only requesters.
            $table->renameColumn('last_vendor_reply_at', 'last_requester_reply_at');

            $table->index(['requester_type', 'is_closed']);
            $table->index('customer_user_id');
        });
    }

    public function down(): void
    {
        // One operation per call, and the indexes go first. On SQLite every
        // alter rebuilds the table and re-adds whatever indexes it finds, so
        // dropping a column while its index still exists fails outright, and
        // batching it all into one closure does the same at the end.
        Schema::table('support_conversations', function (Blueprint $table): void {
            $table->dropIndex(['requester_type', 'is_closed']);
            $table->dropIndex(['customer_user_id']);
        });

        Schema::table('support_conversations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_user_id');
        });

        Schema::table('support_conversations', function (Blueprint $table): void {
            $table->dropColumn('requester_type');
        });

        Schema::table('support_conversations', function (Blueprint $table): void {
            $table->renameColumn('last_requester_reply_at', 'last_vendor_reply_at');
        });

        Schema::table('support_conversations', function (Blueprint $table): void {
            $table->foreignId('vendor_id')->nullable(false)->change();
            $table->foreignId('vendor_user_id')->nullable(false)->change();
        });
    }
};
