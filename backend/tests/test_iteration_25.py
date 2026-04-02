"""
Iteration 25 Backend Tests - 3 New Features
============================================
1. P3 Analytics Temporel (Timeline endpoint with 30/60/90 days)
2. P3 A/B Test Framework (ML vs Popularity)
3. Weekly Email Preview endpoint

Plus regression tests for existing endpoints.
"""

import pytest
import requests
import os

BASE_URL = os.environ.get('REACT_APP_BACKEND_URL', '').rstrip('/')

class TestTimelineEndpoint:
    """P3 Analytics Temporel - Timeline endpoint with days parameter"""
    
    def test_timeline_30_days(self):
        """GET /api/merchant-recommendations/stats/timeline?days=30"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/stats/timeline?days=30")
        assert response.status_code == 200
        data = response.json()
        
        # Verify required fields
        assert 'timeline' in data
        assert 'categories' in data
        assert 'source_breakdown' in data
        assert 'period_days' in data
        
        # Verify period_days is 30
        assert data['period_days'] == 30
        
        # Verify timeline structure
        assert isinstance(data['timeline'], list)
        if data['timeline']:
            assert 'day' in data['timeline'][0]
            assert 'interaction_type' in data['timeline'][0]
            assert 'cnt' in data['timeline'][0]
    
    def test_timeline_60_days(self):
        """GET /api/merchant-recommendations/stats/timeline?days=60"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/stats/timeline?days=60")
        assert response.status_code == 200
        data = response.json()
        
        assert data['period_days'] == 60
        assert 'timeline' in data
        assert 'categories' in data
        assert 'source_breakdown' in data
    
    def test_timeline_90_days(self):
        """GET /api/merchant-recommendations/stats/timeline?days=90"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/stats/timeline?days=90")
        assert response.status_code == 200
        data = response.json()
        
        assert data['period_days'] == 90
        assert 'timeline' in data
        assert 'categories' in data
        assert 'source_breakdown' in data
    
    def test_timeline_default_30_days(self):
        """GET /api/merchant-recommendations/stats/timeline - Default should be 30 days"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/stats/timeline")
        assert response.status_code == 200
        data = response.json()
        
        # Default should be 30 days
        assert data['period_days'] == 30
    
    def test_timeline_invalid_days_defaults_to_30(self):
        """GET /api/merchant-recommendations/stats/timeline?days=45 - Invalid days defaults to 30"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/stats/timeline?days=45")
        assert response.status_code == 200
        data = response.json()
        
        # Invalid days (not 30/60/90) should default to 30
        assert data['period_days'] == 30
    
    def test_timeline_source_breakdown_structure(self):
        """Verify source_breakdown contains A/B test sources"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/stats/timeline?days=30")
        assert response.status_code == 200
        data = response.json()
        
        # source_breakdown should be a list
        assert isinstance(data['source_breakdown'], list)
        
        # Check structure if data exists
        if data['source_breakdown']:
            item = data['source_breakdown'][0]
            assert 'source' in item
            assert 'interaction_type' in item
            assert 'cnt' in item


class TestABTestFramework:
    """P3 A/B Test Framework - ML Model vs Popularity"""
    
    def test_ab_test_results_endpoint(self):
        """GET /api/merchant-recommendations/ab-test/results?days=30"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/ab-test/results?days=30")
        assert response.status_code == 200
        data = response.json()
        
        # Verify required fields
        assert 'period_days' in data
        assert 'groups' in data
        assert 'uplift' in data
        assert 'breakdown' in data
        
        # Verify period_days
        assert data['period_days'] == 30
        
        # Verify groups structure
        assert isinstance(data['groups'], dict)
        
        # Verify uplift structure
        assert 'ctr_pct' in data['uplift']
        assert 'conversion_pct' in data['uplift']
        assert 'winner' in data['uplift']
    
    def test_ab_test_ml_group_client(self):
        """GET /api/merchant-recommendations/ab-test/118580?top_k=3 - ML group client"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/ab-test/118580?top_k=3")
        assert response.status_code == 200
        data = response.json()
        
        # Verify required fields
        assert 'client_id' in data
        assert 'ab_group' in data
        assert 'source' in data
        assert 'items' in data
        assert 'count' in data
        
        # Verify client_id
        assert data['client_id'] == 118580
        
        # Verify ab_group is ml_model (deterministic based on hash)
        assert data['ab_group'] == 'ml_model'
        
        # Verify items structure
        assert isinstance(data['items'], list)
        assert len(data['items']) <= 3
        
        if data['items']:
            item = data['items'][0]
            assert 'id' in item
            assert 'name' in item
            assert 'category' in item
            assert 'score' in item
    
    def test_ab_test_popularity_group_client(self):
        """GET /api/merchant-recommendations/ab-test/100?top_k=3 - Popularity group client"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/ab-test/100?top_k=3")
        assert response.status_code == 200
        data = response.json()
        
        # Verify required fields
        assert 'client_id' in data
        assert 'ab_group' in data
        assert 'source' in data
        assert 'items' in data
        
        # Verify client_id
        assert data['client_id'] == 100
        
        # Verify ab_group is popularity (deterministic based on hash)
        assert data['ab_group'] == 'popularity'
        
        # Verify source indicates popularity
        assert 'popularity' in data['source']
    
    def test_ab_test_deterministic_assignment(self):
        """A/B test should be deterministic - same client_id always gets same group"""
        # Call twice for same client
        response1 = requests.get(f"{BASE_URL}/api/merchant-recommendations/ab-test/118580?top_k=3")
        response2 = requests.get(f"{BASE_URL}/api/merchant-recommendations/ab-test/118580?top_k=3")
        
        assert response1.status_code == 200
        assert response2.status_code == 200
        
        data1 = response1.json()
        data2 = response2.json()
        
        # Same client should always get same group
        assert data1['ab_group'] == data2['ab_group']
        assert data1['ab_group'] == 'ml_model'
    
    def test_ab_test_response_includes_ab_group(self):
        """A/B test response must include ab_group field"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/ab-test/118580?top_k=3")
        assert response.status_code == 200
        data = response.json()
        
        # ab_group must be present
        assert 'ab_group' in data
        
        # ab_group must be one of the valid values
        assert data['ab_group'] in ['ml_model', 'popularity']
    
    def test_ab_test_tracking_impressions(self):
        """After calling ab-test endpoint, results should show impressions"""
        # First call ab-test endpoint to create an impression
        ab_response = requests.get(f"{BASE_URL}/api/merchant-recommendations/ab-test/999999?top_k=3")
        assert ab_response.status_code == 200
        
        # Then check results endpoint
        results_response = requests.get(f"{BASE_URL}/api/merchant-recommendations/ab-test/results?days=30")
        assert results_response.status_code == 200
        data = results_response.json()
        
        # Should have breakdown data
        assert 'breakdown' in data
        assert isinstance(data['breakdown'], list)


class TestWeeklyEmailPreview:
    """Weekly Email Preview endpoint"""
    
    def test_weekly_email_preview_returns_html(self):
        """GET /api/merchant-intelligence/weekly-email-preview - Returns HTML"""
        response = requests.get(f"{BASE_URL}/api/merchant-intelligence/weekly-email-preview", timeout=60)
        assert response.status_code == 200
        
        # Should return HTML content
        content_type = response.headers.get('content-type', '')
        assert 'text/html' in content_type
        
        # HTML should contain expected elements
        html = response.text
        assert '<!DOCTYPE html>' in html or '<html' in html
    
    def test_weekly_email_preview_contains_kpi_cards(self):
        """Weekly email preview should contain KPI cards"""
        response = requests.get(f"{BASE_URL}/api/merchant-intelligence/weekly-email-preview", timeout=60)
        assert response.status_code == 200
        
        html = response.text
        
        # Should contain KPI-related content
        assert 'Performants' in html or 'performant' in html.lower()
        assert 'surveiller' in html.lower() or 'A surveiller' in html
        assert 'booster' in html.lower() or 'A booster' in html
    
    def test_weekly_email_preview_contains_executive_summary(self):
        """Weekly email preview should contain executive summary"""
        response = requests.get(f"{BASE_URL}/api/merchant-intelligence/weekly-email-preview", timeout=60)
        assert response.status_code == 200
        
        html = response.text
        
        # Should contain executive summary section
        assert 'Resume executif' in html or 'executive' in html.lower() or 'Gemini AI' in html
    
    def test_weekly_email_preview_contains_actions(self):
        """Weekly email preview should contain commercial actions"""
        response = requests.get(f"{BASE_URL}/api/merchant-intelligence/weekly-email-preview", timeout=60)
        assert response.status_code == 200
        
        html = response.text
        
        # Should contain actions section
        assert 'Actions' in html or 'actions' in html.lower() or 'prioritaires' in html.lower()
    
    def test_weekly_email_preview_contains_top_performers(self):
        """Weekly email preview should contain top performers"""
        response = requests.get(f"{BASE_URL}/api/merchant-intelligence/weekly-email-preview", timeout=60)
        assert response.status_code == 200
        
        html = response.text
        
        # Should contain top performers section
        assert 'Top performeurs' in html or 'top' in html.lower()


class TestRegressionEndpoints:
    """Regression tests for existing endpoints"""
    
    def test_widget_still_works(self):
        """REGRESSION: GET /api/merchant-recommendations/widget/118580"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/widget/118580")
        assert response.status_code == 200
        data = response.json()
        
        assert 'client_id' in data
        assert 'source' in data
        assert 'items' in data
        assert data['client_id'] == 118580
    
    def test_intelligence_digest_still_works(self):
        """REGRESSION: GET /api/merchant-intelligence/digest"""
        response = requests.get(f"{BASE_URL}/api/merchant-intelligence/digest")
        assert response.status_code == 200
        data = response.json()
        
        assert data['success'] == True
        assert 'to_boost' in data
        assert 'to_watch' in data
        assert 'top_performers' in data
        assert 'stats' in data
    
    def test_ml_recommendations_still_works(self):
        """REGRESSION: POST /api/merchant-recommendations"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 118580}
        )
        assert response.status_code == 200
        data = response.json()
        
        assert data['success'] == True
        assert 'client_id' in data
        assert 'recommendations' in data
        assert data['client_id'] == 118580
    
    def test_health_still_works(self):
        """REGRESSION: GET /api/merchant-recommendations/health"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/health")
        assert response.status_code == 200
        data = response.json()
        
        assert 'status' in data
        assert 'model_loaded' in data
        assert data['status'] == 'ready'
        assert data['model_loaded'] == True
    
    def test_stats_still_works(self):
        """REGRESSION: GET /api/merchant-recommendations/stats"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/stats")
        assert response.status_code == 200
        data = response.json()
        
        assert 'total_interactions' in data
        assert 'last_7_days' in data
        assert 'active_merchants' in data
    
    def test_categories_still_works(self):
        """REGRESSION: GET /api/merchant-recommendations/categories"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/categories")
        assert response.status_code == 200
        data = response.json()
        
        assert 'categories' in data
        assert isinstance(data['categories'], list)


if __name__ == "__main__":
    pytest.main([__file__, "-v", "--tb=short"])
