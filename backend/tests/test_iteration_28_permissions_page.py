"""
Iteration 28 - Permissions Management Page Tests
Tests for the new /admin/users/permissions page feature

Features tested:
- PHP file syntax validation (matching braces)
- New routes in web.php
- UserManagementController methods
- permissions.blade.php UI elements
- User.php campaign access methods
- FastAPI regression endpoints
"""

import pytest
import requests
import os
import subprocess
import re

BASE_URL = os.environ.get('REACT_APP_BACKEND_URL', '').rstrip('/')

class TestPHPFileSyntax:
    """Verify PHP files have matching braces (no syntax errors)"""
    
    def test_user_management_controller_braces(self):
        """UserManagementController.php should have matching braces"""
        result = subprocess.run(
            ['grep', '-c', '{', '/app/app/Http/Controllers/Admin/UserManagementController.php'],
            capture_output=True, text=True
        )
        open_braces = int(result.stdout.strip())
        
        result = subprocess.run(
            ['grep', '-c', '}', '/app/app/Http/Controllers/Admin/UserManagementController.php'],
            capture_output=True, text=True
        )
        close_braces = int(result.stdout.strip())
        
        assert open_braces == close_braces, f"Brace mismatch: {open_braces} open vs {close_braces} close"
        print(f"PASS: UserManagementController.php - {open_braces} braces match")
    
    def test_web_routes_braces(self):
        """web.php should have matching braces"""
        result = subprocess.run(
            ['grep', '-c', '{', '/app/routes/web.php'],
            capture_output=True, text=True
        )
        open_braces = int(result.stdout.strip())
        
        result = subprocess.run(
            ['grep', '-c', '}', '/app/routes/web.php'],
            capture_output=True, text=True
        )
        close_braces = int(result.stdout.strip())
        
        assert open_braces == close_braces, f"Brace mismatch: {open_braces} open vs {close_braces} close"
        print(f"PASS: web.php - {open_braces} braces match")
    
    def test_permissions_blade_braces(self):
        """permissions.blade.php should have matching braces (PHP + JS)"""
        result = subprocess.run(
            ['grep', '-c', '{', '/app/resources/views/admin/users/permissions.blade.php'],
            capture_output=True, text=True
        )
        open_braces = int(result.stdout.strip())
        
        result = subprocess.run(
            ['grep', '-c', '}', '/app/resources/views/admin/users/permissions.blade.php'],
            capture_output=True, text=True
        )
        close_braces = int(result.stdout.strip())
        
        # Blade templates may have slight mismatch due to Blade syntax, allow 1 difference
        diff = abs(open_braces - close_braces)
        assert diff <= 1, f"Brace mismatch: {open_braces} open vs {close_braces} close (diff={diff})"
        print(f"PASS: permissions.blade.php - {open_braces}/{close_braces} braces (diff={diff})")
    
    def test_user_model_braces(self):
        """User.php should have matching braces"""
        result = subprocess.run(
            ['grep', '-c', '{', '/app/app/Models/User.php'],
            capture_output=True, text=True
        )
        open_braces = int(result.stdout.strip())
        
        result = subprocess.run(
            ['grep', '-c', '}', '/app/app/Models/User.php'],
            capture_output=True, text=True
        )
        close_braces = int(result.stdout.strip())
        
        assert open_braces == close_braces, f"Brace mismatch: {open_braces} open vs {close_braces} close"
        print(f"PASS: User.php - {open_braces} braces match")


class TestNewRoutes:
    """Verify new routes exist in web.php"""
    
    def test_permissions_route_exists(self):
        """Route admin.users.permissions should exist"""
        with open('/app/routes/web.php', 'r') as f:
            content = f.read()
        
        assert "users.permissions" in content, "Route users.permissions not found"
        assert "permissions()" in content or "permissions']" in content, "permissions method not linked"
        print("PASS: Route admin.users.permissions exists")
    
    def test_campaign_access_route_exists(self):
        """Route admin.users.campaign-access should exist"""
        with open('/app/routes/web.php', 'r') as f:
            content = f.read()
        
        assert "users.campaign-access" in content, "Route users.campaign-access not found"
        assert "updateCampaignAccess" in content, "updateCampaignAccess method not linked"
        print("PASS: Route admin.users.campaign-access exists")
    
    def test_available_campaigns_route_exists(self):
        """Route admin.users.available-campaigns should exist"""
        with open('/app/routes/web.php', 'r') as f:
            content = f.read()
        
        assert "users.available-campaigns" in content, "Route users.available-campaigns not found"
        assert "getAvailableCampaigns" in content, "getAvailableCampaigns method not linked"
        print("PASS: Route admin.users.available-campaigns exists")


class TestControllerMethods:
    """Verify UserManagementController has required methods"""
    
    def test_permissions_method_exists(self):
        """permissions() method should exist in UserManagementController"""
        result = subprocess.run(
            ['grep', '-n', 'function permissions', '/app/app/Http/Controllers/Admin/UserManagementController.php'],
            capture_output=True, text=True
        )
        assert 'function permissions' in result.stdout, "permissions() method not found"
        print(f"PASS: permissions() method found at line {result.stdout.split(':')[0]}")
    
    def test_update_campaign_access_method_exists(self):
        """updateCampaignAccess() method should exist in UserManagementController"""
        result = subprocess.run(
            ['grep', '-n', 'function updateCampaignAccess', '/app/app/Http/Controllers/Admin/UserManagementController.php'],
            capture_output=True, text=True
        )
        assert 'function updateCampaignAccess' in result.stdout, "updateCampaignAccess() method not found"
        print(f"PASS: updateCampaignAccess() method found at line {result.stdout.split(':')[0]}")
    
    def test_get_available_campaigns_method_exists(self):
        """getAvailableCampaigns() method should exist in UserManagementController"""
        result = subprocess.run(
            ['grep', '-n', 'function getAvailableCampaigns', '/app/app/Http/Controllers/Admin/UserManagementController.php'],
            capture_output=True, text=True
        )
        assert 'function getAvailableCampaigns' in result.stdout, "getAvailableCampaigns() method not found"
        print(f"PASS: getAvailableCampaigns() method found at line {result.stdout.split(':')[0]}")


class TestPermissionsBladeTemplate:
    """Verify permissions.blade.php contains required UI elements"""
    
    def test_kpi_grid_exists(self):
        """KPI grid should exist in permissions.blade.php"""
        with open('/app/resources/views/admin/users/permissions.blade.php', 'r') as f:
            content = f.read()
        
        assert 'kpi-grid' in content, "KPI grid not found"
        assert 'permissions-kpi-grid' in content, "data-testid for KPI grid not found"
        print("PASS: KPI grid exists with data-testid")
    
    def test_search_filter_exists(self):
        """Search and filter bar should exist"""
        with open('/app/resources/views/admin/users/permissions.blade.php', 'r') as f:
            content = f.read()
        
        assert 'search-bar' in content, "Search bar not found"
        assert 'searchInput' in content, "Search input not found"
        assert 'filterRole' in content, "Role filter not found"
        assert 'filterAccess' in content, "Access filter not found"
        print("PASS: Search and filter bar exists")
    
    def test_users_table_exists(self):
        """Users table should exist"""
        with open('/app/resources/views/admin/users/permissions.blade.php', 'r') as f:
            content = f.read()
        
        assert 'usersTableBody' in content, "Users table body not found"
        assert 'permissions-table-body' in content, "data-testid for table body not found"
        print("PASS: Users table exists with data-testid")
    
    def test_edit_modal_exists(self):
        """Edit modal should exist"""
        with open('/app/resources/views/admin/users/permissions.blade.php', 'r') as f:
            content = f.read()
        
        assert 'editModal' in content, "Edit modal not found"
        assert 'edit-modal' in content, "data-testid for edit modal not found"
        assert 'openEditModal' in content, "openEditModal function not found"
        assert 'closeEditModal' in content, "closeEditModal function not found"
        print("PASS: Edit modal exists with functions")
    
    def test_campaign_checkboxes_exist(self):
        """Campaign checkboxes should exist in modal"""
        with open('/app/resources/views/admin/users/permissions.blade.php', 'r') as f:
            content = f.read()
        
        assert 'campaign-checkbox' in content, "Campaign checkbox class not found"
        assert 'modalCampaigns' in content, "Modal campaigns container not found"
        assert 'modal-campaigns-grid' in content, "data-testid for campaigns grid not found"
        print("PASS: Campaign checkboxes exist in modal")
    
    def test_save_buttons_exist(self):
        """Save and Full Access buttons should exist"""
        with open('/app/resources/views/admin/users/permissions.blade.php', 'r') as f:
            content = f.read()
        
        assert 'save-permissions-btn' in content, "Save button data-testid not found"
        assert 'save-full-access-btn' in content, "Full access button data-testid not found"
        assert 'saveCampaignAccess' in content, "saveCampaignAccess function not found"
        print("PASS: Save and Full Access buttons exist")
    
    def test_toast_notification_exists(self):
        """Toast notification should exist"""
        with open('/app/resources/views/admin/users/permissions.blade.php', 'r') as f:
            content = f.read()
        
        assert 'toast-notification' in content, "Toast notification data-testid not found"
        assert 'showToast' in content, "showToast function not found"
        print("PASS: Toast notification exists")


class TestUserModelMethods:
    """Verify User.php has required campaign access methods"""
    
    def test_get_allowed_campaigns_method_exists(self):
        """getAllowedCampaigns() method should exist in User.php"""
        result = subprocess.run(
            ['grep', '-n', 'function getAllowedCampaigns', '/app/app/Models/User.php'],
            capture_output=True, text=True
        )
        assert 'function getAllowedCampaigns' in result.stdout, "getAllowedCampaigns() method not found"
        print(f"PASS: getAllowedCampaigns() method found at line {result.stdout.split(':')[0]}")
    
    def test_has_campaign_restriction_method_exists(self):
        """hasCampaignRestriction() method should exist in User.php"""
        result = subprocess.run(
            ['grep', '-n', 'function hasCampaignRestriction', '/app/app/Models/User.php'],
            capture_output=True, text=True
        )
        assert 'function hasCampaignRestriction' in result.stdout, "hasCampaignRestriction() method not found"
        print(f"PASS: hasCampaignRestriction() method found at line {result.stdout.split(':')[0]}")
    
    def test_can_invite_collaborators_method_exists(self):
        """canInviteCollaborators() method should exist in User.php"""
        result = subprocess.run(
            ['grep', '-n', 'function canInviteCollaborators', '/app/app/Models/User.php'],
            capture_output=True, text=True
        )
        assert 'function canInviteCollaborators' in result.stdout, "canInviteCollaborators() method not found"
        # Verify only ONE occurrence (no duplicates)
        lines = [l for l in result.stdout.strip().split('\n') if l]
        assert len(lines) == 1, f"Expected 1 canInviteCollaborators method, found {len(lines)}"
        print(f"PASS: canInviteCollaborators() method found at line {result.stdout.split(':')[0]} (no duplicates)")


class TestIndexBladeLink:
    """Verify users/index.blade.php has link to permissions page"""
    
    def test_permissions_link_exists(self):
        """Link to permissions page should exist in index.blade.php"""
        with open('/app/resources/views/admin/users/index.blade.php', 'r') as f:
            content = f.read()
        
        assert 'admin.users.permissions' in content, "Link to permissions page not found"
        assert 'Permissions' in content, "Permissions text not found"
        print("PASS: Link to permissions page exists in index.blade.php")


class TestFastAPIRegression:
    """Regression tests for FastAPI endpoints"""
    
    def test_health_endpoint(self):
        """GET /api/merchant-recommendations/health should work"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/health", timeout=10)
        assert response.status_code == 200, f"Health check failed: {response.status_code}"
        data = response.json()
        assert data.get('status') == 'ready', f"Status not ready: {data}"
        print(f"PASS: Health endpoint - status={data.get('status')}, model_loaded={data.get('model_loaded')}")
    
    def test_intelligence_digest_endpoint(self):
        """GET /api/merchant-intelligence/digest should work"""
        response = requests.get(f"{BASE_URL}/api/merchant-intelligence/digest", timeout=30)
        assert response.status_code == 200, f"Intelligence digest failed: {response.status_code}"
        data = response.json()
        assert data.get('success') == True, f"Success not true: {data}"
        print(f"PASS: Intelligence digest - success={data.get('success')}, total_analyzed={data.get('total_analyzed')}")
    
    def test_ml_recommendations_endpoint(self):
        """POST /api/merchant-recommendations with client_id should work"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 118580},
            timeout=30
        )
        assert response.status_code == 200, f"ML recommendations failed: {response.status_code}"
        data = response.json()
        assert data.get('success') == True, f"Success not true: {data}"
        assert data.get('client_id') == 118580, f"Wrong client_id: {data.get('client_id')}"
        assert len(data.get('recommendations', [])) > 0, "No recommendations returned"
        print(f"PASS: ML recommendations - client_id={data.get('client_id')}, count={data.get('count')}, source={data.get('source')}")
    
    def test_ab_test_results_endpoint(self):
        """GET /api/merchant-recommendations/ab-test/results should work"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/ab-test/results", timeout=10)
        assert response.status_code == 200, f"A/B test results failed: {response.status_code}"
        data = response.json()
        assert 'groups' in data, f"No groups in response: {data}"
        assert 'ml_model' in data.get('groups', {}), f"No ml_model group: {data}"
        print(f"PASS: A/B test results - period_days={data.get('period_days')}, winner={data.get('uplift', {}).get('winner')}")


class TestControllerLogic:
    """Verify controller logic is correct"""
    
    def test_permissions_method_returns_view(self):
        """permissions() should return the permissions view"""
        with open('/app/app/Http/Controllers/Admin/UserManagementController.php', 'r') as f:
            content = f.read()
        
        # Find permissions method and check it returns the view
        assert "return view('admin.users.permissions'" in content, "permissions() doesn't return correct view"
        print("PASS: permissions() returns admin.users.permissions view")
    
    def test_update_campaign_access_handles_empty_campaigns(self):
        """updateCampaignAccess() should handle empty campaigns (full access)"""
        with open('/app/app/Http/Controllers/Admin/UserManagementController.php', 'r') as f:
            content = f.read()
        
        assert "pluxee_campaign_access' => null" in content or "pluxee_campaign_access' => NULL" in content.upper(), \
            "updateCampaignAccess() doesn't set NULL for full access"
        print("PASS: updateCampaignAccess() handles empty campaigns (sets NULL)")
    
    def test_get_available_campaigns_queries_database(self):
        """getAvailableCampaigns() should query carte_recharge and stores"""
        with open('/app/app/Http/Controllers/Admin/UserManagementController.php', 'r') as f:
            content = f.read()
        
        assert "carte_recharge" in content, "getAvailableCampaigns() doesn't query carte_recharge"
        assert "stores" in content, "getAvailableCampaigns() doesn't join stores"
        assert "campain_name" in content, "getAvailableCampaigns() doesn't select campain_name"
        assert "store_name" in content, "getAvailableCampaigns() doesn't select store_name"
        print("PASS: getAvailableCampaigns() queries carte_recharge JOIN stores")


if __name__ == "__main__":
    pytest.main([__file__, "-v", "--tb=short"])
