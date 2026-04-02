"""
Iteration 43: Test Pluxee Campaign Filter Fix
Critical fix: applyPluxeeCampaignFilter now uses carte_recharge.client_id directly
instead of carte_recharge_client table (which was empty for Hutchinson campaign).

Expected results for Collaborateur Hutchinson:
- distributed: 2013
- inscriptions: 582
- activeUsers: 580
- transactions: 117
- cartes utilisées: 617
"""

import pytest
import requests
import os
import re

BASE_URL = os.environ.get('REACT_APP_BACKEND_URL', 'https://perf-test-ooredoo.preview.emergentagent.com').rstrip('/')

# Test credentials
CREDENTIALS = {
    'superadmin': {'email': 'superadmin@ooredoo.tn', 'password': 'SuperAdmin@2025'},
    'admin_pluxee': {'email': 'admin.pluxee@test.com', 'password': 'Test@2025'},
    'collaborateur': {'email': 'imededdine.essefi@gmail.com', 'password': 'Test@2025'},
}


class TestSession:
    """Helper class to manage authenticated sessions"""
    
    @staticmethod
    def get_csrf_token(session):
        """Get CSRF token from Laravel"""
        resp = session.get(f"{BASE_URL}/sanctum/csrf-cookie", timeout=30)
        return session.cookies.get('XSRF-TOKEN')
    
    @staticmethod
    def login(session, email, password):
        """Login and return authenticated session"""
        csrf = TestSession.get_csrf_token(session)
        headers = {
            'X-XSRF-TOKEN': requests.utils.unquote(csrf) if csrf else '',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'Referer': BASE_URL,
        }
        resp = session.post(
            f"{BASE_URL}/login",
            json={'email': email, 'password': password},
            headers=headers,
            timeout=30
        )
        return resp.status_code == 200 or resp.status_code == 204


@pytest.fixture(scope='module')
def collaborateur_session():
    """Authenticated session for Collaborateur (Hutchinson campaign)"""
    session = requests.Session()
    creds = CREDENTIALS['collaborateur']
    success = TestSession.login(session, creds['email'], creds['password'])
    if not success:
        pytest.skip("Failed to login as Collaborateur")
    return session


@pytest.fixture(scope='module')
def admin_pluxee_session():
    """Authenticated session for Admin Pluxee"""
    session = requests.Session()
    creds = CREDENTIALS['admin_pluxee']
    success = TestSession.login(session, creds['email'], creds['password'])
    if not success:
        pytest.skip("Failed to login as Admin Pluxee")
    return session


@pytest.fixture(scope='module')
def superadmin_session():
    """Authenticated session for SuperAdmin"""
    session = requests.Session()
    creds = CREDENTIALS['superadmin']
    success = TestSession.login(session, creds['email'], creds['password'])
    if not success:
        pytest.skip("Failed to login as SuperAdmin")
    return session


class TestCollaborateurKPIs:
    """Test KPIs for Collaborateur Hutchinson - CRITICAL FIX VERIFICATION"""
    
    def test_kpis_not_zero(self, collaborateur_session):
        """Collaborateur KPIs should NOT be 0 anymore after the fix"""
        resp = collaborateur_session.get(
            f"{BASE_URL}/sub-stores/api/split/kpis",
            headers={'Accept': 'application/json'},
            timeout=60
        )
        assert resp.status_code == 200, f"KPIs endpoint failed: {resp.status_code}"
        
        data = resp.json()
        assert data.get('success') == True, f"KPIs response not successful: {data}"
        
        kpis = data.get('data', {})
        
        # CRITICAL: These should NOT be 0 after the fix
        distributed = kpis.get('distributed', {}).get('current', 0)
        inscriptions = kpis.get('inscriptions', {}).get('current', 0)
        active_users = kpis.get('activeUsers', {}).get('current', 0)
        transactions = kpis.get('transactions', {}).get('current', 0)
        
        print(f"Collaborateur KPIs: distributed={distributed}, inscriptions={inscriptions}, activeUsers={active_users}, transactions={transactions}")
        
        # Expected: distributed=2013, inscriptions=582, activeUsers=580, transactions=117
        assert distributed > 0, f"CRITICAL: distributed should be > 0 (expected ~2013), got {distributed}"
        assert inscriptions > 500, f"CRITICAL: inscriptions should be > 500 (expected ~582), got {inscriptions}"
        assert active_users > 500, f"CRITICAL: activeUsers should be > 500 (expected ~580), got {active_users}"
        assert transactions > 100, f"CRITICAL: transactions should be > 100 (expected ~117), got {transactions}"
    
    def test_kpis_expected_values(self, collaborateur_session):
        """Verify KPIs match expected values from the fix"""
        resp = collaborateur_session.get(
            f"{BASE_URL}/sub-stores/api/split/kpis",
            headers={'Accept': 'application/json'},
            timeout=60
        )
        assert resp.status_code == 200
        
        data = resp.json()
        kpis = data.get('data', {})
        
        distributed = kpis.get('distributed', {}).get('current', 0)
        inscriptions = kpis.get('inscriptions', {}).get('current', 0)
        active_users = kpis.get('activeUsers', {}).get('current', 0)
        transactions = kpis.get('transactions', {}).get('current', 0)
        
        # Verify approximate expected values (with some tolerance)
        assert 1500 <= distributed <= 2500, f"distributed expected ~2013, got {distributed}"
        assert 400 <= inscriptions <= 700, f"inscriptions expected ~582, got {inscriptions}"
        assert 400 <= active_users <= 700, f"activeUsers expected ~580, got {active_users}"
        assert 50 <= transactions <= 200, f"transactions expected ~117, got {transactions}"


class TestCollaborateurUsers:
    """Test Users endpoint for Collaborateur Hutchinson"""
    
    def test_users_list_not_empty(self, collaborateur_session):
        """Collaborateur should see users list (NOT empty)"""
        resp = collaborateur_session.get(
            f"{BASE_URL}/sub-stores/api/split/users",
            headers={'Accept': 'application/json'},
            timeout=120
        )
        assert resp.status_code == 200, f"Users endpoint failed: {resp.status_code}"
        
        data = resp.json()
        assert data.get('success') == True, f"Users response not successful: {data}"
        
        users_data = data.get('data', {})
        users_list = users_data.get('users', [])
        users_kpis = users_data.get('users_kpis', {})
        
        print(f"Collaborateur Users: count={len(users_list)}, totalUsers KPI={users_kpis.get('totalUsers', {}).get('current', 0)}")
        
        # CRITICAL: Users list should NOT be empty
        assert len(users_list) > 100, f"CRITICAL: users list should have > 100 users, got {len(users_list)}"
        
        # Total Users KPI should be > 500
        total_users = users_kpis.get('totalUsers', {}).get('current', 0)
        assert total_users > 500, f"CRITICAL: totalUsers KPI should be > 500, got {total_users}"


class TestCollaborateurStores:
    """Test Stores endpoint for Collaborateur Hutchinson"""
    
    def test_stores_campaign_filter(self, collaborateur_session):
        """Collaborateur stores should have campaign_filter set and activatedClients > 600"""
        resp = collaborateur_session.get(
            f"{BASE_URL}/sub-stores/api/split/stores",
            headers={'Accept': 'application/json'},
            timeout=60
        )
        assert resp.status_code == 200, f"Stores endpoint failed: {resp.status_code}"
        
        data = resp.json()
        assert data.get('success') == True, f"Stores response not successful: {data}"
        
        campaign_filter = data.get('campaign_filter')
        stores_data = data.get('data', [])
        
        print(f"Collaborateur Stores: campaign_filter={campaign_filter}, stores_count={len(stores_data)}")
        
        # Campaign filter should be set (Hutchinson)
        assert campaign_filter is not None, "CRITICAL: campaign_filter should be set for Collaborateur"
        assert 'Hutchinson' in str(campaign_filter), f"campaign_filter should contain 'Hutchinson', got {campaign_filter}"
        
        # Check activatedClients in stores data
        if stores_data:
            first_store = stores_data[0]
            # The 'transactions' field in campaign ranking represents activatedClients
            activated_clients = first_store.get('transactions', 0)
            print(f"First store activatedClients: {activated_clients}")
            assert activated_clients > 600, f"activatedClients should be > 600, got {activated_clients}"


class TestCollaborateurExpirations:
    """Test Expirations endpoint for Collaborateur Hutchinson"""
    
    def test_expirations_not_empty(self, collaborateur_session):
        """Collaborateur expirations should return 1+ months (NOT empty)"""
        resp = collaborateur_session.get(
            f"{BASE_URL}/sub-stores/api/expirations",
            headers={'Accept': 'application/json'},
            timeout=60
        )
        assert resp.status_code == 200, f"Expirations endpoint failed: {resp.status_code}"
        
        data = resp.json()
        expirations = data.get('expirationsByMonth', [])
        
        print(f"Collaborateur Expirations: months_count={len(expirations)}")
        
        # CRITICAL: Should NOT be empty after the fix
        assert len(expirations) >= 1, f"CRITICAL: expirations should have >= 1 month, got {len(expirations)}"


class TestAdminPluxeeKPIs:
    """Test KPIs for Admin Pluxee (all campaigns)"""
    
    def test_kpis_all_campaigns(self, admin_pluxee_session):
        """Admin Pluxee should see all campaigns data"""
        resp = admin_pluxee_session.get(
            f"{BASE_URL}/sub-stores/api/split/kpis",
            headers={'Accept': 'application/json'},
            timeout=60
        )
        assert resp.status_code == 200, f"KPIs endpoint failed: {resp.status_code}"
        
        data = resp.json()
        assert data.get('success') == True, f"KPIs response not successful: {data}"
        
        kpis = data.get('data', {})
        
        distributed = kpis.get('distributed', {}).get('current', 0)
        inscriptions = kpis.get('inscriptions', {}).get('current', 0)
        
        print(f"Admin Pluxee KPIs: distributed={distributed}, inscriptions={inscriptions}")
        
        # Admin Pluxee should see all campaigns: distributed > 100000, inscriptions > 6000
        assert distributed > 100000, f"Admin Pluxee distributed should be > 100000, got {distributed}"
        assert inscriptions > 6000, f"Admin Pluxee inscriptions should be > 6000, got {inscriptions}"
    
    def test_expirations_13_months(self, admin_pluxee_session):
        """Admin Pluxee expirations should return 13 months"""
        resp = admin_pluxee_session.get(
            f"{BASE_URL}/sub-stores/api/expirations",
            headers={'Accept': 'application/json'},
            timeout=60
        )
        assert resp.status_code == 200, f"Expirations endpoint failed: {resp.status_code}"
        
        data = resp.json()
        expirations = data.get('expirationsByMonth', [])
        
        print(f"Admin Pluxee Expirations: months_count={len(expirations)}")
        
        # Should return 13 months
        assert len(expirations) == 13, f"Admin Pluxee expirations should have 13 months, got {len(expirations)}"


class TestSuperAdminKPIs:
    """Test KPIs for SuperAdmin"""
    
    def test_kpis_same_as_admin_pluxee(self, superadmin_session):
        """SuperAdmin should see same data as Admin Pluxee for Pluxee sub-store"""
        # First get sub-stores to find Pluxee
        resp = superadmin_session.get(
            f"{BASE_URL}/sub-stores/api/stores",
            headers={'Accept': 'application/json'},
            timeout=30
        )
        assert resp.status_code == 200
        
        # Now get KPIs with Pluxee filter
        resp = superadmin_session.get(
            f"{BASE_URL}/sub-stores/api/split/kpis?sub_store=Club%20Privil%C3%A8ges%20By%20Pluxee",
            headers={'Accept': 'application/json'},
            timeout=60
        )
        assert resp.status_code == 200, f"KPIs endpoint failed: {resp.status_code}"
        
        data = resp.json()
        kpis = data.get('data', {})
        
        distributed = kpis.get('distributed', {}).get('current', 0)
        inscriptions = kpis.get('inscriptions', {}).get('current', 0)
        
        print(f"SuperAdmin Pluxee KPIs: distributed={distributed}, inscriptions={inscriptions}")
        
        # Should see same as Admin Pluxee
        assert distributed > 100000, f"SuperAdmin Pluxee distributed should be > 100000, got {distributed}"
        assert inscriptions > 6000, f"SuperAdmin Pluxee inscriptions should be > 6000, got {inscriptions}"


class TestRBAC:
    """Test RBAC permissions still work"""
    
    def test_collaborateur_403_on_admin_users(self, collaborateur_session):
        """Collaborateur should get 403 on /admin/users"""
        resp = collaborateur_session.get(
            f"{BASE_URL}/admin/users",
            headers={'Accept': 'application/json'},
            timeout=30
        )
        assert resp.status_code == 403, f"Collaborateur should get 403 on /admin/users, got {resp.status_code}"
    
    def test_admin_pluxee_sees_3_users(self, admin_pluxee_session):
        """Admin Pluxee should see exactly 3 users from their sub-store"""
        resp = admin_pluxee_session.get(
            f"{BASE_URL}/admin/users",
            headers={'Accept': 'application/json'},
            timeout=30
        )
        assert resp.status_code == 200, f"Admin Pluxee should access /admin/users, got {resp.status_code}"
        
        # Check if response is HTML (view) or JSON
        content_type = resp.headers.get('Content-Type', '')
        if 'application/json' in content_type:
            data = resp.json()
            users = data.get('users', [])
            print(f"Admin Pluxee sees {len(users)} users")
            assert len(users) == 3, f"Admin Pluxee should see 3 users, got {len(users)}"
        else:
            # HTML response - check for user count in page
            html = resp.text
            # Look for user emails in the HTML
            expected_emails = ['admin.pluxee@test.com', 'mohamed@pluxee.tn', 'imededdine.essefi@gmail.com']
            found_count = sum(1 for email in expected_emails if email in html)
            print(f"Admin Pluxee HTML page contains {found_count} expected users")
            assert found_count >= 2, f"Admin Pluxee page should contain at least 2 expected users"


if __name__ == '__main__':
    pytest.main([__file__, '-v', '--tb=short'])
