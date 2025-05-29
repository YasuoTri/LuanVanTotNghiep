# from fastapi import FastAPI, Query, HTTPException
# from typing import Optional
# import pickle
# from CourseRecommendationSystem import recommend, update_model

# # Load pre-trained models at startup
# try:
#     courses_list = pickle.load(open('models/courses.pkl', 'rb'))
#     similarity = pickle.load(open('models/similarity.pkl', 'rb'))
#     svd = pickle.load(open('models/svd.pkl', 'rb'))
#     user_interactions = pickle.load(open('models/user_interactions.pkl', 'rb'))
#     user_competency = pickle.load(open('models/user_competency.pkl', 'rb'))
#     user_features = pickle.load(open('models/user_features.pkl', 'rb'))
# except FileNotFoundError as e:
#     raise HTTPException(status_code=500, detail=f"Model file missing: {e}")

# app = FastAPI()

# @app.get("/recommend")
# def get_recommendation(
#     user_id: Optional[int] = Query(default=None, description="User ID for personalized recommendations"),
#     course_name: Optional[str] = Query(default=None, description="Course name for content-based recommendations"),
#     alpha: float = Query(default=0.5, ge=0, le=1, description="Weight for hybrid filtering (0 = collaborative, 1 = content-based)")
# ):
#     """
#     Get course recommendations based on user_id, course_name, or both.
#     Returns a list of courses with details: course_id, course_name, difficulty_level, university, skills, description, price, rating, course_url, status, categories.
#     """
#     result = recommend(
#         user_id=user_id,
#         course_name=course_name,
#         alpha=alpha,
#         courses_list=courses_list,
#         similarity=similarity,
#         svd=svd,
#         user_interactions=user_interactions,
#         user_competency=user_competency,
#         user_features=user_features
#     )
#     return {"recommendations": result}

# @app.post("/update-model")
# def trigger_model_update():
#     """
#     Trigger model retraining and update saved models.
#     """
#     try:
#         update_model()
#         # Reload models after update
#         global courses_list, similarity, svd, user_interactions, user_competency, user_features
#         courses_list = pickle.load(open('models/courses.pkl', 'rb'))
#         similarity = pickle.load(open('models/similarity.pkl', 'rb'))
#         svd = pickle.load(open('models/svd.pkl', 'rb'))
#         user_interactions = pickle.load(open('models/user_interactions.pkl', 'rb'))
#         user_competency = pickle.load(open('models/user_competency.pkl', 'rb'))
#         user_features = pickle.load(open('models/user_features.pkl', 'rb'))
#         return {"status": "Model updated successfully"}
#     except Exception as e:
#         raise HTTPException(status_code=500, detail=f"Model update failed: {str(e)}")


from fastapi import FastAPI, Query, HTTPException
from typing import Optional
import pickle
from CourseRecommendationSystem import recommend, update_model

# Load pre-trained models at startup
try:
    courses_list = pickle.load(open('models/courses.pkl', 'rb'))
    similarity = pickle.load(open('models/similarity.pkl', 'rb'))
    svd = pickle.load(open('models/svd.pkl', 'rb'))
    user_interactions = pickle.load(open('models/user_interactions.pkl', 'rb'))
    user_competency = pickle.load(open('models/user_competency.pkl', 'rb'))
    user_features = pickle.load(open('models/user_features.pkl', 'rb'))
    svd_predictions = pickle.load(open('models/svd_predictions.pkl', 'rb'))
    pathways = pickle.load(open('models/pathways.pkl', 'rb'))
except FileNotFoundError as e:
    raise HTTPException(status_code=500, detail=f"Model file missing: {e}")

app = FastAPI()

@app.get("/recommend")
def get_recommendation(
    user_id: Optional[int] = Query(default=None, description="User ID for personalized recommendations"),
    course_name: Optional[str] = Query(default=None, description="Course name for content-based recommendations"),
    alpha: float = Query(default=0.5, ge=0, le=1, description="Weight for hybrid filtering (0 = collaborative, 1 = content-based)")
):
    """
    Get course recommendations based on user_id, course_name, or both.
    Returns a list of courses with details: course_id, course_name, difficulty_level, university, skills, description, price, rating, course_url, status, categories.
    """
    result = recommend(
        user_id=user_id,
        course_name=course_name,
        alpha=alpha,
        courses_list=courses_list,
        similarity=similarity,
        svd=svd,
        user_interactions=user_interactions,
        user_competency=user_competency,
        user_features=user_features,
        svd_predictions=svd_predictions,
        pathways=pathways
    )
    return {"recommendations": result}

@app.post("/update-model")
def trigger_model_update():
    """
    Trigger model retraining and update saved models.
    """
    try:
        update_model()
        # Reload models after update
        global courses_list, similarity, svd, user_interactions, user_competency, user_features, svd_predictions, pathways
        courses_list = pickle.load(open('models/courses.pkl', 'rb'))
        similarity = pickle.load(open('models/similarity.pkl', 'rb'))
        svd = pickle.load(open('models/svd.pkl', 'rb'))
        user_interactions = pickle.load(open('models/user_interactions.pkl', 'rb'))
        user_competency = pickle.load(open('models/user_competency.pkl', 'rb'))
        user_features = pickle.load(open('models/user_features.pkl', 'rb'))
        svd_predictions = pickle.load(open('models/svd_predictions.pkl', 'rb'))
        pathways = pickle.load(open('models/pathways.pkl', 'rb'))
        return {"status": "Model updated successfully"}
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Model update failed: {str(e)}")