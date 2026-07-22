<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createSimpleMaster('universities');
        $this->createSimpleMaster('occupations');
        $this->createSimpleMaster('banks');

        if (! Schema::hasTable('institutes')) {
            Schema::create('institutes', function (Blueprint $table): void {
                $this->masterColumns($table);
                $table->foreignId('university_id')->nullable()->constrained('universities')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $table): void {
                $this->masterColumns($table);
                $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
                $table->string('ifsc_code', 20)->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
        Schema::dropIfExists('institutes');
        Schema::dropIfExists('banks');
        Schema::dropIfExists('occupations');
        Schema::dropIfExists('universities');
    }

    private function createSimpleMaster(string $table): void
    {
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $table): void {
            $this->masterColumns($table);
        });
    }

    private function masterColumns(Blueprint $table): void
    {
        $table->id();
        $table->uuid()->unique();
        $table->string('code', 40)->unique();
        $table->string('name');
        $table->text('description')->nullable();
        $table->boolean('is_active')->default(true)->index();
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamps();
        $table->softDeletes();
    }
};
