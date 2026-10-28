<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway_order_id')->nullable()->index();
            $table->string('gateway_payment_id')->nullable()->index();
            $table->string('currency')->default('INR');
            $table->string('payment_method')->nullable()->index();
            $table->json('metadata')->nullable();
        });

        DB::table('payments')
            ->select('id', 'gateway', 'transaction_id', 'method', 'payload')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $payload = is_string($row->payload) ? json_decode($row->payload, true) : null;

                    DB::table('payments')->where('id', $row->id)->update([
                        'gateway_order_id' => $payload['order_id'] ?? ($payload['id'] ?? null),
                        'gateway_payment_id' => $row->transaction_id,
                        'payment_method' => $row->method,
                        'currency' => 'INR',
                        'metadata' => $payload ? json_encode($payload) : null,
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['gateway_order_id', 'gateway_payment_id', 'currency', 'payment_method', 'metadata']);
        });
    }
};
