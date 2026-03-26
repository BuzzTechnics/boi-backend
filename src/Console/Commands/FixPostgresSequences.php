<?php

namespace Boi\Backend\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixPostgresSequences extends Command
{
    protected $signature = 'fix:postgres-sequences';

    protected $description = 'Fix PostgreSQL sequences that are out of sync';

    public function handle()
    {
        $tables = $this->getTablesWithSequences();

        foreach ($tables as $table) {
            $this->fixSequence($table);
        }

        $this->info('All sequences fixed successfully');

        return Command::SUCCESS;
    }

    private function getTablesWithSequences()
    {
        $tables = DB::select("
            SELECT tablename 
            FROM pg_tables 
            WHERE schemaname = 'public'
        ");

        $tablesWithSequences = [];

        foreach ($tables as $table) {
            $tableName = $table->tablename;
            $sequenceName = $tableName.'_id_seq';

            $sequenceExists = DB::selectOne('
                SELECT EXISTS (
                    SELECT 1 
                    FROM pg_class 
                    WHERE relname = ?
                ) as exists
            ', [$sequenceName]);

            if ($sequenceExists && $sequenceExists->exists) {
                $tablesWithSequences[] = $tableName;
            }
        }

        return $tablesWithSequences;
    }

    private function fixSequence($tableName)
    {
        if (! Schema::hasTable($tableName)) {
            $this->warn("Table {$tableName} does not exist");

            return;
        }

        $sequenceName = $tableName.'_id_seq';

        $maxId = DB::table($tableName)->max('id');

        if ($maxId === null) {
            $this->info("Table {$tableName} is empty, setting sequence to 1");
            DB::statement("SELECT setval('{$sequenceName}', 1, false)");
        } else {
            DB::statement("SELECT setval('{$sequenceName}', (SELECT MAX(id) FROM {$tableName}))");
            $this->info("Fixed sequence for {$tableName} (max id: {$maxId})");
        }
    }
}
