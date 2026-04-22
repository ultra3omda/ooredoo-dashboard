"""
ML Dashboard API Tests
Tests for ML Pipeline endpoints: insights, A/B testing, training
"""
import pytest
import requests
import os

BASE_URL = os.environ.get('REACT_APP_BACKEND_URL', 'https://perf-test-ooredoo.preview.emergentagent.com').rstrip('/')

# Test credentials from environment
TEST_EMAIL = os.getenv("TEST_ADMIN_EMAIL", "superadmin@ooredoo.tn")
TEST_PASSWORD = os.getenv("TEST_ADMIN_PASSWORD", "SuperAdmin@2025")


class TestMLDashboardAPI:
    """ML Dashboard API endpoint tests"""
    
    @pytest.fixture(autouse=True)
    def setup(self):
        """Setup session with authentication"""
        self.session = requests.Session()
        self.session.headers.update({
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        })
        
        # Login to get session
        login_response = self.session.get(f"{BASE_URL}/login")
        
        # Get CSRF token from login page
        csrf_token = None
        if 'csrf-token' in login_response.text:
            import re
            match = re.search(r'name="csrf-token" content="([^"]+)"', login_response.text)
            if match:
                csrf_token = match.group(1)
        
        if csrf_token:
            self.session.headers.update({'X-CSRF-TOKEN': csrf_token})
        
        # Perform login
        login_data = {
            'email': TEST_EMAIL,
            'password': TEST_PASSWORD,
            '_token': csrf_token
        }
        
        login_result = self.session.post(f"{BASE_URL}/login", data=login_data, allow_redirects=True)
        
        # Verify login was successful
        if '/dashboard' not in login_result.url and login_result.status_code != 200:
            pytest.skip("Login failed - skipping authenticated tests")
    
    def test_ml_insights_endpoint(self):
        """Test ML Insights endpoint returns expected data"""
        response = self.session.get(f"{BASE_URL}/admin/ml-dashboard/insights")
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        assert data.get('success') == True, "Expected success=True"
        
        # Verify expected fields
        assert 'accuracy' in data, "Missing 'accuracy' field"
        assert 'churn_risk_count' in data, "Missing 'churn_risk_count' field"
        assert 'avg_success_rate' in data, "Missing 'avg_success_rate' field"
        
        # Verify accuracy is reasonable (not >100%)
        if data.get('accuracy') is not None:
            assert data['accuracy'] <= 100, f"Accuracy {data['accuracy']} is >100%"
        
        print(f"ML Insights: accuracy={data.get('accuracy')}, churn_risk={data.get('churn_risk_count')}, success_rate={data.get('avg_success_rate')}")
    
    def test_ml_dashboard_page_loads(self):
        """Test ML Dashboard page loads without errors"""
        response = self.session.get(f"{BASE_URL}/admin/ml-dashboard")
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        # Check for error messages in HTML
        assert 'alert-danger' not in response.text or 'Erreur' not in response.text, "Found error on ML Dashboard page"
        
        # Check for expected elements
        assert 'ML Dashboard' in response.text, "ML Dashboard title not found"
        assert 'Performance du Modele' in response.text or 'Performance du Modèle' in response.text, "Model performance section not found"
        
        print("ML Dashboard page loaded successfully")
    
    def test_ab_test_start_endpoint(self):
        """Test A/B Test start endpoint returns test_id"""
        # Get CSRF token first
        dashboard_response = self.session.get(f"{BASE_URL}/admin/ml-dashboard")
        
        import re
        csrf_match = re.search(r'name="csrf-token" content="([^"]+)"', dashboard_response.text)
        if csrf_match:
            self.session.headers.update({'X-CSRF-TOKEN': csrf_match.group(1)})
        
        response = self.session.post(f"{BASE_URL}/admin/ml-dashboard/ab-test/start", json={
            'name': 'Test A/B Automated',
            'description': 'Automated test from pytest',
            'target_group_size': 100,
            'duration_days': 7
        })
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        assert data.get('success') == True, f"Expected success=True, got {data}"
        assert 'test_id' in data, f"Missing 'test_id' in response: {data}"
        
        print(f"A/B Test started successfully: test_id={data.get('test_id')}")
    
    def test_task_status_endpoint(self):
        """Test task status endpoint"""
        response = self.session.get(f"{BASE_URL}/admin/ml-dashboard/task-status")
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        assert data.get('success') == True, "Expected success=True"
        
        # Should have extract_features and train_model status
        assert 'extract_features' in data or 'train_model' in data, "Missing task status fields"
        
        print(f"Task status: {data}")


class TestDashboardKPIs:
    """Dashboard KPI endpoint tests"""
    
    @pytest.fixture(autouse=True)
    def setup(self):
        """Setup session with authentication"""
        self.session = requests.Session()
        self.session.headers.update({
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        })
        
        # Login
        login_response = self.session.get(f"{BASE_URL}/login")
        
        import re
        csrf_match = re.search(r'name="csrf-token" content="([^"]+)"', login_response.text)
        csrf_token = csrf_match.group(1) if csrf_match else None
        
        if csrf_token:
            self.session.headers.update({'X-CSRF-TOKEN': csrf_token})
        
        login_data = {
            'email': TEST_EMAIL,
            'password': TEST_PASSWORD,
            '_token': csrf_token
        }
        
        login_result = self.session.post(f"{BASE_URL}/login", data=login_data, allow_redirects=True)
        
        if '/dashboard' not in login_result.url and login_result.status_code != 200:
            pytest.skip("Login failed - skipping authenticated tests")
    
    def test_kpis_endpoint(self):
        """Test KPIs endpoint returns reasonable values"""
        response = self.session.get(f"{BASE_URL}/api/dashboard/split/kpis")
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        assert data.get('success') == True, "Expected success=True"
        
        kpis = data.get('data', {})
        
        # Check retention rate is reasonable (<=100%)
        retention = kpis.get('retentionRate', {})
        if retention and retention.get('current') is not None:
            rate = float(retention['current'])
            assert rate <= 100, f"Retention rate {rate}% is >100%"
            print(f"Retention Rate: {rate}%")
        
        # Check conversion rate is reasonable (<=100%)
        conversion = kpis.get('conversionRate', {})
        if conversion and conversion.get('current') is not None:
            rate = float(conversion['current'])
            assert rate <= 100, f"Conversion rate {rate}% is >100%"
            print(f"Conversion Rate: {rate}%")
    
    def test_merchants_endpoint(self):
        """Test merchants endpoint"""
        response = self.session.get(f"{BASE_URL}/api/dashboard/split/merchants")
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        assert data.get('success') == True, "Expected success=True"
        
        print(f"Merchants data loaded successfully")


if __name__ == "__main__":
    pytest.main([__file__, "-v", "--tb=short"])
