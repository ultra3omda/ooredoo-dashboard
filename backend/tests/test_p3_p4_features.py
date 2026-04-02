"""
Test suite for P3 (Monitoring) and P4 (Refactoring) features
Laravel 10 application with PHP-FPM + Nginx + Redis

P4: Split dashboard endpoints (6 endpoints)
P3: Monitoring endpoints (health, alerts, warmup-status)
"""

import pytest
import requests
import os
import re
from urllib.parse import urljoin

# API URL from environment or default
BASE_URL = "https://05e71150-e8ce-4b77-a854-80030459ae3b.preview.emergentagent.com"

# Test credentials
TEST_EMAIL = "superadmin@ooredoo.tn"
TEST_PASSWORD = "SuperAdmin@2025"


class TestSession:
    """Shared session with authentication"""
    _session = None
    _authenticated = False
    
    @classmethod
    def get_session(cls):
        if cls._session is None:
            cls._session = requests.Session()
            cls._session.headers.update({
                "Accept": "application/json",
                "Content-Type": "application/x-www-form-urlencoded",
            })
        return cls._session
    
    @classmethod
    def authenticate(cls):
        if cls._authenticated:
            return True
        
        session = cls.get_session()
        
        # Step 1: Get login page to extract CSRF token
        login_page = session.get(f"{BASE_URL}/login", timeout=30)
        if login_page.status_code != 200:
            print(f"Failed to load login page: {login_page.status_code}")
            return False
        
        # Extract CSRF token from HTML
        csrf_match = re.search(r'name="_token"\s+value="([^"]+)"', login_page.text)
        if not csrf_match:
            csrf_match = re.search(r'<meta name="csrf-token" content="([^"]+)"', login_page.text)
        
        if not csrf_match:
            print("Could not find CSRF token in login page")
            return False
        
        csrf_token = csrf_match.group(1)
        print(f"Found CSRF token: {csrf_token[:20]}...")
        
        # Step 2: Submit login form
        login_data = {
            "_token": csrf_token,
            "email": TEST_EMAIL,
            "password": TEST_PASSWORD,
        }
        
        login_response = session.post(
            f"{BASE_URL}/login",
            data=login_data,
            allow_redirects=True,
            timeout=30
        )
        
        # Check if login was successful (redirected to dashboard)
        if "/dashboard" in login_response.url or login_response.status_code == 200:
            cls._authenticated = True
            print(f"Login successful! Redirected to: {login_response.url}")
            return True
        
        print(f"Login failed: {login_response.status_code} - {login_response.url}")
        return False


@pytest.fixture(scope="module")
def auth_session():
    """Authenticated session fixture"""
    session = TestSession.get_session()
    if TestSession.authenticate():
        return session
    pytest.skip("Authentication failed - skipping authenticated tests")


@pytest.fixture
def api_session():
    """Basic session without auth for public endpoints"""
    session = requests.Session()
    session.headers.update({
        "Accept": "application/json",
        "Content-Type": "application/json",
    })
    return session


# ==========================================
# P3 MONITORING TESTS (No auth required)
# ==========================================

class TestP3MonitoringHealth:
    """P3: Health check endpoint tests"""
    
    def test_health_endpoint_returns_json(self, api_session):
        """GET /api/monitoring/health returns valid JSON with health checks"""
        response = api_session.get(f"{BASE_URL}/api/monitoring/health", timeout=30)
        
        # Health endpoint can return 503 when critical (expected behavior)
        assert response.status_code in [200, 503], f"Unexpected status: {response.status_code}"
        
        data = response.json()
        print(f"Health check response: {data.get('overall_status', 'N/A')}")
        
        # Validate response structure
        assert "overall_status" in data, "Missing overall_status field"
        assert "checks" in data, "Missing checks field"
        assert "checked_at" in data, "Missing checked_at field"
        
        # Validate checks contain required components
        checks = data["checks"]
        required_checks = ["database", "redis", "warmup_cache", "disk", "api_endpoints"]
        for check in required_checks:
            assert check in checks, f"Missing health check: {check}"
            assert "status" in checks[check], f"Missing status in {check} check"
            assert "message" in checks[check], f"Missing message in {check} check"
        
        print(f"All {len(required_checks)} health checks present")
    
    def test_health_check_database_status(self, api_session):
        """Database health check returns valid status"""
        response = api_session.get(f"{BASE_URL}/api/monitoring/health", timeout=30)
        data = response.json()
        
        db_check = data["checks"]["database"]
        assert db_check["status"] in ["healthy", "warning", "critical"]
        assert "latency_ms" in db_check
        print(f"Database status: {db_check['status']}, latency: {db_check.get('latency_ms', 'N/A')}ms")
    
    def test_health_check_redis_status(self, api_session):
        """Redis health check returns valid status"""
        response = api_session.get(f"{BASE_URL}/api/monitoring/health", timeout=30)
        data = response.json()
        
        redis_check = data["checks"]["redis"]
        assert redis_check["status"] in ["healthy", "warning", "critical"]
        print(f"Redis status: {redis_check['status']}")


class TestP3MonitoringAlerts:
    """P3: Alerts endpoint tests"""
    
    def test_get_alerts_returns_array_and_stats(self, api_session):
        """GET /api/monitoring/alerts returns alerts array and stats object"""
        response = api_session.get(f"{BASE_URL}/api/monitoring/alerts", timeout=30)
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        
        # Validate response structure
        assert "alerts" in data, "Missing alerts array"
        assert "stats" in data, "Missing stats object"
        assert isinstance(data["alerts"], list), "alerts should be a list"
        assert isinstance(data["stats"], dict), "stats should be a dict"
        
        # Validate stats structure
        stats = data["stats"]
        assert "total" in stats, "Missing total in stats"
        assert "unacknowledged" in stats, "Missing unacknowledged in stats"
        assert "by_severity" in stats, "Missing by_severity in stats"
        
        print(f"Alerts: {len(data['alerts'])} total, {stats['unacknowledged']} unacknowledged")
    
    def test_get_alerts_unacknowledged_filter(self, api_session):
        """GET /api/monitoring/alerts?unacknowledged_only=true filters correctly"""
        response = api_session.get(
            f"{BASE_URL}/api/monitoring/alerts",
            params={"unacknowledged_only": "true"},
            timeout=30
        )
        
        assert response.status_code == 200
        data = response.json()
        
        # All returned alerts should be unacknowledged
        for alert in data["alerts"]:
            assert alert.get("acknowledged") == False, "Found acknowledged alert in unacknowledged filter"
        
        print(f"Unacknowledged alerts filter working: {len(data['alerts'])} alerts")


class TestP3MonitoringWarmupStatus:
    """P3: Warmup status endpoint tests"""
    
    def test_warmup_status_returns_coverage(self, api_session):
        """GET /api/monitoring/warmup-status returns coverage_pct and details"""
        response = api_session.get(f"{BASE_URL}/api/monitoring/warmup-status", timeout=30)
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        
        # Validate response structure
        assert "coverage_pct" in data, "Missing coverage_pct"
        assert "total_cached" in data, "Missing total_cached"
        assert "total_expected" in data, "Missing total_expected"
        assert "details" in data, "Missing details array"
        
        assert isinstance(data["coverage_pct"], (int, float)), "coverage_pct should be numeric"
        assert isinstance(data["details"], list), "details should be a list"
        
        print(f"Warmup coverage: {data['coverage_pct']}% ({data['total_cached']}/{data['total_expected']})")
    
    def test_warmup_status_details_structure(self, api_session):
        """Warmup status details have correct structure"""
        response = api_session.get(f"{BASE_URL}/api/monitoring/warmup-status", timeout=30)
        data = response.json()
        
        if data["details"]:
            detail = data["details"][0]
            assert "period" in detail, "Missing period in detail"
            assert "section" in detail, "Missing section in detail"
            assert "cached" in detail, "Missing cached in detail"
            print(f"Detail structure valid: period={detail['period']}, section={detail['section']}")


class TestP3MonitoringAlertActions:
    """P3: Alert action endpoints tests"""
    
    def test_clear_alerts(self, api_session):
        """DELETE /api/monitoring/alerts clears all alerts"""
        response = api_session.delete(f"{BASE_URL}/api/monitoring/alerts", timeout=30)
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        assert "cleared" in data, "Missing cleared field"
        assert data["cleared"] == True, "cleared should be True"
        
        print("Clear alerts endpoint working")
    
    def test_acknowledge_all_alerts(self, api_session):
        """POST /api/monitoring/alerts/acknowledge-all acknowledges all alerts"""
        response = api_session.post(f"{BASE_URL}/api/monitoring/alerts/acknowledge-all", timeout=30)
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        assert "acknowledged" in data, "Missing acknowledged field"
        
        print(f"Acknowledge all endpoint working: {data['acknowledged']} alerts acknowledged")
    
    def test_acknowledge_single_alert(self, api_session):
        """POST /api/monitoring/alerts/{alertId}/acknowledge works"""
        # First get alerts to find an ID
        alerts_response = api_session.get(f"{BASE_URL}/api/monitoring/alerts", timeout=30)
        alerts_data = alerts_response.json()
        
        if not alerts_data["alerts"]:
            # Create a test scenario by triggering health check (which may create alerts)
            api_session.get(f"{BASE_URL}/api/monitoring/health", timeout=30)
            alerts_response = api_session.get(f"{BASE_URL}/api/monitoring/alerts", timeout=30)
            alerts_data = alerts_response.json()
        
        if alerts_data["alerts"]:
            alert_id = alerts_data["alerts"][0]["id"]
            response = api_session.post(
                f"{BASE_URL}/api/monitoring/alerts/{alert_id}/acknowledge",
                timeout=30
            )
            
            assert response.status_code == 200, f"Expected 200, got {response.status_code}"
            data = response.json()
            assert "success" in data, "Missing success field"
            print(f"Acknowledge single alert working: {alert_id}")
        else:
            print("No alerts to acknowledge - skipping single acknowledge test")


# ==========================================
# P4 SPLIT DASHBOARD TESTS (Auth required)
# ==========================================

class TestP4SplitKPIs:
    """P4: Split KPIs endpoint tests"""
    
    def test_split_kpis_returns_200(self, auth_session):
        """GET /api/dashboard/split/kpis returns 200 with valid data"""
        response = auth_session.get(f"{BASE_URL}/api/dashboard/split/kpis", timeout=60)
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        assert "success" in data, "Missing success field"
        assert data["success"] == True, "success should be True"
        assert "section" in data, "Missing section field"
        assert data["section"] == "kpis", "section should be 'kpis'"
        assert "data" in data, "Missing data field"
        
        print(f"KPIs endpoint working, execution time: {data.get('execution_time_ms', 'N/A')}ms")


class TestP4SplitSubscriptions:
    """P4: Split Subscriptions endpoint tests"""
    
    def test_split_subscriptions_returns_200(self, auth_session):
        """GET /api/dashboard/split/subscriptions returns 200 with valid data"""
        response = auth_session.get(f"{BASE_URL}/api/dashboard/split/subscriptions", timeout=120)
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        assert "success" in data, "Missing success field"
        assert data["success"] == True, "success should be True"
        assert "section" in data, "Missing section field"
        assert data["section"] == "subscriptions", "section should be 'subscriptions'"
        
        print(f"Subscriptions endpoint working, execution time: {data.get('execution_time_ms', 'N/A')}ms")


class TestP4SplitTransactions:
    """P4: Split Transactions endpoint tests"""
    
    def test_split_transactions_returns_200(self, auth_session):
        """GET /api/dashboard/split/transactions returns 200 with valid data"""
        response = auth_session.get(f"{BASE_URL}/api/dashboard/split/transactions", timeout=60)
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        assert "success" in data, "Missing success field"
        assert data["success"] == True, "success should be True"
        assert "section" in data, "Missing section field"
        assert data["section"] == "transactions", "section should be 'transactions'"
        
        print(f"Transactions endpoint working, execution time: {data.get('execution_time_ms', 'N/A')}ms")


class TestP4SplitMerchants:
    """P4: Split Merchants endpoint tests"""
    
    def test_split_merchants_returns_200(self, auth_session):
        """GET /api/dashboard/split/merchants returns 200 with valid data"""
        response = auth_session.get(f"{BASE_URL}/api/dashboard/split/merchants", timeout=120)
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        assert "success" in data, "Missing success field"
        assert data["success"] == True, "success should be True"
        assert "section" in data, "Missing section field"
        assert data["section"] == "merchants", "section should be 'merchants'"
        
        print(f"Merchants endpoint working, execution time: {data.get('execution_time_ms', 'N/A')}ms")


class TestP4SplitTimwe:
    """P4: Split Timwe endpoint tests"""
    
    def test_split_timwe_returns_200(self, auth_session):
        """GET /api/dashboard/split/timwe returns 200 with valid data"""
        response = auth_session.get(f"{BASE_URL}/api/dashboard/split/timwe", timeout=120)
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        assert "success" in data, "Missing success field"
        assert data["success"] == True, "success should be True"
        assert "section" in data, "Missing section field"
        assert data["section"] == "timwe_stats", "section should be 'timwe_stats'"
        
        print(f"Timwe endpoint working, execution time: {data.get('execution_time_ms', 'N/A')}ms")


class TestP4SplitOoredoo:
    """P4: Split Ooredoo endpoint tests"""
    
    def test_split_ooredoo_returns_200(self, auth_session):
        """GET /api/dashboard/split/ooredoo returns 200 with valid data"""
        response = auth_session.get(f"{BASE_URL}/api/dashboard/split/ooredoo", timeout=60)
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        assert "success" in data, "Missing success field"
        assert data["success"] == True, "success should be True"
        assert "section" in data, "Missing section field"
        assert data["section"] == "ooredoo_stats", "section should be 'ooredoo_stats'"
        
        print(f"Ooredoo endpoint working, execution time: {data.get('execution_time_ms', 'N/A')}ms")


# ==========================================
# FRONTEND PAGE TESTS (Auth required)
# ==========================================

class TestDashboardPage:
    """Dashboard main page tests"""
    
    def test_dashboard_loads(self, auth_session):
        """GET /dashboard loads without 500 errors"""
        response = auth_session.get(f"{BASE_URL}/dashboard", timeout=30)
        
        assert response.status_code == 200, f"Dashboard returned {response.status_code}"
        assert "Club Privil" in response.text or "dashboard" in response.text.lower()
        
        print("Dashboard page loads successfully")


class TestMonitoringPage:
    """Monitoring dashboard page tests"""
    
    def test_monitoring_page_loads(self, auth_session):
        """GET /monitoring loads without 500 errors"""
        response = auth_session.get(f"{BASE_URL}/monitoring", timeout=30)
        
        assert response.status_code == 200, f"Monitoring page returned {response.status_code}"
        
        # Check for monitoring-related content
        content_lower = response.text.lower()
        assert "monitoring" in content_lower or "health" in content_lower or "alert" in content_lower
        
        print("Monitoring page loads successfully")


if __name__ == "__main__":
    pytest.main([__file__, "-v", "--tb=short"])
