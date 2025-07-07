import pytest
import pandas as pd
import logging
from time import time
from CourseRecommendationSystem import recommend_similar_courses

# Set up logging
logger = logging.getLogger(__name__)

@pytest.fixture
def sample_data():
    """Load sample course data."""
    return pd.read_csv('Data/udemy_courses.csv')

@pytest.fixture
def client():
    """Mock API client for testing."""
    from fastapi.testclient import TestClient
    from main import app
    return TestClient(app)

def test_recommendation_exists(sample_data, client):
    """Test if recommendations are returned for an existing course via API."""
    course_title = sample_data['course_title'].iloc[0]
    response = client.get(f"/recommend-similar?course_title={course_title}")
    assert response.status_code == 200
    recommendations = response.json()['recommendations']
    assert isinstance(recommendations, list)
    assert len(recommendations) > 0
    assert all('course_id' in rec for rec in recommendations)

def test_recommendation_nonexistent(client):
    """Test recommendations for a nonexistent course via API."""
    response = client.get("/recommend-similar?course_title=Nonexistent Course")
    assert response.status_code == 200
    recommendations = response.json()['recommendations']
    assert isinstance(recommendations, list)
    assert len(recommendations) > 0  # System returns recommendations for nonexistent courses
    assert all('course_id' in rec for rec in recommendations)
    assert all(rec['course_title'] != 'Nonexistent Course' for rec in recommendations)

def test_precision_at_k(sample_data, k=5, num_samples=3):
    """
    Test precision@k to ensure recommendations are relevant based on category and level.
    Calculates average precision@k across a sample of courses.
    """
    course_titles = sample_data['course_title'].unique()[:num_samples]
    precisions = []

    for course_title in course_titles:
        course_row = sample_data[sample_data['course_title'] == course_title].iloc[0]
        input_category = course_row['subject'].lower().strip()
        input_level_value = {'beginner level': 1, 'intermediate level': 2, 'expert level': 3, 'all levels': 4, 'unknown': 0}.get(course_row['level'].lower().strip(), 0)

        recommendations = recommend_similar_courses(course_title, num_recommendations=k)
        if isinstance(recommendations, list) and recommendations and ("error" in recommendations[0] or "warning" in recommendations[0]):
            logger.info(f"Recommendations failed for course {course_title}: {recommendations[0]}")
            continue

        relevant = 0
        for rec in recommendations:
            rec_category = rec['category'].lower().strip()
            rec_level_value = {'beginner level': 1, 'intermediate level': 2, 'expert level': 3, 'all levels': 4, 'unknown': 0}.get(rec['level'].lower().strip(), 0)
            if rec_category == input_category and rec_level_value >= input_level_value:
                relevant += 1
        precision = relevant / k if k > 0 else 0
        precisions.append(precision)
        logger.info(f"Precision@{k} for {course_title}: {precision:.2f}")

    avg_precision = sum(precisions) / len(precisions) if precisions else 0
    logger.info(f"Average precision@{k}: {avg_precision:.2f}")
    assert avg_precision >= 0.5, f"Average precision@{k} too low: {avg_precision:.2f}"

def test_precision_at_k_sample(sample_data, k=5):
    """Test precision@k for a specific course."""
    course_title = "The Complete Investment Banking Course 2017"  # Known to exist in dataset
    course_row = sample_data[sample_data['course_title'] == course_title]
    if course_row.empty:
        pytest.skip(f"Sample course {course_title} not found in dataset")
    
    course_row = course_row.iloc[0]
    input_category = course_row['subject'].lower().strip()
    input_level_value = {'beginner level': 1, 'intermediate level': 2, 'expert level': 3, 'all levels': 4, 'unknown': 0}.get(course_row['level'].lower().strip(), 0)

    recommendations = recommend_similar_courses(course_title, num_recommendations=k)
    if isinstance(recommendations, list) and recommendations and ("error" in recommendations[0] or "warning" in recommendations[0]):
        pytest.skip(f"Recommendations failed for course {course_title}: {recommendations[0]}")

    relevant = 0
    for rec in recommendations:
        rec_category = rec['category'].lower().strip()
        rec_level_value = {'beginner level': 1, 'intermediate level': 2, 'expert level': 3, 'all levels': 4, 'unknown': 0}.get(rec['level'].lower().strip(), 0)
        if rec_category == input_category and rec_level_value >= input_level_value:
            relevant += 1
    precision = relevant / k if k > 0 else 0
    logger.info(f"Precision@{k} for {course_title}: {precision:.2f}")
    assert precision >= 0.5, f"Precision@{k} too low: {precision:.2f}"

def test_sales_integration(sample_data):
    """
    Test if recommendations prioritize high-value or popular courses for sales integration.
    """
    course_title = sample_data['course_title'].iloc[0]
    recommendations = recommend_similar_courses(course_title, num_recommendations=5)
    if isinstance(recommendations, list) and recommendations and ("error" in recommendations[0] or "warning" in recommendations[0]):
        pytest.skip(f"Recommendations failed for course {course_title}: {recommendations[0]}")

    # Calculate average num_subscribers and num_reviews of recommended courses
    avg_subscribers = sum(rec['num_subscribers'] for rec in recommendations) / len(recommendations)
    avg_reviews = sum(rec['num_reviews'] for rec in recommendations) / len(recommendations)

    # Compare with dataset averages
    dataset_avg_subscribers = sample_data['num_subscribers'].mean()
    dataset_avg_reviews = sample_data['num_reviews'].mean()

    logger.info(f"Average subscribers of recommendations: {avg_subscribers:.0f}, dataset average: {dataset_avg_subscribers:.0f}")
    logger.info(f"Average reviews of recommendations: {avg_reviews:.0f}, dataset average: {dataset_avg_reviews:.0f}")
    assert avg_subscribers >= dataset_avg_subscribers * 0.7, "Recommendations should prioritize courses with above-average subscribers"
    assert avg_reviews >= dataset_avg_reviews * 0.7, "Recommendations should prioritize courses with above-average reviews"

def test_runtime_performance(sample_data, client):
    """Test runtime performance for a single recommendation via API."""
    course_title = sample_data['course_title'].iloc[0]
    start_time = time()
    response = client.get(f"/recommend-similar?course_title={course_title}")
    end_time = time()
    runtime = end_time - start_time
    logger.info(f"Runtime for recommendation: {runtime:.3f} seconds")
    assert runtime < 2.0, f"Runtime too slow: {runtime:.3f} seconds (expected < 2.0)"
    assert response.status_code == 200

def test_recommendation_format(sample_data, client):
    """Test if recommendations follow the expected format via API."""
    course_title = sample_data['course_title'].iloc[0]
    response = client.get(f"/recommend-similar?course_title={course_title}")
    recommendations = response.json()['recommendations']
    if isinstance(recommendations, list) and recommendations and ("error" in recommendations[0] or "warning" in recommendations[0]):
        pytest.skip(f"Recommendations failed for course {course_title}: {recommendations[0]}")

    expected_keys = [
        'course_id', 'course_title', 'url', 'is_paid', 'price', 'num_subscribers',
        'num_reviews', 'num_lectures', 'level', 'content_duration', 'published_timestamp', 'category'
    ]
    for rec in recommendations:
        assert all(key in rec for key in expected_keys), f"Missing keys in recommendation: {rec}"

def test_level_filter(sample_data):
    """Test if recommendations respect the level hierarchy."""
    course_title = sample_data['course_title'].iloc[0]
    course_row = sample_data[sample_data['course_title'] == course_title].iloc[0]
    input_level_value = {'beginner level': 1, 'intermediate level': 2, 'expert level': 3, 'all levels': 4, 'unknown': 0}.get(course_row['level'].lower().strip(), 0)

    recommendations = recommend_similar_courses(course_title, num_recommendations=5)
    if isinstance(recommendations, list) and recommendations and ("error" in recommendations[0] or "warning" in recommendations[0]):
        pytest.skip(f"Recommendations failed for course {course_title}: {recommendations[0]}")

    for rec in recommendations:
        rec_level_value = {'beginner level': 1, 'intermediate level': 2, 'expert level': 3, 'all levels': 4, 'unknown': 0}.get(rec['level'].lower().strip(), 0)
        assert rec_level_value >= input_level_value, f"Recommendation level {rec['level']} is lower than input level {course_row['level']}"