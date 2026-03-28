"""
Test suite for Weekly Reporting System API
Laravel 10 application with PHP-FPM + Nginx

Endpoints tested:
- GET /api/reports/recipients - List all recipients
- POST /api/reports/recipients - Create recipient (ceo, marketing, partner types)
- PUT /api/reports/recipients/{id} - Update recipient
- DELETE /api/reports/recipients/{id} - Delete recipient
- POST /api/reports/recipients/{id}/toggle - Toggle active status
- GET /api/reports/logs - Get report send history
- GET /api/reports/partners?q=smart - Search partners
- GET /api/reports/schedule - Get schedule config
- POST /api/report-ai-suggestions - AI suggestions endpoint (FastAPI)
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
# GET /api/reports/recipients - List Recipients
# ==========================================

class TestGetRecipients:
    """GET /api/reports/recipients endpoint tests"""
    
    def test_get_recipients_returns_200(self, api_session):
        """GET /api/reports/recipients returns 200 with recipients array"""
        response = api_session.get(f"{BASE_URL}/api/reports/recipients", timeout=30)
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        assert "recipients" in data, "Missing recipients field"
        assert isinstance(data["recipients"], list), "recipients should be a list"
        
        print(f"GET /api/reports/recipients: {len(data['recipients'])} recipients found")


# ==========================================
# POST /api/reports/recipients - Create Recipient
# ==========================================

class TestCreateRecipient:
    """POST /api/reports/recipients endpoint tests"""
    
    def test_create_ceo_recipient(self, api_session, cleanup_test_recipients):
        """Create CEO type recipient"""
        payload = {
            "name": "TEST_CEO_User",
            "email": "test_ceo@example.com",
            "type": "ceo",
            "is_active": True,
            "schedule_day": "monday",
            "schedule_time": "08:00"
        }
        
        response = api_session.post(f"{BASE_URL}/api/reports/recipients", json=payload, timeout=30)
        
        assert response.status_code == 201, f"Expected 201, got {response.status_code}. Response: {response.text}"
        
        data = response.json()
        assert "recipient" in data, "Missing recipient field"
        assert data["recipient"]["name"] == "TEST_CEO_User"
        assert data["recipient"]["email"] == "test_ceo@example.com"
        assert data["recipient"]["type"] == "ceo"
        
        # Store ID for cleanup
        cleanup_test_recipients.append(data["recipient"]["id"])
        
        print(f"Created CEO recipient: ID={data['recipient']['id']}")
    
    def test_create_marketing_recipient(self, api_session, cleanup_test_recipients):
        """Create Marketing type recipient"""
        payload = {
            "name": "TEST_Marketing_User",
            "email": "test_marketing@example.com",
            "type": "marketing",
            "is_active": True
        }
        
        response = api_session.post(f"{BASE_URL}/api/reports/recipients", json=payload, timeout=30)
        
        assert response.status_code == 201, f"Expected 201, got {response.status_code}. Response: {response.text}"
        
        data = response.json()
        assert data["recipient"]["type"] == "marketing"
        
        cleanup_test_recipients.append(data["recipient"]["id"])
        print(f"Created Marketing recipient: ID={data['recipient']['id']}")
    
    def test_create_partner_recipient_with_partner_id(self, api_session, cleanup_test_recipients):
        """Create Partner type recipient with partner_id=15 (SMART GYM)"""
        payload = {
            "name": "TEST_Partner_User",
            "email": "test_partner@example.com",
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
        print(f"Created Partner recipient: ID={data['recipient']['id']}, partner_id=15")
    
    def test_create_partner_without_partner_id_fails(self, api_session):
        """Partner type requires partner_id - should fail validation"""
        payload = {
            "name": "TEST_Invalid_Partner",
            "email": "test_invalid@example.com",
            "type": "partner"
            # Missing partner_id
        }
        
        response = api_session.post(f"{BASE_URL}/api/reports/recipients", json=payload, timeout=30)
        
        assert response.status_code == 422, f"Expected 422 validation error, got {response.status_code}"
        print("Validation correctly rejects partner without partner_id")
    
    def test_create_duplicate_recipient_fails(self, api_session, cleanup_test_recipients):
        """Duplicate email+type combination should fail"""
        payload = {
            "name": "TEST_Duplicate_User",
            "email": "test_duplicate@example.com",
            "type": "ceo",
            "is_active": True
        }
        
        # Create first
        response1 = api_session.post(f"{BASE_URL}/api/reports/recipients", json=payload, timeout=30)
        assert response1.status_code == 201
        cleanup_test_recipients.append(response1.json()["recipient"]["id"])
        
        # Try to create duplicate
        response2 = api_session.post(f"{BASE_URL}/api/reports/recipients", json=payload, timeout=30)
        assert response2.status_code == 422, f"Expected 422 for duplicate, got {response2.status_code}"
        
        print("Duplicate recipient correctly rejected")
    
    def test_create_recipient_invalid_email_fails(self, api_session):
        """Invalid email format should fail validation"""
        payload = {
            "name": "TEST_Invalid_Email",
            "email": "not-an-email",
            "type": "ceo"
        }
        
        response = api_session.post(f"{BASE_URL}/api/reports/recipients", json=payload, timeout=30)
        
        assert response.status_code == 422, f"Expected 422 for invalid email, got {response.status_code}"
        print("Invalid email correctly rejected")
    
    def test_create_recipient_invalid_type_fails(self, api_session):
        """Invalid type should fail validation"""
        payload = {
            "name": "TEST_Invalid_Type",
            "email": "test_type@example.com",
            "type": "invalid_type"
        }
        
        response = api_session.post(f"{BASE_URL}/api/reports/recipients", json=payload, timeout=30)
        
        assert response.status_code == 422, f"Expected 422 for invalid type, got {response.status_code}"
        print("Invalid type correctly rejected")


# ==========================================
# PUT /api/reports/recipients/{id} - Update Recipient
# ==========================================

class TestUpdateRecipient:
    """PUT /api/reports/recipients/{id} endpoint tests"""
    
    def test_update_recipient_name(self, api_session, cleanup_test_recipients):
        """Update recipient name"""
        # Create a recipient first
        create_payload = {
            "name": "TEST_Update_Original",
            "email": "test_update@example.com",
            "type": "ceo",
            "is_active": True
        }
        create_response = api_session.post(f"{BASE_URL}/api/reports/recipients", json=create_payload, timeout=30)
        assert create_response.status_code == 201
        recipient_id = create_response.json()["recipient"]["id"]
        cleanup_test_recipients.append(recipient_id)
        
        # Update the name
        update_payload = {"name": "TEST_Update_Modified"}
        update_response = api_session.put(f"{BASE_URL}/api/reports/recipients/{recipient_id}", json=update_payload, timeout=30)
        
        assert update_response.status_code == 200, f"Expected 200, got {update_response.status_code}"
        
        data = update_response.json()
        assert data["recipient"]["name"] == "TEST_Update_Modified"
        
        # Verify with GET
        get_response = api_session.get(f"{BASE_URL}/api/reports/recipients", timeout=30)
        recipients = get_response.json()["recipients"]
        updated = next((r for r in recipients if r["id"] == recipient_id), None)
        assert updated is not None
        assert updated["name"] == "TEST_Update_Modified"
        
        print(f"Updated recipient {recipient_id} name successfully")
    
    def test_update_nonexistent_recipient_fails(self, api_session):
        """Update non-existent recipient should return 404"""
        update_payload = {"name": "TEST_Nonexistent"}
        response = api_session.put(f"{BASE_URL}/api/reports/recipients/99999", json=update_payload, timeout=30)
        
        assert response.status_code == 404, f"Expected 404, got {response.status_code}"
        print("Non-existent recipient update correctly returns 404")


# ==========================================
# DELETE /api/reports/recipients/{id} - Delete Recipient
# ==========================================

class TestDeleteRecipient:
    """DELETE /api/reports/recipients/{id} endpoint tests"""
    
    def test_delete_recipient(self, api_session):
        """Delete recipient and verify removal"""
        # Create a recipient first
        create_payload = {
            "name": "TEST_Delete_User",
            "email": "test_delete@example.com",
            "type": "marketing",
            "is_active": True
        }
        create_response = api_session.post(f"{BASE_URL}/api/reports/recipients", json=create_payload, timeout=30)
        assert create_response.status_code == 201
        recipient_id = create_response.json()["recipient"]["id"]
        
        # Delete the recipient
        delete_response = api_session.delete(f"{BASE_URL}/api/reports/recipients/{recipient_id}", timeout=30)
        
        assert delete_response.status_code == 200, f"Expected 200, got {delete_response.status_code}"
        
        # Verify deletion with GET
        get_response = api_session.get(f"{BASE_URL}/api/reports/recipients", timeout=30)
        recipients = get_response.json()["recipients"]
        deleted = next((r for r in recipients if r["id"] == recipient_id), None)
        assert deleted is None, "Recipient should be deleted"
        
        print(f"Deleted recipient {recipient_id} successfully")
    
    def test_delete_nonexistent_recipient_fails(self, api_session):
        """Delete non-existent recipient should return 404"""
        response = api_session.delete(f"{BASE_URL}/api/reports/recipients/99999", timeout=30)
        
        assert response.status_code == 404, f"Expected 404, got {response.status_code}"
        print("Non-existent recipient delete correctly returns 404")


# ==========================================
# POST /api/reports/recipients/{id}/toggle - Toggle Active Status
# ==========================================

class TestToggleRecipient:
    """POST /api/reports/recipients/{id}/toggle endpoint tests"""
    
    def test_toggle_recipient_status(self, api_session, cleanup_test_recipients):
        """Toggle recipient active status"""
        # Create an active recipient
        create_payload = {
            "name": "TEST_Toggle_User",
            "email": "test_toggle@example.com",
            "type": "ceo",
            "is_active": True
        }
        create_response = api_session.post(f"{BASE_URL}/api/reports/recipients", json=create_payload, timeout=30)
        assert create_response.status_code == 201
        recipient_id = create_response.json()["recipient"]["id"]
        cleanup_test_recipients.append(recipient_id)
        
        # Toggle to inactive
        toggle_response = api_session.post(f"{BASE_URL}/api/reports/recipients/{recipient_id}/toggle", timeout=30)
        
        assert toggle_response.status_code == 200, f"Expected 200, got {toggle_response.status_code}"
        
        data = toggle_response.json()
        assert data["recipient"]["is_active"] == False, "Should be inactive after toggle"
        
        # Toggle back to active
        toggle_response2 = api_session.post(f"{BASE_URL}/api/reports/recipients/{recipient_id}/toggle", timeout=30)
        assert toggle_response2.status_code == 200
        assert toggle_response2.json()["recipient"]["is_active"] == True, "Should be active after second toggle"
        
        print(f"Toggle recipient {recipient_id} status works correctly")


# ==========================================
# GET /api/reports/logs - Get Report Logs
# ==========================================

class TestGetLogs:
    """GET /api/reports/logs endpoint tests"""
    
    def test_get_logs_returns_200(self, api_session):
        """GET /api/reports/logs returns 200 with logs array"""
        response = api_session.get(f"{BASE_URL}/api/reports/logs", timeout=30)
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        assert "logs" in data, "Missing logs field"
        assert isinstance(data["logs"], list), "logs should be a list"
        
        print(f"GET /api/reports/logs: {len(data['logs'])} logs found")


# ==========================================
# GET /api/reports/partners - Search Partners
# ==========================================

class TestGetPartners:
    """GET /api/reports/partners endpoint tests"""
    
    def test_get_partners_returns_200(self, api_session):
        """GET /api/reports/partners returns 200 with partners array"""
        response = api_session.get(f"{BASE_URL}/api/reports/partners", timeout=30)
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        assert "partners" in data, "Missing partners field"
        assert isinstance(data["partners"], list), "partners should be a list"
        
        print(f"GET /api/reports/partners: {len(data['partners'])} partners found")
    
    def test_search_partners_with_query(self, api_session):
        """GET /api/reports/partners?q=smart returns filtered results"""
        response = api_session.get(f"{BASE_URL}/api/reports/partners", params={"q": "smart"}, timeout=30)
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        assert "partners" in data
        
        # All results should contain "smart" in name (case-insensitive)
        for partner in data["partners"]:
            assert "smart" in partner["partner_name"].lower(), f"Partner {partner['partner_name']} doesn't match 'smart'"
        
        # Should find SMART GYM (partner_id=15)
        smart_gym = next((p for p in data["partners"] if p["partner_id"] == 15), None)
        assert smart_gym is not None, "SMART GYM (partner_id=15) should be in results"
        
        print(f"Partner search 'smart': {len(data['partners'])} results, SMART GYM found")


# ==========================================
# GET /api/reports/schedule - Get Schedule Config
# ==========================================

class TestGetSchedule:
    """GET /api/reports/schedule endpoint tests"""
    
    def test_get_schedule_returns_200(self, api_session):
        """GET /api/reports/schedule returns 200 with config"""
        response = api_session.get(f"{BASE_URL}/api/reports/schedule", timeout=30)
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        assert "global_day" in data, "Missing global_day field"
        assert "global_time" in data, "Missing global_time field"
        assert "recipients_count" in data, "Missing recipients_count field"
        
        # Validate day is valid
        valid_days = ["monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"]
        assert data["global_day"] in valid_days, f"Invalid day: {data['global_day']}"
        
        print(f"Schedule config: day={data['global_day']}, time={data['global_time']}, active_recipients={data['recipients_count']}")


# ==========================================
# POST /api/report-ai-suggestions - AI Suggestions (FastAPI)
# ==========================================

class TestAISuggestions:
    """POST /api/report-ai-suggestions endpoint tests (FastAPI proxy)"""
    
    def test_ai_suggestions_returns_200(self, api_session):
        """POST /api/report-ai-suggestions returns 200 with suggestions"""
        payload = {
            "prompt": "Analyse ces KPIs: Taux de rétention 95%, Nouveaux abonnés 500, Revenus 10000 TND",
            "report_type": "ceo"
        }
        
        response = api_session.post(f"{BASE_URL}/api/report-ai-suggestions", json=payload, timeout=60)
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}. Response: {response.text}"
        
        data = response.json()
        assert "suggestions" in data, "Missing suggestions field"
        
        # Suggestions should be non-empty string
        assert isinstance(data["suggestions"], str), "suggestions should be a string"
        
        print(f"AI suggestions received: {len(data['suggestions'])} chars")
    
    def test_ai_suggestions_empty_prompt(self, api_session):
        """POST /api/report-ai-suggestions with empty prompt returns empty suggestions"""
        payload = {
            "prompt": "",
            "report_type": "marketing"
        }
        
        response = api_session.post(f"{BASE_URL}/api/report-ai-suggestions", json=payload, timeout=30)
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        assert "suggestions" in data
        assert data["suggestions"] == "", "Empty prompt should return empty suggestions"
        
        print("Empty prompt correctly returns empty suggestions")


# ==========================================
# RGPD: Partner Report Data Isolation
# ==========================================

class TestRGPDPartnerIsolation:
    """RGPD compliance: Partner reports should only contain partner-specific data"""
    
    def test_partner_recipient_has_partner_id(self, api_session, cleanup_test_recipients):
        """Partner type recipient must have partner_id for RGPD filtering"""
        # Create partner recipient
        payload = {
            "name": "TEST_RGPD_Partner",
            "email": "test_rgpd@example.com",
            "type": "partner",
            "partner_id": 15,  # SMART GYM
            "is_active": True
        }
        
        response = api_session.post(f"{BASE_URL}/api/reports/recipients", json=payload, timeout=30)
        assert response.status_code == 201
        
        recipient = response.json()["recipient"]
        cleanup_test_recipients.append(recipient["id"])
        
        # Verify partner_id is stored
        assert recipient["partner_id"] == 15, "partner_id should be stored for RGPD filtering"
        
        # Verify in GET response
        get_response = api_session.get(f"{BASE_URL}/api/reports/recipients", timeout=30)
        recipients = get_response.json()["recipients"]
        created = next((r for r in recipients if r["id"] == recipient["id"]), None)
        
        assert created is not None
        assert created["partner_id"] == 15
        assert created["partner_name"] == "SMART GYM", "Partner name should be resolved"
        
        print(f"RGPD: Partner recipient correctly stores partner_id=15 (SMART GYM)")


if __name__ == "__main__":
    pytest.main([__file__, "-v", "--tb=short"])
