"""
Iteration 26: Digital Scoring Module Tests
Tests for real web scraping digital presence scoring with Gemini AI audit.

Features tested:
- GET /api/merchant-intelligence/digital-scores - Batch scoring with real web scraping
- GET /api/merchant-intelligence/digital-score/{partner_id} - Single merchant scoring
- POST /api/merchant-intelligence/digital-audit/{partner_id} - Gemini AI audit
- GET /api/merchant-intelligence/digital-scores/html - HTML dashboard

Regression tests:
- GET /api/merchant-recommendations/health
- GET /api/merchant-intelligence/digest
- GET /api/merchant-recommendations/ab-test/results
- GET /api/merchant-intelligence/weekly-email-preview
- POST /api/merchant-recommendations
"""

import pytest
import requests
import os
import time

# Use the public URL from environment
BASE_URL = os.environ.get('REACT_APP_BACKEND_URL', 'https://perf-test-ooredoo.preview.emergentagent.com').rstrip('/')

# Test merchant: TRAVELTODO partner_id=1209
TEST_PARTNER_ID = 1209
TEST_CLIENT_ID = 118580


class TestDigitalScoringBatch:
    """Test batch digital scoring with real web scraping (30-60s timeout)"""
    
    def test_digital_scores_batch_limit_10(self):
        """GET /api/merchant-intelligence/digital-scores?limit=10 - Batch scoring"""
        response = requests.get(
            f"{BASE_URL}/api/merchant-intelligence/digital-scores",
            params={"limit": 10},
            timeout=120  # 2 minutes for batch scraping
        )
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text[:500]}"
        
        data = response.json()
        
        # Verify response structure
        assert data.get('success') == True, f"Expected success=True, got {data.get('success')}"
        assert 'count' in data, "Missing 'count' field"
        assert 'avg_score' in data, "Missing 'avg_score' field"
        assert 'distribution' in data, "Missing 'distribution' field"
        assert 'merchants' in data, "Missing 'merchants' field"
        
        # Verify distribution has expected levels
        distribution = data['distribution']
        assert 'EXCELLENT' in distribution, "Missing EXCELLENT in distribution"
        assert 'BON' in distribution, "Missing BON in distribution"
        assert 'MOYEN' in distribution, "Missing MOYEN in distribution"
        assert 'FAIBLE' in distribution, "Missing FAIBLE in distribution"
        
        # Verify merchants array structure
        merchants = data['merchants']
        assert isinstance(merchants, list), "merchants should be a list"
        assert len(merchants) > 0, "merchants list should not be empty"
        
        # Verify first merchant structure
        merchant = merchants[0]
        assert 'partner_id' in merchant, "Missing partner_id"
        assert 'partner_name' in merchant, "Missing partner_name"
        assert 'digital_score' in merchant, "Missing digital_score"
        assert 'level' in merchant, "Missing level"
        assert 'breakdown' in merchant, "Missing breakdown"
        assert 'scrape_data' in merchant, "Missing scrape_data"
        assert 'has_website' in merchant, "Missing has_website"
        assert 'has_facebook' in merchant, "Missing has_facebook"
        assert 'has_instagram' in merchant, "Missing has_instagram"
        
        # Verify digital_score is 0-100
        score = merchant['digital_score']
        assert 0 <= score <= 100, f"digital_score should be 0-100, got {score}"
        
        # Verify breakdown structure
        breakdown = merchant['breakdown']
        assert 'website' in breakdown, "Missing website in breakdown"
        assert 'facebook' in breakdown, "Missing facebook in breakdown"
        assert 'instagram' in breakdown, "Missing instagram in breakdown"
        assert 'google' in breakdown, "Missing google in breakdown"
        
        # Verify scrape_data structure
        scrape_data = merchant['scrape_data']
        assert 'website' in scrape_data, "Missing website in scrape_data"
        assert 'facebook' in scrape_data, "Missing facebook in scrape_data"
        assert 'instagram' in scrape_data, "Missing instagram in scrape_data"
        assert 'google' in scrape_data, "Missing google in scrape_data"
        
        print(f"PASS: Batch scoring returned {data['count']} merchants, avg_score={data['avg_score']}")
        print(f"Distribution: {distribution}")


class TestDigitalScoringSingle:
    """Test single merchant digital scoring (10-15s timeout)"""
    
    def test_digital_score_traveltodo(self):
        """GET /api/merchant-intelligence/digital-score/1209 - Score TRAVELTODO"""
        response = requests.get(
            f"{BASE_URL}/api/merchant-intelligence/digital-score/{TEST_PARTNER_ID}",
            timeout=30  # 30s for single merchant
        )
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text[:500]}"
        
        data = response.json()
        
        # Verify success
        assert data.get('success') == True, f"Expected success=True, got {data.get('success')}"
        
        # Verify partner info
        assert data.get('partner_id') == TEST_PARTNER_ID, f"Expected partner_id={TEST_PARTNER_ID}"
        assert 'partner_name' in data, "Missing partner_name"
        
        # Verify scoring fields
        assert 'digital_score' in data, "Missing digital_score"
        assert 'level' in data, "Missing level"
        assert 'breakdown' in data, "Missing breakdown"
        assert 'scrape_data' in data, "Missing scrape_data"
        
        # Verify digital_score is 0-100
        score = data['digital_score']
        assert 0 <= score <= 100, f"digital_score should be 0-100, got {score}"
        
        # Verify level is valid
        level = data['level']
        assert level in ['EXCELLENT', 'BON', 'MOYEN', 'FAIBLE'], f"Invalid level: {level}"
        
        # Verify breakdown
        breakdown = data['breakdown']
        assert breakdown['website'] <= 30, "website score should be <= 30"
        assert breakdown['facebook'] <= 25, "facebook score should be <= 25"
        assert breakdown['instagram'] <= 25, "instagram score should be <= 25"
        assert breakdown['google'] <= 20, "google score should be <= 20"
        
        # Verify scrape_data has real scraping results
        scrape_data = data['scrape_data']
        website_data = scrape_data.get('website', {})
        assert 'url' in website_data, "Missing url in website scrape_data"
        assert 'accessible' in website_data, "Missing accessible in website scrape_data"
        
        print(f"PASS: TRAVELTODO (partner_id={TEST_PARTNER_ID}) scored {score}/100 ({level})")
        print(f"Breakdown: website={breakdown['website']}/30, facebook={breakdown['facebook']}/25, instagram={breakdown['instagram']}/25, google={breakdown['google']}/20")
    
    def test_digital_score_invalid_partner(self):
        """GET /api/merchant-intelligence/digital-score/999999 - Invalid partner"""
        response = requests.get(
            f"{BASE_URL}/api/merchant-intelligence/digital-score/999999",
            timeout=30
        )
        
        assert response.status_code == 404, f"Expected 404 for invalid partner, got {response.status_code}"
        
        data = response.json()
        assert data.get('success') == False, "Expected success=False for invalid partner"
        
        print("PASS: Invalid partner returns 404")


class TestDigitalAudit:
    """Test Gemini AI digital audit (20-30s timeout)"""
    
    def test_digital_audit_traveltodo(self):
        """POST /api/merchant-intelligence/digital-audit/1209 - Gemini AI audit"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-intelligence/digital-audit/{TEST_PARTNER_ID}",
            timeout=60  # 60s for AI audit
        )
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text[:500]}"
        
        data = response.json()
        
        # Verify success
        assert data.get('success') == True, f"Expected success=True, got {data.get('success')}"
        
        # Verify partner info
        assert data.get('partner_id') == TEST_PARTNER_ID, f"Expected partner_id={TEST_PARTNER_ID}"
        assert 'partner_name' in data, "Missing partner_name"
        
        # Verify scoring fields
        assert 'digital_score' in data, "Missing digital_score"
        assert 'level' in data, "Missing level"
        
        # Verify audit object
        assert 'audit' in data, "Missing audit object"
        audit = data['audit']
        
        # Verify audit structure (Gemini AI response)
        assert 'diagnostic' in audit, "Missing diagnostic in audit"
        assert 'points_forts' in audit, "Missing points_forts in audit"
        assert 'points_faibles' in audit, "Missing points_faibles in audit"
        assert 'recommendations' in audit, "Missing recommendations in audit"
        assert 'score_potentiel' in audit, "Missing score_potentiel in audit"
        assert 'strategie_contenu' in audit, "Missing strategie_contenu in audit"
        
        # Verify recommendations structure
        recommendations = audit['recommendations']
        assert isinstance(recommendations, list), "recommendations should be a list"
        if len(recommendations) > 0:
            rec = recommendations[0]
            assert 'priority' in rec, "Missing priority in recommendation"
            assert 'canal' in rec, "Missing canal in recommendation"
            assert 'action' in rec, "Missing action in recommendation"
        
        # Verify strategie_contenu structure
        strategie = audit['strategie_contenu']
        assert 'frequence_publication' in strategie, "Missing frequence_publication"
        assert 'types_contenu' in strategie, "Missing types_contenu"
        assert 'ton_recommande' in strategie, "Missing ton_recommande"
        
        print(f"PASS: Gemini AI audit for TRAVELTODO completed")
        print(f"Diagnostic: {audit['diagnostic'][:100]}...")
        print(f"Score potentiel: {audit['score_potentiel']}")
        print(f"Recommendations count: {len(recommendations)}")


class TestDigitalScoresHTML:
    """Test HTML dashboard for digital scores"""
    
    def test_digital_scores_html_dashboard(self):
        """GET /api/merchant-intelligence/digital-scores/html?limit=10 - HTML dashboard"""
        response = requests.get(
            f"{BASE_URL}/api/merchant-intelligence/digital-scores/html",
            params={"limit": 10},
            timeout=120  # 2 minutes for batch scraping + HTML generation
        )
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text[:500]}"
        
        # Verify content type is HTML
        content_type = response.headers.get('content-type', '')
        assert 'text/html' in content_type, f"Expected text/html, got {content_type}"
        
        # Verify HTML content
        html = response.text
        assert '<html' in html.lower(), "Response should contain <html>"
        assert '<table' in html.lower(), "Response should contain table"
        
        # Verify KPI cards are present (at least one level should be present)
        has_level = any(level in html.upper() for level in ['EXCELLENT', 'BON', 'MOYEN', 'FAIBLE'])
        assert has_level, "Should contain at least one score level (EXCELLENT/BON/MOYEN/FAIBLE)"
        
        # Verify it contains merchant data
        assert 'digital' in html.lower(), "Should contain 'digital' keyword"
        assert 'score' in html.lower(), "Should contain 'score' keyword"
        
        print(f"PASS: HTML dashboard returned {len(html)} bytes")


class TestRegressionEndpoints:
    """Regression tests for existing endpoints"""
    
    def test_health_endpoint(self):
        """REGRESSION: GET /api/merchant-recommendations/health"""
        response = requests.get(
            f"{BASE_URL}/api/merchant-recommendations/health",
            timeout=10
        )
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        assert 'status' in data, "Missing status field"
        assert data['status'] in ['ready', 'fallback_only'], f"Unexpected status: {data['status']}"
        
        print(f"PASS: Health check - status={data['status']}")
    
    def test_intelligence_digest(self):
        """REGRESSION: GET /api/merchant-intelligence/digest"""
        response = requests.get(
            f"{BASE_URL}/api/merchant-intelligence/digest",
            timeout=30
        )
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        assert data.get('success') == True, f"Expected success=True"
        
        print(f"PASS: Intelligence digest works")
    
    def test_ab_test_results(self):
        """REGRESSION: GET /api/merchant-recommendations/ab-test/results"""
        response = requests.get(
            f"{BASE_URL}/api/merchant-recommendations/ab-test/results",
            timeout=30
        )
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        assert 'period_days' in data, "Missing period_days"
        assert 'groups' in data, "Missing groups"
        assert 'uplift' in data, "Missing uplift"
        
        print(f"PASS: A/B test results works")
    
    def test_weekly_email_preview(self):
        """REGRESSION: GET /api/merchant-intelligence/weekly-email-preview"""
        response = requests.get(
            f"{BASE_URL}/api/merchant-intelligence/weekly-email-preview",
            timeout=60  # AI generation takes time
        )
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        content_type = response.headers.get('content-type', '')
        assert 'text/html' in content_type, f"Expected text/html, got {content_type}"
        
        html = response.text
        assert '<html' in html.lower(), "Response should contain <html>"
        
        print(f"PASS: Weekly email preview works")
    
    def test_ml_recommendations(self):
        """REGRESSION: POST /api/merchant-recommendations with client_id"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": TEST_CLIENT_ID},
            timeout=30
        )
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        assert data.get('success') == True, f"Expected success=True"
        assert 'recommendations' in data, "Missing recommendations"
        assert data.get('client_id') == TEST_CLIENT_ID, f"Expected client_id={TEST_CLIENT_ID}"
        
        print(f"PASS: ML recommendations works - {data.get('count')} recommendations")


if __name__ == "__main__":
    pytest.main([__file__, "-v", "--tb=short"])
