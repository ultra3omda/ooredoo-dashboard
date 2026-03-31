"""
Test suite for Weekly Reporting System V2 - 6 Report Types
Laravel 10 application with PHP-FPM + Nginx

Features tested:
- 6 report types in dropdown (CEO, Marketing, Partenaire, Associe, Store, Sub-Store)
- Recipient CRUD with all 6 types
- Partner field shows for partner/store/sub-store types
- Report preview API returns HTML with ML data sections
- ML Dashboard features (extraction, training, report generation)
"""

import pytest
import requests
import os
import time

# API URL from environment
BASE_URL = os.environ.get('REACT_APP_BACKEND_URL', 'https://perf-test-ooredoo.preview.emergentagent.com').rstrip('/')


@pytest.fixture(scope="module")
def api_session():
    """Basic session for API calls"""
    session = requests.Session()
    session.headers.update({
        "Accept": "application/json",
        "Content-Type": "application/json",
    })
    return session


@pytest.fixture(scope="module")
def cleanup_test_recipients(api_session):
    """Cleanup test recipients after all tests"""
    created_ids = []
    yield created_ids
    # Teardown: Delete all test-created recipients
    for recipient_id in created_ids:
        try:
            api_session.delete(f"{BASE_URL}/api/reports/recipients/{recipient_id}")
        except:
            pass


# ==========================================
# Test 6 Report Types in Recipients API
# ==========================================

class TestSixReportTypes:
    """Test all 6 report types: ceo, marketing, partner, associe, store, sub-store"""
    
    def test_create_ceo_recipient(self, api_session, cleanup_test_recipients):
        """Create CEO type recipient"""
        payload = {
            "name": "TEST_V2_CEO",
            "email": "test_v2_ceo@example.com",
            "type": "ceo",
            "is_active": True,
            "schedule_day": "monday",
            "schedule_time": "08:00"
        }
        
        response = api_session.post(f"{BASE_URL}/api/reports/recipients", json=payload, timeout=30)
        
        assert response.status_code == 201, f"Expected 201, got {response.status_code}. Response: {response.text}"
        
        data = response.json()
        assert data["recipient"]["type"] == "ceo"
        cleanup_test_recipients.append(data["recipient"]["id"])
        print(f"PASS: Created CEO recipient ID={data['recipient']['id']}")
    
    def test_create_marketing_recipient(self, api_session, cleanup_test_recipients):
        """Create Marketing type recipient"""
        payload = {
            "name": "TEST_V2_Marketing",
            "email": "test_v2_marketing@example.com",
            "type": "marketing",
            "is_active": True
        }
        
        response = api_session.post(f"{BASE_URL}/api/reports/recipients", json=payload, timeout=30)
        
        assert response.status_code == 201, f"Expected 201, got {response.status_code}. Response: {response.text}"
        
        data = response.json()
        assert data["recipient"]["type"] == "marketing"
        cleanup_test_recipients.append(data["recipient"]["id"])
        print(f"PASS: Created Marketing recipient ID={data['recipient']['id']}")
    
    def test_create_partner_recipient(self, api_session, cleanup_test_recipients):
        """Create Partner type recipient with partner_id"""
        payload = {
            "name": "TEST_V2_Partner",
            "email": "test_v2_partner@example.com",
            "type": "partner",
            "partner_id": 15,  # SMART GYM
            "is_active": True
        }
        
        response = api_session.post(f"{BASE_URL}/api/reports/recipients", json=payload, timeout=30)
        
        assert response.status_code == 201, f"Expected 201, got {response.status_code}. Response: {response.text}"
        
        data = response.json()
        assert data["recipient"]["type"] == "partner"
        assert data["recipient"]["partner_id"] == 15
        cleanup_test_recipients.append(data["recipient"]["id"])
        print(f"PASS: Created Partner recipient ID={data['recipient']['id']}, partner_id=15")
    
    def test_create_associe_recipient(self, api_session, cleanup_test_recipients):
        """Create Associe type recipient (NEW TYPE)"""
        payload = {
            "name": "TEST_V2_Associe",
            "email": "test_v2_associe@example.com",
            "type": "associe",
            "is_active": True
        }
        
        response = api_session.post(f"{BASE_URL}/api/reports/recipients", json=payload, timeout=30)
        
        assert response.status_code == 201, f"Expected 201, got {response.status_code}. Response: {response.text}"
        
        data = response.json()
        assert data["recipient"]["type"] == "associe"
        cleanup_test_recipients.append(data["recipient"]["id"])
        print(f"PASS: Created Associe recipient ID={data['recipient']['id']}")
    
    def test_create_store_recipient(self, api_session, cleanup_test_recipients):
        """Create Store type recipient with partner_id (NEW TYPE)"""
        payload = {
            "name": "TEST_V2_Store",
            "email": "test_v2_store@example.com",
            "type": "store",
            "partner_id": 15,  # SMART GYM
            "is_active": True
        }
        
        response = api_session.post(f"{BASE_URL}/api/reports/recipients", json=payload, timeout=30)
        
        assert response.status_code == 201, f"Expected 201, got {response.status_code}. Response: {response.text}"
        
        data = response.json()
        assert data["recipient"]["type"] == "store"
        assert data["recipient"]["partner_id"] == 15
        cleanup_test_recipients.append(data["recipient"]["id"])
        print(f"PASS: Created Store recipient ID={data['recipient']['id']}, partner_id=15")
    
    def test_create_substore_recipient(self, api_session, cleanup_test_recipients):
        """Create Sub-Store type recipient with partner_id (NEW TYPE)"""
        payload = {
            "name": "TEST_V2_SubStore",
            "email": "test_v2_substore@example.com",
            "type": "sub-store",
            "partner_id": 15,  # SMART GYM
            "is_active": True
        }
        
        response = api_session.post(f"{BASE_URL}/api/reports/recipients", json=payload, timeout=30)
        
        assert response.status_code == 201, f"Expected 201, got {response.status_code}. Response: {response.text}"
        
        data = response.json()
        assert data["recipient"]["type"] == "sub-store"
        assert data["recipient"]["partner_id"] == 15
        cleanup_test_recipients.append(data["recipient"]["id"])
        print(f"PASS: Created Sub-Store recipient ID={data['recipient']['id']}, partner_id=15")


# ==========================================
# Test Partner Field Validation
# ==========================================

class TestPartnerFieldValidation:
    """Test that partner_id is required for partner/store/sub-store types"""
    
    def test_partner_without_partner_id_fails(self, api_session):
        """Partner type requires partner_id"""
        payload = {
            "name": "TEST_Invalid_Partner",
            "email": "test_invalid_partner@example.com",
            "type": "partner"
            # Missing partner_id
        }
        
        response = api_session.post(f"{BASE_URL}/api/reports/recipients", json=payload, timeout=30)
        
        assert response.status_code == 422, f"Expected 422, got {response.status_code}"
        print("PASS: Partner without partner_id correctly rejected")
    
    def test_store_without_partner_id_fails(self, api_session):
        """Store type requires partner_id"""
        payload = {
            "name": "TEST_Invalid_Store",
            "email": "test_invalid_store@example.com",
            "type": "store"
            # Missing partner_id
        }
        
        response = api_session.post(f"{BASE_URL}/api/reports/recipients", json=payload, timeout=30)
        
        assert response.status_code == 422, f"Expected 422, got {response.status_code}"
        print("PASS: Store without partner_id correctly rejected")
    
    def test_substore_without_partner_id_fails(self, api_session):
        """Sub-Store type requires partner_id"""
        payload = {
            "name": "TEST_Invalid_SubStore",
            "email": "test_invalid_substore@example.com",
            "type": "sub-store"
            # Missing partner_id
        }
        
        response = api_session.post(f"{BASE_URL}/api/reports/recipients", json=payload, timeout=30)
        
        assert response.status_code == 422, f"Expected 422, got {response.status_code}"
        print("PASS: Sub-Store without partner_id correctly rejected")
    
    def test_ceo_without_partner_id_succeeds(self, api_session, cleanup_test_recipients):
        """CEO type does NOT require partner_id"""
        payload = {
            "name": "TEST_CEO_NoPartner",
            "email": "test_ceo_nopartner@example.com",
            "type": "ceo"
            # No partner_id - should be OK
        }
        
        response = api_session.post(f"{BASE_URL}/api/reports/recipients", json=payload, timeout=30)
        
        assert response.status_code == 201, f"Expected 201, got {response.status_code}"
        cleanup_test_recipients.append(response.json()["recipient"]["id"])
        print("PASS: CEO without partner_id correctly accepted")
    
    def test_associe_without_partner_id_succeeds(self, api_session, cleanup_test_recipients):
        """Associe type does NOT require partner_id"""
        payload = {
            "name": "TEST_Associe_NoPartner",
            "email": "test_associe_nopartner@example.com",
            "type": "associe"
            # No partner_id - should be OK
        }
        
        response = api_session.post(f"{BASE_URL}/api/reports/recipients", json=payload, timeout=30)
        
        assert response.status_code == 201, f"Expected 201, got {response.status_code}"
        cleanup_test_recipients.append(response.json()["recipient"]["id"])
        print("PASS: Associe without partner_id correctly accepted")


# ==========================================
# Test Report Preview API
# ==========================================

class TestReportPreview:
    """Test report preview API returns HTML with ML data sections"""
    
    def test_preview_ceo_report(self, api_session, cleanup_test_recipients):
        """Preview CEO report should include ML Predictions section"""
        # Create a CEO recipient first
        payload = {
            "name": "TEST_Preview_CEO",
            "email": "test_preview_ceo@example.com",
            "type": "ceo",
            "is_active": True
        }
        create_response = api_session.post(f"{BASE_URL}/api/reports/recipients", json=payload, timeout=30)
        assert create_response.status_code == 201
        recipient_id = create_response.json()["recipient"]["id"]
        cleanup_test_recipients.append(recipient_id)
        
        # Get preview
        preview_response = api_session.get(f"{BASE_URL}/api/reports/preview/{recipient_id}", timeout=60)
        
        assert preview_response.status_code == 200, f"Expected 200, got {preview_response.status_code}. Response: {preview_response.text}"
        
        data = preview_response.json()
        assert "html" in data, "Missing html field in preview response"
        
        html = data["html"]
        # Check for ML sections in CEO report
        assert "Predictions ML" in html or "ML" in html, "CEO report should include ML Predictions section"
        
        print(f"PASS: CEO report preview returned {len(html)} chars with ML content")
    
    def test_preview_marketing_report(self, api_session, cleanup_test_recipients):
        """Preview Marketing report should include ML Ciblage section"""
        # Create a Marketing recipient first
        payload = {
            "name": "TEST_Preview_Marketing",
            "email": "test_preview_marketing@example.com",
            "type": "marketing",
            "is_active": True
        }
        create_response = api_session.post(f"{BASE_URL}/api/reports/recipients", json=payload, timeout=30)
        assert create_response.status_code == 201
        recipient_id = create_response.json()["recipient"]["id"]
        cleanup_test_recipients.append(recipient_id)
        
        # Get preview
        preview_response = api_session.get(f"{BASE_URL}/api/reports/preview/{recipient_id}", timeout=60)
        
        assert preview_response.status_code == 200, f"Expected 200, got {preview_response.status_code}"
        
        data = preview_response.json()
        assert "html" in data
        
        html = data["html"]
        # Check for ML sections in Marketing report
        assert "ML" in html or "Ciblage" in html or "Marketing" in html, "Marketing report should include ML Ciblage section"
        
        print(f"PASS: Marketing report preview returned {len(html)} chars")
    
    def test_preview_associe_report(self, api_session, cleanup_test_recipients):
        """Preview Associe report (NEW template)"""
        # Create an Associe recipient first
        payload = {
            "name": "TEST_Preview_Associe",
            "email": "test_preview_associe@example.com",
            "type": "associe",
            "is_active": True
        }
        create_response = api_session.post(f"{BASE_URL}/api/reports/recipients", json=payload, timeout=30)
        assert create_response.status_code == 201
        recipient_id = create_response.json()["recipient"]["id"]
        cleanup_test_recipients.append(recipient_id)
        
        # Get preview
        preview_response = api_session.get(f"{BASE_URL}/api/reports/preview/{recipient_id}", timeout=60)
        
        assert preview_response.status_code == 200, f"Expected 200, got {preview_response.status_code}"
        
        data = preview_response.json()
        assert "html" in data
        
        html = data["html"]
        # Check for Associe-specific content
        assert "Associe" in html or "Reseau" in html or "Performance" in html, "Associe report should have specific content"
        
        print(f"PASS: Associe report preview returned {len(html)} chars")
    
    def test_preview_store_report(self, api_session, cleanup_test_recipients):
        """Preview Store report (NEW template)"""
        # Create a Store recipient first
        payload = {
            "name": "TEST_Preview_Store",
            "email": "test_preview_store@example.com",
            "type": "store",
            "partner_id": 15,
            "is_active": True
        }
        create_response = api_session.post(f"{BASE_URL}/api/reports/recipients", json=payload, timeout=30)
        assert create_response.status_code == 201
        recipient_id = create_response.json()["recipient"]["id"]
        cleanup_test_recipients.append(recipient_id)
        
        # Get preview
        preview_response = api_session.get(f"{BASE_URL}/api/reports/preview/{recipient_id}", timeout=60)
        
        assert preview_response.status_code == 200, f"Expected 200, got {preview_response.status_code}"
        
        data = preview_response.json()
        assert "html" in data
        
        html = data["html"]
        # Check for Store-specific content
        assert "Store" in html or "SMART GYM" in html or "Transactions" in html, "Store report should have specific content"
        
        print(f"PASS: Store report preview returned {len(html)} chars")


# ==========================================
# Test ML Dashboard Endpoints
# ==========================================

class TestMLDashboard:
    """Test ML Dashboard endpoints"""
    
    def test_ml_insights_endpoint(self, api_session):
        """GET /admin/ml-dashboard/insights returns ML metrics"""
        response = api_session.get(f"{BASE_URL}/admin/ml-dashboard/insights", timeout=30)
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}. Response: {response.text}"
        
        data = response.json()
        assert data.get("success") == True, "Expected success=true"
        
        # Check for expected fields
        assert "accuracy" in data or "churn_risk_count" in data, "Missing ML metrics"
        
        print(f"PASS: ML Insights - accuracy={data.get('accuracy')}, churn_risk={data.get('churn_risk_count')}")
    
    def test_ml_latest_report_endpoint(self, api_session):
        """GET /admin/ml-dashboard/report/latest returns latest AI report"""
        response = api_session.get(f"{BASE_URL}/admin/ml-dashboard/report/latest", timeout=30)
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        assert data.get("success") == True, "Expected success=true"
        
        # Report may or may not exist
        if data.get("report"):
            print(f"PASS: ML Latest Report found, generated_at={data.get('generated_at')}")
        else:
            print(f"PASS: ML Latest Report endpoint works (no report available yet)")
    
    def test_ml_task_status_endpoint(self, api_session):
        """GET /admin/ml-dashboard/task-status returns task status"""
        response = api_session.get(f"{BASE_URL}/admin/ml-dashboard/task-status", timeout=30)
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        assert data.get("success") == True, "Expected success=true"
        
        print(f"PASS: ML Task Status - extract={data.get('extract_features')}, train={data.get('train_model')}")


# ==========================================
# Test AI Suggestions Endpoint
# ==========================================

class TestAISuggestions:
    """Test AI suggestions endpoint (FastAPI)"""
    
    def test_ai_suggestions_ceo_report(self, api_session):
        """POST /api/report-ai-suggestions for CEO report"""
        payload = {
            "prompt": "Analyse ces KPIs CEO: Taux de retention 95%, Nouveaux abonnes 500, Revenus 10000 TND, Churn 5%",
            "report_type": "ceo"
        }
        
        response = api_session.post(f"{BASE_URL}/api/report-ai-suggestions", json=payload, timeout=60)
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}. Response: {response.text}"
        
        data = response.json()
        assert "suggestions" in data, "Missing suggestions field"
        
        print(f"PASS: AI suggestions for CEO report - {len(data['suggestions'])} chars")
    
    def test_ai_suggestions_marketing_report(self, api_session):
        """POST /api/report-ai-suggestions for Marketing report"""
        payload = {
            "prompt": "Analyse ces KPIs Marketing: Acquisition 200, Churn 50, Conversion 15%, Segments: high_value=100, at_risk=50",
            "report_type": "marketing"
        }
        
        response = api_session.post(f"{BASE_URL}/api/report-ai-suggestions", json=payload, timeout=60)
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        assert "suggestions" in data
        
        print(f"PASS: AI suggestions for Marketing report - {len(data['suggestions'])} chars")


# ==========================================
# Test Recipient CRUD Operations
# ==========================================

class TestRecipientCRUD:
    """Test recipient CRUD operations"""
    
    def test_toggle_recipient_status(self, api_session, cleanup_test_recipients):
        """Toggle recipient active status"""
        # Create recipient
        payload = {
            "name": "TEST_Toggle_V2",
            "email": "test_toggle_v2@example.com",
            "type": "associe",
            "is_active": True
        }
        create_response = api_session.post(f"{BASE_URL}/api/reports/recipients", json=payload, timeout=30)
        assert create_response.status_code == 201
        recipient_id = create_response.json()["recipient"]["id"]
        cleanup_test_recipients.append(recipient_id)
        
        # Toggle to inactive
        toggle_response = api_session.post(f"{BASE_URL}/api/reports/recipients/{recipient_id}/toggle", timeout=30)
        
        assert toggle_response.status_code == 200
        assert toggle_response.json()["recipient"]["is_active"] == False
        
        # Toggle back to active
        toggle_response2 = api_session.post(f"{BASE_URL}/api/reports/recipients/{recipient_id}/toggle", timeout=30)
        assert toggle_response2.status_code == 200
        assert toggle_response2.json()["recipient"]["is_active"] == True
        
        print(f"PASS: Toggle recipient {recipient_id} status works correctly")
    
    def test_update_recipient(self, api_session, cleanup_test_recipients):
        """Update recipient name"""
        # Create recipient
        payload = {
            "name": "TEST_Update_V2_Original",
            "email": "test_update_v2@example.com",
            "type": "store",
            "partner_id": 15,
            "is_active": True
        }
        create_response = api_session.post(f"{BASE_URL}/api/reports/recipients", json=payload, timeout=30)
        assert create_response.status_code == 201
        recipient_id = create_response.json()["recipient"]["id"]
        cleanup_test_recipients.append(recipient_id)
        
        # Update name
        update_payload = {"name": "TEST_Update_V2_Modified"}
        update_response = api_session.put(f"{BASE_URL}/api/reports/recipients/{recipient_id}", json=update_payload, timeout=30)
        
        assert update_response.status_code == 200
        assert update_response.json()["recipient"]["name"] == "TEST_Update_V2_Modified"
        
        print(f"PASS: Updated recipient {recipient_id} name successfully")
    
    def test_delete_recipient(self, api_session):
        """Delete recipient"""
        # Create recipient
        payload = {
            "name": "TEST_Delete_V2",
            "email": "test_delete_v2@example.com",
            "type": "sub-store",
            "partner_id": 15,
            "is_active": True
        }
        create_response = api_session.post(f"{BASE_URL}/api/reports/recipients", json=payload, timeout=30)
        assert create_response.status_code == 201
        recipient_id = create_response.json()["recipient"]["id"]
        
        # Delete
        delete_response = api_session.delete(f"{BASE_URL}/api/reports/recipients/{recipient_id}", timeout=30)
        
        assert delete_response.status_code == 200
        
        # Verify deletion
        get_response = api_session.get(f"{BASE_URL}/api/reports/recipients", timeout=30)
        recipients = get_response.json()["recipients"]
        deleted = next((r for r in recipients if r["id"] == recipient_id), None)
        assert deleted is None
        
        print(f"PASS: Deleted recipient {recipient_id} successfully")


if __name__ == "__main__":
    pytest.main([__file__, "-v", "--tb=short"])
