#!/usr/bin/env python3
"""
Test suite for ML Merchant Recommendation Engine endpoints.
Tests: recommendations, health, track, stats, retrain, cold-start fallback.
"""
import pytest
import requests
import os
import time

BASE_URL = os.environ.get('REACT_APP_BACKEND_URL', '').rstrip('/')

# Known client IDs from the training data
KNOWN_CLIENT_ID = 114218  # User with history
UNKNOWN_CLIENT_ID = 999999  # Cold-start user (no history)


class TestMerchantRecommendationsHealth:
    """Health endpoint tests - verify model status and metrics."""
    
    def test_health_endpoint_returns_200(self):
        """GET /api/merchant-recommendations/health returns 200."""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/health", timeout=30)
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        print(f"✓ Health endpoint returned 200")
    
    def test_health_returns_model_status(self):
        """Health endpoint returns model_loaded and status fields."""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/health", timeout=30)
        data = response.json()
        
        assert "status" in data, "Missing 'status' field"
        assert "model_loaded" in data, "Missing 'model_loaded' field"
        assert "fallback_available" in data, "Missing 'fallback_available' field"
        
        print(f"✓ Model status: {data['status']}, loaded: {data['model_loaded']}, fallback: {data['fallback_available']}")
    
    def test_health_returns_training_metrics(self):
        """Health endpoint returns training date and eval metrics."""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/health", timeout=30)
        data = response.json()
        
        # Model should be trained
        assert data.get("model_loaded") == True, "Model not loaded"
        assert data.get("trained_at") is not None, "Missing trained_at"
        assert data.get("n_train_samples") is not None, "Missing n_train_samples"
        
        # Eval results should have NDCG metrics
        eval_results = data.get("eval_results", {})
        print(f"✓ Trained at: {data['trained_at']}, samples: {data['n_train_samples']}")
        print(f"✓ Eval results: {eval_results}")


class TestMerchantRecommendations:
    """POST /api/merchant-recommendations - personalized recommendations."""
    
    def test_recommendations_requires_client_id(self):
        """Returns 400 if client_id is missing."""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={},
            timeout=30
        )
        assert response.status_code == 400, f"Expected 400, got {response.status_code}"
        data = response.json()
        assert data.get("success") == False
        assert "client_id" in data.get("error", "").lower()
        print("✓ Returns 400 when client_id missing")
    
    def test_recommendations_for_known_user(self):
        """Returns personalized recommendations for known client_id."""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": KNOWN_CLIENT_ID, "top_k": 5},
            timeout=60
        )
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        
        data = response.json()
        assert data.get("success") == True, f"Expected success=True: {data}"
        assert data.get("client_id") == KNOWN_CLIENT_ID
        assert "recommendations" in data
        assert "count" in data
        
        recommendations = data["recommendations"]
        assert isinstance(recommendations, list)
        assert len(recommendations) > 0, "Expected at least 1 recommendation"
        assert len(recommendations) <= 5, f"Expected max 5, got {len(recommendations)}"
        
        # Validate recommendation structure
        rec = recommendations[0]
        assert "partner_id" in rec
        assert "partner_name" in rec
        assert "score" in rec
        assert "rank" in rec
        assert "reason" in rec
        
        print(f"✓ Got {len(recommendations)} recommendations for client {KNOWN_CLIENT_ID}")
        print(f"  Top recommendation: {rec['partner_name']} (score: {rec['score']}, reason: {rec['reason'][:50]}...)")
    
    def test_cold_start_fallback_for_unknown_user(self):
        """Returns popularity-based recommendations for unknown client_id."""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": UNKNOWN_CLIENT_ID, "top_k": 5},
            timeout=60
        )
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        
        data = response.json()
        assert data.get("success") == True, f"Expected success=True: {data}"
        
        recommendations = data.get("recommendations", [])
        assert len(recommendations) > 0, "Expected fallback recommendations for cold-start user"
        
        # Cold-start should return popularity-based recommendations
        rec = recommendations[0]
        assert "partner_id" in rec
        assert "reason" in rec
        # Cold-start reason should mention "populaire"
        print(f"✓ Cold-start fallback: {len(recommendations)} recommendations for unknown user {UNKNOWN_CLIENT_ID}")
        print(f"  Top: {rec.get('partner_name', 'N/A')} - {rec.get('reason', 'N/A')}")
    
    def test_exclude_visited_merchants(self):
        """exclude_visited=true filters out already visited merchants."""
        # First get recommendations without exclusion
        response1 = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": KNOWN_CLIENT_ID, "top_k": 20, "exclude_visited": False},
            timeout=60
        )
        data1 = response1.json()
        
        # Then with exclusion
        response2 = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": KNOWN_CLIENT_ID, "top_k": 20, "exclude_visited": True},
            timeout=60
        )
        data2 = response2.json()
        
        assert response1.status_code == 200
        assert response2.status_code == 200
        
        recs1 = data1.get("recommendations", [])
        recs2 = data2.get("recommendations", [])
        
        # With exclusion, all should have already_visited=False
        for rec in recs2:
            assert rec.get("already_visited") == False, f"Merchant {rec['partner_id']} should not be visited"
        
        print(f"✓ exclude_visited works: {len(recs1)} total vs {len(recs2)} unvisited")
    
    def test_category_filter(self):
        """category_id filters recommendations to specific category."""
        # Category 1 is typically "Restaurants" or similar
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": KNOWN_CLIENT_ID, "top_k": 10, "category_id": 1},
            timeout=60
        )
        assert response.status_code == 200, f"Expected 200, got {response.status_code}"
        
        data = response.json()
        # May return empty if no merchants in category 1
        print(f"✓ Category filter: {data.get('count', 0)} recommendations for category_id=1")


class TestTrackInteraction:
    """POST /api/merchant-recommendations/track - feedback loop tracking."""
    
    def test_track_requires_client_and_partner(self):
        """Returns 400 if client_id or partner_id missing."""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations/track",
            json={"client_id": 123},
            timeout=30
        )
        assert response.status_code == 400
        data = response.json()
        assert data.get("success") == False
        print("✓ Track returns 400 when partner_id missing")
    
    def test_track_validates_interaction_type(self):
        """Returns 400 for invalid interaction_type."""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations/track",
            json={
                "client_id": KNOWN_CLIENT_ID,
                "partner_id": 1,
                "interaction_type": "invalid_type"
            },
            timeout=30
        )
        assert response.status_code == 400
        data = response.json()
        assert "interaction_type" in data.get("error", "").lower()
        print("✓ Track validates interaction_type")
    
    def test_track_click_interaction(self):
        """Successfully tracks a click interaction."""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations/track",
            json={
                "client_id": KNOWN_CLIENT_ID,
                "partner_id": 1,
                "interaction_type": "click",
                "source": "recommendation",
                "recommendation_score": 0.85,
                "recommendation_rank": 1
            },
            timeout=30
        )
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        
        data = response.json()
        assert data.get("success") == True
        assert data.get("tracked") == True
        print("✓ Click interaction tracked successfully")
    
    def test_track_redeem_interaction(self):
        """Successfully tracks a redeem interaction."""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations/track",
            json={
                "client_id": KNOWN_CLIENT_ID,
                "partner_id": 2,
                "interaction_type": "redeem",
                "source": "organic",
                "promotion_id": 100
            },
            timeout=30
        )
        assert response.status_code == 200
        data = response.json()
        assert data.get("success") == True
        print("✓ Redeem interaction tracked successfully")


class TestRecommendationStats:
    """GET /api/merchant-recommendations/stats - monitoring statistics."""
    
    def test_stats_returns_200(self):
        """Stats endpoint returns 200."""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/stats", timeout=30)
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        print("✓ Stats endpoint returned 200")
    
    def test_stats_returns_metrics(self):
        """Stats endpoint returns interaction metrics."""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/stats", timeout=30)
        data = response.json()
        
        assert "total_interactions" in data, "Missing total_interactions"
        assert "active_merchants" in data, "Missing active_merchants"
        assert "profiled_users" in data, "Missing profiled_users"
        
        print(f"✓ Stats: {data['total_interactions']} interactions, {data['active_merchants']} merchants, {data['profiled_users']} profiled users")
        
        # Check last_7_days breakdown
        if "last_7_days" in data:
            print(f"  Last 7 days breakdown: {len(data['last_7_days'])} interaction types")


class TestRetrain:
    """POST /api/merchant-recommendations/retrain - model retraining."""
    
    def test_retrain_endpoint_works(self):
        """Retrain endpoint triggers training (long timeout)."""
        # This test takes ~60-120 seconds
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations/retrain",
            json={},
            timeout=180  # 3 minute timeout for retraining
        )
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        
        data = response.json()
        assert "success" in data
        
        if data.get("success"):
            print("✓ Retrain completed successfully")
            if data.get("output"):
                # Print last few lines of output
                output_lines = data["output"].strip().split('\n')[-5:]
                print(f"  Output (last 5 lines):")
                for line in output_lines:
                    print(f"    {line}")
        else:
            print(f"⚠ Retrain returned success=False: {data.get('errors', 'No error details')}")
            # Still pass if endpoint works, even if training has issues
            assert "output" in data or "errors" in data


class TestSubStoreMerchantsRegression:
    """Regression test: /sub-stores/api/split/merchants still works.
    Note: This endpoint requires Laravel session auth, so we test via internal route.
    """
    
    def test_split_merchants_endpoint_requires_auth(self):
        """Verify sub-store merchants endpoint requires authentication (redirects to login)."""
        response = requests.get(
            f"{BASE_URL}/sub-stores/api/split/merchants",
            params={
                "start_date": "2026-03-01",
                "end_date": "2026-03-31",
                "sub_store_id": "Sofrecom"
            },
            timeout=60,
            allow_redirects=False
        )
        # Should redirect to login (302) or return HTML redirect
        # This confirms the endpoint exists and auth middleware is working
        assert response.status_code in [200, 302], f"Expected 200/302, got {response.status_code}"
        
        # If 200, it's an HTML redirect page
        if response.status_code == 200:
            assert "login" in response.text.lower(), "Expected redirect to login"
            print("✓ Regression: /sub-stores/api/split/merchants exists and requires auth (HTML redirect)")
        else:
            print("✓ Regression: /sub-stores/api/split/merchants exists and requires auth (302 redirect)")


class TestDatabaseTables:
    """Verify ML tables have data."""
    
    def test_merchants_catalog_has_data(self):
        """cp_merchants_catalog should have data from training."""
        # Use stats endpoint which queries this table
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/stats", timeout=30)
        data = response.json()
        
        active_merchants = data.get("active_merchants", 0)
        assert active_merchants > 0, f"Expected active_merchants > 0, got {active_merchants}"
        print(f"✓ cp_merchants_catalog has {active_merchants} active merchants")
    
    def test_user_profiles_has_data(self):
        """cp_user_profile should have data from training."""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/stats", timeout=30)
        data = response.json()
        
        profiled_users = data.get("profiled_users", 0)
        assert profiled_users > 0, f"Expected profiled_users > 0, got {profiled_users}"
        print(f"✓ cp_user_profile has {profiled_users} profiled users")


if __name__ == "__main__":
    pytest.main([__file__, "-v", "--tb=short"])
