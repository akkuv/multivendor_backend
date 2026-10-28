<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Encrypt existing (historically plaintext) Shiprocket secrets at rest so
     * the models can apply Laravel "encrypted" casts going forward.
     */
    public function up(): void
    {
        $columns = ['password', 'token', 'webhook_secret'];

        foreach (DB::table('shiprocket_settings')->get() as $row) {
            $updates = [];

            foreach ($columns as $column) {
                $value = $row->{$column} ?? null;

                if ($value !== null && $value !== '') {
                    $updates[$column] = encrypt($value);
                }
            }

            if ($updates !== []) {
                DB::table('shiprocket_settings')->where('id', $row->id)->update($updates);
            }
        }
    }

    public function down(): void
    {
        $columns = ['password', 'token', 'webhook_secret'];

        foreach (DB::table('shiprocket_settings')->get() as $row) {
            $updates = [];

            foreach ($columns as $column) {
                $value = $row->{$column} ?? null;

                if ($value !== null && $value !== '') {
                    try {
                        $updates[$column] = decrypt($value);
                    } catch (DecryptException) {
                        // Already plaintext; leave as-is.
                    }
                }
            }

            if ($updates !== []) {
                DB::table('shiprocket_settings')->where('id', $row->id)->update($updates);
            }
        }
    }
};
