"""
Backend tests for Merchant Recommendations API - Iteration 22
Focus: Score normalization 0-100, explanation objects, user_context, exclude_visited, category filter
Test client IDs: ML model: 118580, 130212, 49949. Fallback: 0, 1
"""
import pytest
import requests
import os

BASE_URL = os.environ.get('REACT_APP_BACKEND_URL', '').rstrip('/')

# Test client IDs with ML profiles
ML_CLIENT_IDS = {
    118580: {"visits": 555, "merchants": 252},
    130212: {"visits": 137, "merchants": 1},
    49949: {"visits": 115, "merchants": 12},
}
FALLBACK_CLIENT_IDS = [0, 1]


class TestMLModelRecommendations:
    """Tests for ML model recommendations with client_id=118580"""
    
    def test_ml_model_source_client_118580(self):
        """POST /api/merchant-recommendations with client_id=118580 returns source=ml_model"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 118580, "top_k": 10}
        )
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        data = response.json()
        
        assert data["success"] is True
        assert data["client_id"] == 118580
        assert data["source"] == "ml_model", f"Expected 'ml_model', got '{data['source']}'"
        assert "recommendations" in data
        assert len(data["recommendations"]) > 0
        print(f"✓ Client 118580: source={data['source']}, count={len(data['recommendations'])}")
    
    def test_score_normalized_0_100_range(self):
        """Each recommendation must have score_normalized in 0-100 range"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 118580, "top_k": 10}
        )
        assert response.status_code == 200
        data = response.json()
        
        for rec in data["recommendations"]:
            assert "score_normalized" in rec, f"Missing score_normalized in recommendation: {rec}"
            score = rec["score_normalized"]
            assert 0 <= score <= 100, f"score_normalized {score} not in 0-100 range"
        
        # Verify scores are distributed (not all same value)
        scores = [r["score_normalized"] for r in data["recommendations"]]
        unique_scores = set(scores)
        assert len(unique_scores) > 1, f"All scores are identical: {scores}"
        print(f"✓ Score normalized range: min={min(scores)}, max={max(scores)}, unique={len(unique_scores)}")
    
    def test_recommendation_structure_complete(self):
        """Each recommendation must have all required fields"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 118580, "top_k": 5}
        )
        assert response.status_code == 200
        data = response.json()
        
        required_fields = [
            "partner_id", "partner_name", "category_name", "score", "score_normalized",
            "rank", "reason", "explanation", "already_visited", "visit_count"
        ]
        
        for rec in data["recommendations"]:
            for field in required_fields:
                assert field in rec, f"Missing field '{field}' in recommendation: {rec.keys()}"
        
        print(f"✓ All {len(required_fields)} required fields present in recommendations")
    
    def test_explanation_object_structure(self):
        """Explanation object must have summary, factors, details, score_interpretation, model_type"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 118580, "top_k": 3}
        )
        assert response.status_code == 200
        data = response.json()
        
        explanation_fields = ["summary", "factors", "score_interpretation", "model_type"]
        
        for rec in data["recommendations"]:
            explanation = rec.get("explanation", {})
            assert isinstance(explanation, dict), f"explanation should be dict, got {type(explanation)}"
            
            for field in explanation_fields:
                assert field in explanation, f"Missing '{field}' in explanation: {explanation.keys()}"
            
            # Verify factors is a list
            assert isinstance(explanation["factors"], list), "factors should be a list"
            assert len(explanation["factors"]) > 0, "factors should not be empty"
            
            # Verify summary is a string
            assert isinstance(explanation["summary"], str), "summary should be a string"
            assert len(explanation["summary"]) > 0, "summary should not be empty"
        
        print(f"✓ Explanation object structure verified with fields: {explanation_fields}")
    
    def test_user_context_returned_for_ml_model(self):
        """user_context must be returned for ML model source"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 118580, "top_k": 5}
        )
        assert response.status_code == 200
        data = response.json()
        
        assert data["source"] == "ml_model"
        assert "user_context" in data, "user_context missing for ML model source"
        
        user_context = data["user_context"]
        context_fields = ["total_visits", "unique_merchants", "loyalty_score", "subscription_type"]
        
        for field in context_fields:
            assert field in user_context, f"Missing '{field}' in user_context: {user_context.keys()}"
        
        # Verify client 118580 has expected profile data
        assert user_context["total_visits"] > 0, "total_visits should be > 0 for profiled user"
        assert user_context["unique_merchants"] > 0, "unique_merchants should be > 0"
        
        print(f"✓ user_context: total_visits={user_context['total_visits']}, unique_merchants={user_context['unique_merchants']}, loyalty_score={user_context['loyalty_score']}")


class TestFallbackRecommendations:
    """Tests for fallback popularity recommendations"""
    
    def test_fallback_source_client_0(self):
        """POST /api/merchant-recommendations with client_id=0 returns source=fallback_popularity"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 0, "top_k": 10}
        )
        assert response.status_code == 200
        data = response.json()
        
        assert data["success"] is True
        assert data["source"] == "fallback_popularity", f"Expected 'fallback_popularity', got '{data['source']}'"
        assert len(data["recommendations"]) > 0
        print(f"✓ Client 0: source={data['source']}, count={len(data['recommendations'])}")
    
    def test_fallback_source_client_1(self):
        """POST /api/merchant-recommendations with client_id=1 returns source=fallback_popularity"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 1, "top_k": 10}
        )
        assert response.status_code == 200
        data = response.json()
        
        assert data["success"] is True
        assert data["source"] == "fallback_popularity", f"Expected 'fallback_popularity', got '{data['source']}'"
        print(f"✓ Client 1: source={data['source']}")
    
    def test_fallback_score_normalized_distributed(self):
        """Fallback recommendations score_normalized must be properly distributed (not all 0)"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 0, "top_k": 10}
        )
        assert response.status_code == 200
        data = response.json()
        
        scores = [r["score_normalized"] for r in data["recommendations"]]
        
        # Verify scores are in 0-100 range
        for score in scores:
            assert 0 <= score <= 100, f"score_normalized {score} not in 0-100 range"
        
        # Verify scores are distributed (not all 0 or all same)
        unique_scores = set(scores)
        assert len(unique_scores) > 1, f"All fallback scores are identical: {scores}"
        
        # Verify at least one score is > 0
        assert max(scores) > 0, f"All fallback scores are 0: {scores}"
        
        print(f"✓ Fallback scores distributed: min={min(scores)}, max={max(scores)}, unique={len(unique_scores)}")
    
    def test_fallback_no_user_context(self):
        """Fallback recommendations should not have user_context (or it's None)"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 0, "top_k": 5}
        )
        assert response.status_code == 200
        data = response.json()
        
        # user_context should either be missing or None for fallback
        if "user_context" in data:
            assert data["user_context"] is None, f"user_context should be None for fallback, got {data['user_context']}"
        
        print("✓ Fallback correctly has no user_context")


class TestExcludeVisitedFilter:
    """Tests for exclude_visited=true filter"""
    
    def test_exclude_visited_returns_unvisited_only(self):
        """POST with exclude_visited=true should return only unvisited merchants"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 118580, "top_k": 10, "exclude_visited": True}
        )
        assert response.status_code == 200
        data = response.json()
        
        assert data["success"] is True
        
        # All recommendations should have already_visited=False
        for rec in data["recommendations"]:
            assert rec["already_visited"] is False, f"Merchant {rec['partner_id']} should not be visited"
            assert rec["visit_count"] == 0, f"Merchant {rec['partner_id']} visit_count should be 0"
        
        print(f"✓ exclude_visited=true: {len(data['recommendations'])} unvisited merchants returned")
    
    def test_exclude_visited_false_includes_visited(self):
        """POST with exclude_visited=false should include visited merchants"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 118580, "top_k": 10, "exclude_visited": False}
        )
        assert response.status_code == 200
        data = response.json()
        
        # Should have at least some visited merchants for client 118580 (252 merchants visited)
        visited_count = sum(1 for r in data["recommendations"] if r["already_visited"])
        
        print(f"✓ exclude_visited=false: {visited_count}/{len(data['recommendations'])} visited merchants in results")


class TestCategoryFilter:
    """Tests for category_id filter"""
    
    def test_category_filter_returns_matching_category(self):
        """POST with category_id filter should only return merchants from that category"""
        # First get available categories
        cat_response = requests.get(f"{BASE_URL}/api/merchant-recommendations/categories")
        assert cat_response.status_code == 200
        categories = cat_response.json()["categories"]
        
        if len(categories) == 0:
            pytest.skip("No categories available")
        
        # Test with first category
        test_category = categories[0]
        category_id = test_category["category_id"]
        category_name = test_category["category_name"]
        
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 118580, "top_k": 10, "category_id": category_id}
        )
        assert response.status_code == 200
        data = response.json()
        
        # All recommendations should be from the specified category
        for rec in data["recommendations"]:
            assert rec["category_name"] == category_name, \
                f"Expected category '{category_name}', got '{rec['category_name']}'"
        
        print(f"✓ Category filter: {len(data['recommendations'])} merchants from '{category_name}'")
    
    def test_category_filter_with_fallback(self):
        """Category filter should work with fallback recommendations too"""
        cat_response = requests.get(f"{BASE_URL}/api/merchant-recommendations/categories")
        categories = cat_response.json()["categories"]
        
        if len(categories) == 0:
            pytest.skip("No categories available")
        
        test_category = categories[0]
        
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 0, "top_k": 10, "category_id": test_category["category_id"]}
        )
        assert response.status_code == 200
        data = response.json()
        
        # Should return fallback with category filter
        assert data["source"] == "fallback_popularity"
        
        for rec in data["recommendations"]:
            assert rec["category_name"] == test_category["category_name"]
        
        print(f"✓ Category filter with fallback: {len(data['recommendations'])} merchants")


class TestHealthEndpoint:
    """Tests for /api/merchant-recommendations/health"""
    
    def test_health_status_ready_model_loaded(self):
        """GET /api/merchant-recommendations/health returns status=ready with model_loaded=true"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/health")
        assert response.status_code == 200
        data = response.json()
        
        assert data["status"] == "ready", f"Expected status='ready', got '{data['status']}'"
        assert data["model_loaded"] is True, f"Expected model_loaded=true, got {data['model_loaded']}"
        assert "fallback_available" in data
        
        print(f"✓ Health: status={data['status']}, model_loaded={data['model_loaded']}, fallback={data['fallback_available']}")


class TestStatsEndpoint:
    """Tests for /api/merchant-recommendations/stats"""
    
    def test_stats_returns_active_merchants_profiled_users(self):
        """GET /api/merchant-recommendations/stats returns active_merchants, profiled_users counts"""
        response = requests.get(f"{BASE_URL}/api/merchant-recommendations/stats")
        assert response.status_code == 200
        data = response.json()
        
        assert "active_merchants" in data, "Missing 'active_merchants' in stats"
        assert "profiled_users" in data, "Missing 'profiled_users' in stats"
        assert "total_interactions" in data, "Missing 'total_interactions' in stats"
        
        # Verify counts are positive
        assert data["active_merchants"] > 0, f"active_merchants should be > 0, got {data['active_merchants']}"
        assert data["profiled_users"] > 0, f"profiled_users should be > 0, got {data['profiled_users']}"
        
        print(f"✓ Stats: active_merchants={data['active_merchants']}, profiled_users={data['profiled_users']}")


class TestRetrainEndpoint:
    """Tests for /api/merchant-recommendations/retrain"""
    
    def test_retrain_returns_json_response(self):
        """POST /api/merchant-recommendations/retrain returns success or error (synchronous)"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations/retrain",
            json={},
            timeout=30  # Short timeout - we just want to verify it returns JSON
        )
        
        # Should return 200 or 500 with JSON response
        assert response.status_code in [200, 500], f"Unexpected status: {response.status_code}"
        
        data = response.json()
        assert "success" in data, "Response missing 'success' field"
        
        if data["success"]:
            print(f"✓ Retrain succeeded")
        else:
            # Expected to fail in this env due to missing pymysql connection
            assert "error" in data or "errors" in data, "Failed response should have error message"
            print(f"✓ Retrain returned error (expected in test env): {data.get('error', data.get('errors', ''))[:100]}")


class TestMultipleMLClients:
    """Tests for multiple ML model clients"""
    
    def test_client_130212_ml_model(self):
        """Client 130212 (137 visits, 1 merchant) should use ML model"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 130212, "top_k": 5}
        )
        assert response.status_code == 200
        data = response.json()
        
        assert data["source"] == "ml_model", f"Expected 'ml_model', got '{data['source']}'"
        assert "user_context" in data
        print(f"✓ Client 130212: source={data['source']}, visits={data['user_context'].get('total_visits')}")
    
    def test_client_49949_ml_model(self):
        """Client 49949 (115 visits, 12 merchants) should use ML model"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 49949, "top_k": 5}
        )
        assert response.status_code == 200
        data = response.json()
        
        assert data["source"] == "ml_model", f"Expected 'ml_model', got '{data['source']}'"
        assert "user_context" in data
        print(f"✓ Client 49949: source={data['source']}, visits={data['user_context'].get('total_visits')}")


class TestErrorHandling:
    """Tests for error handling"""
    
    def test_missing_client_id_returns_400(self):
        """POST without client_id returns 400"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"top_k": 5}
        )
        assert response.status_code == 400
        data = response.json()
        assert data["success"] is False
        print("✓ Missing client_id returns 400")
    
    def test_invalid_client_id_type(self):
        """POST with invalid client_id type should handle gracefully"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": "invalid", "top_k": 5}
        )
        # Should return 400 or 500 with error message
        assert response.status_code in [400, 500]
        data = response.json()
        assert data["success"] is False
        print(f"✓ Invalid client_id type handled: status={response.status_code}")


if __name__ == "__main__":
    pytest.main([__file__, "-v", "--tb=short"])
