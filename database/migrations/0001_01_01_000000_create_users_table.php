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
        Schema::create('users', function (Blueprint $table) {
            $table->id()->comment = "使用者編號";
            $table->string('name', 40)->comment = "姓名";
            $table->string('account', 40)->nullable()->comment = "帳號代碼";
            $table->string('password', 100)->comment = "加密密碼";
            $table->string('group_type', 60)->default('Normal')->comment = "來源(Google/Facebook/Normal)";
            $table->string('email', 50)->unique()->comment = "電子信箱";
            $table->timestamp('email_verified_at')->nullable()->comment = "電子信箱驗證";

            $table->integer('user_type')->default(2)->comment = "使用者類別(1:admin,2:管理員,3:一般會員)";
            $table->boolean('user_status')->default(0)->comment = "帳號狀態(false:停用, true:啟用)";

            $table->integer('area_type')->default(1)->comment = "使用者所屬區域(1:大林廠、2:桃園廠、3:大林與桃園廠)";
            $table->integer('mail_type')->default(1)->comment = "使用者是否發送Email(1.發信, 2:不發信)";

            $table->boolean('resetpwd')->default(0)->comment = "是否為忘記密碼重置(false:否, true:是)";
            $table->boolean('is_lock')->default(0)->comment = "帳號是否鎖住(false:否, true:是)";
            $table->integer('pw_err_count')->default(0)->comment = "登入錯誤次數";
            $table->timestamp('pw_err_date')->nullable()->comment = "登入錯誤最後日期";
            $table->timestamp('user_open_at')->nullable()->comment = "管理員開通帳號時間";
            $table->integer('open_user_uid')->nullable()->comment = "開通帳號之管理員編號";

            $table->string('tick')->nullable()->comment = "email tick";
            
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
