"""
Backend tests for Merchant Recommendations feature (iteration 21)
Tests: ML recommendations, timeline, categories, source field, stats
"""
import pytest
import requests
import os
import time

BASE_URL = os.environ.get('REACT_APP_BACKEND_URL', '').rstrip('/')

class TestMerchantRecommendationsAPI:
    """Tests for /api/merchant-recommendations endpoints"""
    
    def test_recommendations_ml_model_source(self):
        """POST /api/merchant-recommendations with client 114218 returns source='ml_model'"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 114218, "top_k": 5}
        )
        assert response.status_code == 200
        data = response.json()
        
        assert data["success"] is True
        assert data["client_id"] == 114218
        assert data["source"] == "ml_model", f"Expected 'ml_model', got '{data['source']}'"
        assert "recommendations" in data
        assert len(data["recommendations"]) > 0
        
        # Verify recommendation structure
        rec = data["recommendations"][0]
        assert "partner_id" in rec
        assert "partner_name" in rec
        assert "score" in rec
        assert "rank" in rec
        print(f"✓ Client 114218 recommendations: {len(data['recommendations'])} results, source={data['source']}")
    
    def test_recommendations_fallback_popularity_source(self):
        """POST /api/merchant-recommendations with client 0 returns source='fallback_popularity'"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 0, "top_k": 5}
        )
        assert response.status_code == 200
        data = response.json()
        
        assert data["success"] is True
        assert data["source"] == "fallback_popularity", f"Expected 'fallback_popularity', got '{data['source']}'"
        assert len(data["recommendations"]) > 0
        print(f"✓ Client 0 (cold start) recommendations: source={data['source']}")
    
    def test_recommendations_with_category_filter(self):
        """POST /api/merchant-recommendations with category filter"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 114218, "top_k": 5, "category_id": 1}  # Restaurants & cafés
        )
        assert response.status_code == 200
        data = response.json()
        assert data["success"] is True
        print(f"✓ Category filter works: {len(data['recommendations'])} results")
    
    def test_recommendations_missing_client_id(self):
        """POST /api/merchant-recommendations without client_id returns 400"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"top_k": 5}
        )
        assert response.status_code == 400
        data = response.json()
        assert data["success"] is False
        assert "client_id" in data.get("error", "").lower()
        print("✓ Missing client_id returns 400 error")


class TestTimelineEndpoint:
    """Tests for /api/merchant-recommendations/stats/timeline"""
    
    def test_timeline_returns_arrays(self):
        """GET /api/merchant-recommendations/stats/timeline returns timeline and categories arrays"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/stats/timeline")
        assert response.status_code == 200
        data = response.json()
        
        assert "timeline" in data, "Response missing 'timeline' field"
        assert "categories" in data, "Response missing 'categories' field"
        assert isinstance(data["timeline"], list), "timeline should be a list"
        assert isinstance(data["categories"], list), "categories should be a list"
        
        # If timeline has data, verify structure
        if len(data["timeline"]) > 0:
            item = data["timeline"][0]
            assert "day" in item
            assert "interaction_type" in item
            assert "cnt" in item
        
        print(f"✓ Timeline endpoint: {len(data['timeline'])} timeline entries, {len(data['categories'])} categories")


class TestCategoriesEndpoint:
    """Tests for /api/merchant-recommendations/categories"""
    
    def test_categories_returns_11(self):
        """GET /api/merchant-recommendations/categories returns 11 categories"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/categories")
        assert response.status_code == 200
        data = response.json()
        
        assert "categories" in data
        categories = data["categories"]
        assert len(categories) == 11, f"Expected 11 categories, got {len(categories)}"
        
        # Verify structure
        for cat in categories:
            assert "category_id" in cat
            assert "category_name" in cat
        
        cat_names = [c["category_name"] for c in categories]
        print(f"✓ Categories endpoint: {len(categories)} categories - {cat_names[:3]}...")


class TestStatsEndpoint:
    """Tests for /api/merchant-recommendations/stats"""
    
    def test_stats_returns_kpis(self):
        """GET /api/merchant-recommendations/stats returns KPIs"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/stats")
        assert response.status_code == 200
        data = response.json()
        
        assert "active_merchants" in data
        assert "profiled_users" in data
        assert "total_interactions" in data
        
        # Verify expected values
        assert data["active_merchants"] == 576, f"Expected 576 merchants, got {data['active_merchants']}"
        assert data["profiled_users"] == 19249, f"Expected 19249 profiles, got {data['profiled_users']}"
        
        print(f"✓ Stats: {data['active_merchants']} merchants, {data['profiled_users']} profiles, {data['total_interactions']} interactions")


class TestHealthEndpoint:
    """Tests for /api/merchant-recommendations/health"""
    
    def test_health_check(self):
        """GET /api/merchant-recommendations/health returns status"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/health")
        assert response.status_code == 200
        data = response.json()
        
        assert "status" in data
        assert data["status"] in ["ready", "fallback_only"]
        assert "model_loaded" in data
        assert "fallback_available" in data
        
        print(f"✓ Health: status={data['status']}, model_loaded={data['model_loaded']}")


class TestRedisCachePerformance:
    """Tests for Redis cache performance on merchants split endpoint"""
    
    def test_cache_performance(self):
        """2nd call to merchants split should be faster (cached)"""
        url = f"{BASE_URL}/sub-stores/api/split/merchants?sub_store=ALL&start_date=2025-01-01&end_date=2025-12-31"
        
        # First call (may be slow)
        start1 = time.time()
        response1 = requests.get(url)
        time1 = (time.time() - start1) * 1000
        assert response1.status_code == 200
        
        # Second call (should be cached)
        start2 = time.time()
        response2 = requests.get(url)
        time2 = (time.time() - start2) * 1000
        assert response2.status_code == 200
        
        print(f"✓ Cache test: 1st call={time1:.0f}ms, 2nd call={time2:.0f}ms")
        
        # 2nd call should be under 500ms (cached)
        assert time2 < 500, f"2nd call took {time2:.0f}ms, expected <500ms (cache miss?)"


class TestRegressionMerchantKPIs:
    """Regression tests for existing merchant KPIs"""
    
    def test_merchant_kpis_8_values(self):
        """GET /sub-stores/api/split/merchants returns all 8 KPIs"""
        response = requests.get(
            f"{BASE_URL}/sub-stores/api/split/merchants",
            params={"sub_store": "ALL", "start_date": "2025-01-01", "end_date": "2025-12-31"}
        )
        assert response.status_code == 200
        data = response.json()
        
        # Verify all 8 KPIs exist
        expected_kpis = [
            "totalPartners", "activeMerchants", "totalLocationsActive",
            "activeMerchantRatio", "totalTransactions", "transactionsPerMerchant",
            "topMerchantShare", "diversity"
        ]
        
        for kpi in expected_kpis:
            assert kpi in data, f"Missing KPI: {kpi}"
        
        # Verify specific values
        assert data["totalPartners"]["current"] == 576
        assert data["activeMerchants"]["current"] > 0
        assert data["totalTransactions"]["current"] > 0
        
        print(f"✓ Regression: All 8 merchant KPIs present - totalPartners={data['totalPartners']['current']}")


if __name__ == "__main__":
    pytest.main([__file__, "-v", "--tb=short"])
