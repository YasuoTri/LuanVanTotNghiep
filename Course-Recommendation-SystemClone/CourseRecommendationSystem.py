import pandas as pd
import numpy as np
from sklearn.metrics.pairwise import cosine_similarity
from sklearn.feature_extraction.text import TfidfVectorizer
import pickle
import os
import logging
import sys
import random
# Set up logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

def resource_path(relative_path):
    """Lấy đường dẫn file phù hợp cho cả khi chạy từ PyInstaller"""
    if hasattr(sys, '_MEIPASS'):
        return os.path.join(sys._MEIPASS, relative_path)
    return os.path.join(os.path.abspath("."), relative_path)

def normalize_difficulty(difficulty):
    """Normalize course difficulty levels to a score."""
    mapping = {'Beginner': 0.3, 'Intermediate': 0.6, 'Advanced': 0.9}
    return mapping.get(difficulty, 0.3)

# Define level hierarchy for comparison
LEVEL_HIERARCHY = {
    'beginner level': 2,
    'intermediate level': 3,
    'expert level': 4,
    'all levels': 1,
    'unknown': 0
}

def get_level_value(level):
    """Convert course level to numerical value based on hierarchy."""
    return LEVEL_HIERARCHY.get(level.lower().strip(), 0)

def recommend_similar_courses(course_title, level=None, subject=None, data_file='Data/udemy_courses.csv', num_recommendations=20):
    """
    Recommend courses similar to the input course based on course_title, level, and subject.
    
    Parameters:
    - course_title (str): Title of the input course.
    - level (str, optional): Level of the input course (e.g., 'beginner', 'intermediate', 'advanced').
    - subject (str, optional): Subject/category of the input course.
    - data_file (str): Path to the CSV file containing course data.
    - num_recommendations (int): Number of courses to recommend.
    
    Returns:
    - list: List of dictionaries containing recommended course details.
    """
    try:
        # Load precomputed models
        # with open('models/courses.pkl', 'rb') as f:
        #     df = pickle.load(f)
        # with open('models/tfidf_vectorizer.pkl', 'rb') as f:
        #     vectorizer = pickle.load(f)
        # with open('models/tfidf_matrix.pkl', 'rb') as f:
        #     tfidf_matrix = pickle.load(f)
        with open(resource_path('models/courses.pkl'), 'rb') as f:
            df = pickle.load(f)
        with open(resource_path('models/tfidf_vectorizer.pkl'), 'rb') as f:
            vectorizer = pickle.load(f)
        with open(resource_path('models/tfidf_matrix.pkl'), 'rb') as f:
            tfidf_matrix = pickle.load(f)
        logger.info(f"Loaded {len(df)} courses and precomputed TF-IDF matrix")
        
        # Clean data
        df['course_title'] = df['course_title'].fillna('')
        df['level'] = df['level'].str.lower().fillna('unknown')
        df['subject'] = df['subject'].str.lower().fillna('unknown')
        
        # Normalize input
        course_title = course_title.lower()
        level = level.lower() if level else None
        subject = subject.lower() if subject else None
        
        # Check if course_title exists in dataset
        input_course = df[df['course_title'].str.lower() == course_title]
        
        if not input_course.empty:
            # Course found in dataset
            input_course = input_course.iloc[0]
            input_level_value = get_level_value(input_course['level'])
            input_text = ' '.join([
                input_course['course_title'].lower(),
                input_course['level'].lower(),
                input_course['subject'].lower()
            ])
            logger.info(f"Course '{course_title}' found in dataset with level '{input_course['level']}' and subject '{input_course['subject']}'.")
        else:
            # Course not found, use input values or defaults
            if level and subject:
                logger.info(f"Course '{course_title}' not found in dataset. Using provided level '{level}' and subject '{subject}'.")
                input_level_value = get_level_value(level)
                input_text = ' '.join([
                    course_title,
                    level,
                    subject
                ])
            else:
                logger.info(f"Course '{course_title}' not found in dataset. Using default level 'beginner' and subject 'unknown'.")
                input_level_value = get_level_value('beginner')
                input_text = ' '.join([
                    course_title,
                    'beginner',
                    'unknown'
                ])
        
        # Compute input vector and similarity
        input_vector = vectorizer.transform([input_text])
        similarity_scores = cosine_similarity(input_vector, tfidf_matrix).flatten()
        logger.info(f"Similarity scores range: {similarity_scores.min()} to {similarity_scores.max()}")
        
        # Create similarity DataFrame
        similarity_df = pd.DataFrame({
            'course_id': df['course_id'],
            'course_title': df['course_title'],
            'similarity': similarity_scores,
            'level': df['level'],
            'subject': df['subject'],
            'num_reviews': df['num_reviews'],
            'num_subscribers': df['num_subscribers']
        })
        
        # Filter candidates (exclude the input course if it exists in dataset)
        candidates = similarity_df[
            (similarity_df['course_title'].str.lower() != course_title) |
            ((level is not None) & (similarity_df['level'].str.lower() != level)) |
            ((subject is not None) & (similarity_df['subject'].str.lower() != subject))
        ]
        candidates = candidates[similarity_df['similarity'] > 0.5]
        logger.info(f"Number of candidates after filtering: {len(candidates)}")
        
        if candidates.empty:
            logger.warning("No candidates found after filtering.")
            return []
            # return [{"warning": "No similar courses found due to filtering."}]
        
        # Apply level filter
        candidates = candidates.copy()
        candidates['level_value'] = candidates['level'].apply(get_level_value)
        candidates = candidates[candidates['level_value'] >= input_level_value]
        logger.info(f"Number of candidates after level filtering: {len(candidates)}")
        
        if candidates.empty:
            logger.warning("No candidates found after level filtering.")
            return []
            # return [{"warning": "No courses found with level equal to or higher than the input course."}]
        
        # Sort by similarity and select top candidates
        top_candidates = candidates.sort_values(by='similarity', ascending=False).head(num_recommendations)
        logger.info(f"Top candidates: {top_candidates[['course_title', 'similarity', 'level']].to_dict('records')}")
        
        # Format output
        recommendations = []
        for _, row in top_candidates.iterrows():
            course_row = df[df['course_title'] == row['course_title']].iloc[0]
            recommendations.append({
                'course_id': str(course_row['course_id']),
                'course_title': str(course_row['course_title']),
                'url': str(course_row['url']),
                'image': str(course_row['image']),
                'is_paid': bool(course_row['is_paid']),
                'price': str(course_row['price']),
                'course_rating': int(course_row['course_rating']),
                'num_subscribers': int(course_row['num_subscribers']),
                'num_reviews': int(course_row['num_reviews']),
                'num_lectures': int(course_row['num_lectures']),
                'level': str(course_row['level']),
                'content_duration': float(course_row['content_duration']),
                'published_timestamp': str(course_row['published_timestamp']),
                'category': str(course_row['subject'])
            })
        
        return recommendations
    
    except Exception as e:
        logger.error(f"Error in recommendation: {str(e)}")
        return []

def recommend_user_user_cf(user_id, ratings_file='Data/ratings.csv', courses_file='Data/udemy_courses.csv', num_recommendations=30):
    try:
        # Load data với dtype nhất quán
        ratings = pd.read_csv(ratings_file, dtype={'user_id': str, 'course_id': str})
        courses = pd.read_csv(courses_file, dtype={'course_id': str})

        # Tạo user-item matrix
        user_item_matrix = ratings.pivot_table(index='user_id', columns='course_id', values='rating').fillna(0)

        # Kiểm tra user có tồn tại không
        if str(user_id) not in user_item_matrix.index:
            return []
            # return [{"warning": f"User {user_id} not found in dataset."}]

        # Tính similarity giữa các user
        similarity = cosine_similarity(user_item_matrix)
        similarity_df = pd.DataFrame(similarity, index=user_item_matrix.index, columns=user_item_matrix.index)

        # Lấy các user tương tự với ngưỡng > 0.3, loại bỏ chính mình
        similar_users = similarity_df.loc[str(user_id)]
        similar_users = similar_users[similar_users >= 0.5].sort_values(ascending=False)
        similar_users = similar_users.drop(labels=str(user_id), errors='ignore')[:5]

        # Lấy các đánh giá cao (rating >= 4) từ các user tương tự
        similar_user_ids = similar_users.index.tolist()
        similar_ratings = ratings[ratings['user_id'].isin(similar_user_ids)]
        high_rated = similar_ratings[similar_ratings['rating'] >= 4]

        # Loại bỏ các khóa học user đã đánh giá
        user_rated = ratings[ratings['user_id'] == str(user_id)]['course_id'].tolist()
        recommendable = high_rated[~high_rated['course_id'].isin(user_rated)]

        # Tính điểm trung bình và tần suất
        top_courses = (
            recommendable.groupby('course_id')
            .agg(score=('rating', 'mean'), count=('rating', 'count'))
            .sort_values(['score', 'count'], ascending=False)
            .head(num_recommendations)
            .reset_index()
        )

        # Merge với thông tin khóa học
        recommendations = pd.merge(top_courses, courses, on='course_id', how='left')

        result = []
        for _, row in recommendations.iterrows():
            result.append({
                'course_id': row['course_id'],
                'course_title': row['course_title'],
                'url': str(row['url']),
                'image':str(row['image']),
                'course_rating': int(course_row['course_rating']),
                'is_paid': row['is_paid'],
                'price': row['price'],
                'num_subscribers': row['num_subscribers'],
                'num_reviews': row['num_reviews'],
                'num_lectures': row['num_lectures'],
                'level': row['level'],
                'content_duration': row['content_duration'],
                'published_timestamp': row['published_timestamp'],
                'subject': row['subject']
            })

        # Nếu chưa đủ, dùng fallback bằng CBF
        if len(result) < num_recommendations:
            user_highest = ratings[ratings['user_id'] == str(user_id)].sort_values(by='rating', ascending=False).head(1)
            if not user_highest.empty:
                course_id = user_highest.iloc[0]['course_id']
                course_row = courses[courses['course_id'] == course_id]
                if not course_row.empty:
                    course_title = course_row.iloc[0]['course_title']
                    cbf_recs = recommend_similar_courses(course_title)
                    existing_ids = set([c['course_id'] for c in result])
                    for cbf_course in cbf_recs:
                        if 'course_id' not in cbf_course:
                            continue
                        if cbf_course['course_id'] not in existing_ids:
                            result.append(cbf_course)
                            if len(result) >= num_recommendations:
                                break

        # return result if result else [{"message": "No strong recommendations found."}]
        return result if result else []

    except Exception as e:
        return []

def update_model(data_file='Data/udemy_courses.csv'):
    """
    Preprocess the Udemy dataset and save the processed data for recommendations.
    
    Parameters:
    - data_file (str): Path to the CSV file containing course data.
    
    Returns:
    - bool: True if update is successful.
    """
    logger.info("Starting model update")
    if not os.path.exists('models'):
        os.makedirs('models')

    try:
        # Load and preprocess data
        df = pd.read_csv(data_file)
        required_columns = [
            'course_id', 'course_title', 'url','image','course_rating', 'is_paid', 'price', 'num_subscribers',
            'num_reviews', 'num_lectures', 'level', 'content_duration', 'published_timestamp', 'subject'
        ]
        missing_columns = [col for col in required_columns if col not in df.columns]
        if missing_columns:
            logger.error(f"Missing columns in {data_file}: {missing_columns}")
            raise Exception(f"Missing columns in {data_file}: {missing_columns}")
        
        df['course_title'] = df['course_title'].fillna('')
        df['level'] = df['level'].str.lower().fillna('unknown')
        df['image'] = df['image'].str.lower().fillna('unknown')
        df['course_rating'] = pd.to_numeric(df['course_rating'], errors='coerce').fillna(0).astype(int)
        df['subject'] = df['subject'].str.lower().fillna('unknown')
        df['price'] = pd.to_numeric(df['price'], errors='coerce').fillna(0).astype(str)
        df['num_subscribers'] = pd.to_numeric(df['num_subscribers'], errors='coerce').fillna(0).astype(int)
        df['num_reviews'] = pd.to_numeric(df['num_reviews'], errors='coerce').fillna(0).astype(int)
        df['num_lectures'] = pd.to_numeric(df['num_lectures'], errors='coerce').fillna(0).astype(int)
        df['content_duration'] = pd.to_numeric(df['content_duration'], errors='coerce').fillna(0).astype(float)
        df['published_timestamp'] = df['published_timestamp'].fillna('unknown')
        df['course_id'] = df['course_id'].astype(str)
        
        courses_list = df[[
            'course_id', 'course_title', 'url','image','course_rating', 'is_paid', 'price', 'num_subscribers',
            'num_reviews', 'num_lectures', 'level', 'content_duration', 'published_timestamp', 'subject'
        ]]
        
        # Compute TF-IDF matrix for CBF
        df['combined_text'] = (
            df['course_title'].str.lower() + ' ' +
            df['level'].str.lower() + ' ' +
            df['subject'].str.lower()
        )
        vectorizer = TfidfVectorizer(stop_words='english')
        tfidf_matrix = vectorizer.fit_transform(df['combined_text'])
        
        # # Load ratings data for CF
        ratings = pd.read_csv('Data/ratings.csv')
        
        # Create user-item matrix
        user_item_matrix = ratings.pivot(index='user_id', columns='course_id', values='rating').fillna(0)
        
        with open(resource_path('models/courses.pkl'), 'wb') as f:
            pickle.dump(courses_list, f)
        with open(resource_path('models/tfidf_vectorizer.pkl'), 'wb') as f:
            pickle.dump(vectorizer, f)
        with open(resource_path('models/tfidf_matrix.pkl'), 'wb') as f:
              pickle.dump(tfidf_matrix, f)
        with open(resource_path('models/user_item_matrix.pkl'), 'wb') as f:
           pickle.dump(user_item_matrix, f)
        logger.info("All files saved successfully")
        return True
    except Exception as e:
        logger.error(f"Error in model update: {str(e)}")
        raise Exception(f"Error in model update: {str(e)}")