"""
Backend tests for AWS Personalize-inspired Merchant Recommendations Features - Iteration 23
Focus: recommendation_type, because_you_visited, similar_users_count, HTML explain endpoint

Recommendation Types:
- DISCOVERY: Merchant the user has never visited
- RE_ENGAGEMENT: Merchant visited before but not recently (>30 days)
- LOYALTY: Merchant the user visits frequently
- TRENDING: Popular merchant with high recent activity

Test client IDs: ML model: 118580, 130212, 49949. Cold-start: 0
"""
import pytest
import requests
import os

BASE_URL = os.environ.get('REACT_APP_BACKEND_URL', '').rstrip('/')

# Valid recommendation types
VALID_REC_TYPES = ['DISCOVERY', 'RE_ENGAGEMENT', 'LOYALTY', 'TRENDING']


class TestRecommendationType:
    """Tests for recommendation_type field in recommendations"""
    
    def test_recommendation_type_present_client_118580(self):
        """POST /api/merchant-recommendations with client_id=118580 returns recommendation_type for each rec"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 118580, "top_k": 10}
        )
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        data = response.json()
        
        assert data["success"] is True
        assert len(data["recommendations"]) > 0
        
        for rec in data["recommendations"]:
            assert "recommendation_type" in rec, f"Missing recommendation_type in rec: {rec.keys()}"
            rec_type = rec["recommendation_type"]
            assert rec_type in VALID_REC_TYPES, f"Invalid recommendation_type '{rec_type}', expected one of {VALID_REC_TYPES}"
        
        # Count types
        type_counts = {}
        for rec in data["recommendations"]:
            rt = rec["recommendation_type"]
            type_counts[rt] = type_counts.get(rt, 0) + 1
        
        print(f"✓ Client 118580 recommendation_type distribution: {type_counts}")
    
    def test_exclude_visited_returns_discovery_or_trending_only(self):
        """POST with client_id=49949 and exclude_visited=true returns DISCOVERY or TRENDING types only"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 49949, "top_k": 10, "exclude_visited": True}
        )
        assert response.status_code == 200
        data = response.json()
        
        assert data["success"] is True
        
        # When exclude_visited=true, only unvisited merchants are returned
        # Unvisited merchants can only be DISCOVERY or TRENDING
        allowed_types = ['DISCOVERY', 'TRENDING']
        
        for rec in data["recommendations"]:
            rec_type = rec["recommendation_type"]
            assert rec_type in allowed_types, \
                f"With exclude_visited=true, expected DISCOVERY or TRENDING, got '{rec_type}'"
            # Also verify already_visited is False
            assert rec["already_visited"] is False, f"Merchant {rec['partner_id']} should not be visited"
        
        type_counts = {}
        for rec in data["recommendations"]:
            rt = rec["recommendation_type"]
            type_counts[rt] = type_counts.get(rt, 0) + 1
        
        print(f"✓ Client 49949 exclude_visited=true types: {type_counts}")
    
    def test_cold_start_returns_trending_or_discovery(self):
        """POST with client_id=0 (fallback) returns TRENDING or DISCOVERY types"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 0, "top_k": 10}
        )
        assert response.status_code == 200
        data = response.json()
        
        assert data["success"] is True
        assert data["source"] == "fallback_popularity"
        
        # Cold-start users should get TRENDING or DISCOVERY
        allowed_types = ['DISCOVERY', 'TRENDING']
        
        for rec in data["recommendations"]:
            rec_type = rec["recommendation_type"]
            assert rec_type in allowed_types, \
                f"Cold-start should return DISCOVERY or TRENDING, got '{rec_type}'"
        
        type_counts = {}
        for rec in data["recommendations"]:
            rt = rec["recommendation_type"]
            type_counts[rt] = type_counts.get(rt, 0) + 1
        
        print(f"✓ Cold-start (client 0) types: {type_counts}")


class TestBecauseYouVisited:
    """Tests for because_you_visited array in recommendations"""
    
    def test_because_you_visited_present_client_118580(self):
        """POST with client_id=118580 returns because_you_visited array for each recommendation"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 118580, "top_k": 10}
        )
        assert response.status_code == 200
        data = response.json()
        
        assert data["success"] is True
        
        for rec in data["recommendations"]:
            assert "because_you_visited" in rec, f"Missing because_you_visited in rec: {rec.keys()}"
            because = rec["because_you_visited"]
            assert isinstance(because, list), f"because_you_visited should be list, got {type(because)}"
        
        # Check structure of non-empty because_you_visited entries
        non_empty_count = 0
        for rec in data["recommendations"]:
            because = rec["because_you_visited"]
            if len(because) > 0:
                non_empty_count += 1
                for link in because:
                    assert "partner_name" in link, f"Missing partner_name in because_you_visited: {link.keys()}"
                    assert "visit_count" in link, f"Missing visit_count in because_you_visited: {link.keys()}"
                    assert "link_reason" in link, f"Missing link_reason in because_you_visited: {link.keys()}"
        
        print(f"✓ Client 118580: {non_empty_count}/{len(data['recommendations'])} recs have because_you_visited links")
    
    def test_cold_start_empty_because_you_visited(self):
        """POST with client_id=0 (fallback) returns empty because_you_visited"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 0, "top_k": 10}
        )
        assert response.status_code == 200
        data = response.json()
        
        for rec in data["recommendations"]:
            assert "because_you_visited" in rec
            because = rec["because_you_visited"]
            assert isinstance(because, list)
            assert len(because) == 0, f"Cold-start should have empty because_you_visited, got {because}"
        
        print("✓ Cold-start (client 0) has empty because_you_visited for all recs")


class TestSimilarUsersCount:
    """Tests for similar_users_count field in recommendations"""
    
    def test_similar_users_count_present_client_118580(self):
        """POST with client_id=118580 returns similar_users_count integer >= 0"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 118580, "top_k": 10}
        )
        assert response.status_code == 200
        data = response.json()
        
        for rec in data["recommendations"]:
            assert "similar_users_count" in rec, f"Missing similar_users_count in rec: {rec.keys()}"
            sim_count = rec["similar_users_count"]
            assert isinstance(sim_count, int), f"similar_users_count should be int, got {type(sim_count)}"
            assert sim_count >= 0, f"similar_users_count should be >= 0, got {sim_count}"
        
        # Count how many have similar users
        with_similar = sum(1 for r in data["recommendations"] if r["similar_users_count"] > 0)
        total_similar = sum(r["similar_users_count"] for r in data["recommendations"])
        
        print(f"✓ Client 118580: {with_similar}/{len(data['recommendations'])} recs have similar_users, total={total_similar}")


class TestUserContext:
    """Tests for user_context with total_visits, unique_merchants, loyalty_score"""
    
    def test_user_context_client_130212(self):
        """POST with client_id=130212 returns correct user_context fields"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 130212, "top_k": 5}
        )
        assert response.status_code == 200
        data = response.json()
        
        assert data["source"] == "ml_model"
        assert "user_context" in data
        
        uc = data["user_context"]
        
        # Required fields
        assert "total_visits" in uc, f"Missing total_visits in user_context: {uc.keys()}"
        assert "unique_merchants" in uc, f"Missing unique_merchants in user_context: {uc.keys()}"
        assert "loyalty_score" in uc, f"Missing loyalty_score in user_context: {uc.keys()}"
        
        # Type checks
        assert isinstance(uc["total_visits"], int), f"total_visits should be int, got {type(uc['total_visits'])}"
        assert isinstance(uc["unique_merchants"], int), f"unique_merchants should be int, got {type(uc['unique_merchants'])}"
        assert isinstance(uc["loyalty_score"], (int, float)), f"loyalty_score should be numeric, got {type(uc['loyalty_score'])}"
        
        # Value checks
        assert uc["total_visits"] >= 0
        assert uc["unique_merchants"] >= 0
        assert uc["loyalty_score"] >= 0
        
        print(f"✓ Client 130212 user_context: total_visits={uc['total_visits']}, unique_merchants={uc['unique_merchants']}, loyalty_score={uc['loyalty_score']}")


class TestExplanationModelType:
    """Tests for explanation object containing model_type and exploration_weight"""
    
    def test_explanation_contains_model_type_lightgbm(self):
        """Each recommendation explanation contains model_type mentioning 'LightGBM'"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 118580, "top_k": 5}
        )
        assert response.status_code == 200
        data = response.json()
        
        for rec in data["recommendations"]:
            explanation = rec.get("explanation", {})
            assert "model_type" in explanation, f"Missing model_type in explanation: {explanation.keys()}"
            
            model_type = explanation["model_type"]
            assert "LightGBM" in model_type, f"model_type should mention 'LightGBM', got '{model_type}'"
        
        print(f"✓ All explanations contain model_type with 'LightGBM'")
    
    def test_explanation_contains_exploration_weight(self):
        """Each recommendation explanation contains exploration_weight"""
        response = requests.post(
            f"{BASE_URL}/api/merchant-recommendations",
            json={"client_id": 118580, "top_k": 5}
        )
        assert response.status_code == 200
        data = response.json()
        
        for rec in data["recommendations"]:
            explanation = rec.get("explanation", {})
            assert "exploration_weight" in explanation, f"Missing exploration_weight in explanation: {explanation.keys()}"
            
            exp_weight = explanation["exploration_weight"]
            assert isinstance(exp_weight, (int, float)), f"exploration_weight should be numeric, got {type(exp_weight)}"
            assert 0 <= exp_weight <= 1, f"exploration_weight should be 0-1, got {exp_weight}"
        
        print(f"✓ All explanations contain exploration_weight (value={data['recommendations'][0]['explanation']['exploration_weight']})")


class TestHTMLExplainEndpoint:
    """Tests for GET /api/merchant-recommendations/explain/{client_id} HTML endpoint"""
    
    def test_explain_returns_html_status_200(self):
        """GET /api/merchant-recommendations/explain/118580?top_k=5 returns valid HTML with status 200"""
        response = requests.get(
            f"{BASE_URL}/api/merchant-recommendations/explain/118580",
            params={"top_k": 5}
        )
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text[:500]}"
        
        # Check content type is HTML
        content_type = response.headers.get("content-type", "")
        assert "text/html" in content_type, f"Expected text/html, got '{content_type}'"
        
        # Check it's valid HTML
        html = response.text
        assert "<!DOCTYPE html>" in html or "<html" in html, "Response should be HTML"
        assert "</html>" in html, "HTML should have closing tag"
        
        print(f"✓ Explain endpoint returns valid HTML ({len(html)} chars)")
    
    def test_explain_html_contains_rapport_recommandations(self):
        """GET /api/merchant-recommendations/explain/118580 HTML contains 'Rapport de Recommandations'"""
        response = requests.get(
            f"{BASE_URL}/api/merchant-recommendations/explain/118580",
            params={"top_k": 5}
        )
        assert response.status_code == 200
        
        html = response.text
        assert "Rapport de Recommandations" in html, "HTML should contain 'Rapport de Recommandations'"
        
        print("✓ HTML contains 'Rapport de Recommandations'")
    
    def test_explain_html_contains_profil_client(self):
        """GET /api/merchant-recommendations/explain/118580 HTML contains 'Profil Client'"""
        response = requests.get(
            f"{BASE_URL}/api/merchant-recommendations/explain/118580",
            params={"top_k": 5}
        )
        assert response.status_code == 200
        
        html = response.text
        assert "Profil Client" in html, "HTML should contain 'Profil Client'"
        
        print("✓ HTML contains 'Profil Client'")
    
    def test_explain_html_contains_type_badges(self):
        """GET /api/merchant-recommendations/explain/118580 HTML contains recommendation type badges"""
        response = requests.get(
            f"{BASE_URL}/api/merchant-recommendations/explain/118580",
            params={"top_k": 10}
        )
        assert response.status_code == 200
        
        html = response.text
        
        # Check for at least one type badge (French labels)
        type_labels = ["A decouvrir", "A re-visiter", "Favori", "Tendance"]
        found_types = [label for label in type_labels if label in html]
        
        assert len(found_types) > 0, f"HTML should contain at least one type badge from {type_labels}"
        
        print(f"✓ HTML contains type badges: {found_types}")
    
    def test_explain_exclude_visited_discovery_cards(self):
        """GET /api/merchant-recommendations/explain/49949?top_k=5&exclude_visited=true returns HTML with DISCOVERY cards"""
        response = requests.get(
            f"{BASE_URL}/api/merchant-recommendations/explain/49949",
            params={"top_k": 5, "exclude_visited": "true"}
        )
        assert response.status_code == 200
        
        html = response.text
        
        # Should contain DISCOVERY type label ("A decouvrir") or TRENDING ("Tendance")
        assert "A decouvrir" in html or "Tendance" in html, \
            "HTML with exclude_visited=true should contain 'A decouvrir' or 'Tendance' badges"
        
        print("✓ Explain with exclude_visited=true contains DISCOVERY/TRENDING cards")
    
    def test_explain_cold_start_fallback(self):
        """GET /api/merchant-recommendations/explain/0?top_k=5 returns HTML for cold-start user"""
        response = requests.get(
            f"{BASE_URL}/api/merchant-recommendations/explain/0",
            params={"top_k": 5}
        )
        assert response.status_code == 200
        
        html = response.text
        
        # Should be valid HTML
        assert "<!DOCTYPE html>" in html or "<html" in html
        
        # Should contain profile section (even for cold-start)
        assert "Profil Client" in html or "Client #0" in html
        
        # Should contain fallback source indicator
        assert "Fallback" in html or "fallback" in html or "Popularity" in html or "popularity" in html
        
        print("✓ Explain for cold-start (client 0) returns valid HTML with fallback indicator")


class TestExplainHTMLContent:
    """Additional tests for HTML explain endpoint content"""
    
    def test_explain_html_contains_model_info(self):
        """HTML explain should contain model information (LightGBM)"""
        response = requests.get(
            f"{BASE_URL}/api/merchant-recommendations/explain/118580",
            params={"top_k": 5}
        )
        assert response.status_code == 200
        
        html = response.text
        
        # Should mention the model type
        assert "LightGBM" in html, "HTML should mention 'LightGBM' model"
        
        print("✓ HTML contains LightGBM model reference")
    
    def test_explain_html_contains_score_info(self):
        """HTML explain should contain score information"""
        response = requests.get(
            f"{BASE_URL}/api/merchant-recommendations/explain/118580",
            params={"top_k": 5}
        )
        assert response.status_code == 200
        
        html = response.text
        
        # Should contain score display (normalized /100)
        assert "/100" in html, "HTML should contain score display '/100'"
        
        print("✓ HTML contains score information")


if __name__ == "__main__":
    pytest.main([__file__, "-v", "--tb=short"])
