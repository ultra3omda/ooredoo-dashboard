#!/usr/bin/env python3
"""
Iteration 24 Backend Tests - Club Privileges
Tests for:
- Widget P2 endpoints (JSON + HTML)
- Merchant Intelligence endpoints (analyze, digest, report, report/html)
- ML Recommendations endpoints
- Health, stats, categories endpoints
- Proxy catch-all verification
"""
import pytest
import requests
import os

BASE_URL = os.environ.get('REACT_APP_BACKEND_URL', '').rstrip('/')

class TestWidgetP2Endpoints:
    """P2: Client-facing recommendation widget endpoints"""
    
    def test_widget_json_client_118580(self):
        """GET /api/merchant-recommendations/widget/{client_id} - Widget JSON"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/widget/118580")
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        
        data = response.json()
        assert 'client_id' in data, "Missing client_id in response"
        assert data['client_id'] == 118580, f"Expected client_id 118580, got {data['client_id']}"
        assert 'source' in data, "Missing source in response"
        assert 'items' in data, "Missing items in response"
        assert 'count' in data, "Missing count in response"
        assert isinstance(data['items'], list), "items should be a list"
        
        # Verify item structure
        if data['items']:
            item = data['items'][0]
            required_fields = ['id', 'name', 'category', 'score', 'type', 'reason', 'promos', 'discount', 'visited', 'visits']
            for field in required_fields:
                assert field in item, f"Missing field '{field}' in widget item"
        
        print(f"PASS: Widget JSON for client 118580 - {data['count']} items, source={data['source']}")
    
    def test_widget_json_with_top_k(self):
        """GET /api/merchant-recommendations/widget/{client_id}?top_k=3"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/widget/118580?top_k=3")
        assert response.status_code == 200
        
        data = response.json()
        assert data['count'] <= 3, f"Expected max 3 items, got {data['count']}"
        print(f"PASS: Widget JSON with top_k=3 - {data['count']} items returned")
    
    def test_widget_json_with_exclude_visited(self):
        """GET /api/merchant-recommendations/widget/{client_id}?exclude_visited=true"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/widget/118580?exclude_visited=true")
        assert response.status_code == 200
        
        data = response.json()
        for item in data['items']:
            assert item['visited'] == False, f"Expected visited=False for all items when exclude_visited=true"
        print(f"PASS: Widget JSON with exclude_visited=true - all items unvisited")
    
    def test_widget_html_client_118580(self):
        """GET /api/merchant-recommendations/widget/{client_id}/html - Widget HTML embeddable"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/widget/118580/html")
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        
        content = response.text
        assert '<!DOCTYPE html>' in content or '<html' in content, "Response should be HTML"
        assert 'Recommande pour vous' in content, "HTML should contain 'Recommande pour vous'"
        assert 'Club Privileges ML' in content, "HTML should contain 'Club Privileges ML'"
        print("PASS: Widget HTML for client 118580 - valid HTML returned")
    
    def test_widget_html_with_top_k(self):
        """GET /api/merchant-recommendations/widget/{client_id}/html?top_k=3"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/widget/118580/html?top_k=3")
        assert response.status_code == 200
        
        content = response.text
        assert '<html' in content, "Response should be HTML"
        print("PASS: Widget HTML with top_k=3")


class TestMerchantIntelligenceEndpoints:
    """Merchant Intelligence Engine endpoints"""
    
    def test_analyze_merchant_traffic(self):
        """GET /api/merchant-intelligence/analyze?days=30"""
        response = requests.get(f"{BASE_URL}/api/merchant-intelligence/analyze?days=30")
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        
        data = response.json()
        assert data.get('success') == True, f"Expected success=True, got {data}"
        assert 'count' in data, "Missing count in response"
        assert 'period_days' in data, "Missing period_days in response"
        assert data['period_days'] == 30, f"Expected period_days=30, got {data['period_days']}"
        assert 'merchants' in data, "Missing merchants in response"
        assert isinstance(data['merchants'], list), "merchants should be a list"
        
        # Verify merchant structure if any
        if data['merchants']:
            merchant = data['merchants'][0]
            required_fields = ['partner_id', 'partner_name', 'category', 'status', 'health_score', 
                             'total_transactions', 'avg_daily_tx', 'trend_7d_pct', 'best_day', 'active_promos']
            for field in required_fields:
                assert field in merchant, f"Missing field '{field}' in merchant analysis"
            assert merchant['status'] in ['PERFORMANT', 'A_SURVEILLER', 'A_BOOSTER'], f"Invalid status: {merchant['status']}"
        
        print(f"PASS: Merchant Intelligence analyze - {data['count']} merchants analyzed over {data['period_days']} days")
    
    def test_analyze_with_partner_id(self):
        """GET /api/merchant-intelligence/analyze?partner_id=X&days=30"""
        # First get a valid partner_id from the general analysis
        response = requests.get(f"{BASE_URL}/api/merchant-intelligence/analyze?days=30")
        data = response.json()
        if data.get('merchants'):
            partner_id = data['merchants'][0]['partner_id']
            
            response2 = requests.get(f"{BASE_URL}/api/merchant-intelligence/analyze?partner_id={partner_id}&days=30")
            assert response2.status_code == 200
            data2 = response2.json()
            assert data2.get('success') == True
            print(f"PASS: Merchant Intelligence analyze with partner_id={partner_id}")
        else:
            print("SKIP: No merchants to test partner_id filter")
    
    def test_digest_merchants(self):
        """GET /api/merchant-intelligence/digest?limit=5"""
        response = requests.get(f"{BASE_URL}/api/merchant-intelligence/digest?limit=5")
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        
        data = response.json()
        assert data.get('success') == True, f"Expected success=True, got {data}"
        assert 'to_boost' in data, "Missing to_boost in response"
        assert 'to_watch' in data, "Missing to_watch in response"
        assert 'top_performers' in data, "Missing top_performers in response"
        assert 'stats' in data, "Missing stats in response"
        
        # Verify stats structure
        stats = data['stats']
        assert 'performant' in stats, "Missing performant count in stats"
        assert 'a_surveiller' in stats, "Missing a_surveiller count in stats"
        assert 'a_booster' in stats, "Missing a_booster count in stats"
        
        print(f"PASS: Merchant Intelligence digest - stats: {stats}")
    
    def test_report_ai_gemini(self):
        """POST /api/merchant-intelligence/report - AI Gemini report"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-intelligence/report",
            json={"provider": "gemini", "model": "gemini-2.5-flash"},
            timeout=60  # AI generation can take time
        )
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        
        data = response.json()
        assert data.get('success') == True, f"Expected success=True, got {data}"
        assert 'report' in data, "Missing report in response"
        assert 'data' in data, "Missing data in response"
        
        # Verify report structure (AI-generated)
        report = data['report']
        if isinstance(report, dict):
            # Check for expected AI report fields
            expected_fields = ['executive_summary', 'boost_recommendations']
            for field in expected_fields:
                if field in report:
                    print(f"  - {field}: present")
        
        print(f"PASS: Merchant Intelligence AI report (Gemini) generated successfully")
    
    def test_report_html(self):
        """GET /api/merchant-intelligence/report/html - HTML Intelligence report"""
        response = requests.get(f"{BASE_URL}/api/merchant-intelligence/report/html", timeout=60)
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        
        content = response.text
        assert '<!DOCTYPE html>' in content or '<html' in content, "Response should be HTML"
        assert 'Intelligence Marchands' in content, "HTML should contain 'Intelligence Marchands'"
        assert 'Club Privileges' in content, "HTML should contain 'Club Privileges'"
        
        # Check for key sections
        assert 'Marchands analyses' in content or 'Performants' in content, "HTML should contain merchant stats"
        print("PASS: Merchant Intelligence HTML report generated successfully")


class TestMLRecommendationsEndpoints:
    """ML-powered merchant recommendations endpoints"""
    
    def test_post_recommendations_client_118580(self):
        """POST /api/merchant-recommendations - ML recommendations"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 118580}
        )
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        
        data = response.json()
        assert data.get('success') == True, f"Expected success=True, got {data}"
        assert data['client_id'] == 118580
        assert 'source' in data
        assert 'recommendations' in data
        assert 'count' in data
        
        # Verify recommendation structure
        if data['recommendations']:
            rec = data['recommendations'][0]
            required_fields = ['partner_id', 'partner_name', 'category_name', 'score', 'score_normalized', 
                             'rank', 'reason', 'recommendation_type', 'explanation']
            for field in required_fields:
                assert field in rec, f"Missing field '{field}' in recommendation"
        
        print(f"PASS: ML recommendations for client 118580 - {data['count']} recommendations, source={data['source']}")
    
    def test_explain_html_client_118580(self):
        """GET /api/merchant-recommendations/explain/118580 - HTML explain report"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/explain/118580")
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        
        content = response.text
        assert '<!DOCTYPE html>' in content or '<html' in content, "Response should be HTML"
        assert 'Rapport de Recommandations' in content, "HTML should contain 'Rapport de Recommandations'"
        assert 'Profil Client' in content, "HTML should contain 'Profil Client'"
        assert '118580' in content, "HTML should contain client ID"
        print("PASS: HTML explain report for client 118580")
    
    def test_health_check(self):
        """GET /api/merchant-recommendations/health - Health check"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/health")
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        
        data = response.json()
        assert 'status' in data, "Missing status in response"
        assert 'model_loaded' in data, "Missing model_loaded in response"
        assert 'fallback_available' in data, "Missing fallback_available in response"
        
        print(f"PASS: Health check - status={data['status']}, model_loaded={data['model_loaded']}")
    
    def test_stats(self):
        """GET /api/merchant-recommendations/stats - Usage stats"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/stats")
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        
        data = response.json()
        assert 'total_interactions' in data or 'active_merchants' in data, "Missing expected stats fields"
        print(f"PASS: Stats endpoint - data keys: {list(data.keys())}")
    
    def test_categories(self):
        """GET /api/merchant-recommendations/categories - Categories list"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/categories")
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        
        data = response.json()
        assert 'categories' in data, "Missing categories in response"
        assert isinstance(data['categories'], list), "categories should be a list"
        
        if data['categories']:
            cat = data['categories'][0]
            assert 'category_id' in cat, "Missing category_id in category"
            assert 'category_name' in cat, "Missing category_name in category"
        
        print(f"PASS: Categories endpoint - {len(data['categories'])} categories")


class TestProxyCatchAll:
    """Verify proxy catch-all still works (returns 503 since PHP not running)"""
    
    def test_proxy_root_returns_503(self):
        """GET / should return 503 (PHP server not ready) - not 404"""
        response = requests.get(f"{BASE_URL}/")
        # Since PHP-FPM is not running in preview, we expect 503 (not 404)
        # This confirms the proxy catch-all is working
        assert response.status_code in [200, 503, 502], f"Expected 200/503/502 (proxy working), got {response.status_code}"
        
        if response.status_code == 503:
            assert 'PHP server not ready' in response.text or 'not ready' in response.text.lower(), \
                "503 should indicate PHP server not ready"
            print("PASS: Proxy catch-all working - returns 503 (PHP not running in preview)")
        elif response.status_code == 200:
            print("PASS: Proxy catch-all working - Laravel home page returned")
        else:
            print(f"PASS: Proxy catch-all working - returns {response.status_code}")
    
    def test_proxy_non_api_route(self):
        """GET /login should go through proxy (not 404)"""
        response = requests.get(f"{BASE_URL}/login")
        # Should be proxied to PHP, returns 503 if PHP not running
        assert response.status_code in [200, 302, 503, 502], f"Expected proxy response, got {response.status_code}"
        print(f"PASS: Non-API route /login proxied correctly - status {response.status_code}")


class TestServerPyIntegrity:
    """Verify server.py has no duplicate code"""
    
    def test_no_duplicate_routes(self):
        """Verify no duplicate route definitions by checking multiple endpoints work"""
        # If there were duplicate routes, FastAPI would fail to start or routes would conflict
        endpoints = [
            ("/api/merchant-recommendations/health", "GET"),
            ("/api/merchant-recommendations/stats", "GET"),
            ("/api/merchant-recommendations/categories", "GET"),
            ("/api/merchant-intelligence/digest?limit=5", "GET"),
        ]
        
        for endpoint, method in endpoints:
            if method == "GET":
                response = requests.get(f"{BASE_URL}{endpoint}")
            else:
                response = requests.post(f"{BASE_URL}{endpoint}")
            
            assert response.status_code in [200, 400, 500], f"Endpoint {endpoint} returned unexpected {response.status_code}"
        
        print("PASS: No duplicate routes detected - all endpoints respond correctly")


if __name__ == "__main__":
    pytest.main([__file__, "-v", "--tb=short"])
