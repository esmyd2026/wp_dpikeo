<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_flow_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_id')->constrained('marketing_flows')->cascadeOnDelete();
            $table->uuid('node_uuid');
            $table->string('node_type', 40);
            $table->string('name');
            $table->text('message_template')->nullable();
            $table->json('config')->nullable();
            $table->integer('position_x')->default(0);
            $table->integer('position_y')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_start')->default(false);
            $table->timestamps();

            $table->unique(['flow_id', 'node_uuid'], 'mf_nodes_flow_uuid_unique');
        });

        Schema::create('marketing_flow_edges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_id')->constrained('marketing_flows')->cascadeOnDelete();
            $table->uuid('source_node_uuid');
            $table->string('source_handle', 120);
            $table->uuid('target_node_uuid');
            $table->timestamps();

            $table->unique(['flow_id', 'source_node_uuid', 'source_handle'], 'mf_edges_source_unique');
            $table->index(['flow_id', 'target_node_uuid'], 'mf_edges_target_index');
        });

        Schema::create('marketing_flow_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_id')->constrained('marketing_flows')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->json('snapshot');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['flow_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_flow_versions');
        Schema::dropIfExists('marketing_flow_edges');
        Schema::dropIfExists('marketing_flow_nodes');
    }
};
