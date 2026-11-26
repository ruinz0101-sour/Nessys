<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared("
                CREATE OR REPLACE FUNCTION prevent_audit_logs_modification()
                RETURNS TRIGGER AS \$\$
                BEGIN
                    RAISE EXCEPTION 'audit_logs table is append-only (INSERT/SELECT only)';
                END;
                \$\$ LANGUAGE plpgsql;

                CREATE TRIGGER audit_logs_no_update_or_delete
                BEFORE UPDATE OR DELETE ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION prevent_audit_logs_modification();
            ");
        } elseif ($driver === 'sqlite') {
            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS audit_logs_no_update
                BEFORE UPDATE ON audit_logs
                BEGIN
                    SELECT RAISE(ABORT, 'audit_logs table is append-only (INSERT/SELECT only)');
                END;

                CREATE TRIGGER IF NOT EXISTS audit_logs_no_delete
                BEFORE DELETE ON audit_logs
                BEGIN
                    SELECT RAISE(ABORT, 'audit_logs table is append-only (INSERT/SELECT only)');
                END;
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared('
                DROP TRIGGER IF EXISTS audit_logs_no_update_or_delete ON audit_logs;
                DROP FUNCTION IF EXISTS prevent_audit_logs_modification();
            ');
        } elseif ($driver === 'sqlite') {
            DB::unprepared('
                DROP TRIGGER IF EXISTS audit_logs_no_update;
                DROP TRIGGER IF EXISTS audit_logs_no_delete;
            ');
        }
    }
};
