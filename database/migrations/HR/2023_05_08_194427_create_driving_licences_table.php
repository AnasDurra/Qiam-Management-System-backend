<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driving_licences', function (Blueprint $table) {
            $table->id('driving_licence_id');
            $table->unsignedBigInteger('emp_data_id');
            $table->string('category', 50)->nullable();
            $table->date('date_of_issue');
            $table->string('place_of_issue', 100);
            $table->string('number', 30);
            $table->date('expiry_date');
            $table->string('blood_group', 25);
            $table->timestamps();

            $table->foreign('emp_data_id')->references('emp_data_id')->on('emp_data');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('driving_licences');
    }
};
