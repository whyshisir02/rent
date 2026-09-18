use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\User;
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add verification decision columns to profile tables
        Schema::table('landlords', function (Blueprint $table) {
            $table->string('verification_rejection_reason', 500)->nullable()->after('verification_status');
            $table->foreignId('verified_by')->nullable()->after('verification_rejection_reason')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('verification_rejection_reason', 500)->nullable()->after('verification_status');
            $table->foreignId('verified_by')->nullable()->after('verification_rejection_reason')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('landlords', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropColumn(['verification_rejection_reason', 'verified_by', 'verified_at']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropColumn(['verification_rejection_reason', 'verified_by', 'verified_at']);
        });

        // Evidence tables never had status columns in Option A (build-time stripped)
    }
};
