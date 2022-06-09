<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('check_in', function (Blueprint $table) {
            $table->integer('calculated_flex')->nullable();
            $table->integer('calculated')->nullable();
            $table->dateTime('checked_in');
            $table->dateTime('checked_out')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('check_in', function (Blueprint $table) {
            $table->dropColumn('calculated_flex');
            $table->dropColumn('calculated');
            $table->dropColumn('checked_in');
            $table->dropColumn('checked_out');
        });
    }
};
