"""
Backend API Tests for Club Privilèges Dashboard
Tests sub-stores and main dashboard API endpoints
"""
import pytest
import requests
import os

BASE_URL = os.environ.get('REACT_APP_BACKEND_URL', 'https://perf-test-ooredoo.preview.emergentagent.com')

class TestSubStoresAPI:
    """Sub-stores API endpoint tests"""
    
    def test_substores_kpis_endpoint(self):
        """Test /sub-stores/api/split/kpis endpoint"""
        response = requests.get(
            f"{BASE_URL}/sub-stores/api/split/kpis",
            params={
                "start_date": "2025-01-01",
                "end_date": "2026-04-01",
                "sub_store": "ALL"
            },
            timeout=60  # KPIs can take 10-15 seconds
        )
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        data = response.json()
        assert "kpis" in data or "success" in data or isinstance(data, dict), f"Unexpected response: {data}"
        print(f"Sub-stores KPIs response: {data}")
    
    def test_substores_charts_endpoint(self):
        """Test /sub-stores/api/split/charts endpoint"""
        response = requests.get(
            f"{BASE_URL}/sub-stores/api/split/charts",
            params={
                "start_date": "2025-01-01",
                "end_date": "2026-04-01",
                "sub_store": "ALL"
            },
            timeout=60
        )
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        data = response.json()
        print(f"Sub-stores charts response keys: {data.keys() if isinstance(data, dict) else type(data)}")
    
    def test_substores_stores_endpoint(self):
        """Test /sub-stores/api/split/stores endpoint"""
        response = requests.get(
            f"{BASE_URL}/sub-stores/api/split/stores",
            params={
                "start_date": "2025-01-01",
                "end_date": "2026-04-01"
            },
            timeout=60
        )
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        data = response.json()
        print(f"Sub-stores stores response: {type(data)}")
    
    def test_substores_merchants_endpoint(self):
        """Test /sub-stores/api/split/merchants endpoint"""
        response = requests.get(
            f"{BASE_URL}/sub-stores/api/split/merchants",
            params={
                "start_date": "2025-01-01",
                "end_date": "2026-04-01",
                "sub_store": "ALL"
            },
            timeout=60
        )
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        data = response.json()
        print(f"Sub-stores merchants response: {type(data)}")
    
    def test_substores_users_endpoint(self):
        """Test /sub-stores/api/split/users endpoint"""
        response = requests.get(
            f"{BASE_URL}/sub-stores/api/split/users",
            params={
                "start_date": "2025-01-01",
                "end_date": "2026-04-01",
                "sub_store": "ALL"
            },
            timeout=60
        )
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        data = response.json()
        print(f"Sub-stores users response: {type(data)}")


class TestMainDashboardAPI:
    """Main dashboard API endpoint tests"""
    
    def test_dashboard_kpis_endpoint(self):
        """Test /api/dashboard/split/kpis endpoint"""
        response = requests.get(
            f"{BASE_URL}/api/dashboard/split/kpis",
            params={
                "start_date": "2026-03-01",
                "end_date": "2026-04-01",
                "operator": "ALL"
            },
            timeout=60
        )
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        data = response.json()
        assert "kpis" in data or isinstance(data, dict), f"Unexpected response: {data}"
        print(f"Dashboard KPIs response keys: {data.keys() if isinstance(data, dict) else type(data)}")
    
    def test_dashboard_transactions_endpoint(self):
        """Test /api/dashboard/split/transactions endpoint"""
        response = requests.get(
            f"{BASE_URL}/api/dashboard/split/transactions",
            params={
                "start_date": "2026-03-01",
                "end_date": "2026-04-01",
                "operator": "ALL"
            },
            timeout=60
        )
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        data = response.json()
        print(f"Dashboard transactions response: {type(data)}")
    
    def test_dashboard_subscriptions_endpoint(self):
        """Test /api/dashboard/split/subscriptions endpoint"""
        response = requests.get(
            f"{BASE_URL}/api/dashboard/split/subscriptions",
            params={
                "start_date": "2026-03-01",
                "end_date": "2026-04-01",
                "operator": "ALL"
            },
            timeout=60
        )
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        data = response.json()
        print(f"Dashboard subscriptions response: {type(data)}")
    
    def test_dashboard_merchants_endpoint(self):
        """Test /api/dashboard/split/merchants endpoint"""
        response = requests.get(
            f"{BASE_URL}/api/dashboard/split/merchants",
            params={
                "start_date": "2026-03-01",
                "end_date": "2026-04-01",
                "operator": "ALL"
            },
            timeout=60
        )
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        data = response.json()
        print(f"Dashboard merchants response: {type(data)}")
    
    def test_operators_endpoint(self):
        """Test /api/operators endpoint"""
        response = requests.get(
            f"{BASE_URL}/api/operators",
            timeout=30
        )
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        data = response.json()
        assert "operators" in data, f"Expected 'operators' key in response: {data}"
        print(f"Operators count: {len(data.get('operators', []))}")


class TestMerchantRecommendationsAPI:
    """Merchant recommendations API tests"""
    
    def test_recommendations_health(self):
        """Test /api/merchant-recommendations/health endpoint"""
        response = requests.get(
            f"{BASE_URL}/api/merchant-recommendations/health",
            timeout=30
        )
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        data = response.json()
        assert "status" in data, f"Expected 'status' key in response: {data}"
        print(f"Recommendations health: {data}")
    
    def test_recommendations_categories(self):
        """Test /api/merchant-recommendations/categories endpoint"""
        response = requests.get(
            f"{BASE_URL}/api/merchant-recommendations/categories",
            timeout=30
        )
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        data = response.json()
        print(f"Categories response: {type(data)}")
    
    def test_recommendations_for_client(self):
        """Test /api/merchant-recommendations endpoint"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 1, "top_k": 5},
            timeout=30
        )
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        data = response.json()
        assert "success" in data, f"Expected 'success' key in response: {data}"
        print(f"Recommendations response: success={data.get('success')}, count={data.get('count', 0)}")


class TestEklektikAPI:
    """Eklektik dashboard API tests"""
    
    def test_eklektik_kpis(self):
        """Test /api/eklektik-dashboard/kpis endpoint"""
        response = requests.get(
            f"{BASE_URL}/api/eklektik-dashboard/kpis",
            params={
                "start_date": "2026-03-01",
                "end_date": "2026-04-01",
                "operator": "ALL"
            },
            timeout=30
        )
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        data = response.json()
        assert "success" in data, f"Expected 'success' key in response: {data}"
        print(f"Eklektik KPIs: success={data.get('success')}")
    
    def test_eklektik_overview_chart(self):
        """Test /api/eklektik-dashboard/overview-chart endpoint"""
        response = requests.get(
            f"{BASE_URL}/api/eklektik-dashboard/overview-chart",
            params={
                "start_date": "2026-03-01",
                "end_date": "2026-04-01",
                "operator": "ALL"
            },
            timeout=30
        )
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        data = response.json()
        print(f"Eklektik overview chart: success={data.get('success')}")


if __name__ == "__main__":
    pytest.main([__file__, "-v", "--tb=short"])
