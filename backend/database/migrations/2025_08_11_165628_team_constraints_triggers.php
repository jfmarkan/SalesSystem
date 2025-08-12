<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {

        // 1) Al crear team: garantizar membresía del manager como MANAGER
        DB::unprepared("DROP TRIGGER IF EXISTS trg_teams_after_insert_mgr_member;");
        DB::unprepared("
            CREATE TRIGGER trg_teams_after_insert_mgr_member
            AFTER INSERT ON teams
            FOR EACH ROW
            BEGIN
              INSERT IGNORE INTO team_members (team_id, user_id, role, created_at, updated_at)
              VALUES (NEW.id, NEW.manager_user_id, 'MANAGER', NOW(), NOW());
            END
        ");

        // 2) Al cambiar manager: mover membresía MANAGER al nuevo user
        DB::unprepared("DROP TRIGGER IF EXISTS trg_teams_before_update_mgr_swap;");
        DB::unprepared("
            CREATE TRIGGER trg_teams_before_update_mgr_swap
            BEFORE UPDATE ON teams
            FOR EACH ROW
            BEGIN
              IF NEW.manager_user_id <> OLD.manager_user_id THEN
                -- antiguo manager: mantener como miembro pero no MANAGER
                UPDATE team_members
                   SET role = CASE WHEN user_id = OLD.manager_user_id THEN 'SALES_REP' ELSE role END,
                       updated_at = NOW()
                 WHERE team_id = OLD.id AND user_id = OLD.manager_user_id;

                -- nuevo manager: debe existir como miembro MANAGER
                INSERT INTO team_members (team_id, user_id, role, created_at, updated_at)
                VALUES (OLD.id, NEW.manager_user_id, 'MANAGER', NOW(), NOW())
                ON DUPLICATE KEY UPDATE role = 'MANAGER', updated_at = NOW();
              END IF;
            END
        ");

        // 3) Prohibir tener MANAGER distinto del definido en teams.manager_user_id
        DB::unprepared("DROP TRIGGER IF EXISTS trg_team_members_before_insert_one_manager;");
        DB::unprepared("
            CREATE TRIGGER trg_team_members_before_insert_one_manager
            BEFORE INSERT ON team_members
            FOR EACH ROW
            BEGIN
              DECLARE mgr_id BIGINT;
              SELECT manager_user_id INTO mgr_id FROM teams WHERE id = NEW.team_id;

              IF NEW.role = 'MANAGER' AND NEW.user_id <> mgr_id THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only the designated manager can have MANAGER role.';
              END IF;
            END
        ");

        DB::unprepared("DROP TRIGGER IF EXISTS trg_team_members_before_update_one_manager;");
        DB::unprepared("
            CREATE TRIGGER trg_team_members_before_update_one_manager
            BEFORE UPDATE ON team_members
            FOR EACH ROW
            BEGIN
              DECLARE mgr_id BIGINT;
              SELECT manager_user_id INTO mgr_id FROM teams WHERE id = NEW.team_id;

              -- No permitir quitar rol MANAGER al manager vigente
              IF OLD.user_id = mgr_id AND OLD.role = 'MANAGER' AND NEW.role <> 'MANAGER' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Team must always have a manager.';
              END IF;

              -- No permitir setear MANAGER a alguien que no sea el manager designado
              IF NEW.role = 'MANAGER' AND NEW.user_id <> mgr_id THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only the designated manager can have MANAGER role.';
              END IF;
            END
        ");

        // 4) Prohibir borrar la fila del manager
        DB::unprepared("DROP TRIGGER IF EXISTS trg_team_members_before_delete_protect_manager;");
        DB::unprepared("
            CREATE TRIGGER trg_team_members_before_delete_protect_manager
            BEFORE DELETE ON team_members
            FOR EACH ROW
            BEGIN
              DECLARE mgr_id BIGINT;
              SELECT manager_user_id INTO mgr_id FROM teams WHERE id = OLD.team_id;
              IF OLD.user_id = mgr_id THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot remove manager from team_members.';
              END IF;
            END
        ");
    }

    public function down(): void {
        DB::unprepared("DROP TRIGGER IF EXISTS trg_team_members_before_delete_protect_manager;");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_team_members_before_update_one_manager;");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_team_members_before_insert_one_manager;");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_teams_before_update_mgr_swap;");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_teams_after_insert_mgr_member;");
    }
};
