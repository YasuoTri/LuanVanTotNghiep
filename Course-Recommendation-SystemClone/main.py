# import os
# from dotenv import load_dotenv
# import pickle
# import pandas as pd
# from fastapi import FastAPI, HTTPException, Depends, Security, BackgroundTasks
# from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
# from pydantic import BaseModel
# from sqlalchemy import create_engine, Column, Integer, String, Float, Boolean, DateTime, ForeignKey, text
# from sqlalchemy.ext.declarative import declarative_base
# from sqlalchemy.orm import sessionmaker, Session
# from surprise import SVD, Dataset, Reader
# from sentence_transformers import SentenceTransformer
# import faiss
# import numpy as np
# import jwt
# import logging
# from typing import List, Optional, Dict
# from datetime import datetime
# from contextlib import contextmanager
# from pathlib import Path
# # Đường dẫn đến file .env của Laravel
# dotenv_path = os.path.join(os.path.dirname(os.path.dirname(__file__)), 'course-recommendation', '.env')
# # Load các biến từ file .env
# load_dotenv(dotenv_path)
# # Cấu hình logging
# logging.basicConfig(
#     level=logging.INFO,
#     format='%(asctime)s - %(levelname)s - %(message)s',
#     handlers=[
#         logging.FileHandler('logs/api.log'),
#         logging.StreamHandler()
#     ]
# )
# logger = logging.getLogger(__name__)

# # Cấu hình cơ sở dữ liệu (MySQL)
# DATABASE_URL = "mysql+pymysql://root:123@localhost:8200/course_recommendation5"
# try:
#     engine = create_engine(DATABASE_URL)
#     SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)
# except Exception as e:
#     logger.error(f"Failed to connect to database: {e}")
#     raise

# # Hàm cache file
# def sanitize_cache_key(key: str) -> str:
#     return key.replace(':', '_').replace('/', '_').replace('\\', '_')

# def cache_get(key: str):
#     cache_key = sanitize_cache_key(key)
#     cache_file = Path(f"cache/{cache_key}.pkl")
#     if cache_file.exists():
#         with open(cache_file, 'rb') as f:
#             return pickle.load(f)
#     return None

# def cache_set(key: str, value, ttl: int = 300):
#     cache_key = sanitize_cache_key(key)
#     cache_file = Path(f"cache/{cache_key}.pkl")
#     cache_file.parent.mkdir(exist_ok=True)
#     with open(cache_file, 'wb') as f:
#         pickle.dump(value, f)

# # Cấu hình JWT
# # JWT_SECRET = "w8CRIzwhgckY4AMOeUCh77VLEp3UpyYXXnnI5lRq4D7Ue0c8ZkQ1czmKsdG6Avru"
# # Lấy JWT_SECRET từ biến môi trường
# JWT_SECRET = os.getenv('JWT_SECRET')
# JWT_ALGORITHM = "HS256"

# # Khởi tạo FastAPI
# app = FastAPI()
# security = HTTPBearer()

# Base = declarative_base()

# # Định nghĩa mô hình SQLAlchemy
# class User(Base):
#     __tablename__ = "users"
#     id = Column(Integer, primary_key=True, index=True)
#     userid_DI = Column(String(255), unique=True, nullable=False)
#     final_cc_cname_DI = Column(String(100), default="Unknown")
#     LoE_DI = Column(String(50), default="Unknown")
#     YoB = Column(Integer, nullable=True)

# class Course(Base):
#     __tablename__ = "courses"
#     id = Column(Integer, primary_key=True, index=True)
#     course_name = Column(String(255), nullable=False)
#     course_description = Column(String, nullable=True)
#     difficulty_level = Column(String(50), nullable=True)

# class Interaction(Base):
#     __tablename__ = "interactions"
#     id = Column(Integer, primary_key=True, index=True)
#     user_id = Column(Integer, ForeignKey("users.id"), nullable=False)
#     course_id = Column(Integer, ForeignKey("courses.id"), nullable=False)
#     rating = Column(Float, nullable=True)
#     viewed = Column(Boolean, default=False)
#     timestamp = Column(DateTime, default=datetime.utcnow)

# class SimilarityMatrix(Base):
#     __tablename__ = "similarity_matrix"
#     course_id_1 = Column(Integer, ForeignKey("courses.id"), primary_key=True)
#     course_id_2 = Column(Integer, ForeignKey("courses.id"), primary_key=True)
#     similarity_score = Column(Float, nullable=False)

# # Tạo bảng và index
# try:
#     Base.metadata.create_all(bind=engine)
#     with engine.connect() as conn:
#         conn.execute(text("CREATE INDEX IF NOT EXISTS idx_interactions_user_id ON interactions (user_id)"))
#         conn.execute(text("CREATE INDEX IF NOT EXISTS idx_interactions_course_id ON interactions (course_id)"))
#         conn.execute(text("CREATE INDEX IF NOT EXISTS idx_course_name ON courses (course_name)"))
#         conn.execute(text("ALTER TABLE similarity_matrix DROP INDEX IF EXISTS idx_course_id_2"))
#         conn.execute(text("CREATE INDEX IF NOT EXISTS idx_similarity_course_id_1 ON similarity_matrix (course_id_1)"))
#         conn.execute(text("CREATE INDEX IF NOT EXISTS idx_similarity_course_id_2 ON similarity_matrix (course_id_2)"))
#         conn.commit()
#     logger.info("Database tables and indexes created")
# except Exception as e:
#     logger.error(f"Failed to create tables: {e}")
#     raise

# # Định nghĩa mô hình Pydantic
# class UserProfile(BaseModel):
#     userid_DI: str
#     final_cc_cname_DI: str = "Unknown"
#     LoE_DI: str = "Unknown"
#     YoB: Optional[int] = None

# class LoginInput(BaseModel):
#     userid_DI: str

# class RatingInput(BaseModel):
#     user_id: int
#     course_id: int
#     rating: float

# class RecommendInput(BaseModel):
#     user_id: Optional[int] = None
#     course_name: Optional[str] = None

# class RecommendOutput(BaseModel):
#     courses: List[str]

# # Khởi tạo mô hình và Faiss
# class RecommendationSystem:
#     def __init__(self):
#         self.svd = SVD(n_factors=100, n_epochs=20, random_state=42)
#         self.model = SentenceTransformer('all-MiniLM-L6-v2')
#         self.dimension = 384
#         self.faiss_index = None
#         self.course_ids = []
#         self.load_data()

#     def load_data(self):
#         try:
#             courses = pd.read_sql("SELECT * FROM courses", engine)
#             if courses.empty:
#                 raise ValueError("No courses found in database")
#             embeddings = self.compute_embeddings(courses['course_description'].fillna('').tolist())
#             self.build_faiss_index(embeddings, courses['id'].tolist())
#             logger.info("Loaded courses and built Faiss index")
#         except Exception as e:
#             logger.error(f"Failed to load data: {e}")
#             raise

#     def compute_embeddings(self, descriptions: List[str]) -> np.ndarray:
#         cache_key = "course_embeddings"
#         cached = cache_get(cache_key)
#         if cached is not None:
#             logger.info("Loaded embeddings from file cache")
#             return cached
#         embeddings = self.model.encode(descriptions, show_progress_bar=True)
#         cache_set(cache_key, embeddings, ttl=3600)
#         logger.info("Saved embeddings to file cache")
#         return embeddings

#     def build_faiss_index(self, embeddings: np.ndarray, course_ids: List[int]):
#         self.faiss_index = faiss.IndexFlatIP(self.dimension)
#         faiss.normalize_L2(embeddings)
#         self.faiss_index.add(embeddings)
#         self.course_ids = course_ids
#         logger.info("Faiss index built")

#     def update_similarity_matrix(self, db: Session, background_tasks):
#         try:
#             logger.info("Starting similarity matrix update")
#             query = """
#             SELECT c.*
#             FROM courses c
#             JOIN (
#                 SELECT course_id
#                 FROM interactions
#                 GROUP BY course_id
#                 ORDER BY COUNT(*) DESC
#                 LIMIT 1000
#             ) i ON c.id = i.course_id
#             """
#             courses = pd.read_sql(query, db.bind)
            
#             if len(courses) < 2:
#                 logger.warning("Not enough courses to compute similarity")
#                 raise ValueError("Need at least 2 courses to compute similarity")
            
#             logger.info(f"Found {len(courses)} courses for similarity matrix update")

#             # Compute embeddings
#             cache_key = "course_embeddings"
#             embeddings = cache_get(cache_key)
#             if embeddings is None:
#                 descriptions = courses['course_description'].fillna('').tolist()
#                 logger.info("Computing embeddings for courses")
#                 embeddings = self.model.encode(descriptions, show_progress_bar=True)
#                 cache_set(cache_key, embeddings, ttl=3600)
#                 logger.info("Cached course embeddings")
#             else:
#                 logger.info("Loaded embeddings from cache")

#             # Compute similarity matrix using NumPy
#             logger.info("Computing similarity matrix")
#             scores = np.dot(embeddings, embeddings.T)
            
#             # Prepare data for bulk insert
#             similarity_data = []
#             for i, cid1 in enumerate(courses['id']):
#                 for j, cid2 in enumerate(courses['id']):
#                     if i < j:
#                         similarity_data.append({
#                             'course_id_1': cid1,
#                             'course_id_2': cid2,
#                             'similarity_score': float(scores[i, j])
#                         })

#             # Clear existing similarity_matrix
#             logger.info("Clearing existing similarity matrix")
#             db.execute(text("DELETE FROM similarity_matrix"))
            
#             # Bulk insert similarity data
#             logger.info("Inserting similarity matrix into database")
#             chunk_size = 10000
#             for i in range(0, len(similarity_data), chunk_size):
#                 chunk = similarity_data[i:i + chunk_size]
#                 db.execute(
#                     text("""
#                     INSERT INTO similarity_matrix (course_id_1, course_id_2, similarity_score)
#                     VALUES (:course_id_1, :course_id_2, :similarity_score)
#                     """),
#                     chunk
#                 )
#             db.commit()
#             logger.info(f"Inserted {len(similarity_data)} rows into similarity_matrix")
            
#         except Exception as e:
#             logger.error(f"Failed to update similarity matrix: {e}")
#             db.rollback()
#             raise

#     def get_difficulty_boost(self, education: Optional[str], difficulty_level: Optional[str]) -> float:
#         if not education or not difficulty_level:
#             return 0.0
#         education_clean = education.replace("'s", "")
#         boosts = {
#             ('Master', 'Advanced'): 2.0, ('Master', 'Intermediate'): 1.0, ('Master', 'Beginner'): -1.0,
#             ('Doctorate', 'Advanced'): 2.0, ('Doctorate', 'Intermediate'): 1.0, ('Doctorate', 'Beginner'): -1.0,
#             ('Bachelor', 'Advanced'): 0.5, ('Bachelor', 'Intermediate'): 1.0, ('Bachelor', 'Beginner'): 0.0,
#             ('Secondary', 'Beginner'): 2.0, ('Secondary', 'Intermediate'): 0.5, ('Secondary', 'Advanced'): -1.0,
#             ('High School', 'Beginner'): 2.0, ('High School', 'Intermediate'): 0.5, ('High School', 'Advanced'): -1.0
#         }
#         return boosts.get((education_clean, difficulty_level), 0.0)

#     def recommend(self, user_id: Optional[int], course_name: Optional[str], db: Session, alpha: float = 0.2) -> List[str]:
#         cache_key = f"recommend:{user_id}:{course_name}"
#         cached = cache_get(cache_key)
#         if cached is not None:
#             logger.info(f"Loaded recommendations from file cache for user_id={user_id}, course_name={course_name}")
#             return cached

#         try:
#             interactions = pd.read_sql("SELECT * FROM interactions", db.bind)
#             users = pd.read_sql("SELECT * FROM users", db.bind)
#             courses = pd.read_sql("SELECT * FROM courses", db.bind)
#         except Exception as e:
#             logger.error(f"Database query failed: {e}")
#             raise HTTPException(status_code=500, detail=f"Database query failed: {e}")

#         recommended_courses = []

#         if interactions.empty and not course_name:
#             raise HTTPException(status_code=400, detail="No interaction data available. Please provide a course_name.")

#         if not interactions.empty:
#             cache_key_svd = "svd_model"
#             cached_svd = cache_get(cache_key_svd)
#             if cached_svd is not None:
#                 self.svd = cached_svd
#                 logger.info("Loaded SVD model from file cache")
#             else:
#                 try:
#                     reader = Reader(rating_scale=(1, 5))
#                     surprise_data = Dataset.load_from_df(interactions[['user_id', 'course_id', 'rating']].dropna(), reader)
#                     trainset = surprise_data.build_full_trainset()
#                     self.svd.fit(trainset)
#                     cache_set(cache_key_svd, self.svd, ttl=3600)
#                     logger.info("Saved SVD model to file cache")
#                 except Exception as e:
#                     logger.error(f"SVD training failed: {e}")
#                     raise HTTPException(status_code=500, detail=f"SVD training failed: {e}")

#         country, education, age = 'Unknown', 'Unknown', 30
#         if user_id:
#             user_info = users[users['id'] == user_id]
#             if not user_info.empty:
#                 country = user_info['final_cc_cname_DI'].iloc[0]
#                 education = user_info['LoE_DI'].iloc[0]
#                 age = 2025 - user_info['YoB'].iloc[0] if not pd.isna(user_info['YoB'].iloc[0]) else 30

#         country_boost = 0.2 if country != 'Unknown' else 0
#         education_boost = 0.2 if education in ['Bachelor', 'Master'] else 0
#         age_boost = 0.2 if 20 <= age <= 40 else 0

#         user_history = interactions[interactions['user_id'] == user_id]
#         interacted_courses = set(user_history['course_id'])
#         course_ids = [cid for cid in courses['id'].unique() if cid not in interacted_courses]

#         cf_scores = {}
#         if not interactions.empty:
#             try:
#                 cf_predictions = [self.svd.predict(user_id, cid) for cid in course_ids]
#                 cf_scores = {pred.iid: pred.est for pred in cf_predictions}
#             except Exception as e:
#                 logger.error(f"SVD prediction failed: {e}")
#                 raise HTTPException(status_code=500, detail=f"SVD prediction failed: {e}")

#         if course_name:
#             course_row = courses[courses['course_name'] == course_name]
#             if course_row.empty:
#                 raise HTTPException(status_code=404, detail="Course not found")
#             course_id = course_row['id'].iloc[0]

#             query = """
# SELECT course_id_2, similarity_score
# FROM similarity_matrix
# WHERE course_id_1 = %s
# ORDER BY similarity_score DESC
# LIMIT 10
# """
#             try:
#                 similar_courses = pd.read_sql_query(query, db.bind, params=(course_id,))
#             except Exception as e:
#                 logger.error(f"Failed to query similarity_matrix: {e}")
#                 raise HTTPException(status_code=500, detail=f"Failed to query similarity_matrix: {e}")

#             content_scores = {row['course_id_2']: row['similarity_score'] for _, row in similar_courses.iterrows()}
#             hybrid_scores = {}
#             for cid in content_scores:
#                 if cid not in interacted_courses:
#                     hybrid_scores[cid] = alpha * content_scores[cid] + (1 - alpha) * cf_scores.get(cid, 3.0)

#             for cid in hybrid_scores:
#                 course_info = courses[courses['id'] == cid]
#                 if not course_info.empty:
#                     description = course_info['course_description'].iloc[0].lower() if pd.notna(course_info['course_description'].iloc[0]) else ''
#                     difficulty_level = course_info['difficulty_level'].iloc[0] if pd.notna(course_info['difficulty_level'].iloc[0]) else 'Unknown'
#                     score = hybrid_scores[cid]
#                     if country != 'Unknown' and 'global' in description:
#                         score += country_boost
#                     if education in ['Bachelor', 'Master'] and 'advanced' in description:
#                         score += education_boost
#                     if age_boost > 0 and 'professional' in description:
#                         score += age_boost
#                     difficulty_boost = self.get_difficulty_boost(education, difficulty_level)
#                     score += difficulty_boost
#                     hybrid_scores[cid] = score

#             top_courses = sorted(hybrid_scores.items(), key=lambda x: x[1], reverse=True)[:6]
#             seen_courses = set()
#             for course_id, _ in top_courses:
#                 course_name = courses[courses['id'] == course_id]['course_name'].iloc[0]
#                 if course_name not in seen_courses:
#                     recommended_courses.append(course_name)
#                     seen_courses.add(course_name)
#                 if len(recommended_courses) >= 6:
#                     break
#         else:
#             for cid in cf_scores:
#                 course_info = courses[courses['id'] == cid]
#                 if not course_info.empty:
#                     description = course_info['course_description'].iloc[0].lower() if pd.notna(course_info['course_description'].iloc[0]) else ''
#                     difficulty_level = course_info['difficulty_level'].iloc[0] if pd.notna(course_info['difficulty_level'].iloc[0]) else 'Unknown'
#                     score = cf_scores[cid]
#                     if country != 'Unknown' and 'global' in description:
#                         score += country_boost
#                     if education in ['Bachelor', 'Master'] and 'advanced' in description:
#                         score += education_boost
#                     if age_boost > 0 and 'professional' in description:
#                         score += age_boost
#                     difficulty_boost = self.get_difficulty_boost(education, difficulty_level)
#                     score += difficulty_boost
#                     cf_scores[cid] = score

#             top_courses = sorted(cf_scores.items(), key=lambda x: x[1], reverse=True)[:6]
#             seen_courses = set()
#             for course_id, _ in top_courses:
#                 course_name = courses[courses['id'] == course_id]['course_name'].iloc[0]
#                 if course_name not in seen_courses:
#                     recommended_courses.append(course_name)
#                     seen_courses.add(course_name)
#                 if len(recommended_courses) >= 6:
#                     break

#         cache_set(cache_key, recommended_courses, ttl=300)
#         logger.info(f"Saved recommendations to file cache for user_id={user_id}, course_name={course_name}")
#         return recommended_courses

# # Khởi tạo hệ thống đề xuất
# recommendation_system = RecommendationSystem()

# # Dependency để lấy session DB
# def get_db():
#     db = SessionLocal()
#     try:
#         yield db
#     finally:
#         db.close()

# # Xác thực JWT
# async def verify_token(credentials: HTTPAuthorizationCredentials = Security(security)):
#     try:
#         token = credentials.credentials
#         payload = jwt.decode(token, JWT_SECRET, algorithms=[JWT_ALGORITHM])
#         userid_DI = payload.get("sub")
#         if not userid_DI:
#             raise HTTPException(status_code=401, detail="Invalid token")
#         return userid_DI
#     except jwt.PyJWTError:
#         raise HTTPException(status_code=401, detail="Invalid token")

# # API Endpoints
# @app.post("/login", response_model=RecommendOutput)
# async def login(login: LoginInput, db: Session = Depends(get_db), userid_DI: str = Depends(verify_token)):
#     try:
#         if login.userid_DI != userid_DI:
#             logger.warning(f"Token mismatch for userid_DI: {login.userid_DI}")
#             raise HTTPException(status_code=401, detail="Token mismatch")
#         user = db.query(User).filter(User.userid_DI == login.userid_DI).first()
#         if not user:
#             logger.warning(f"User not found: {login.userid_DI}")
#             raise HTTPException(status_code=404, detail="User not found")
#         courses = recommendation_system.recommend(user.id, None, db)
#         return RecommendOutput(courses=courses)
#     except Exception as e:
#         logger.error(f"Login failed: {e}")
#         raise HTTPException(status_code=500, detail=f"Login failed: {e}")

# @app.post("/users", response_model=UserProfile)
# async def create_user(user: UserProfile, db: Session = Depends(get_db), userid_DI: str = Depends(verify_token)):
#     try:
#         db_user = User(**user.dict())
#         db.add(db_user)
#         db.commit()
#         db.refresh(db_user)
#         logger.info(f"Created user: {user.userid_DI}")
#         return user
#     except Exception as e:
#         logger.error(f"Failed to create user: {e}")
#         raise HTTPException(status_code=400, detail=f"Failed to create user: {e}")

# @app.post("/rate", response_model=RatingInput)
# async def submit_rating(rating: RatingInput, db: Session = Depends(get_db), userid_DI: str = Depends(verify_token)):
#     try:
#         db_interaction = Interaction(**rating.dict(), viewed=True)
#         db.add(db_interaction)
#         db.commit()
#         db.refresh(db_interaction)
#         logger.info(f"Submitted rating: user_id={rating.user_id}, course_id={rating.course_id}")
#         return rating
#     except Exception as e:
#         logger.error(f"Failed to submit rating: {e}")
#         raise HTTPException(status_code=400, detail=f"Failed to submit rating: {e}")

# @app.post("/recommend", response_model=RecommendOutput)
# async def get_recommendations(input: RecommendInput, db: Session = Depends(get_db), userid_DI: str = Depends(verify_token)):
#     try:
#         courses = recommendation_system.recommend(input.user_id, input.course_name, db)
#         logger.info(f"Generated recommendations for user_id={input.user_id}, course_name={input.course_name}")
#         return RecommendOutput(courses=courses)
#     except Exception as e:
#         logger.error(f"Recommendation failed: {e}")
#         raise HTTPException(status_code=500, detail=f"Recommendation failed: {e}")

# @app.post("/recommend-laravel", response_model=RecommendOutput)
# async def get_recommendations_laravel(input: RecommendInput, db: Session = Depends(get_db), userid_DI: str = Depends(verify_token)):
#     try:
#         user = db.query(User).filter(User.userid_DI == userid_DI).first()
#         if not user:
#             logger.warning(f"User not found: {userid_DI}")
#             raise HTTPException(status_code=404, detail="User not found")
#         courses = recommendation_system.recommend(user.id, input.course_name, db)
#         logger.info(f"Generated recommendations for user_id={user.id}, course_name={input.course_name}")
#         return RecommendOutput(courses=courses)
#     except Exception as e:
#         logger.error(f"Recommendation failed: {e}")
#         raise HTTPException(status_code=500, detail=f"Recommendation failed: {e}")

# @app.get("/courses", response_model=List[str])
# async def get_courses(db: Session = Depends(get_db), userid_DI: str = Depends(verify_token)):
#     try:
#         courses = pd.read_sql("SELECT course_name FROM courses", db.bind)
#         logger.info("Fetched courses list")
#         return courses['course_name'].tolist()
#     except Exception as e:
#         logger.error(f"Failed to fetch courses: {e}")
#         raise HTTPException(status_code=500, detail=f"Failed to fetch courses: {e}")

# @app.post("/update-models")
# async def update_models(background_tasks: BackgroundTasks, db: Session = Depends(get_db), userid_DI: str = Depends(verify_token)):
#     try:
#         background_tasks.add_task(recommendation_system.update_similarity_matrix, db, background_tasks)
#         logger.info("Started model update task")
#         return {"message": "Model update task started"}
#     except Exception as e:
#         logger.error(f"Model update failed: {e}")
#         raise HTTPException(status_code=500, detail=f"Model update failed: {e}")

# @app.get("/health")
# async def health_check():
#     try:
#         with engine.connect() as conn:
#             conn.execute(text("SELECT 1"))
#         logger.info("Health check passed")
#         return {"status": "healthy"}
#     except Exception as e:
#         logger.error(f"Health check failed: {e}")
#         raise HTTPException(status_code=500, detail="Health check failed")

import os
from dotenv import load_dotenv
import pickle
import pandas as pd
from fastapi import FastAPI, HTTPException, Depends, Security, BackgroundTasks
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from pydantic import BaseModel
from sqlalchemy import create_engine, Column, Integer, String, Float, Boolean, DateTime, ForeignKey, text
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import sessionmaker, Session
from surprise import SVD, Dataset, Reader
from sentence_transformers import SentenceTransformer
import faiss
import numpy as np
import jwt
import logging
from typing import List, Optional, Dict
from datetime import datetime
from contextlib import contextmanager
from pathlib import Path

# Load environment variables
dotenv_path = os.path.join(os.path.dirname(os.path.dirname(__file__)), 'course-recommendation', '.env')
load_dotenv(dotenv_path)

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('logs/api.log'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

# Database configuration (MySQL)
DATABASE_URL = "mysql+pymysql://root:123@localhost:8200/course_recommendation5"
try:
    engine = create_engine(DATABASE_URL)
    SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)
except Exception as e:
    logger.error(f"Failed to connect to database: {e}")
    raise

# Cache functions
def sanitize_cache_key(key: str) -> str:
    return key.replace(':', '_').replace('/', '_').replace('\\', '_')

def cache_get(key: str):
    cache_key = sanitize_cache_key(key)
    cache_file = Path(f"cache/{cache_key}.pkl")
    if cache_file.exists():
        with open(cache_file, 'rb') as f:
            return pickle.load(f)
    return None

def cache_set(key: str, value, ttl: int = 300):
    cache_key = sanitize_cache_key(key)
    cache_file = Path(f"cache/{cache_key}.pkl")
    cache_file.parent.mkdir(exist_ok=True)
    with open(cache_file, 'wb') as f:
        pickle.dump(value, f)

# JWT configuration
JWT_SECRET = os.getenv('JWT_SECRET')
JWT_ALGORITHM = "HS256"

# Initialize FastAPI
app = FastAPI()
security = HTTPBearer()

Base = declarative_base()

# SQLAlchemy Models
class User(Base):
    __tablename__ = "users"
    id = Column(Integer, primary_key=True, index=True)
    userid_DI = Column(String(255), unique=True, nullable=False)
    final_cc_cname_DI = Column(String(100), default="Unknown")
    LoE_DI = Column(String(50), default="Unknown")
    YoB = Column(Integer, nullable=True)

class Course(Base):
    __tablename__ = "courses"
    id = Column(Integer, primary_key=True, index=True)
    course_name = Column(String(255), nullable=False)
    course_description = Column(String, nullable=True)
    difficulty_level = Column(String(50), nullable=True)

class Interaction(Base):
    __tablename__ = "interactions"
    id = Column(Integer, primary_key=True, index=True)
    user_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    course_id = Column(Integer, ForeignKey("courses.id"), nullable=False)
    rating = Column(Float, nullable=True)
    viewed = Column(Boolean, default=False)
    timestamp = Column(DateTime, default=datetime.utcnow)

class SimilarityMatrix(Base):
    __tablename__ = "similarity_matrix"
    course_id_1 = Column(Integer, ForeignKey("courses.id"), primary_key=True)
    course_id_2 = Column(Integer, ForeignKey("courses.id"), primary_key=True)
    similarity_score = Column(Float, nullable=False)

# Create tables and indexes
try:
    Base.metadata.create_all(bind=engine)
    with engine.connect() as conn:
        conn.execute(text("CREATE INDEX IF NOT EXISTS idx_interactions_user_id ON interactions (user_id)"))
        conn.execute(text("CREATE INDEX IF NOT EXISTS idx_interactions_course_id ON interactions (course_id)"))
        conn.execute(text("CREATE INDEX IF NOT EXISTS idx_course_name ON courses (course_name)"))
        conn.execute(text("ALTER TABLE similarity_matrix DROP INDEX IF EXISTS idx_course_id_2"))
        conn.execute(text("CREATE INDEX IF NOT EXISTS idx_similarity_course_id_1 ON similarity_matrix (course_id_1)"))
        conn.execute(text("CREATE INDEX IF NOT EXISTS idx_similarity_course_id_2 ON similarity_matrix (course_id_2)"))
        conn.commit()
    logger.info("Database tables and indexes created")
except Exception as e:
    logger.error(f"Failed to create tables: {e}")
    raise

# Pydantic Models
class UserProfile(BaseModel):
    id: Optional[int] = None
    final_cc_cname_DI: str = "Unknown"
    LoE_DI: str = "Unknown"
    YoB: Optional[int] = None

class LoginInput(BaseModel):
    id: int

class RatingInput(BaseModel):
    user_id: int
    course_id: int
    rating: float

class RecommendInput(BaseModel):
    user_id: Optional[int] = None
    course_name: Optional[str] = None

class RecommendOutput(BaseModel):
    courses: List[str]

# Recommendation System
class RecommendationSystem:
    def __init__(self):
        self.svd = SVD(n_factors=100, n_epochs=20, random_state=42)
        self.model = SentenceTransformer('all-MiniLM-L6-v2')
        self.dimension = 384
        self.faiss_index = None
        self.course_ids = []
        self.load_data()

    def load_data(self):
        try:
            courses = pd.read_sql("SELECT * FROM courses", engine)
            if courses.empty:
                raise ValueError("No courses found in database")
            embeddings = self.compute_embeddings(courses['course_description'].fillna('').tolist())
            self.build_faiss_index(embeddings, courses['id'].tolist())
            logger.info("Loaded courses and built Faiss index")
        except Exception as e:
            logger.error(f"Failed to load data: {e}")
            raise

    def compute_embeddings(self, descriptions: List[str]) -> np.ndarray:
        cache_key = "course_embeddings"
        cached = cache_get(cache_key)
        if cached is not None:
            logger.info("Loaded embeddings from file cache")
            return cached
        embeddings = self.model.encode(descriptions, show_progress_bar=True)
        cache_set(cache_key, embeddings, ttl=3600)
        logger.info("Saved embeddings to file cache")
        return embeddings

    def build_faiss_index(self, embeddings: np.ndarray, course_ids: List[int]):
        self.faiss_index = faiss.IndexFlatIP(self.dimension)
        faiss.normalize_L2(embeddings)
        self.faiss_index.add(embeddings)
        self.course_ids = course_ids
        logger.info("Faiss index built")

    def update_similarity_matrix(self, db: Session, background_tasks):
        try:
            logger.info("Starting similarity matrix update")
            query = """
            SELECT c.*
            FROM courses c
            JOIN (
                SELECT course_id
                FROM interactions
                GROUP BY course_id
                ORDER BY COUNT(*) DESC
                LIMIT 1000
            ) i ON c.id = i.course_id
            """
            courses = pd.read_sql(query, db.bind)
            
            if len(courses) < 2:
                logger.warning("Not enough courses to compute similarity")
                raise ValueError("Need at least 2 courses to compute similarity")
            
            logger.info(f"Found {len(courses)} courses for similarity matrix update")

            # Compute embeddings
            cache_key = "course_embeddings"
            embeddings = cache_get(cache_key)
            if embeddings is None:
                descriptions = courses['course_description'].fillna('').tolist()
                logger.info("Computing embeddings for courses")
                embeddings = self.model.encode(descriptions, show_progress_bar=True)
                cache_set(cache_key, embeddings, ttl=3600)
                logger.info("Cached course embeddings")
            else:
                logger.info("Loaded embeddings from cache")

            # Compute similarity matrix using NumPy
            logger.info("Computing similarity matrix")
            scores = np.dot(embeddings, embeddings.T)
            
            # Prepare data for bulk insert
            similarity_data = []
            for i, cid1 in enumerate(courses['id']):
                for j, cid2 in enumerate(courses['id']):
                    if i < j:
                        similarity_data.append({
                            'course_id_1': cid1,
                            'course_id_2': cid2,
                            'similarity_score': float(scores[i, j])
                        })

            # Clear existing similarity_matrix
            logger.info("Clearing existing similarity matrix")
            db.execute(text("DELETE FROM similarity_matrix"))
            
            # Bulk insert similarity data
            logger.info("Inserting similarity matrix into database")
            chunk_size = 10000
            for i in range(0, len(similarity_data), chunk_size):
                chunk = similarity_data[i:i + chunk_size]
                db.execute(
                    text("""
                    INSERT INTO similarity_matrix (course_id_1, course_id_2, similarity_score)
                    VALUES (:course_id_1, :course_id_2, :similarity_score)
                    """),
                    chunk
                )
            db.commit()
            logger.info(f"Inserted {len(similarity_data)} rows into similarity_matrix")
            
        except Exception as e:
            logger.error(f"Failed to update similarity matrix: {e}")
            db.rollback()
            raise

    def get_difficulty_boost(self, education: Optional[str], difficulty_level: Optional[str]) -> float:
        if not education or not difficulty_level:
            return 0.0
        education_clean = education.replace("'s", "")
        boosts = {
            ('Master', 'Advanced'): 2.0, ('Master', 'Intermediate'): 1.0, ('Master', 'Beginner'): -1.0,
            ('Doctorate', 'Advanced'): 2.0, ('Doctorate', 'Intermediate'): 1.0, ('Doctorate', 'Beginner'): -1.0,
            ('Bachelor', 'Advanced'): 0.5, ('Bachelor', 'Intermediate'): 1.0, ('Bachelor', 'Beginner'): 0.0,
            ('Secondary', 'Beginner'): 2.0, ('Secondary', 'Intermediate'): 0.5, ('Secondary', 'Advanced'): -1.0,
            ('High School', 'Beginner'): 2.0, ('High School', 'Intermediate'): 0.5, ('High School', 'Advanced'): -1.0
        }
        return boosts.get((education_clean, difficulty_level), 0.0)

    def recommend(self, user_id: Optional[int], course_name: Optional[str], db: Session, alpha: float = 0.2) -> List[str]:
        cache_key = f"recommend:{user_id}:{course_name}"
        cached = cache_get(cache_key)
        if cached is not None:
            logger.info(f"Loaded recommendations from file cache for user_id={user_id}, course_name={course_name}")
            return cached

        try:
            interactions = pd.read_sql("SELECT * FROM interactions", db.bind)
            users = pd.read_sql("SELECT * FROM users", db.bind)
            courses = pd.read_sql("SELECT * FROM courses", db.bind)
        except Exception as e:
            logger.error(f"Database query failed: {e}")
            raise HTTPException(status_code=500, detail=f"Database query failed: {e}")

        recommended_courses = []

        if interactions.empty and not course_name:
            raise HTTPException(status_code=400, detail="No interaction data available. Please provide a course_name.")

        if not interactions.empty:
            cache_key_svd = "svd_model"
            cached_svd = cache_get(cache_key_svd)
            if cached_svd is not None:
                self.svd = cached_svd
                logger.info("Loaded SVD model from file cache")
            else:
                try:
                    reader = Reader(rating_scale=(1, 5))
                    surprise_data = Dataset.load_from_df(interactions[['user_id', 'course_id', 'rating']].dropna(), reader)
                    trainset = surprise_data.build_full_trainset()
                    self.svd.fit(trainset)
                    cache_set(cache_key_svd, self.svd, ttl=3600)
                    logger.info("Saved SVD model to file cache")
                except Exception as e:
                    logger.error(f"SVD training failed: {e}")
                    raise HTTPException(status_code=500, detail=f"SVD training failed: {e}")

        country, education, age = 'Unknown', 'Unknown', 30
        if user_id:
            user_info = users[users['id'] == user_id]
            if not user_info.empty:
                country = user_info['final_cc_cname_DI'].iloc[0]
                education = user_info['LoE_DI'].iloc[0]
                age = 2025 - user_info['YoB'].iloc[0] if not pd.isna(user_info['YoB'].iloc[0]) else 30

        country_boost = 0.2 if country != 'Unknown' else 0
        education_boost = 0.2 if education in ['Bachelor', 'Master'] else 0
        age_boost = 0.2 if 20 <= age <= 40 else 0

        user_history = interactions[interactions['user_id'] == user_id]
        interacted_courses = set(user_history['course_id'])
        course_ids = [cid for cid in courses['id'].unique() if cid not in interacted_courses]

        cf_scores = {}
        if not interactions.empty:
            try:
                cf_predictions = [self.svd.predict(user_id, cid) for cid in course_ids]
                cf_scores = {pred.iid: pred.est for pred in cf_predictions}
            except Exception as e:
                logger.error(f"SVD prediction failed: {e}")
                raise HTTPException(status_code=500, detail=f"SVD prediction failed: {e}")

        if course_name:
            course_row = courses[courses['course_name'] == course_name]
            if course_row.empty:
                raise HTTPException(status_code=404, detail="Course not found")
            course_id = course_row['id'].iloc[0]

            query = """
            SELECT course_id_2, similarity_score
            FROM similarity_matrix
            WHERE course_id_1 = :course_id
            ORDER BY similarity_score DESC
            LIMIT 10
            """
            try:
                similar_courses = pd.read_sql_query(query, db.bind, params={'course_id': course_id})
            except Exception as e:
                logger.error(f"Failed to query similarity_matrix: {e}")
                raise HTTPException(status_code=500, detail=f"Failed to query similarity_matrix: {e}")

            content_scores = {row['course_id_2']: row['similarity_score'] for _, row in similar_courses.iterrows()}
            hybrid_scores = {}
            for cid in content_scores:
                if cid not in interacted_courses:
                    hybrid_scores[cid] = alpha * content_scores[cid] + (1 - alpha) * cf_scores.get(cid, 3.0)

            for cid in hybrid_scores:
                course_info = courses[courses['id'] == cid]
                if not course_info.empty:
                    description = course_info['course_description'].iloc[0].lower() if pd.notna(course_info['course_description'].iloc[0]) else ''
                    difficulty_level = course_info['difficulty_level'].iloc[0] if pd.notna(course_info['difficulty_level'].iloc[0]) else 'Unknown'
                    score = hybrid_scores[cid]
                    if country != 'Unknown' and 'global' in description:
                        score += country_boost
                    if education in ['Bachelor', 'Master'] and 'advanced' in description:
                        score += education_boost
                    if age_boost > 0 and 'professional' in description:
                        score += age_boost
                    difficulty_boost = self.get_difficulty_boost(education, difficulty_level)
                    score += difficulty_boost
                    hybrid_scores[cid] = score

            top_courses = sorted(hybrid_scores.items(), key=lambda x: x[1], reverse=True)[:6]
            seen_courses = set()
            for course_id, _ in top_courses:
                course_name = courses[courses['id'] == course_id]['course_name'].iloc[0]
                if course_name not in seen_courses:
                    recommended_courses.append(course_name)
                    seen_courses.add(course_name)
                if len(recommended_courses) >= 6:
                    break
        else:
            for cid in cf_scores:
                course_info = courses[courses['id'] == cid]
                if not course_info.empty:
                    description = course_info['course_description'].iloc[0].lower() if pd.notna(course_info['course_description'].iloc[0]) else ''
                    difficulty_level = course_info['difficulty_level'].iloc[0] if pd.notna(course_info['difficulty_level'].iloc[0]) else 'Unknown'
                    score = cf_scores[cid]
                    if country != 'Unknown' and 'global' in description:
                        score += country_boost
                    if education in ['Bachelor', 'Master'] and 'advanced' in description:
                        score += education_boost
                    if age_boost > 0 and 'professional' in description:
                        score += age_boost
                    difficulty_boost = self.get_difficulty_boost(education, difficulty_level)
                    score += difficulty_boost
                    cf_scores[cid] = score

            top_courses = sorted(cf_scores.items(), key=lambda x: x[1], reverse=True)[:6]
            seen_courses = set()
            for course_id, _ in top_courses:
                course_name = courses[courses['id'] == course_id]['course_name'].iloc[0]
                if course_name not in seen_courses:
                    recommended_courses.append(course_name)
                    seen_courses.add(course_name)
                if len(recommended_courses) >= 6:
                    break

        cache_set(cache_key, recommended_courses, ttl=300)
        logger.info(f"Saved recommendations to file cache for user_id={user_id}, course_name={course_name}")
        return recommended_courses

# Initialize recommendation system
recommendation_system = RecommendationSystem()

# Dependency to get DB session
def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()

# JWT authentication
async def verify_token(credentials: HTTPAuthorizationCredentials = Security(security)):
    try:
        token = credentials.credentials
        payload = jwt.decode(token, JWT_SECRET, algorithms=[JWT_ALGORITHM])
        user_id = payload.get("sub")
        if not user_id:
            raise HTTPException(status_code=401, detail="Invalid token")
        try:
            user_id = int(user_id)  # Ensure user_id is an integer
        except ValueError:
            raise HTTPException(status_code=401, detail="Invalid user ID in token")
        return user_id
    except jwt.PyJWTError:
        raise HTTPException(status_code=401, detail="Invalid token")

# API Endpoints
@app.post("/login", response_model=RecommendOutput)
async def login(login: LoginInput, db: Session = Depends(get_db), user_id: int = Depends(verify_token)):
    try:
        if login.id != user_id:
            logger.warning(f"Token mismatch for user_id: {login.id}")
            raise HTTPException(status_code=401, detail="Token mismatch")
        user = db.query(User).filter(User.id == login.id).first()
        if not user:
            logger.warning(f"User not found: {login.id}")
            raise HTTPException(status_code=404, detail="User not found")
        courses = recommendation_system.recommend(user.id, None, db)
        return RecommendOutput(courses=courses)
    except Exception as e:
        logger.error(f"Login failed: {e}")
        raise HTTPException(status_code=500, detail=f"Login failed: {e}")

@app.post("/users", response_model=UserProfile)
async def create_user(user: UserProfile, db: Session = Depends(get_db), user_id: int = Depends(verify_token)):
    try:
        # Generate a unique userid_DI if not provided
        userid_DI = f"user_{user_id}_{datetime.utcnow().timestamp()}"
        db_user = User(id=user.id, userid_DI=userid_DI, **user.dict(exclude={'id'}))
        db.add(db_user)
        db.commit()
        db.refresh(db_user)
        logger.info(f"Created user: id={user.id}")
        return user
    except Exception as e:
        logger.error(f"Failed to create user: {e}")
        raise HTTPException(status_code=400, detail=f"Failed to create user: {e}")

@app.post("/rate", response_model=RatingInput)
async def submit_rating(rating: RatingInput, db: Session = Depends(get_db), user_id: int = Depends(verify_token)):
    try:
        if rating.user_id != user_id:
            logger.warning(f"Token mismatch for user_id: {rating.user_id}")
            raise HTTPException(status_code=401, detail="Token mismatch")
        db_interaction = Interaction(**rating.dict(), viewed=True)
        db.add(db_interaction)
        db.commit()
        db.refresh(db_interaction)
        logger.info(f"Submitted rating: user_id={rating.user_id}, course_id={rating.course_id}")
        return rating
    except Exception as e:
        logger.error(f"Failed to submit rating: {e}")
        raise HTTPException(status_code=400, detail=f"Failed to submit rating: {e}")

@app.post("/recommend", response_model=RecommendOutput)
async def get_recommendations(input: RecommendInput, db: Session = Depends(get_db), user_id: int = Depends(verify_token)):
    try:
        if input.user_id and input.user_id != user_id:
            logger.warning(f"Token mismatch for user_id: {input.user_id}")
            raise HTTPException(status_code=401, detail="Token mismatch")
        courses = recommendation_system.recommend(input.user_id or user_id, input.course_name, db)
        logger.info(f"Generated recommendations for user_id={input.user_id or user_id}, course_name={input.course_name}")
        return RecommendOutput(courses=courses)
    except Exception as e:
        logger.error(f"Recommendation failed: {e}")
        raise HTTPException(status_code=500, detail=f"Recommendation failed: {e}")

@app.post("/recommend-laravel", response_model=RecommendOutput)
async def get_recommendations_laravel(input: RecommendInput, db: Session = Depends(get_db), user_id: int = Depends(verify_token)):
    try:
        user = db.query(User).filter(User.id == user_id).first()
        if not user:
            logger.warning(f"User not found: {user_id}")
            raise HTTPException(status_code=404, detail="User not found")
        courses = recommendation_system.recommend(user.id, input.course_name, db)
        logger.info(f"Generated recommendations for user_id={user.id}, course_name={input.course_name}")
        return RecommendOutput(courses=courses)
    except Exception as e:
        logger.error(f"Recommendation failed: {e}")
        raise HTTPException(status_code=500, detail=f"Recommendation failed: {e}")

@app.get("/courses", response_model=List[str])
async def get_courses(db: Session = Depends(get_db), user_id: int = Depends(verify_token)):
    try:
        courses = pd.read_sql("SELECT course_name FROM courses", db.bind)
        logger.info("Fetched courses list")
        return courses['course_name'].tolist()
    except Exception as e:
        logger.error(f"Failed to fetch courses: {e}")
        raise HTTPException(status_code=500, detail=f"Failed to fetch courses: {e}")

@app.post("/update-models")
async def update_models(background_tasks: BackgroundTasks, db: Session = Depends(get_db), user_id: int = Depends(verify_token)):
    try:
        background_tasks.add_task(recommendation_system.update_similarity_matrix, db, background_tasks)
        logger.info("Started model update task")
        return {"message": "Model update task started"}
    except Exception as e:
        logger.error(f"Model update failed: {e}")
        raise HTTPException(status_code=500, detail=f"Model update failed: {e}")

@app.get("/health")
async def health_check():
    try:
        with engine.connect() as conn:
            conn.execute(text("SELECT 1"))
        logger.info("Health check passed")
        return {"status": "healthy"}
    except Exception as e:
        logger.error(f"Health check failed: {e}")
        raise HTTPException(status_code=500, detail="Health check failed")