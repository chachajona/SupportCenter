<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            Schema::table('knowledge_articles', function (Blueprint $table) {
                $table->fullText(['title', 'content'], 'knowledge_articles_fulltext');
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
                CREATE INDEX knowledge_articles_fulltext
                    ON knowledge_articles
                    USING GIN (
                        to_tsvector(\'simple\', coalesce(title, \'\') || \' \' || coalesce(content, \'\'))
                    )
            ');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            Schema::table('knowledge_articles', function (Blueprint $table) {
                $table->dropFullText('knowledge_articles_fulltext');
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS knowledge_articles_fulltext');
        }
    }
};
