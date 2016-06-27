<?php

namespace Klsandbox\ReportRoute\Http\Controllers;

use App\Models\Organization;
use Illuminate\Support\Facades\Input;
use Klsandbox\ReportRoute\Models\MonthlyReport;
use Artisan;
use Session;
use Redirect;
use Klsandbox\SiteModel\Site;
use App\Http\Controllers\Controller;

class ReportController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function postUpdateMonthlyReport($year, $month)
    {
        Artisan::call('site:updatereport', ['--year' => $year, '--month' => $month]);
        Session::flash('success_message', 'Report has been refreshed.');

        return Redirect::back();
    }

    public function getMonthlyReport($year, $month, $is_hq, $organization_id, $filter)
    {
        $report = MonthlyReport::forSite()
            ->where('year', '=', $year)
            ->where('month', '=', $month)
            ->where('is_hq', '=', $is_hq);

        if ($organization_id) {
            $report = $report->where('organization_id', '=', $organization_id);
        }

        $report = $report->with('userReports')
            ->first();

        Site::protect($report, 'Report');

        $userReports = $report->userReports;

        if ($filter == 'active') {
            $userReports = $userReports->filter(function ($item) {
                return $item->orders_count +
                $item->introductions_count +
                $item->bonus_payout_cash +
                $item->bonus_payout_gold +
                $item->bonus_payout_not_chosen;
            });
        }

        $hasBonus = (bool)config('bonus');

        return view('report-route::monthly-report')
            ->with('year', $year)
            ->with('month', $month)
            ->with('totalOrders', $report->orders_count)
            ->with('totalApprovedOrders', $report->approved_orders_count)
            ->with('newUsersCount', $report->new_users_count)
            ->with('totalUsersCount', $report->total_users_count)
            ->with('totalRevenue', $report->total_revenue)
            ->with('bonusPayoutForMonth', $report->getBonusPayout())
            ->with('userData', $userReports)
            ->with('filter', $filter)
            ->with('has_bonus', $hasBonus)
            ->with('for', $is_hq ? 'HQ' : Organization::find($organization_id)->name);
    }

    public function getMonthlyReportList($filter)
    {
        $q = MonthlyReport::forSite();

        $q = $q->where('is_hq', '=', $filter == 'hq');

        if ($filter == 'org') {
            $q = $q->where('organization_id', '=', Organization::HQ()->id);
        } elseif ($filter == 'pl') {
            $q = $q->where('organization_id', '<>', Organization::HQ()->id);
        }

        $show_all = Input::get('show_all');

        if (!$show_all) {
            $q = $q->where('orders_count', '>', 0);
        }


        $list = $q->orderBy('year', 'DESC')
            ->orderBy('month', 'DESC')
            ->paginate(50);

        return view('report-route::list-monthly-report')->with('list', $list);
    }
}
