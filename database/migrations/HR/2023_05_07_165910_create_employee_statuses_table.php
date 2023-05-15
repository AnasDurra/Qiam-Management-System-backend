<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_statuses', function (Blueprint $table) {
            $table->id('status_id');
            $table->unsignedBigInteger('emp_id');
            $table->unsignedBigInteger('emp_status_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('cur_title');
            $table->integer('cur_dep');


            $table->foreign('emp_id')->references('emp_id')->on('employees');
            $table->foreign('emp_status_id')->references('emp_status_id')->on('employment_statuses');

            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('employee_statuses');
    }
};
