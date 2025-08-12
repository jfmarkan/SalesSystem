<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ForecastRadarController extends Controller
{
    /**
     * Radar dataset per Profit Center for the logged-in user.
     * Query params:
     *  - unit: raw|m3|euro (default raw)
     */
    public function radar(Request $request)
    {
        $userId = (int) $request->user()->id;
        $unit   = $request->query('unit', 'raw'); // raw|m3|euro

        $salesCol    = $unit === 'euro' ? 'VolumeInEuro'  : ($unit === 'm3' ? 'VolumeInM3'  : 'RawVolume');
        $budgetCol   = $unit === 'euro' ? 'BudgetInEuro'  : ($unit === 'm3' ? 'BudgetInM3'  : 'RawBudget');
        $forecastCol = $unit === 'euro' ? 'ForecastInEuro': ($unit === 'm3' ? 'ForecastInM3': 'RawForecast');

        // CTEs (MySQL 8+) mirroring your Access logic (fiscal year Apr..Dec curr + Jan..Mar next)
        $sql = "
            WITH pcs AS (
                SELECT DISTINCT cp.profit_center_code
                FROM assignment_matrix a
                JOIN user_manager um   ON um.id  = a.user_manager_id
                JOIN client_profit_center cp ON cp.id  = a.client_profit_center_id
                WHERE um.user_id = :user_id
            ),
            b AS (
                SELECT cp.profit_center_code,
                       SUM(b.volume) AS RawBudget,
                       SUM(b.volume * COALESCE(uc.factor_to_m3, 1))                                   AS BudgetInM3,
                       SUM(b.volume * COALESCE(uc.factor_to_m3, 1) * COALESCE(uc.factor_to_euro, 1)) AS BudgetInEuro
                FROM assignment_matrix a
                JOIN user_manager um   ON um.id  = a.user_manager_id
                JOIN client_profit_center cp ON cp.id  = a.client_profit_center_id
                JOIN budgets b         ON b.client_profit_center_id = a.client_profit_center_id
                LEFT JOIN unit_conversions uc ON uc.profit_center_code = cp.profit_center_code
                WHERE um.user_id = :user_id
                  AND (
                        (b.budget_month BETWEEN 4 AND 12 AND b.budget_year = YEAR(CURDATE()))
                     OR (b.budget_month BETWEEN 1 AND 3  AND b.budget_year = YEAR(CURDATE()) + 1)
                  )
                GROUP BY cp.profit_center_code
            ),
            f AS (
                SELECT cp.profit_center_code,
                       SUM(f.volume) AS RawForecast,
                       SUM(f.volume * COALESCE(uc.factor_to_m3, 1))                                   AS ForecastInM3,
                       SUM(f.volume * COALESCE(uc.factor_to_m3, 1) * COALESCE(uc.factor_to_euro, 1)) AS ForecastInEuro
                FROM assignment_matrix a
                JOIN user_manager um   ON um.id  = a.user_manager_id
                JOIN client_profit_center cp ON cp.id  = a.client_profit_center_id
                JOIN forecasts f       ON f.assignment_matrix_id = a.id
                LEFT JOIN unit_conversions uc ON uc.profit_center_code = cp.profit_center_code
                WHERE um.user_id = :user_id
                  AND (
                        (f.forecast_month BETWEEN 4 AND 12 AND f.forecast_year = YEAR(CURDATE()))
                     OR (f.forecast_month BETWEEN 1 AND 3  AND f.forecast_year = YEAR(CURDATE()) + 1)
                  )
                GROUP BY cp.profit_center_code
            ),
            s AS (
                SELECT cp.profit_center_code,
                       SUM(s.volume) AS RawVolume,
                       SUM(s.volume * COALESCE(uc.factor_to_m3, 1))                                   AS VolumeInM3,
                       SUM(s.volume * COALESCE(uc.factor_to_m3, 1) * COALESCE(uc.factor_to_euro, 1)) AS VolumeInEuro
                FROM assignment_matrix a
                JOIN user_manager um   ON um.id  = a.user_manager_id
                JOIN client_profit_center cp ON cp.id  = a.client_profit_center_id
                JOIN sales s           ON s.client_profit_center_id = a.client_profit_center_id
                LEFT JOIN unit_conversions uc ON uc.profit_center_code = cp.profit_center_code
                WHERE um.user_id = :user_id
                  AND (
                        (s.sales_month BETWEEN 4 AND 12 AND s.sales_year = YEAR(CURDATE()))
                     OR (s.sales_month BETWEEN 1 AND 3  AND s.sales_year = YEAR(CURDATE()) + 1)
                  )
                GROUP BY cp.profit_center_code
            )
            SELECT
              pc.profit_center_code,
              pc.profit_center_name,
              COALESCE(s.$salesCol,    0) AS total_sales,
              COALESCE(b.$budgetCol,   0) AS total_budget,
              COALESCE(f.$forecastCol, 0) AS total_forecast
            FROM profit_centers pc
            INNER JOIN pcs ON pcs.profit_center_code = pc.profit_center_code
            LEFT  JOIN s   ON s.profit_center_code   = pc.profit_center_code
            LEFT  JOIN b   ON b.profit_center_code   = pc.profit_center_code
            LEFT  JOIN f   ON f.profit_center_code   = pc.profit_center_code
            ORDER BY pc.profit_center_code
        ";

        $rows = DB::select($sql, ['user_id' => $userId]);

        return response()->json([
            'unit' => $unit,
            'data' => $rows,
        ]);
    }

    /**
     * Detailed table for figures (separate widget).
     * Query params:
     *  - unit: raw|m3|euro (default raw)
     *  - mode: ytd|last_month|full_year (default ytd)
     *      - ytd: from fiscal year start (Apr 1) up to the last full month
     *      - last_month: previous calendar month
     *      - full_year: full fiscal window Apr..Dec(current) + Jan..Mar(next)
     */
    public function radarTable(Request $request)
    {
        $userId = (int) $request->user()->id;
        $unit   = $request->query('unit', 'raw');        // raw|m3|euro
        $mode   = $request->query('mode', 'ytd');        // ytd|last_month|full_year

        $salesCol    = $unit === 'euro' ? 'VolumeInEuro'  : ($unit === 'm3' ? 'VolumeInM3'  : 'RawVolume');
        $budgetCol   = $unit === 'euro' ? 'BudgetInEuro'  : ($unit === 'm3' ? 'BudgetInM3'  : 'RawBudget');
        $forecastCol = $unit === 'euro' ? 'ForecastInEuro': ($unit === 'm3' ? 'ForecastInM3': 'RawForecast');

        // WHERE fragments per mode (re-usable across tables/aliases)
        // Helpers for last full month:
        //   prev = DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
        //   PMONTH = MONTH(prev), PYEAR = YEAR(prev)
        $where = [
            'budget' => '',
            'forecast' => '',
            'sales' => '',
        ];

        if ($mode === 'full_year') {
            $where['budget']   = "( (b.budget_month BETWEEN 4 AND 12 AND b.budget_year = YEAR(CURDATE()))
                                OR (b.budget_month BETWEEN 1 AND 3  AND b.budget_year = YEAR(CURDATE()) + 1) )";
            $where['forecast'] = "( (f.forecast_month BETWEEN 4 AND 12 AND f.forecast_year = YEAR(CURDATE()))
                                OR (f.forecast_month BETWEEN 1 AND 3  AND f.forecast_year = YEAR(CURDATE()) + 1) )";
            $where['sales']    = "( (s.sales_month BETWEEN 4 AND 12 AND s.sales_year = YEAR(CURDATE()))
                                OR (s.sales_month BETWEEN 1 AND 3  AND s.sales_year = YEAR(CURDATE()) + 1) )";
        } elseif ($mode === 'last_month') {
            $where['budget']   = "( b.budget_month = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                                    AND b.budget_year  = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) )";
            $where['forecast'] = "( f.forecast_month = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                                    AND f.forecast_year  = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) )";
            $where['sales']    = "( s.sales_month = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                                    AND s.sales_year  = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) )";
        } else { // ytd (from Apr 1 of fiscal year to last full month)
            // Build two-part condition to bridge calendar year boundary:
            // Determine fiscal year start year FY: if MONTH(CURDATE())>=4 then YEAR(CURDATE()) else YEAR(CURDATE())-1
            // and last full month (PMONTH/PYEAR). We map months Apr..Dec to FY, Jan..Mar to FY+1.
            $where['budget'] = "(
                (b.budget_year = CASE WHEN MONTH(CURDATE()) >= 4 THEN YEAR(CURDATE()) ELSE YEAR(CURDATE())-1 END
                 AND b.budget_month BETWEEN 4 AND LEAST(12, MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))))
                OR
                (b.budget_month BETWEEN 1 AND 3
                 AND b.budget_year = CASE WHEN MONTH(CURDATE()) >= 4 THEN YEAR(CURDATE())+1 ELSE YEAR(CURDATE()) END
                 AND MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) <= 3
                 AND b.budget_month <= MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)))
            )";
            $where['forecast'] = "(
                (f.forecast_year = CASE WHEN MONTH(CURDATE()) >= 4 THEN YEAR(CURDATE()) ELSE YEAR(CURDATE())-1 END
                 AND f.forecast_month BETWEEN 4 AND LEAST(12, MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))))
                OR
                (f.forecast_month BETWEEN 1 AND 3
                 AND f.forecast_year = CASE WHEN MONTH(CURDATE()) >= 4 THEN YEAR(CURDATE())+1 ELSE YEAR(CURDATE()) END
                 AND MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) <= 3
                 AND f.forecast_month <= MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)))
            )";
            $where['sales'] = "(
                (s.sales_year = CASE WHEN MONTH(CURDATE()) >= 4 THEN YEAR(CURDATE()) ELSE YEAR(CURDATE())-1 END
                 AND s.sales_month BETWEEN 4 AND LEAST(12, MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))))
                OR
                (s.sales_month BETWEEN 1 AND 3
                 AND s.sales_year = CASE WHEN MONTH(CURDATE()) >= 4 THEN YEAR(CURDATE())+1 ELSE YEAR(CURDATE()) END
                 AND MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) <= 3
                 AND s.sales_month <= MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)))
            )";
        }

        $sql = "
            WITH pcs AS (
                SELECT DISTINCT cp.profit_center_code
                FROM assignment_matrix a
                JOIN user_manager um   ON um.id  = a.user_manager_id
                JOIN client_profit_center cp ON cp.id  = a.client_profit_center_id
                WHERE um.user_id = :user_id
            ),
            b AS (
                SELECT cp.profit_center_code,
                       SUM(b.volume) AS RawBudget,
                       SUM(b.volume * COALESCE(uc.factor_to_m3, 1))                                   AS BudgetInM3,
                       SUM(b.volume * COALESCE(uc.factor_to_m3, 1) * COALESCE(uc.factor_to_euro, 1)) AS BudgetInEuro
                FROM assignment_matrix a
                JOIN user_manager um   ON um.id  = a.user_manager_id
                JOIN client_profit_center cp ON cp.id  = a.client_profit_center_id
                JOIN budgets b         ON b.client_profit_center_id = a.client_profit_center_id
                LEFT JOIN unit_conversions uc ON uc.profit_center_code = cp.profit_center_code
                WHERE um.user_id = :user_id AND {$where['budget']}
                GROUP BY cp.profit_center_code
            ),
            f AS (
                SELECT cp.profit_center_code,
                       SUM(f.volume) AS RawForecast,
                       SUM(f.volume * COALESCE(uc.factor_to_m3, 1))                                   AS ForecastInM3,
                       SUM(f.volume * COALESCE(uc.factor_to_m3, 1) * COALESCE(uc.factor_to_euro, 1)) AS ForecastInEuro
                FROM assignment_matrix a
                JOIN user_manager um   ON um.id  = a.user_manager_id
                JOIN client_profit_center cp ON cp.id  = a.client_profit_center_id
                JOIN forecasts f       ON f.assignment_matrix_id = a.id
                LEFT JOIN unit_conversions uc ON uc.profit_center_code = cp.profit_center_code
                WHERE um.user_id = :user_id AND {$where['forecast']}
                GROUP BY cp.profit_center_code
            ),
            s AS (
                SELECT cp.profit_center_code,
                       SUM(s.volume) AS RawVolume,
                       SUM(s.volume * COALESCE(uc.factor_to_m3, 1))                                   AS VolumeInM3,
                       SUM(s.volume * COALESCE(uc.factor_to_m3, 1) * COALESCE(uc.factor_to_euro, 1)) AS VolumeInEuro
                FROM assignment_matrix a
                JOIN user_manager um   ON um.id  = a.user_manager_id
                JOIN client_profit_center cp ON cp.id  = a.client_profit_center_id
                JOIN sales s           ON s.client_profit_center_id = a.client_profit_center_id
                LEFT JOIN unit_conversions uc ON uc.profit_center_code = cp.profit_center_code
                WHERE um.user_id = :user_id AND {$where['sales']}
                GROUP BY cp.profit_center_code
            )
            SELECT
              pc.profit_center_code,
              pc.profit_center_name,
              COALESCE(s.$salesCol,    0) AS total_sales,
              COALESCE(b.$budgetCol,   0) AS total_budget,
              COALESCE(f.$forecastCol, 0) AS total_forecast
            FROM profit_centers pc
            INNER JOIN pcs ON pcs.profit_center_code = pc.profit_center_code
            LEFT  JOIN s   ON s.profit_center_code   = pc.profit_center_code
            LEFT  JOIN b   ON b.profit_center_code   = pc.profit_center_code
            LEFT  JOIN f   ON f.profit_center_code   = pc.profit_center_code
            ORDER BY pc.profit_center_code
        ";

        $rows = DB::select($sql, ['user_id' => $userId]);

        return response()->json([
            'unit' => $unit,
            'mode' => $mode,
            'data' => $rows,
        ]);
    }
}