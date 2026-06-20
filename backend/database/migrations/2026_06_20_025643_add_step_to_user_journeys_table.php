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
        Schema::table('user_journeys', function (Blueprint $table) {
            $table->integer('step')->default(3)->after('current_day'); // step 1, 2 ou 3 (padrão 3 para compatibilidade com os testes anteriores)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_journeys', function (Blueprint $table) {
            $table->dropColumn('step');
        });
    }
};
