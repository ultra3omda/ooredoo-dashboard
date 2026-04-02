<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MLMerchantRecommendationService;
use Illuminate\Http\Request;

class MerchantRecommendationController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!auth()->user() || !auth()->user()->isSuperAdmin()) {
                abort(403, 'Accès réservé au Super Administrateur.');
            }
            return $next($request);
        });
    }

    public function index()
    {
        $service = new MLMerchantRecommendationService();
        $health = $service->getHealth();

        return view('admin.merchant-recommendations', compact('health'));
    }

    public function getRecommendations(Request $request)
    {
        $service = new MLMerchantRecommendationService();
        $result = $service->getRecommendations(
            (int) $request->input('client_id'),
            (int) $request->input('top_k', 10),
            $request->input('category_id') ? (int) $request->input('category_id') : null,
            (bool) $request->input('exclude_visited', false)
        );

        return response()->json($result);
    }

    public function getPopular(Request $request)
    {
        $service = new MLMerchantRecommendationService();
        $result = $service->getRecommendations(0, 20);
        return response()->json($result);
    }

    public function retrain()
    {
        $service = new MLMerchantRecommendationService();
        $result = $service->triggerRetrain();
        return response()->json($result);
    }

    public function health()
    {
        $service = new MLMerchantRecommendationService();
        return response()->json($service->getHealth());
    }
}
