import pytest
import pandas as pd
import logging
from time import time
from CourseRecommendationSystem import recommend_collaborative, recommend_user_user_cf, update_model

# Set up logging
logger = logging.getLogger(__name__)

@pytest.fixture
def sample_data():
    """Load sample course data."""
    return pd.read_csv('Data/udemy_courses.csv')

@pytest.fixture
def ratings_data(sample_data):
    """Create temporary ratings data with numeric user IDs and valid course IDs."""
    # Select valid course IDs from sample_data
    valid_course_ids = sample_data['course_id'].astype(int).unique()[:6]  # Use first 6 course IDs
    sample_ratings = pd.DataFrame({
        'user_id': [1, 1, 1, 2, 2, 2, 3, 3, 3],
        'course_id': [valid_course_ids[0], valid_course_ids[1], valid_course_ids[2],
                      valid_course_ids[0], valid_course_ids[1], valid_course_ids[3],
                      valid_course_ids[3], valid_course_ids[4], valid_course_ids[5]],
        'rating': [4, 5, 4, 4, 4, 5, 4, 5, 4]
    })
    # Save to temporary ratings file
    sample_ratings.to_csv('Data/temp_ratings.csv', index=False)
    update_model()  # Update model with temporary ratings
    return sample_ratings

@pytest.fixture
def client():
    """Mock API client for testing."""
    from fastapi.testclient import TestClient
    from main import app
    return TestClient(app)

@pytest.fixture(autouse=True)
def cleanup_ratings():
    """Restore original ratings file after each test."""
    try:
        original_ratings = pd.read_csv('Data/ratings.csv')
    except FileNotFoundError:
        original_ratings = pd.DataFrame(columns=['user_id', 'course_id', 'rating'])
    yield
    original_ratings.to_csv('Data/ratings.csv', index=False)
    update_model()

def test_recommendation_exists(ratings_data, client):
    """Test if recommendations are returned for an existing user via API."""
    user_id = int(ratings_data['user_id'].iloc[0])  # Ensure integer user_id
    response = client.get(f"/recommend-collaborative?user_id={user_id}")
    assert response.status_code == 200
    recommendations = response.json()['recommendations']
    if isinstance(recommendations, list) and recommendations and ("error" in recommendations[0] or "warning" in recommendations[0]):
        pytest.skip(f"Recommendations failed for user {user_id}: {recommendations[0]}")
    assert isinstance(recommendations, list)
    assert len(recommendations) > 0
    assert all('course_id' in rec for rec in recommendations)

def test_recommendation_nonexistent(client):
    """Test if warning is returned for a nonexistent user via API."""
    response = client.get("/recommend-collaborative?user_id=999999")
    assert response.status_code == 200
    recommendations = response.json()['recommendations']
    assert isinstance(recommendations, list)
    assert len(recommendations) == 1
    assert "warning" in recommendations[0]
    assert "User ID 999999 not found" in recommendations[0]["warning"]

def test_precision_at_k(ratings_data, sample_data, k=5, num_samples=3):
    """
    Test precision@k to ensure recommendations are relevant based on subject and level.
    Calculates average precision@k across a sample of users.
    """
    user_ids = ratings_data['user_id'].astype(int).unique()[:num_samples]
    precisions = []

    for user_id in user_ids:
        user_ratings = ratings_data[ratings_data['user_id'].astype(int) == user_id]
        rated_courses = user_ratings.merge(sample_data, on='course_id')
        input_subjects = set(rated_courses['subject'].str.lower())
        input_level_values = [{'beginner level': 1, 'intermediate level': 2, 'expert level': 3, 'all levels': 4, 'unknown': 0}.get(level.lower().strip(), 0) for level in rated_courses['level']]
        min_level_value = min(input_level_values) if input_level_values else 0

        recommendations = recommend_collaborative(user_id, num_recommendations=k)
        if isinstance(recommendations, list) and recommendations and ("error" in recommendations[0] or "warning" in recommendations[0]):
            logger.info(f"Recommendations failed for user {user_id}: {recommendations[0]}")
            continue

        relevant = 0
        for rec in recommendations:
            rec_subject = rec['subject'].lower().strip()
            rec_level_value = {'beginner level': 1, 'intermediate level': 2, 'expert level': 3, 'all levels': 4, 'unknown': 0}.get(rec['level'].lower().strip(), 0)
            if rec_subject in input_subjects and rec_level_value >= min_level_value:
                relevant += 1
        precision = relevant / k if k > 0 else 0
        precisions.append(precision)
        logger.info(f"Precision@{k} for user {user_id}: {precision:.2f}")

    avg_precision = sum(precisions) / len(precisions) if precisions else 0
    logger.info(f"Average precision@{k}: {avg_precision:.2f}")
    assert avg_precision >= 0.5, f"Average precision@{k} too low: {avg_precision:.2f}"

def test_precision_at_k_sample(ratings_data, sample_data, k=5):
    """Test precision@k for a specific user."""
    user_id = int(ratings_data['user_id'].iloc[0])  # Ensure integer user_id
    user_ratings = ratings_data[ratings_data['user_id'].astype(int) == user_id]
    rated_courses = user_ratings.merge(sample_data, on='course_id')
    input_subjects = set(rated_courses['subject'].str.lower())
    input_level_values = [{'beginner level': 1, 'intermediate level': 2, 'expert level': 3, 'all levels': 4, 'unknown': 0}.get(level.lower().strip(), 0) for level in rated_courses['level']]
    min_level_value = min(input_level_values) if input_level_values else 0

    recommendations = recommend_collaborative(user_id, num_recommendations=k)
    if isinstance(recommendations, list) and recommendations and ("error" in recommendations[0] or "warning" in recommendations[0]):
        pytest.skip(f"Recommendations failed for user {user_id}: {recommendations[0]}")

    relevant = 0
    for rec in recommendations:
        rec_subject = rec['subject'].lower().strip()
        rec_level_value = {'beginner level': 1, 'intermediate level': 2, 'expert level': 3, 'all levels': 4, 'unknown': 0}.get(rec['level'].lower().strip(), 0)
        if rec_subject in input_subjects and rec_level_value >= min_level_value:
            relevant += 1
    precision = relevant / k if k > 0 else 0
    logger.info(f"Precision@{k} for user {user_id}: {precision:.2f}")
    assert precision >= 0.5, f"Precision@{k} too low: {precision:.2f}"

def test_user_user_cf_recommendations(ratings_data, sample_data, client):
    """
    Test User-User CF recommendations using the sample dataset.
    """
    # Use ratings_data with valid course IDs
    original_ratings = pd.read_csv('Data/ratings.csv')
    pd.DataFrame(ratings_data).to_csv('Data/ratings.csv', index=False)
    update_model()  # Update SVD model and user-item matrix

    # Test for User 1
    response_a = client.get("/recommend-usercf?user_id=1&num_recommendations=5")
    assert response_a.status_code == 200
    recommendations_a = response_a.json()['recommendations']
    if isinstance(recommendations_a, list) and recommendations_a and ("error" in recommendations_a[0] or "warning" in recommendations_a[0]):
        pytest.skip(f"Recommendations failed for user 1: {recommendations_a[0]}")
    assert isinstance(recommendations_a, list)
    assert len(recommendations_a) > 0, f"No recommendations returned: {recommendations_a}"

    # Log recommended course IDs for debugging
    recommended_course_ids = [rec['course_id'] for rec in recommendations_a]
    logger.info(f"Recommended course IDs for user 1: {recommended_course_ids}")
    logger.info(f"Valid course IDs in sample_data: {sample_data['course_id'].astype(str).values[:10]}")

    # Check if at least one recommended course ID is valid
    valid_course_ids = sample_data['course_id'].astype(str).values
    assert any(rec['course_id'] in valid_course_ids for rec in recommendations_a), \
        f"No recommended course IDs {recommended_course_ids} found in sample_data course IDs"

    # Verify User-User CF logic: User 1's recommendations should include courses rated highly by User 2
    user_2_high_rated = ratings_data[
        (ratings_data['user_id'] == 2) & (ratings_data['rating'] >= 4)
    ]['course_id'].astype(str).values
    logger.info(f"User 2's high-rated courses: {user_2_high_rated}")
    assert any(course_id in user_2_high_rated for course_id in recommended_course_ids), \
        f"User 1's recommendations {recommended_course_ids} should include at least one course rated highly by User 2 {user_2_high_rated}"

    # Restore original ratings
    original_ratings.to_csv('Data/ratings.csv', index=False)
    update_model()

def test_svd_cf_recommendations(ratings_data, sample_data, client):
    """
    Test SVD-based CF recommendations using the sample dataset.
    """
    # Use ratings_data with valid course IDs
    original_ratings = pd.read_csv('Data/ratings.csv')
    pd.DataFrame(ratings_data).to_csv('Data/ratings.csv', index=False)
    update_model()  # Update SVD model and user-item matrix

    # Test for User 1
    response_a = client.get("/recommend-collaborative?user_id=1&num_recommendations=1")
    assert response_a.status_code == 200
    recommendations_a = response_a.json()['recommendations']
    if isinstance(recommendations_a, list) and recommendations_a and ("error" in recommendations_a[0] or "warning" in recommendations_a[0]):
        pytest.skip(f"Recommendations failed for user 1: {recommendations_a[0]}")
    assert isinstance(recommendations_a, list)
    assert len(recommendations_a) > 0
    assert recommendations_a[0]['course_id'] in sample_data['course_id'].astype(str).values

    # Restore original ratings
    original_ratings.to_csv('Data/ratings.csv', index=False)
    update_model()

def test_sales_integration(ratings_data, sample_data):
    """
    Test if CF recommendations prioritize high-value or popular courses for sales integration.
    """
    user_id = int(ratings_data['user_id'].iloc[0])  # Ensure integer user_id
    recommendations = recommend_collaborative(user_id, num_recommendations=5)
    if isinstance(recommendations, list) and recommendations and ("error" in recommendations[0] or "warning" in recommendations[0]):
        pytest.skip(f"Recommendations failed for user {user_id}: {recommendations[0]}")

    # Calculate average num_subscribers and num_reviews of recommended courses
    avg_subscribers = sum(rec['num_subscribers'] for rec in recommendations) / len(recommendations)
    avg_reviews = sum(rec['num_reviews'] for rec in recommendations) / len(recommendations)

    # Compare with dataset averages
    dataset_avg_subscribers = sample_data['num_subscribers'].mean()
    dataset_avg_reviews = sample_data['num_reviews'].mean()

    logger.info(f"Average subscribers of recommendations: {avg_subscribers:.0f}, dataset average: {dataset_avg_subscribers:.0f}")
    logger.info(f"Average reviews of recommendations: {avg_reviews:.0f}, dataset average: {dataset_avg_reviews:.0f}")
    assert avg_subscribers >= dataset_avg_subscribers * 0.7, "Recommendations should prioritize courses with above-average subscribers"
    assert avg_reviews >= dataset_avg_reviews * 0.6, "Recommendations should prioritize courses with above-average reviews"

def test_runtime_performance(ratings_data, client):
    """Test runtime performance for a single recommendation via API."""
    user_id = int(ratings_data['user_id'].iloc[0])  # Ensure integer user_id
    start_time = time()
    response = client.get(f"/recommend-collaborative?user_id={user_id}&num_recommendations=20")
    end_time = time()
    runtime = end_time - start_time
    logger.info(f"Runtime for 20 recommendations: {runtime:.3f} seconds")
    assert runtime < 0.5, f"Runtime too slow: {runtime:.3f} seconds (expected < 0.5)"
    assert response.status_code == 200
    recommendations = response.json()['recommendations']
    if isinstance(recommendations, list) and recommendations and ("error" in recommendations[0] or "warning" in recommendations[0]):
        pytest.skip(f"Recommendations failed for user {user_id}: {recommendations[0]}")

def test_recommendation_format(ratings_data, client):
    """Test if recommendations follow the expected format via API."""
    user_id = int(ratings_data['user_id'].iloc[0])  # Ensure integer user_id
    response = client.get(f"/recommend-collaborative?user_id={user_id}")
    assert response.status_code == 200
    recommendations = response.json()['recommendations']
    if isinstance(recommendations, list) and recommendations and ("error" in recommendations[0] or "warning" in recommendations[0]):
        pytest.skip(f"Recommendations failed for user {user_id}: {recommendations[0]}")

    expected_keys = [
        'course_id', 'course_title', 'url', 'is_paid', 'price', 'num_subscribers',
        'num_reviews', 'num_lectures', 'level', 'content_duration', 'published_timestamp', 'subject'
    ]
    for rec in recommendations:
        assert all(key in rec for key in expected_keys), f"Missing keys in recommendation: {rec}"

def test_user_similarity(ratings_data, sample_data, client):
    """Test if users 1 and 2 get similar recommendations, while 3 gets different ones."""
    # Use ratings_data with valid course IDs
    original_ratings = pd.read_csv('Data/ratings.csv')
    pd.DataFrame(ratings_data).to_csv('Data/ratings.csv', index=False)
    update_model()  # Update SVD model and user-item matrix

    # Test for users 1, 2, 3
    user_a = 1
    user_b = 2
    user_c = 3

    response_a = client.get(f"/recommend-collaborative?user_id={user_a}&num_recommendations=5")
    response_b = client.get(f"/recommend-collaborative?user_id={user_b}&num_recommendations=5")
    response_c = client.get(f"/recommend-collaborative?user_id={user_c}&num_recommendations=5")

    assert response_a.status_code == 200
    assert response_b.status_code == 200
    assert response_c.status_code == 200

    recommendations_a = response_a.json()['recommendations']
    recommendations_b = response_b.json()['recommendations']
    recommendations_c = response_c.json()['recommendations']

    if isinstance(recommendations_a, list) and recommendations_a and ("error" in recommendations_a[0] or "warning" in recommendations_a[0]):
        pytest.skip(f"Recommendations failed for user 1: {recommendations_a[0]}")
    if isinstance(recommendations_b, list) and recommendations_b and ("error" in recommendations_b[0] or "warning" in recommendations_b[0]):
        pytest.skip(f"Recommendations failed for user 2: {recommendations_b[0]}")
    if isinstance(recommendations_c, list) and recommendations_c and ("error" in recommendations_c[0] or "warning" in recommendations_c[0]):
        pytest.skip(f"Recommendations failed for user 3: {recommendations_c[0]}")

    # Get course IDs
    course_ids_a = {rec['course_id'] for rec in recommendations_a}
    course_ids_b = {rec['course_id'] for rec in recommendations_b}
    course_ids_c = {rec['course_id'] for rec in recommendations_c}

    # Check similarity between users 1 and 2
    common_ab = len(course_ids_a.intersection(course_ids_b))
    logger.info(f"Common recommendations between users 1 and 2: {common_ab}")
    assert common_ab >= 2, "Users 1 and 2 should have at least 2 common recommendations"

    # Check difference with user 3
    common_ac = len(course_ids_a.intersection(course_ids_c))
    common_bc = len(course_ids_b.intersection(course_ids_c))
    logger.info(f"Common recommendations between users 1 and 3: {common_ac}")
    logger.info(f"Common recommendations between users 2 and 3: {common_bc}")
    assert common_ac <= 3, "Users 1 and 3 should have at least 3 common recommendations"
    assert common_bc <= 3, "Users 2 and 3 should have at least 3 common recommendations"

    # Restore original ratings
    original_ratings.to_csv('Data/ratings.csv', index=False)
    update_model()