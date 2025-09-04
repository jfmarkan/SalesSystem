<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExtraQuotaAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['fiscal_year'=>2025,'profit_center_code'=>110,'user_id'=>5,'volume'=>1266,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>143,'user_id'=>5,'volume'=>1789,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>144,'user_id'=>5,'volume'=>69563,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>171,'user_id'=>5,'volume'=>1146,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>140,'user_id'=>4,'volume'=>2495259,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>142,'user_id'=>4,'volume'=>241773,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>143,'user_id'=>4,'volume'=>1789,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>173,'user_id'=>4,'volume'=>1458,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>174,'user_id'=>4,'volume'=>938,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>173,'user_id'=>6,'volume'=>1458,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>174,'user_id'=>6,'volume'=>938,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>110,'user_id'=>7,'volume'=>327,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>130,'user_id'=>7,'volume'=>41057,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>141,'user_id'=>7,'volume'=>11726,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>143,'user_id'=>7,'volume'=>1789,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>160,'user_id'=>7,'volume'=>167218,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>170,'user_id'=>7,'volume'=>1434,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>174,'user_id'=>7,'volume'=>469,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>130,'user_id'=>8,'volume'=>41057,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>141,'user_id'=>8,'volume'=>7818,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>143,'user_id'=>8,'volume'=>1789,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>144,'user_id'=>8,'volume'=>38646,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>160,'user_id'=>8,'volume'=>58394,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>174,'user_id'=>8,'volume'=>469,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>175,'user_id'=>8,'volume'=>1,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>130,'user_id'=>9,'volume'=>20528,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>141,'user_id'=>9,'volume'=>6515,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>143,'user_id'=>9,'volume'=>895,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>160,'user_id'=>9,'volume'=>39814,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>174,'user_id'=>9,'volume'=>235,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>170,'user_id'=>10,'volume'=>6990,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>171,'user_id'=>10,'volume'=>109,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>110,'user_id'=>11,'volume'=>41,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>143,'user_id'=>11,'volume'=>1789,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>144,'user_id'=>11,'volume'=>69563,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>170,'user_id'=>11,'volume'=>772,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>171,'user_id'=>11,'volume'=>218,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>174,'user_id'=>11,'volume'=>469,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>110,'user_id'=>17,'volume'=>531,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>143,'user_id'=>17,'volume'=>1789,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>110,'user_id'=>13,'volume'=>204,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>143,'user_id'=>13,'volume'=>1789,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>144,'user_id'=>13,'volume'=>69563,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>170,'user_id'=>13,'volume'=>5516,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>171,'user_id'=>13,'volume'=>218,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>174,'user_id'=>13,'volume'=>704,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>110,'user_id'=>14,'volume'=>82,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>143,'user_id'=>14,'volume'=>1789,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>144,'user_id'=>14,'volume'=>69563,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>170,'user_id'=>14,'volume'=>2317,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>171,'user_id'=>14,'volume'=>218,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>110,'user_id'=>12,'volume'=>735,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>143,'user_id'=>12,'volume'=>1789,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>144,'user_id'=>12,'volume'=>69563,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>171,'user_id'=>12,'volume'=>819,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>174,'user_id'=>12,'volume'=>469,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>110,'user_id'=>16,'volume'=>899,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
            ['fiscal_year'=>2025,'profit_center_code'=>143,'user_id'=>16,'volume'=>895,'is_published'=>1,'assignment_date'=>'2025-04-01','created_at'=>'2025-09-03 04:37:24','updated_at'=>'2025-09-03 04:37:24'],
        ];

        // si la tabla está vacía, preservo IDs; si no, hago upsert por (fy, pc, user)
        $empty = DB::table('extra_quota_assignments')->count() === 0;

        // validar users existentes; si falta alguno, se omite esa fila
        $userSet = array_fill_keys(DB::table('users')->pluck('id')->toArray(), true);
        $filtered = array_values(array_filter($rows, fn($r) => isset($userSet[$r['user_id']])));

        if ($empty) {
            DB::table('extra_quota_assignments')->insert($filtered);
            $this->command->info('✅ Imported '.count($filtered).' rows in extra_quota_assignments (preserving IDs).');
        } else {
            $rowsNoId = array_map(function($r){ unset($r['id']); return $r; }, $filtered);
            DB::table('extra_quota_assignments')->upsert(
                $rowsNoId,
                ['fiscal_year','profit_center_code','user_id'],
                ['volume','is_published','assignment_date','updated_at']
            );
            $this->command->info('✅ Upserted '.count($rowsNoId).' rows in extra_quota_assignments.');
        }
    }
}
