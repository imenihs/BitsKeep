<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spec_groups', function (Blueprint $table) {
            if (! Schema::hasColumn('spec_groups', 'series_management_mode')) {
                $table->string('series_management_mode', 32)->default('single')->index();
            }
        });

        Schema::create('component_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spec_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('value_spec_type_id')->nullable()->constrained('spec_types')->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('manufacturer')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->integer('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['spec_group_id', 'status']);
            $table->index(['manufacturer', 'name']);
        });

        Schema::create('component_series_value_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_series_id')->constrained('component_series')->cascadeOnDelete();
            $table->string('value_set_type', 32)->default('none');
            $table->string('primary_series', 16)->nullable();
            $table->json('extra_series')->nullable();
            $table->json('custom_values')->nullable();
            $table->json('extra_values')->nullable();
            $table->json('excluded_values')->nullable();
            $table->string('unit', 40)->nullable();
            $table->integer('decade_min')->nullable();
            $table->integer('decade_max')->nullable();
            $table->decimal('range_min', 30, 15)->nullable();
            $table->decimal('range_max', 30, 15)->nullable();
            $table->decimal('range_step', 30, 15)->nullable();
            $table->unsignedSmallInteger('rounding_digits')->default(15);
            $table->json('generation_settings')->nullable();
            $table->timestamps();
            $table->unique('component_series_id');
            $table->index(['value_set_type', 'primary_series']);
        });

        Schema::create('component_series_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_series_id')->constrained('component_series')->cascadeOnDelete();
            $table->string('value_text');
            $table->string('value_key');
            $table->decimal('value_numeric', 30, 15)->nullable();
            $table->string('unit', 40)->nullable();
            $table->string('origin', 32)->default('manual')->index();
            $table->string('source_series', 16)->nullable();
            $table->boolean('is_enabled')->default(true)->index();
            $table->boolean('is_stocked')->default(false)->index();
            $table->foreignId('materialized_component_id')->nullable()->constrained('components')->nullOnDelete();
            $table->integer('sort_order')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['component_series_id', 'value_key']);
            $table->index(['component_series_id', 'sort_order']);
            $table->index(['component_series_id', 'is_enabled']);
        });

        Schema::table('components', function (Blueprint $table) {
            if (! Schema::hasColumn('components', 'component_series_id')) {
                $table->foreignId('component_series_id')->nullable()->constrained('component_series')->nullOnDelete();
            }
            if (! Schema::hasColumn('components', 'component_series_value_id')) {
                $table->foreignId('component_series_value_id')->nullable()->constrained('component_series_values')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('components', function (Blueprint $table) {
            if (Schema::hasColumn('components', 'component_series_value_id')) {
                $table->dropConstrainedForeignId('component_series_value_id');
            }
            if (Schema::hasColumn('components', 'component_series_id')) {
                $table->dropConstrainedForeignId('component_series_id');
            }
        });

        Schema::dropIfExists('component_series_values');
        Schema::dropIfExists('component_series_value_policies');
        Schema::dropIfExists('component_series');

        Schema::table('spec_groups', function (Blueprint $table) {
            if (Schema::hasColumn('spec_groups', 'series_management_mode')) {
                $table->dropColumn('series_management_mode');
            }
        });
    }
};
