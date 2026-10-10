<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Esquema base de INAMAP (README Parte 7).
 *
 * - Todas las PK son UUID (S7).
 * - Cada tabla lleva tenancy_id y tenant_id: el TenancyAdmin filtra por
 *   tenancy_id, el resto por tenant_id, igual que los demás dominios.
 * - inamap_id se denormaliza en las tablas hijas para que búsqueda, grafo y
 *   diagnósticos filtren por inamap sin joins a layouts.
 * - La geometría vive en jsonb, en la unidad del inamap (sin PostGIS).
 * - search_text se guarda ya normalizado (minúsculas, sin acentos) por
 *   SearchableObserver, de modo que el índice trigram no necesita unaccent().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        }

        Schema::create('inamaps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenancy_id')->nullable();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('unit', 8)->default('m');
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->jsonb('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('tenancy_id')->references('id')->on('tenancies')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index('tenancy_id');
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('inamap_layouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenancy_id')->nullable();
            $table->uuid('tenant_id');
            $table->uuid('inamap_id');
            $table->string('name');
            $table->integer('level')->default(0);
            $table->decimal('width', 12, 4);
            $table->decimal('height', 12, 4);
            $table->string('image_path')->nullable();
            $table->integer('sort')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('inamap_id')->references('id')->on('inamaps')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['inamap_id', 'level']);
            $table->index('tenant_id');
        });

        // Mismo modelo que las categorías de catálogo del framework: árbol
        // (category_id = padre), imagen, tree_index, y filas de alcance tenancy
        // (tenant_id null) visibles para todos los tenants de esa tenancy.
        Schema::create('inamap_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenancy_id')->nullable();
            $table->uuid('tenant_id')->nullable();
            $table->uuid('category_id')->nullable();
            $table->string('name');
            $table->string('color', 16)->nullable();
            $table->string('icon', 64)->nullable();
            $table->string('image_path')->nullable();
            $table->integer('tree_index')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('tenancy_id')->references('id')->on('tenancies')->onDelete('cascade');
            $table->index('tenant_id');
            $table->index('tenancy_id');
            $table->index('category_id');
        });

        Schema::table('inamap_categories', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('inamap_categories')->onDelete('cascade');
        });

        foreach (['inamap_zones', 'inamap_structures'] as $polygonTable) {
            Schema::create($polygonTable, function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tenancy_id')->nullable();
                $table->uuid('tenant_id');
                $table->uuid('inamap_id');
                $table->uuid('layout_id');
                $table->string('code')->nullable();
                $table->string('label');
                $table->string('color', 16)->nullable();
                $table->jsonb('polygon');
                $table->jsonb('terms')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->text('search_text')->nullable();
                $table->softDeletes();
                $table->timestamps();

                $table->foreign('layout_id')->references('id')->on('inamap_layouts')->onDelete('cascade');
                $table->foreign('inamap_id')->references('id')->on('inamaps')->onDelete('cascade');
                $table->index(['inamap_id', 'layout_id']);
                $table->index('tenant_id');
            });
        }

        Schema::create('inamap_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenancy_id')->nullable();
            $table->uuid('tenant_id');
            $table->uuid('inamap_id');
            $table->uuid('layout_id');
            $table->uuid('structure_id')->nullable();
            $table->uuid('category_id')->nullable();
            $table->string('code')->nullable();
            $table->string('label');
            $table->decimal('x', 12, 4);
            $table->decimal('y', 12, 4);
            $table->string('icon', 64)->nullable();
            $table->string('qr_url', 2048)->nullable();
            $table->jsonb('terms')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->text('search_text')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('layout_id')->references('id')->on('inamap_layouts')->onDelete('cascade');
            $table->foreign('inamap_id')->references('id')->on('inamaps')->onDelete('cascade');
            $table->foreign('structure_id')->references('id')->on('inamap_structures')->nullOnDelete();
            $table->foreign('category_id')->references('id')->on('inamap_categories')->nullOnDelete();
            $table->index(['inamap_id', 'layout_id']);
            $table->index('category_id');
            $table->index('tenant_id');
        });

        Schema::create('inamap_paths', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenancy_id')->nullable();
            $table->uuid('tenant_id');
            $table->uuid('inamap_id');
            $table->uuid('layout_id');
            $table->string('label')->nullable();
            $table->jsonb('points');
            $table->string('direction', 16)->default('both');
            $table->jsonb('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('layout_id')->references('id')->on('inamap_layouts')->onDelete('cascade');
            $table->foreign('inamap_id')->references('id')->on('inamaps')->onDelete('cascade');
            $table->index(['inamap_id', 'layout_id']);
            $table->index('tenant_id');
        });

        Schema::create('inamap_path_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenancy_id')->nullable();
            $table->uuid('tenant_id');
            $table->uuid('inamap_id');
            $table->uuid('from_path_id');
            $table->string('from_end', 8);
            $table->uuid('to_path_id');
            $table->string('to_end', 8);
            $table->string('kind', 16)->default('link');
            $table->string('vertical_type', 16)->nullable();
            $table->decimal('cost', 12, 4)->nullable();
            $table->boolean('bidirectional')->default(true);
            $table->jsonb('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('from_path_id')->references('id')->on('inamap_paths')->onDelete('cascade');
            $table->foreign('to_path_id')->references('id')->on('inamap_paths')->onDelete('cascade');
            $table->foreign('inamap_id')->references('id')->on('inamaps')->onDelete('cascade');
            $table->index('inamap_id');
            $table->index('tenant_id');
        });

        Schema::create('inamap_route_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenancy_id')->nullable();
            $table->uuid('tenant_id');
            $table->uuid('inamap_id');
            $table->uuid('created_by_user_id')->nullable();
            $table->string('created_by', 16)->default('visitor');
            $table->string('from_ref');
            $table->string('to_ref');
            $table->boolean('accessible')->default(false);
            $table->jsonb('route');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('first_opened_at')->nullable();
            $table->unsignedInteger('open_count')->default(0);
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->foreign('inamap_id')->references('id')->on('inamaps')->onDelete('cascade');
            $table->index(['inamap_id', 'status', 'expires_at']);
            $table->index('tenant_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            // slug único por tenant entre inamaps no eliminados.
            DB::statement('CREATE UNIQUE INDEX inamaps_tenant_slug_unique ON inamaps (tenant_id, slug) WHERE deleted_at IS NULL');

            foreach (['inamap_zones', 'inamap_structures', 'inamap_locations'] as $searchable) {
                DB::statement("CREATE INDEX {$searchable}_search_trgm ON {$searchable} USING gin (search_text gin_trgm_ops)");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inamap_route_sessions');
        Schema::dropIfExists('inamap_path_links');
        Schema::dropIfExists('inamap_paths');
        Schema::dropIfExists('inamap_locations');
        Schema::dropIfExists('inamap_structures');
        Schema::dropIfExists('inamap_zones');
        Schema::dropIfExists('inamap_categories');
        Schema::dropIfExists('inamap_layouts');
        Schema::dropIfExists('inamaps');
    }
};
