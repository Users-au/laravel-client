<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * When running this migration, we want to create three new columns and change the password so it can be NULL
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('usersau_id', 36)->unique();
            $table->text('usersau_access_token');
            $table->text('usersau_refresh_token')->nullable();
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * When rolling back this migration, we want to drop the added columns and revert the password to NOT NULL again
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('usersau_id');
            $table->dropColumn('usersau_access_token');
            $table->dropColumn('usersau_refresh_token');
            $table->string('password')->nullable(false)->change();
        });
    }
};
