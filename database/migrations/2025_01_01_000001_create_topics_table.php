<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class CreateTopicsTable
{
    public function up(Capsule $capsule)
    {
        $capsule->schema()->create('topics', function ($table) {
            $table->increments('id');
            $table->string('name')->unique();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(Capsule $capsule)
    {
        $capsule->schema()->dropIfExists('topics');
    }
}