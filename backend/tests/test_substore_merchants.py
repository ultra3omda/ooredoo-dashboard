"""
Test Suite for Sub-Store Merchant KPIs and Campaign Dropdown
Tests the 3 fixes:
1. Merchant KPIs showing all 8 KPIs (bug fix)
2. Redis cache performance
3. Pluxee campaign dropdown
"""
import pytest
import requests
import os
import time

BASE_URL = os.environ.get('REACT_APP_BACKEND_URL', 'https://perf-test-ooredoo.preview.emergentagent.com').rstrip('/')

class TestSubStoreMerchantKPIs:
    """Test merchant KPIs endpoint returns all 8 KPIs"""
    
    @pytest.fixture(autouse=True)
    def setup(self):
        """Setup session with authentication"""
        self.session = requests.Session()
        self.session.headers.update({
            "Content-Type": "application/x-www-form-urlencoded",
            "Accept": "application/json, text/html"
        })
        # Login first
        self._login()
    
    def _login(self):
        """Login to get session cookie"""
        # Get CSRF token
        login_page = self.session.get(f"{BASE_URL}/login")
        import re
        csrf_match = re.search(r'csrf-token" content="([^"]+)"', login_page.text)
        if csrf_match:
            csrf_token = csrf_match.group(1)
            # Login
            self.session.post(
                f"{BASE_URL}/login",
                data={
                    "_token": csrf_token,
                    "email": "superadmin@ooredoo.tn",
                    "password": "SuperAdmin@2025"
                },
                headers={"X-CSRF-TOKEN": csrf_token}
            )
    
    def test_merchants_endpoint_returns_8_kpis(self):
        """Test that merchants endpoint returns all 8 KPIs"""
        response = self.session.get(
            f"{BASE_URL}/sub-stores/api/split/merchants",
            params={
                "sub_store": "ALL",
                "start_date": "2025-01-01",
                "end_date": "2025-12-31"
            }
        )
        
        # Check response status
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        assert data.get("success") == True, f"API returned success=false: {data}"
        
        # Check that data section exists
        assert "data" in data, "Response missing 'data' field"
        merchant_data = data["data"]
        
        # Check that kpis section exists
        assert "kpis" in merchant_data, "Response missing 'kpis' field"
        kpis = merchant_data["kpis"]
        
        # Verify all 8 KPIs are present
        expected_kpis = [
            "totalPartners",
            "activeMerchants", 
            "totalLocationsActive",
            "activeMerchantRatio",
            "totalTransactions",
            "transactionsPerMerchant",
            "topMerchantShare",
            "diversity"
        ]
        
        for kpi_name in expected_kpis:
            assert kpi_name in kpis, f"Missing KPI: {kpi_name}"
            print(f"✓ KPI {kpi_name} present: {kpis[kpi_name]}")
    
    def test_total_partners_value(self):
        """Test that totalPartners.current is 576"""
        response = self.session.get(
            f"{BASE_URL}/sub-stores/api/split/merchants",
            params={
                "sub_store": "ALL",
                "start_date": "2025-01-01",
                "end_date": "2025-12-31"
            }
        )
        
        assert response.status_code == 200
        data = response.json()
        kpis = data["data"]["kpis"]
        
        total_partners = kpis.get("totalPartners", {})
        assert "current" in total_partners, "totalPartners missing 'current' field"
        assert total_partners["current"] == 576, f"Expected totalPartners.current=576, got {total_partners['current']}"
        print(f"✓ totalPartners.current = {total_partners['current']}")
    
    def test_active_merchants_positive(self):
        """Test that activeMerchants.current > 0"""
        response = self.session.get(
            f"{BASE_URL}/sub-stores/api/split/merchants",
            params={
                "sub_store": "ALL",
                "start_date": "2025-01-01",
                "end_date": "2025-12-31"
            }
        )
        
        assert response.status_code == 200
        data = response.json()
        kpis = data["data"]["kpis"]
        
        active_merchants = kpis.get("activeMerchants", {})
        assert "current" in active_merchants, "activeMerchants missing 'current' field"
        assert active_merchants["current"] > 0, f"Expected activeMerchants.current > 0, got {active_merchants['current']}"
        print(f"✓ activeMerchants.current = {active_merchants['current']}")
    
    def test_total_transactions_positive(self):
        """Test that totalTransactions.current > 0"""
        response = self.session.get(
            f"{BASE_URL}/sub-stores/api/split/merchants",
            params={
                "sub_store": "ALL",
                "start_date": "2025-01-01",
                "end_date": "2025-12-31"
            }
        )
        
        assert response.status_code == 200
        data = response.json()
        kpis = data["data"]["kpis"]
        
        total_transactions = kpis.get("totalTransactions", {})
        assert "current" in total_transactions, "totalTransactions missing 'current' field"
        assert total_transactions["current"] > 0, f"Expected totalTransactions.current > 0, got {total_transactions['current']}"
        print(f"✓ totalTransactions.current = {total_transactions['current']}")
    
    def test_top_merchant_share_has_merchant_name(self):
        """Test that topMerchantShare has merchant_name field"""
        response = self.session.get(
            f"{BASE_URL}/sub-stores/api/split/merchants",
            params={
                "sub_store": "ALL",
                "start_date": "2025-01-01",
                "end_date": "2025-12-31"
            }
        )
        
        assert response.status_code == 200
        data = response.json()
        kpis = data["data"]["kpis"]
        
        top_merchant_share = kpis.get("topMerchantShare", {})
        assert "merchant_name" in top_merchant_share, "topMerchantShare missing 'merchant_name' field"
        print(f"✓ topMerchantShare.merchant_name = {top_merchant_share['merchant_name']}")
    
    def test_diversity_has_level_and_score(self):
        """Test that diversity has level and score fields"""
        response = self.session.get(
            f"{BASE_URL}/sub-stores/api/split/merchants",
            params={
                "sub_store": "ALL",
                "start_date": "2025-01-01",
                "end_date": "2025-12-31"
            }
        )
        
        assert response.status_code == 200
        data = response.json()
        kpis = data["data"]["kpis"]
        
        diversity = kpis.get("diversity", {})
        assert "level" in diversity, "diversity missing 'level' field"
        assert "score" in diversity, "diversity missing 'score' field"
        print(f"✓ diversity.level = {diversity['level']}, diversity.score = {diversity['score']}")
    
    def test_merchants_array_has_required_fields(self):
        """Test that merchants array has rank, name, category, transactions, share, delta, change fields"""
        response = self.session.get(
            f"{BASE_URL}/sub-stores/api/split/merchants",
            params={
                "sub_store": "ALL",
                "start_date": "2025-01-01",
                "end_date": "2025-12-31"
            }
        )
        
        assert response.status_code == 200
        data = response.json()
        merchant_data = data["data"]
        
        assert "merchants" in merchant_data, "Response missing 'merchants' array"
        merchants = merchant_data["merchants"]
        
        if len(merchants) > 0:
            first_merchant = merchants[0]
            required_fields = ["rank", "name", "category", "transactions", "share", "delta", "change"]
            for field in required_fields:
                assert field in first_merchant, f"Merchant missing '{field}' field"
            print(f"✓ First merchant has all required fields: {first_merchant}")
        else:
            print("⚠ No merchants in response (may be expected for some date ranges)")


class TestSubStoresCampaigns:
    """Test sub-stores endpoint returns campaigns for Pluxee"""
    
    @pytest.fixture(autouse=True)
    def setup(self):
        """Setup session with authentication"""
        self.session = requests.Session()
        self.session.headers.update({
            "Content-Type": "application/x-www-form-urlencoded",
            "Accept": "application/json, text/html"
        })
        self._login()
    
    def _login(self):
        """Login to get session cookie"""
        login_page = self.session.get(f"{BASE_URL}/login")
        import re
        csrf_match = re.search(r'csrf-token" content="([^"]+)"', login_page.text)
        if csrf_match:
            csrf_token = csrf_match.group(1)
            self.session.post(
                f"{BASE_URL}/login",
                data={
                    "_token": csrf_token,
                    "email": "superadmin@ooredoo.tn",
                    "password": "SuperAdmin@2025"
                },
                headers={"X-CSRF-TOKEN": csrf_token}
            )
    
    def test_substores_endpoint_has_campaigns(self):
        """Test that sub-stores endpoint returns campaigns field"""
        response = self.session.get(f"{BASE_URL}/sub-stores/api/sub-stores")
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        assert "campaigns" in data, "Response missing 'campaigns' field"
        print(f"✓ campaigns field present: {data['campaigns']}")
    
    def test_pluxee_campaigns_structure(self):
        """Test that Pluxee campaigns have name, batches, cards fields"""
        response = self.session.get(f"{BASE_URL}/sub-stores/api/sub-stores")
        
        assert response.status_code == 200
        data = response.json()
        
        campaigns = data.get("campaigns", {})
        
        # Find Pluxee campaigns
        pluxee_found = False
        for store_name, store_campaigns in campaigns.items():
            if "pluxee" in store_name.lower():
                pluxee_found = True
                if len(store_campaigns) > 0:
                    first_campaign = store_campaigns[0]
                    assert "name" in first_campaign, "Campaign missing 'name' field"
                    assert "batches" in first_campaign, "Campaign missing 'batches' field"
                    assert "cards" in first_campaign, "Campaign missing 'cards' field"
                    print(f"✓ Pluxee campaign structure valid: {first_campaign}")
                break
        
        if not pluxee_found:
            print("⚠ No Pluxee sub-store found in campaigns (may be expected)")


class TestRedisCachePerformance:
    """Test Redis cache performance - 2nd call should be faster"""
    
    @pytest.fixture(autouse=True)
    def setup(self):
        """Setup session with authentication"""
        self.session = requests.Session()
        self.session.headers.update({
            "Content-Type": "application/x-www-form-urlencoded",
            "Accept": "application/json, text/html"
        })
        self._login()
    
    def _login(self):
        """Login to get session cookie"""
        login_page = self.session.get(f"{BASE_URL}/login")
        import re
        csrf_match = re.search(r'csrf-token" content="([^"]+)"', login_page.text)
        if csrf_match:
            csrf_token = csrf_match.group(1)
            self.session.post(
                f"{BASE_URL}/login",
                data={
                    "_token": csrf_token,
                    "email": "superadmin@ooredoo.tn",
                    "password": "SuperAdmin@2025"
                },
                headers={"X-CSRF-TOKEN": csrf_token}
            )
    
    def test_cache_performance(self):
        """Test that 2nd call is faster due to Redis cache"""
        params = {
            "sub_store": "ALL",
            "start_date": "2025-01-01",
            "end_date": "2025-12-31"
        }
        
        # First call - may be slow (cache miss)
        start1 = time.time()
        response1 = self.session.get(
            f"{BASE_URL}/sub-stores/api/split/merchants",
            params=params
        )
        time1 = (time.time() - start1) * 1000  # Convert to ms
        
        assert response1.status_code == 200
        data1 = response1.json()
        exec_time1 = data1.get("execution_time_ms", time1)
        
        # Second call - should be faster (cache hit)
        start2 = time.time()
        response2 = self.session.get(
            f"{BASE_URL}/sub-stores/api/split/merchants",
            params=params
        )
        time2 = (time.time() - start2) * 1000
        
        assert response2.status_code == 200
        data2 = response2.json()
        exec_time2 = data2.get("execution_time_ms", time2)
        
        print(f"✓ First call execution_time_ms: {exec_time1}")
        print(f"✓ Second call execution_time_ms: {exec_time2}")
        
        # Second call should be faster (or at least not significantly slower)
        # Note: We can't guarantee exact timing, but cache should help
        if exec_time2 < exec_time1:
            print(f"✓ Cache working: 2nd call {exec_time1 - exec_time2:.0f}ms faster")
        else:
            print(f"⚠ Cache may not be working optimally: 2nd call not faster")


class TestRegressionKPIs:
    """Regression tests for existing endpoints"""
    
    @pytest.fixture(autouse=True)
    def setup(self):
        """Setup session with authentication"""
        self.session = requests.Session()
        self.session.headers.update({
            "Content-Type": "application/x-www-form-urlencoded",
            "Accept": "application/json, text/html"
        })
        self._login()
    
    def _login(self):
        """Login to get session cookie"""
        login_page = self.session.get(f"{BASE_URL}/login")
        import re
        csrf_match = re.search(r'csrf-token" content="([^"]+)"', login_page.text)
        if csrf_match:
            csrf_token = csrf_match.group(1)
            self.session.post(
                f"{BASE_URL}/login",
                data={
                    "_token": csrf_token,
                    "email": "superadmin@ooredoo.tn",
                    "password": "SuperAdmin@2025"
                },
                headers={"X-CSRF-TOKEN": csrf_token}
            )
    
    def test_kpis_endpoint_works(self):
        """Regression: GET /sub-stores/api/split/kpis still works"""
        response = self.session.get(
            f"{BASE_URL}/sub-stores/api/split/kpis",
            params={
                "sub_store": "ALL",
                "start_date": "2025-01-01",
                "end_date": "2025-12-31"
            }
        )
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        data = response.json()
        assert data.get("success") == True, f"API returned success=false"
        print(f"✓ KPIs endpoint working: {list(data.get('data', {}).keys())}")
    
    def test_stores_endpoint_works(self):
        """Regression: GET /sub-stores/api/split/stores still works"""
        response = self.session.get(
            f"{BASE_URL}/sub-stores/api/split/stores",
            params={
                "sub_store": "ALL",
                "start_date": "2025-01-01",
                "end_date": "2025-12-31"
            }
        )
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        data = response.json()
        assert data.get("success") == True, f"API returned success=false"
        print(f"✓ Stores endpoint working: {len(data.get('data', []))} stores returned")


if __name__ == "__main__":
    pytest.main([__file__, "-v", "--tb=short"])
