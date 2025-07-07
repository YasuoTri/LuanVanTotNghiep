from CourseRecommendationSystem import update_model

if __name__ == "__main__":
    try:
        update_model(data_file='Data/udemy_courses.csv')
        print("Model files generated successfully.")
    except Exception as e:
        print(f"Error generating model files: {e}")