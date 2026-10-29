<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->unsignedInteger('quantity')->default(1);
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('position', 150)->nullable();
            $table->string('serial', 200)->nullable();
            $table->date('purchased_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();
            $table->index(['section_id', 'position']);
            $table->index('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
