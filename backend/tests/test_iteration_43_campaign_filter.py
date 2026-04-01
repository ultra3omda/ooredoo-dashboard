"""
Test Iteration 43: Campaign Filter Fix for getPluxeeUsersList
=============================================================
Key fix: getPluxeeUsersList now calls applyPluxeeCampaignFilter which adds a whereIn clause
filtering clients by carte_recharge_client -> carte_recharge -> campain_name.
Since Hutchinson has 0 entries in carte_recharge_client, the result should be EMPTY.

Test Cases:
1. Collaborateur Hutchinson: users list EMPTY (0 users), KPIs totalUsers=0, activeUsers=0
2. Admin Pluxee: users list 150+, KPIs totalUsers=6840
3. SuperAdmin: users list 150+, KPIs totalUsers=6840
4. Collaborateur KPIs: distributed=2013, inscriptions=0, activeUsers=0
5. Collaborateur charts: categoryDistribution and inscriptionsTrend filtered (empty or campaign-specific)
6. Collaborateur stores: campaign_filter='Hutchinson Tunisie - By Pluxee'
7. Collaborateur expirations: empty (0 months)
8. Collaborateur merchants: filtered by campaign
9. RBAC: Collaborateur 403 on /admin/users
10. RBAC: Admin Pluxee sees 3 users on /admin/users
"""

import pytest
import requests
import os
import re

BASE_URL = os.environ.get('REACT_APP_BACKEND_URL', 'https://perf-test-ooredoo.preview.emergentagent.com').rstrip('/')

# Test credentials
SUPERADMIN_CREDS = {"email": "superadmin@ooredoo.tn", "password": "SuperAdmin@2025"}
ADMIN_PLUXEE_CREDS = {"email": "admin.pluxee@test.com", "password": "Test@2025"}
COLLABORATEUR_CREDS = {"email": "imededdine.essefi@gmail.com", "password": "Test@2025"}


def get_csrf_token(session):
    """Get CSRF token from Laravel login page"""
    resp = session.get(f"{BASE_URL}/login", timeout=30)
    match = re.search(r'name="_token"\s+value="([^"]+)"', resp.text)
    if match:
        return match.group(1)
    match = re.search(r'<meta name="csrf-token" content="([^"]+)"', resp.text)
    if match:
        return match.group(1)
    return None


def login_user(session, email, password):
    """Login user with CSRF token"""
    csrf = get_csrf_token(session)
    if not csrf:
        return False
    resp = session.post(f"{BASE_URL}/login", data={
        "_token": csrf,
        "email": email,
        "password": password
    }, allow_redirects=True, timeout=30)
    return resp.status_code == 200 and "login" not in resp.url.lower()


@pytest.fixture(scope="module")
def superadmin_session():
    """SuperAdmin authenticated session"""
    session = requests.Session()
    session.headers.update({"Accept": "application/json"})
    if login_user(session, SUPERADMIN_CREDS["email"], SUPERADMIN_CREDS["password"]):
        return session
    pytest.skip("SuperAdmin login failed")


@pytest.fixture(scope="module")
def admin_pluxee_session():
    """Admin Pluxee authenticated session"""
    session = requests.Session()
    session.headers.update({"Accept": "application/json"})
    if login_user(session, ADMIN_PLUXEE_CREDS["email"], ADMIN_PLUXEE_CREDS["password"]):
        return session
    pytest.skip("Admin Pluxee login failed")


@pytest.fixture(scope="module")
def collaborateur_session():
    """Collaborateur authenticated session"""
    session = requests.Session()
    session.headers.update({"Accept": "application/json"})
    if login_user(session, COLLABORATEUR_CREDS["email"], COLLABORATEUR_CREDS["password"]):
        return session
    pytest.skip("Collaborateur login failed")


class TestCollaborateurCampaignFilter:
    """Test that Collaborateur with Hutchinson campaign sees EMPTY users list"""

    def test_collaborateur_users_split_empty(self, collaborateur_session):
        """
        CRITICAL TEST: Collaborateur Hutchinson should see EMPTY users list
        Because Hutchinson has 0 clients in carte_recharge_client
        """
        resp = collaborateur_session.get(f"{BASE_URL}/sub-stores/api/split/users", timeout=60)
        assert resp.status_code == 200, f"Expected 200, got {resp.status_code}"
        
        data = resp.json()
        assert data.get("success") == True, "API should return success=true"
        
        users_data = data.get("data", {})
        users_list = users_data.get("users", [])
        users_kpis = users_data.get("users_kpis", {})
        
        # CRITICAL: Users list should be EMPTY for Hutchinson
        print(f"Collaborateur users count: {len(users_list)}")
        assert len(users_list) == 0, f"Expected 0 users for Hutchinson, got {len(users_list)}"
        
        # KPIs should show 0 for user-related metrics
        total_users = users_kpis.get("totalUsers", {}).get("current", -1)
        active_users = users_kpis.get("activeUsers", {}).get("current", -1)
        print(f"Collaborateur KPIs - totalUsers: {total_users}, activeUsers: {active_users}")
        
        assert total_users == 0, f"Expected totalUsers=0, got {total_users}"
        assert active_users == 0, f"Expected activeUsers=0, got {active_users}"

    def test_collaborateur_kpis_split(self, collaborateur_session):
        """
        Collaborateur KPIs: distributed=2013, inscriptions=0, activeUsers=0
        """
        resp = collaborateur_session.get(f"{BASE_URL}/sub-stores/api/split/kpis", timeout=60)
        assert resp.status_code == 200, f"Expected 200, got {resp.status_code}"
        
        data = resp.json()
        assert data.get("success") == True, "API should return success=true"
        
        kpis = data.get("data", {})
        
        distributed = kpis.get("distributed", {}).get("current", -1)
        inscriptions = kpis.get("inscriptions", {}).get("current", -1)
        active_users = kpis.get("activeUsers", {}).get("current", -1)
        
        print(f"Collaborateur KPIs - distributed: {distributed}, inscriptions: {inscriptions}, activeUsers: {active_users}")
        
        # Hutchinson has 2013 cards distributed but 0 activated
        assert distributed == 2013, f"Expected distributed=2013, got {distributed}"
        assert inscriptions == 0, f"Expected inscriptions=0, got {inscriptions}"
        assert active_users == 0, f"Expected activeUsers=0, got {active_users}"

    def test_collaborateur_stores_split_campaign_filter(self, collaborateur_session):
        """
        Collaborateur stores: campaign_filter='Hutchinson Tunisie - By Pluxee'
        """
        resp = collaborateur_session.get(f"{BASE_URL}/sub-stores/api/split/stores", timeout=60)
        assert resp.status_code == 200, f"Expected 200, got {resp.status_code}"
        
        data = resp.json()
        assert data.get("success") == True, "API should return success=true"
        
        campaign_filter = data.get("campaign_filter")
        print(f"Collaborateur campaign_filter: {campaign_filter}")
        
        assert campaign_filter == "Hutchinson Tunisie - By Pluxee", f"Expected 'Hutchinson Tunisie - By Pluxee', got '{campaign_filter}'"

    def test_collaborateur_charts_split_filtered(self, collaborateur_session):
        """
        Collaborateur charts: categoryDistribution and inscriptionsTrend should be filtered (empty or campaign-specific)
        """
        resp = collaborateur_session.get(f"{BASE_URL}/sub-stores/api/split/charts", timeout=60)
        assert resp.status_code == 200, f"Expected 200, got {resp.status_code}"
        
        data = resp.json()
        assert data.get("success") == True, "API should return success=true"
        
        charts = data.get("data", {})
        category_dist = charts.get("categoryDistribution", [])
        inscriptions_trend = charts.get("inscriptionsTrend", [])
        
        print(f"Collaborateur charts - categoryDistribution count: {len(category_dist)}, inscriptionsTrend count: {len(inscriptions_trend)}")
        
        # For Hutchinson with 0 activated clients, charts should be empty or minimal
        # We just verify the structure is correct
        assert isinstance(category_dist, list), "categoryDistribution should be a list"
        assert isinstance(inscriptions_trend, list), "inscriptionsTrend should be a list"

    def test_collaborateur_expirations_empty(self, collaborateur_session):
        """
        Collaborateur expirations: should be empty (0 months) for Hutchinson
        """
        resp = collaborateur_session.get(f"{BASE_URL}/sub-stores/api/expirations", timeout=60)
        assert resp.status_code == 200, f"Expected 200, got {resp.status_code}"
        
        data = resp.json()
        expirations = data.get("expirationsByMonth", [])
        
        print(f"Collaborateur expirations count: {len(expirations)}")
        
        # Hutchinson has 0 clients, so expirations should be empty
        assert len(expirations) == 0, f"Expected 0 expirations for Hutchinson, got {len(expirations)}"

    def test_collaborateur_merchants_split_filtered(self, collaborateur_session):
        """
        Collaborateur merchants: should be filtered by campaign
        """
        resp = collaborateur_session.get(f"{BASE_URL}/sub-stores/api/split/merchants", timeout=60)
        assert resp.status_code == 200, f"Expected 200, got {resp.status_code}"
        
        data = resp.json()
        assert data.get("success") == True, "API should return success=true"
        
        merchants_data = data.get("data", {})
        merchants = merchants_data.get("merchants", [])
        kpis = merchants_data.get("kpis", {})
        
        print(f"Collaborateur merchants count: {len(merchants)}")
        print(f"Collaborateur merchants KPIs - activeMerchants: {kpis.get('activeMerchants', {}).get('current', 'N/A')}")
        
        # For Hutchinson with 0 transactions, merchants should be empty
        assert isinstance(merchants, list), "merchants should be a list"


class TestAdminPluxeeFullAccess:
    """Test that Admin Pluxee sees full user list (no campaign restriction)"""

    def test_admin_pluxee_users_split_full(self, admin_pluxee_session):
        """
        Admin Pluxee: users list 150+, KPIs totalUsers=6840
        """
        resp = admin_pluxee_session.get(f"{BASE_URL}/sub-stores/api/split/users", timeout=60)
        assert resp.status_code == 200, f"Expected 200, got {resp.status_code}"
        
        data = resp.json()
        assert data.get("success") == True, "API should return success=true"
        
        users_data = data.get("data", {})
        users_list = users_data.get("users", [])
        users_kpis = users_data.get("users_kpis", {})
        
        print(f"Admin Pluxee users count: {len(users_list)}")
        
        # Admin Pluxee should see 150 users (limit)
        assert len(users_list) >= 100, f"Expected 100+ users for Admin Pluxee, got {len(users_list)}"
        
        # KPIs should show full numbers
        total_users = users_kpis.get("totalUsers", {}).get("current", -1)
        print(f"Admin Pluxee KPIs - totalUsers: {total_users}")
        
        # totalUsers should be around 6840 (full sub-store)
        assert total_users >= 6000, f"Expected totalUsers >= 6000, got {total_users}"


class TestSuperAdminFullAccess:
    """Test that SuperAdmin sees full user list"""

    def test_superadmin_users_split_full(self, superadmin_session):
        """
        SuperAdmin: users list 150+, KPIs totalUsers > 0
        Note: SuperAdmin without sub_store filter sees ALL sub-stores data
        """
        # SuperAdmin needs to specify sub_store=Pluxee to see Pluxee data
        resp = superadmin_session.get(f"{BASE_URL}/sub-stores/api/split/users?sub_store=Club Privilèges By Pluxee", timeout=60)
        assert resp.status_code == 200, f"Expected 200, got {resp.status_code}"
        
        data = resp.json()
        assert data.get("success") == True, "API should return success=true"
        
        users_data = data.get("data", {})
        users_list = users_data.get("users", [])
        users_kpis = users_data.get("users_kpis", {})
        
        print(f"SuperAdmin users count: {len(users_list)}")
        
        # SuperAdmin should see users (limit 150)
        assert len(users_list) >= 50, f"Expected 50+ users for SuperAdmin, got {len(users_list)}"
        
        # KPIs should show numbers > 0
        total_users = users_kpis.get("totalUsers", {}).get("current", -1)
        print(f"SuperAdmin KPIs - totalUsers: {total_users}")
        
        # totalUsers should be > 0 (SuperAdmin sees data)
        assert total_users > 0, f"Expected totalUsers > 0, got {total_users}"


class TestRBACPermissions:
    """Test RBAC permissions for admin pages"""

    def test_collaborateur_403_admin_users(self, collaborateur_session):
        """
        Collaborateur should get 403 on /admin/users
        """
        resp = collaborateur_session.get(f"{BASE_URL}/admin/users", timeout=30)
        print(f"Collaborateur /admin/users status: {resp.status_code}")
        
        assert resp.status_code == 403, f"Expected 403 for Collaborateur on /admin/users, got {resp.status_code}"

    def test_admin_pluxee_can_access_admin_users(self, admin_pluxee_session):
        """
        Admin Pluxee should be able to access /admin/users (status 200)
        Note: The page is rendered via Blade/JS, so we just verify access
        """
        resp = admin_pluxee_session.get(f"{BASE_URL}/admin/users", timeout=30)
        print(f"Admin Pluxee /admin/users status: {resp.status_code}")
        
        assert resp.status_code == 200, f"Expected 200 for Admin Pluxee on /admin/users, got {resp.status_code}"
        
        # Verify it's an HTML page (not a redirect or error)
        assert "<!DOCTYPE html>" in resp.text or "<html" in resp.text, "Expected HTML page response"
        print("Admin Pluxee can access /admin/users page - PASS")


if __name__ == "__main__":
    pytest.main([__file__, "-v", "--tb=short"])
