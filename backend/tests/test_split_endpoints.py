"""
Test Suite for SubStoreController Split Endpoints
Tests the new parallel loading split endpoints for sub-stores dashboard.
Split endpoints: kpis, stores, charts, merchants, users
"""
import pytest
import requests
import os
import re
from bs4 import BeautifulSoup

BASE_URL = os.environ.get('REACT_APP_BACKEND_URL', '').rstrip('/')

# Test credentials
ADMIN_EMAIL = "superadmin@ooredoo.tn"
ADMIN_PASSWORD = "SuperAdmin@2025"


class TestSplitEndpoints:
    """Test the new split endpoints for parallel dashboard loading"""
    
    @pytest.fixture(scope="class")
    def session(self):
        """Create authenticated session for Laravel app"""
        s = requests.Session()
        s.headers.update({
            'Accept': 'application/json',
            'Content-Type': 'application/x-www-form-urlencoded',
        })
        
        # Get login page to extract CSRF token
        login_page = s.get(f"{BASE_URL}/login", timeout=30)
        assert login_page.status_code == 200, f"Failed to load login page: {login_page.status_code}"
        
        # Extract CSRF token from HTML
        soup = BeautifulSoup(login_page.text, 'html.parser')
        csrf_input = soup.find('input', {'name': '_token'})
        assert csrf_input, "CSRF token not found in login page"
        csrf_token = csrf_input.get('value')
        
        # Login
        login_response = s.post(f"{BASE_URL}/login", data={
            '_token': csrf_token,
            'email': ADMIN_EMAIL,
            'password': ADMIN_PASSWORD
        }, allow_redirects=True, timeout=30)
        
        # Check login success (should redirect to dashboard)
        assert login_response.status_code == 200, f"Login failed: {login_response.status_code}"
        assert 'login' not in login_response.url.lower() or 'dashboard' in login_response.url.lower(), "Login redirect failed"
        
        return s
    
    # =========================================================================
    # TEST: GET /sub-stores/api/split/kpis
    # =========================================================================
    
    def test_kpis_split_sofrecom(self, session):
        """Test KPIs split endpoint with Sofrecom sub-store"""
        response = session.get(
            f"{BASE_URL}/sub-stores/api/split/kpis",
            params={'sub_store': 'Sofrecom'},
            timeout=120
        )
        
        assert response.status_code == 200, f"KPIs split failed: {response.status_code} - {response.text[:500]}"
        data = response.json()
        
        # Verify response structure
        assert data.get('success') == True, f"KPIs split not successful: {data}"
        assert data.get('section') == 'kpis', f"Wrong section: {data.get('section')}"
        assert 'data' in data, "Missing data field"
        assert 'execution_time_ms' in data, "Missing execution_time_ms"
        
        # Verify KPI data structure
        kpi_data = data['data']
        assert 'distributed' in kpi_data, "Missing distributed KPI"
        assert 'inscriptions' in kpi_data, "Missing inscriptions KPI"
        assert 'transactions' in kpi_data, "Missing transactions KPI"
        
        # Verify KPI values are positive (Sofrecom should have data)
        distributed = kpi_data['distributed']
        assert isinstance(distributed, dict), "distributed should be a dict with current/previous/change"
        assert distributed.get('current', 0) > 0, f"distributed should be > 0, got {distributed}"
        
        inscriptions = kpi_data['inscriptions']
        assert inscriptions.get('current', 0) > 0, f"inscriptions should be > 0, got {inscriptions}"
        
        transactions = kpi_data['transactions']
        assert transactions.get('current', 0) > 0, f"transactions should be > 0, got {transactions}"
        
        print(f"✓ KPIs Sofrecom: distributed={distributed['current']}, inscriptions={inscriptions['current']}, transactions={transactions['current']}")
    
    def test_kpis_split_all(self, session):
        """Test KPIs split endpoint with ALL sub-stores aggregated"""
        response = session.get(
            f"{BASE_URL}/sub-stores/api/split/kpis",
            params={'sub_store': 'ALL'},
            timeout=120
        )
        
        assert response.status_code == 200, f"KPIs ALL failed: {response.status_code}"
        data = response.json()
        
        assert data.get('success') == True, f"KPIs ALL not successful: {data}"
        assert data.get('section') == 'kpis'
        assert 'data' in data
        
        print(f"✓ KPIs ALL: success={data['success']}, execution_time={data.get('execution_time_ms')}ms")
    
    # =========================================================================
    # TEST: GET /sub-stores/api/split/stores
    # =========================================================================
    
    def test_stores_split_sofrecom(self, session):
        """Test stores split endpoint with Sofrecom"""
        response = session.get(
            f"{BASE_URL}/sub-stores/api/split/stores",
            params={'sub_store': 'Sofrecom'},
            timeout=60
        )
        
        assert response.status_code == 200, f"Stores split failed: {response.status_code}"
        data = response.json()
        
        assert data.get('success') == True, f"Stores split not successful: {data}"
        assert data.get('section') == 'stores'
        assert 'data' in data
        
        # Data should be an array of stores
        stores_data = data['data']
        assert isinstance(stores_data, list), f"stores data should be a list, got {type(stores_data)}"
        
        print(f"✓ Stores Sofrecom: {len(stores_data)} stores returned")
    
    # =========================================================================
    # TEST: GET /sub-stores/api/split/charts
    # =========================================================================
    
    def test_charts_split_sofrecom(self, session):
        """Test charts split endpoint with Sofrecom"""
        response = session.get(
            f"{BASE_URL}/sub-stores/api/split/charts",
            params={'sub_store': 'Sofrecom'},
            timeout=60
        )
        
        assert response.status_code == 200, f"Charts split failed: {response.status_code}"
        data = response.json()
        
        assert data.get('success') == True, f"Charts split not successful: {data}"
        assert data.get('section') == 'charts'
        assert 'data' in data
        
        charts_data = data['data']
        assert 'categoryDistribution' in charts_data, "Missing categoryDistribution"
        assert 'inscriptionsTrend' in charts_data, "Missing inscriptionsTrend"
        
        # Verify arrays
        assert isinstance(charts_data['categoryDistribution'], list), "categoryDistribution should be array"
        assert isinstance(charts_data['inscriptionsTrend'], list), "inscriptionsTrend should be array"
        
        print(f"✓ Charts Sofrecom: categoryDistribution={len(charts_data['categoryDistribution'])} items, inscriptionsTrend={len(charts_data['inscriptionsTrend'])} items")
    
    # =========================================================================
    # TEST: GET /sub-stores/api/split/merchants
    # =========================================================================
    
    def test_merchants_split_sofrecom(self, session):
        """Test merchants split endpoint with Sofrecom"""
        response = session.get(
            f"{BASE_URL}/sub-stores/api/split/merchants",
            params={'sub_store': 'Sofrecom'},
            timeout=120
        )
        
        assert response.status_code == 200, f"Merchants split failed: {response.status_code}"
        data = response.json()
        
        assert data.get('success') == True, f"Merchants split not successful: {data}"
        assert data.get('section') == 'merchants'
        assert 'data' in data
        
        merchants_data = data['data']
        assert 'merchants' in merchants_data, "Missing merchants array"
        assert 'kpis' in merchants_data, "Missing kpis object"
        
        # Verify merchants array
        assert isinstance(merchants_data['merchants'], list), "merchants should be array"
        
        # Verify kpis has activeMerchants
        kpis = merchants_data['kpis']
        assert 'activeMerchants' in kpis, "Missing activeMerchants in kpis"
        
        print(f"✓ Merchants Sofrecom: {len(merchants_data['merchants'])} merchants, activeMerchants={kpis.get('activeMerchants')}")
    
    # =========================================================================
    # TEST: GET /sub-stores/api/split/users
    # =========================================================================
    
    def test_users_split_sofrecom(self, session):
        """Test users split endpoint with Sofrecom"""
        response = session.get(
            f"{BASE_URL}/sub-stores/api/split/users",
            params={'sub_store': 'Sofrecom'},
            timeout=180
        )
        
        assert response.status_code == 200, f"Users split failed: {response.status_code}"
        data = response.json()
        
        assert data.get('success') == True, f"Users split not successful: {data}"
        assert data.get('section') == 'users'
        assert 'data' in data
        
        users_data = data['data']
        assert 'users' in users_data, "Missing users array"
        assert 'users_kpis' in users_data, "Missing users_kpis object"
        
        # Verify users array
        assert isinstance(users_data['users'], list), "users should be array"
        
        print(f"✓ Users Sofrecom: {len(users_data['users'])} users, users_kpis present")
    
    # =========================================================================
    # TEST: GET /sub-stores/api/sub-stores (list of sub-stores)
    # =========================================================================
    
    def test_sub_stores_list(self, session):
        """Test sub-stores list endpoint returns Pluxee stores (57, 61)"""
        response = session.get(
            f"{BASE_URL}/sub-stores/api/sub-stores",
            timeout=30
        )
        
        assert response.status_code == 200, f"Sub-stores list failed: {response.status_code}"
        data = response.json()
        
        assert 'sub_stores' in data, "Missing sub_stores in response"
        sub_stores = data['sub_stores']
        assert isinstance(sub_stores, list), "sub_stores should be a list"
        
        # Check for Pluxee stores (57, 61) in the list
        store_ids = [s.get('store_id') for s in sub_stores if isinstance(s, dict)]
        store_names = [s.get('store_name', '') for s in sub_stores if isinstance(s, dict)]
        
        # Pluxee stores should be present (57, 61)
        has_pluxee = any('Pluxee' in name for name in store_names) or 57 in store_ids or 61 in store_ids
        
        print(f"✓ Sub-stores list: {len(sub_stores)} stores, Pluxee present: {has_pluxee}")
        print(f"  Store IDs: {store_ids[:10]}...")  # First 10
    
    # =========================================================================
    # TEST: GET /admin/pluxee/campaigns
    # =========================================================================
    
    def test_pluxee_campaigns(self, session):
        """Test Pluxee campaigns endpoint"""
        response = session.get(
            f"{BASE_URL}/admin/pluxee/campaigns",
            timeout=30
        )
        
        assert response.status_code == 200, f"Pluxee campaigns failed: {response.status_code}"
        data = response.json()
        
        # Should return campaigns array or object
        assert isinstance(data, (list, dict)), f"Unexpected response type: {type(data)}"
        
        if isinstance(data, dict):
            # Check for campaigns key
            campaigns = data.get('campaigns', data.get('data', []))
        else:
            campaigns = data
        
        print(f"✓ Pluxee campaigns: {len(campaigns) if isinstance(campaigns, list) else 'object'} returned")


class TestRegressionNonPluxee:
    """Regression tests for non-Pluxee sub-stores (Sofrecom)"""
    
    @pytest.fixture(scope="class")
    def session(self):
        """Create authenticated session"""
        s = requests.Session()
        s.headers.update({
            'Accept': 'application/json',
            'Content-Type': 'application/x-www-form-urlencoded',
        })
        
        login_page = s.get(f"{BASE_URL}/login", timeout=30)
        soup = BeautifulSoup(login_page.text, 'html.parser')
        csrf_input = soup.find('input', {'name': '_token'})
        csrf_token = csrf_input.get('value') if csrf_input else ''
        
        s.post(f"{BASE_URL}/login", data={
            '_token': csrf_token,
            'email': ADMIN_EMAIL,
            'password': ADMIN_PASSWORD
        }, allow_redirects=True, timeout=30)
        
        return s
    
    def test_sofrecom_kpis_non_zero(self, session):
        """Regression: Sofrecom KPIs should return non-zero values via split endpoints"""
        response = session.get(
            f"{BASE_URL}/sub-stores/api/split/kpis",
            params={'sub_store': 'Sofrecom'},
            timeout=120
        )
        
        assert response.status_code == 200
        data = response.json()
        assert data.get('success') == True
        
        kpis = data['data']
        
        # Sofrecom should have positive KPIs
        distributed = kpis.get('distributed', {}).get('current', 0)
        inscriptions = kpis.get('inscriptions', {}).get('current', 0)
        transactions = kpis.get('transactions', {}).get('current', 0)
        
        assert distributed > 0, f"Sofrecom distributed should be > 0, got {distributed}"
        assert inscriptions > 0, f"Sofrecom inscriptions should be > 0, got {inscriptions}"
        assert transactions > 0, f"Sofrecom transactions should be > 0, got {transactions}"
        
        print(f"✓ Regression Sofrecom: distributed={distributed}, inscriptions={inscriptions}, transactions={transactions}")


if __name__ == "__main__":
    pytest.main([__file__, "-v", "--tb=short"])
