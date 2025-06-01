
#chạy login được rồi chưa tối ưu hóa
# import os
# import numpy as np
# import pandas as pd
# import matplotlib.pyplot as plt
# import seaborn as sns
# from sklearn.feature_extraction.text import CountVectorizer
# from sklearn.metrics.pairwise import cosine_similarity
# import nltk
# from nltk.stem.porter import PorterStemmer
# import pickle
# from surprise import SVD, Dataset, Reader
# from surprise.model_selection import train_test_split
# from surprise import accuracy

# print('Dependencies Imported')

# # Đọc dữ liệu khóa học
# data = pd.read_csv("Data/Coursera.csv")
# print(data.head(5))

# # Kiểm tra thông tin dữ liệu
# print(data.shape)  # 3522 courses and 7 columns
# print(data.info())
# print(data.isnull().sum())  # Kiểm tra giá trị thiếu

# # Thêm course_id vào DataFrame khóa học
# data['course_id'] = range(1, len(data) + 1)

# # Đọc dữ liệu tương tác người dùng
# user_behavior = pd.read_csv("Data/Courseuserbehavior.csv")
# print(user_behavior.head(5))

# # Tạo user_interactions từ Courseuserbehavior.csv
# # Ánh xạ course_id (giả lập ngẫu nhiên vì không có tên khóa học khớp)
# np.random.seed(42)
# unique_course_ids = user_behavior['course_id'].unique()
# course_id_map = {cid: np.random.choice(data['course_id']) for cid in unique_course_ids}

# # Ánh xạ userid_DI thành số nguyên
# unique_user_ids = user_behavior['userid_DI'].unique()
# user_id_map = {uid: i+1 for i, uid in enumerate(unique_user_ids)}

# # Tính rating
# def calculate_rating(row):
#     # Xử lý grade: chuyển thành float, xử lý NaN và chuỗi không hợp lệ
#     grade_str = row['grade']
#     try:
#         grade = float(grade_str) if pd.notnull(grade_str) and grade_str.strip() != '' else 0
#     except (ValueError, AttributeError):
#         grade = 0
    
#     rating = 0
#     if grade > 0:
#         rating = 1 + 4 * grade  # Chuyển từ 0-1 sang 1-5
#     else:
#         if row['viewed'] == 1:
#             rating += 1
#         if row['explored'] == 1:
#             rating += 1
#         if row['certified'] == 1:
#             rating += 2
#         if pd.notnull(row['nchapters']) and row['nchapters'] > 0:
#             rating += min(row['nchapters'] / 10, 1)  # Tối đa 1 điểm
#     return min(max(rating, 1), 5)  # Đảm bảo rating trong [1, 5]

# user_interactions = pd.DataFrame({
#     'user_id': user_behavior['userid_DI'].map(user_id_map),
#     'course_id': user_behavior['course_id'].map(course_id_map),
#     'rating': user_behavior.apply(calculate_rating, axis=1),
#     'viewed': user_behavior['viewed'].astype(bool),
#     'completed': user_behavior['certified'].astype(bool),
#     'timestamp': pd.to_datetime(user_behavior['last_event_DI'], errors='coerce').fillna(pd.Timestamp.now())
# })

# # Loại bỏ các hàng có giá trị thiếu
# user_interactions = user_interactions.dropna()
# print(user_interactions.head(5))
# print(user_interactions.shape)

# # Lọc các cột cần thiết
# data = data[['Course Name', 'Difficulty Level', 'Course Description', 'Skills', 'course_id']]

# # Tiền xử lý dữ liệu
# data['Course Name'] = data['Course Name'].str.replace(' ', ',')
# data['Course Name'] = data['Course Name'].str.replace(',,', ',')
# data['Course Name'] = data['Course Name'].str.replace(':', '')
# data['Course Description'] = data['Course Description'].str.replace(' ', ',')
# data['Course Description'] = data['Course Description'].str.replace(',,', ',')
# data['Course Description'] = data['Course Description'].str.replace('_', '')
# data['Course Description'] = data['Course Description'].str.replace(':', '')
# data['Course Description'] = data['Course Description'].str.replace('(', '')
# data['Course Description'] = data['Course Description'].str.replace(')', '')
# data['Skills'] = data['Skills'].str.replace('(', '')
# data['Skills'] = data['Skills'].str.replace(')', '')

# # Tạo cột tags
# data['tags'] = data['Course Name'] + data['Difficulty Level'] + data['Course Description'] + data['Skills']

# # Tạo new_df với copy để tránh SettingWithCopyWarning
# new_df = data[['Course Name', 'tags', 'course_id']].copy()

# # Tiền xử lý tags và Course Name
# new_df.loc[:, 'tags'] = data['tags'].str.replace(',', ' ')
# new_df.loc[:, 'Course Name'] = data['Course Name'].str.replace(',', ' ')
# new_df = new_df.rename(columns={'Course Name': 'course_name'})
# new_df.loc[:, 'tags'] = new_df['tags'].apply(lambda x: x.lower())

# # Stemming
# ps = PorterStemmer()

# def stem(text):
#     y = []
#     for i in text.split():
#         y.append(ps.stem(i))
#     return " ".join(y)

# new_df.loc[:, 'tags'] = new_df['tags'].apply(stem)

# print(new_df.head(5))
# print(new_df.shape)  # 3522 courses with tags and 3 columns

# # Content-based: Vector hóa tags
# cv = CountVectorizer(max_features=5000, stop_words='english')
# vectors = cv.fit_transform(new_df['tags']).toarray()
# print(vectors[0])

# # Tính cosine similarity
# similarity = cosine_similarity(vectors)

# # Collaborative filtering: Chuẩn bị dữ liệu
# reader = Reader(rating_scale=(1, 5))
# surprise_data = Dataset.load_from_df(user_interactions[['user_id', 'course_id', 'rating']], reader)
# trainset, testset = train_test_split(surprise_data, test_size=0.2, random_state=42)

# # Huấn luyện mô hình SVD
# svd = SVD()
# svd.fit(trainset)

# # Đánh giá mô hình
# predictions = svd.test(testset)
# print(f"RMSE: {accuracy.rmse(predictions)}")

# # Hàm đề xuất hybrid
# def recommend(user_id=None, course=None, alpha=0.5):
#     if user_id is None and course is None:
#         return ["Vui lòng cung cấp user_id hoặc course."]
    
#     recommended_courses = []
    
#     if user_id is not None:
#         # Collaborative filtering
#         course_ids = new_df['course_id'].unique()
#         cf_predictions = [svd.predict(user_id, course_id) for course_id in course_ids]
#         cf_scores = {pred.iid: pred.est for pred in cf_predictions}
        
#         if course is None:
#             # Chỉ sử dụng collaborative filtering
#             top_courses = sorted(cf_scores.items(), key=lambda x: x[1], reverse=True)[:6]
#             for course_id, _ in top_courses:
#                 course_name = new_df[new_df['course_id'] == course_id]['course_name'].iloc[0]
#                 recommended_courses.append(course_name)
#         else:
#             # Hybrid filtering
#             try:
#                 course_index = new_df[new_df['course_name'] == course].index[0]
#                 course_id = new_df.iloc[course_index]['course_id']
                
#                 # Content-based score
#                 distances = similarity[course_index]
#                 content_scores = sorted(list(enumerate(distances)), reverse=True, key=lambda x: x[1])[1:10]
#                 content_scores = {new_df.iloc[idx]['course_id']: score for idx, score in content_scores}
                
#                 # Kết hợp content-based và collaborative filtering
#                 hybrid_scores = {}
#                 for cid in content_scores:
#                     hybrid_scores[cid] = alpha * content_scores[cid] + (1 - alpha) * cf_scores.get(cid, 0)
                
#                 top_courses = sorted(hybrid_scores.items(), key=lambda x: x[1], reverse=True)[:6]
#                 for course_id, _ in top_courses:
#                     course_name = new_df[new_df['course_id'] == course_id]['course_name'].iloc[0]
#                     recommended_courses.append(course_name)
#             except IndexError:
#                 return [f"Khóa học '{course}' không tìm thấy."]
    
#     else:
#         # Content-based filtering
#         try:
#             course_index = new_df[new_df['course_name'] == course].index[0]
#             distances = sorted(list(enumerate(similarity[course_index])), reverse=True, key=lambda x: x[1])
#             for i in distances[1:7]:
#                 course_name = new_df.iloc[i[0]].course_name
#                 recommended_courses.append(course_name)
#         except IndexError:
#             return [f"Khóa học '{course}' không tìm thấy."]
    
#     return recommended_courses

# # Ví dụ sử dụng
# print("Đề xuất cho user_id=1:")
# print(recommend(user_id=1))
# print("\nĐề xuất hybrid cho user_id=1 và khóa học 'Business Strategy Business Model Canvas Analysis with Miro':")
# print(recommend(user_id=1, course='Business Strategy Business Model Canvas Analysis with Miro'))
# print("\nĐề xuất content-based cho khóa học 'Business Strategy Business Model Canvas Analysis with Miro':")
# print(recommend(course='Business Strategy Business Model Canvas Analysis with Miro'))

# # Xuất model
# os.makedirs('models', exist_ok=True)
# pickle.dump(similarity, open('models/similarity.pkl', 'wb'))
# pickle.dump(new_df.to_dict(), open('models/course_list.pkl', 'wb'))
# pickle.dump(new_df, open('models/courses.pkl', 'wb'))
# pickle.dump(svd, open('models/svd.pkl', 'wb'))
# pickle.dump(user_interactions.to_dict(), open('models/user_interactions.pkl', 'wb'))



# import pandas as pd
# import numpy as np
# try:
#     from sentence_transformers import SentenceTransformer
# except ImportError:
#     print("Error: 'sentence_transformers' not installed. Run 'pip install sentence-transformers'.")
#     exit(1)
# from sklearn.metrics.pairwise import cosine_similarity
# from surprise import SVD, Dataset, Reader
# import pickle
# import os
# import sqlite3

# # Tạo thư mục models nếu chưa tồn tại
# if not os.path.exists('models'):
#     os.makedirs('models')

# # Đọc dữ liệu
# try:
#     data = pd.read_csv('Data/Coursera.csv')
#     user_behavior = pd.read_csv('Data/Courseuserbehavior.csv')
# except FileNotFoundError as e:
#     print(f"Error: {e}. Please ensure 'Coursera.csv' and 'Courseuserbehavior.csv' exist in the 'Data' folder.")
#     exit(1)

# # Tiền xử lý dữ liệu khóa học
# data['Course Description'].fillna('', inplace=True)
# data['course_name'] = data['Course Name']
# data['course_id'] = range(1, len(data) + 1)
# courses_list = data[['course_id', 'course_name', 'Course Description']]

# # Tạo ma trận tương đồng bằng BERT
# try:
#     model = SentenceTransformer('all-MiniLM-L6-v2')
#     course_embeddings = model.encode(courses_list['Course Description'].tolist(), show_progress_bar=True)
#     similarity = cosine_similarity(course_embeddings)
# except Exception as e:
#     print(f"Error generating BERT embeddings: {e}")
#     exit(1)

# # Tiền xử lý dữ liệu người dùng
# unique_course_ids = user_behavior['course_id'].unique()
# unique_user_ids = user_behavior['userid_DI'].unique()

# course_id_map = {cid: i+1 for i, cid in enumerate(unique_course_ids)}
# user_id_map = {uid: i+1 for i, uid in enumerate(unique_user_ids)}

# # Kiểm tra và ánh xạ cột grade
# print("Unique values in 'grade' before processing:")
# print(user_behavior['grade'].unique())
# print("Number of NaN in 'grade':", user_behavior['grade'].isna().sum())

# # Ánh xạ các giá trị không phải số (nếu cần)
# grade_mapping = {
#     'A': 5.0, 'B': 4.0, 'C': 3.0, 'D': 2.0, 'F': 1.0,
#     'Pass': 4.0, 'Fail': 1.0
# }
# user_behavior['grade'] = user_behavior['grade'].replace(grade_mapping)

# # Tạo user_interactions
# user_interactions = pd.DataFrame({
#     'user_id': user_behavior['userid_DI'].map(user_id_map),
#     'course_id': user_behavior['course_id'].map(course_id_map),
#     'rating': pd.to_numeric(user_behavior['grade'], errors='coerce').fillna(1.0).clip(1, 5),
#     'viewed': user_behavior['viewed'].astype(bool),
#     'completed': user_behavior['certified'].astype(bool),
#     'timestamp': pd.to_datetime(user_behavior['last_event_DI'], errors='coerce').fillna(pd.Timestamp.now())
# })

# # Kiểm tra rating sau xử lý
# print("Unique ratings in 'user_interactions':")
# print(user_interactions['rating'].unique())

# # Trích xuất đặc trưng người dùng
# user_features = user_behavior[['userid_DI', 'final_cc_cname_DI', 'LoE_DI', 'YoB']].drop_duplicates()
# user_features['user_id'] = user_features['userid_DI'].map(user_id_map)
# user_features['final_cc_cname_DI'].fillna('Unknown', inplace=True)
# user_features['LoE_DI'].fillna('Unknown', inplace=True)
# user_features['YoB'].fillna(user_features['YoB'].median(), inplace=True)

# # Huấn luyện SVD
# reader = Reader(rating_scale=(1, 5))
# surprise_data = Dataset.load_from_df(user_interactions[['user_id', 'course_id', 'rating']], reader)
# trainset = surprise_data.build_full_trainset()
# svd = SVD(n_factors=100, n_epochs=20, random_state=42)
# svd.fit(trainset)

# # Kết nối SQLite và lưu dữ liệu
# try:
#     conn = sqlite3.connect('users.db')
#     user_interactions.to_sql('interactions', conn, if_exists='replace', index=False)
#     user_features.to_sql('user_features', conn, if_exists='replace', index=False)
# except Exception as e:
#     print(f"Error saving to SQLite: {e}")
#     conn.close()
#     exit(1)
# finally:
#     conn.close()

# # Lưu trữ
# try:
#     pickle.dump(courses_list, open('models/courses.pkl', 'wb'))
#     pickle.dump(similarity, open('models/similarity.pkl', 'wb'))
#     pickle.dump(svd, open('models/svd.pkl', 'wb'))
#     pickle.dump(user_interactions, open('models/user_interactions.pkl', 'wb'))
#     pickle.dump(user_features, open('models/user_features.pkl', 'wb'))
#     print("All files saved successfully.")
# except Exception as e:
#     print(f"Error saving pickle files: {e}")
#     exit(1)


# import pandas as pd
# import numpy as np
# try:
#     from sentence_transformers import SentenceTransformer
# except ImportError:
#     print("Error: 'sentence_transformers' not installed. Run 'pip install sentence-transformers'.")
#     exit(1)
# from sklearn.metrics.pairwise import cosine_similarity
# from surprise import SVD, Dataset, Reader
# import pickle
# import os
# import sqlite3

# # Tạo thư mục models nếu chưa tồn tại
# if not os.path.exists('models'):
#     os.makedirs('models')

# # Đọc dữ liệu
# try:
#     data = pd.read_csv('Data/Coursera.csv')
#     user_behavior = pd.read_csv('Data/Courseuserbehavior.csv')
# except FileNotFoundError as e:
#     print(f"Error: {e}. Please ensure 'Coursera.csv' and 'Courseuserbehavior.csv' exist in the 'Data' folder.")
#     exit(1)

# # Tiền xử lý dữ liệu khóa học
# data['Course Description'].fillna('', inplace=True)
# data['course_name'] = data['Course Name']
# data['course_id'] = range(1, len(data) + 1)
# courses_list = data[['course_id', 'course_name', 'Course Description', 'Difficulty Level']]

# # Tạo ma trận tương đồng bằng BERT
# try:
#     model = SentenceTransformer('all-MiniLM-L6-v2')
#     course_embeddings = model.encode(courses_list['Course Description'].tolist(), show_progress_bar=True)
#     similarity = cosine_similarity(course_embeddings)
# except Exception as e:
#     print(f"Error generating BERT embeddings: {e}")
#     exit(1)

# # Tiền xử lý dữ liệu người dùng
# unique_course_ids = user_behavior['course_id'].unique()
# unique_user_ids = user_behavior['userid_DI'].unique()

# # Cải thiện ánh xạ course_id bằng cách ánh xạ ngẫu nhiên tới course_id trong courses_list
# np.random.seed(42)
# course_id_map = {cid: np.random.choice(courses_list['course_id']) for cid in unique_course_ids}
# user_id_map = {uid: i+1 for i, uid in enumerate(unique_user_ids)}

# # Kiểm tra dữ liệu
# print("Columns in user_behavior:", user_behavior.columns.tolist())
# print("Unique values in 'grade':", user_behavior['grade'].unique())
# print("Number of NaN in 'grade':", user_behavior['grade'].isna().sum())
# if 'explored' in user_behavior.columns:
#     print("Unique values in 'explored':", user_behavior['explored'].unique())
# if 'nchapters' in user_behavior.columns:
#     print("Unique values in 'nchapters':", user_behavior['nchapters'].unique())

# # Hàm tính rating mới (ngẫu nhiên với xác suất cao cho rating 4 và 5)
# def calculate_rating(row):
#     ratings = [1.0, 2.0, 3.0, 4.0, 5.0]
#     probabilities = [0.1, 0.1, 0.2, 0.3, 0.3]  # Ưu tiên rating 4 và 5
#     return np.random.choice(ratings, p=probabilities)

# # Tạo user_interactions
# user_interactions = pd.DataFrame({
#     'user_id': user_behavior['userid_DI'].map(user_id_map),
#     'course_id': user_behavior['course_id'].map(course_id_map),
#     'rating': user_behavior.apply(calculate_rating, axis=1),
#     'viewed': user_behavior['viewed'].astype(bool),
#     'completed': user_behavior['certified'].astype(bool),
#     'timestamp': pd.to_datetime(user_behavior['last_event_DI'], errors='coerce').fillna(pd.Timestamp.now())
# })

# # Kiểm tra và đảm bảo có rating 5
# if 5 not in user_interactions['rating'].values:
#     print("No rating 5 found, forcing at least one rating to 5.")
#     random_index = np.random.choice(user_interactions.index)
#     user_interactions.loc[random_index, 'rating'] = 5.0

# # Ép thêm 10% số hàng thành rating 5
# force_5_percent = 0.1  # 10%
# num_rows_to_force = int(len(user_interactions) * force_5_percent)
# random_indices = np.random.choice(user_interactions.index, size=num_rows_to_force, replace=False)
# user_interactions.loc[random_indices, 'rating'] = 5.0

# # Kiểm tra rating
# print("Unique ratings in 'user_interactions':", user_interactions['rating'].unique())

# # Trích xuất đặc trưng người dùng
# user_features = user_behavior[['userid_DI', 'final_cc_cname_DI', 'LoE_DI', 'YoB']].drop_duplicates()
# user_features['user_id'] = user_features['userid_DI'].map(user_id_map)
# user_features['final_cc_cname_DI'].fillna('Unknown', inplace=True)
# user_features['LoE_DI'].fillna('Unknown', inplace=True)
# user_features['YoB'].fillna(user_features['YoB'].median(), inplace=True)

# # Huấn luyện SVD
# reader = Reader(rating_scale=(1, 5))
# surprise_data = Dataset.load_from_df(user_interactions[['user_id', 'course_id', 'rating']], reader)
# trainset = surprise_data.build_full_trainset()
# svd = SVD(n_factors=100, n_epochs=20, random_state=42)
# svd.fit(trainset)

# # Kết nối SQLite và lưu dữ liệu
# try:
#     conn = sqlite3.connect('users.db')
#     user_interactions.to_sql('interactions', conn, if_exists='replace', index=False)
#     user_features.to_sql('user_features', conn, if_exists='replace', index=False)
# except Exception as e:
#     print(f"Error saving to SQLite: {e}")
#     conn.close()
#     exit(1)
# finally:
#     conn.close()

# # Lưu trữ
# try:
#     pickle.dump(courses_list, open('models/courses.pkl', 'wb'))
#     pickle.dump(similarity, open('models/similarity.pkl', 'wb'))
#     pickle.dump(svd, open('models/svd.pkl', 'wb'))
#     pickle.dump(user_interactions, open('models/user_interactions.pkl', 'wb'))
#     pickle.dump(user_features, open('models/user_features.pkl', 'wb'))
#     print("All files saved successfully.")
# except Exception as e:
#     print(f"Error saving pickle files: {e}")
#     exit(1)


# import pandas as pd
# import numpy as np
# from sentence_transformers import SentenceTransformer
# from sklearn.metrics.pairwise import cosine_similarity
# from sklearn.cluster import KMeans
# from surprise import SVD, Dataset, Reader
# import pickle
# import os
# from datetime import datetime


# # Tạo thư mục models
# if not os.path.exists('models'):
#     os.makedirs('models')

# # Hàm chuẩn hóa độ khó
# def normalize_difficulty(difficulty):
#     mapping = {'Beginner': 0.3, 'Intermediate': 0.6, 'Advanced': 0.9}
#     return mapping.get(difficulty, 0.3)

# # Hàm tính năng lực người dùng
# def calculate_competency(user_interactions, quiz_results, enrollments):
#     if user_interactions.empty:
#         return 0.3
#     avg_quiz_score = quiz_results['score'].mean() / 100 if not quiz_results.empty else 0
#     completion_rate = len(enrollments[enrollments['status'] == 'completed']) / len(enrollments) if not enrollments.empty else 0
#     avg_completion_time = (enrollments['completed_at'] - enrollments['enrolled_at']).mean().total_seconds() / 3600 if not enrollments.empty else 0
#     avg_completion_time_score = max(0, 1 - avg_completion_time / 24)
#     total_courses_completed = len(enrollments[enrollments['status'] == 'completed'])
#     competency_score = (0.4 * avg_quiz_score) + (0.3 * completion_rate) + (0.2 * avg_completion_time_score) + (0.1 * min(total_courses_completed / 10, 1))
#     return min(max(competency_score, 0.3), 0.9)

# # Đọc dữ liệu
# try:
#     data = pd.read_csv('Data/Coursera_new.csv')
#     user_behavior = pd.read_csv('Data/Courseuserbehavior_new.csv')
# except FileNotFoundError as e:
#     print(f"Error: {e}. Please ensure 'Coursera_new.csv' and 'Courseuserbehavior_new.csv' exist in the 'Data' folder.")
#     exit(1)

# # Kiểm tra cột
# print("Columns in Coursera.csv:", data.columns.tolist())
# print("Columns in Courseuserbehavior.csv:", user_behavior.columns.tolist())

# # Tiền xử lý dữ liệu khóa học
# data['Course Description'] = data['Course Description'].fillna('')
# data['course_name'] = data['Course Name']
# data['course_id'] = range(1, len(data) + 1)
# data['instructor'] = data['University'].fillna('Unknown')
# data['keywords'] = data['Skills'].str.split(',').apply(lambda x: ','.join([i.strip().lower() for i in x]) if isinstance(x, list) else '')
# courses_list = data[['course_id', 'course_name', 'Course Description', 'Difficulty Level', 'instructor', 'keywords']]

# # Tạo ma trận tương đồng
# model = SentenceTransformer('all-MiniLM-L6-v2')
# course_texts = (courses_list['Course Description'] + ' ' + courses_list['instructor'] + ' ' + courses_list['keywords']).tolist()
# print("Encoding texts...")
# course_embeddings = model.encode(course_texts, show_progress_bar=True)
# print("Computing cosine similarity...")
# similarity = cosine_similarity(course_embeddings)
# print("Similarity computed.")

# # Tiền xử lý dữ liệu người dùng
# if 'userid_DI' not in user_behavior.columns:
#     print("Error: 'userid_DI' column not found in Courseuserbehavior.csv.")
#     exit(1)

# unique_course_ids = user_behavior['course_id'].unique()
# unique_user_ids = user_behavior['userid_DI'].unique()
# np.random.seed(42)
# course_id_map = {cid: np.random.choice(courses_list['course_id']) for cid in unique_course_ids}
# user_id_map = {uid: i+1 for i, uid in enumerate(unique_user_ids)}

# # Tạo user_interactions
# user_interactions = pd.DataFrame({
#     'user_id': user_behavior['userid_DI'].map(user_id_map),
#     'course_id': user_behavior['course_id'].map(course_id_map),
#     'rating': np.random.choice([1, 2, 3, 4, 5], size=len(user_behavior), p=[0.1, 0.1, 0.2, 0.3, 0.3]),
#     'viewed': user_behavior['viewed'].astype(bool),
#     'completed': user_behavior['certified'].astype(bool),
#     'timestamp': pd.to_datetime(user_behavior['last_event_DI'], errors='coerce').fillna(pd.Timestamp.now())
# })

# # Giả lập quiz_results và enrollments
# quiz_results = pd.DataFrame({
#     'user_id': user_interactions['user_id'],
#     'score': np.random.uniform(50, 100, size=len(user_interactions))
# })
# enrollments = pd.DataFrame({
#     'user_id': user_interactions['user_id'],
#     'course_id': user_interactions['course_id'],
#     'enrolled_at': user_interactions['timestamp'],
#     'completed_at': user_interactions['timestamp'] + pd.Timedelta(hours=np.random.randint(1, 48)),
#     'status': np.random.choice(['active', 'completed'], size=len(user_interactions), p=[0.4, 0.6])
# })

# # Tính năng lực người dùng
# user_competency = pd.DataFrame({
#     'user_id': [user_id_map[uid] for uid in unique_user_ids],
#     'competency_score': [calculate_competency(
#         user_interactions[user_interactions['user_id'] == user_id_map[uid]],
#         quiz_results[quiz_results['user_id'] == user_id_map[uid]],
#         enrollments[enrollments['user_id'] == user_id_map[uid]]
#     ) for uid in unique_user_ids]
# })

# # Phân cụm người dùng
# kmeans = KMeans(n_clusters=5, random_state=42)
# user_features = user_behavior[['userid_DI', 'final_cc_cname_DI', 'LoE_DI', 'YoB']].drop_duplicates()
# user_features['user_id'] = user_features['userid_DI'].map(user_id_map)
# user_features['final_cc_cname_DI'] = user_features['final_cc_cname_DI'].fillna('Unknown')
# user_features['LoE_DI'] = user_features['LoE_DI'].fillna('Unknown')
# user_features['YoB'] = user_features['YoB'].fillna(user_features['YoB'].median())
# user_features['cluster'] = kmeans.fit_predict(user_features[['YoB']].values)

# # Huấn luyện SVD
# reader = Reader(rating_scale=(1, 5))
# surprise_data = Dataset.load_from_df(user_interactions[['user_id', 'course_id', 'rating']], reader)
# trainset = surprise_data.build_full_trainset()
# svd = SVD(n_factors=50, n_epochs=10, random_state=42)
# svd.fit(trainset)

# # Hàm đề xuất
# def recommend(user_id=None, course_name=None, alpha=0.5):
#     if user_id is None and course_name is None:
#         return ["Please provide user_id or course_name."]

#     recommended_courses = []
#     competency_score = user_competency[user_competency['user_id'] == user_id]['competency_score'].iloc[0] if user_id in user_competency['user_id'].values else 0.3

#     if user_id is not None:
#         user_cluster = user_features[user_features['user_id'] == user_id]['cluster'].iloc[0] if user_id in user_features['user_id'].values else 0
#         cluster_users = user_features[user_features['cluster'] == user_cluster]['user_id']
#         cluster_interactions = user_interactions[user_interactions['user_id'].isin(cluster_users)]
#         course_ids = courses_list['course_id'].unique()
#         cf_predictions = [svd.predict(user_id, course_id) for course_id in course_ids]
#         cf_scores = {pred.iid: pred.est for pred in cf_predictions}

#         if course_name is None:
#             valid_courses = [
#                 cid for cid in course_ids
#                 if normalize_difficulty(courses_list[courses_list['course_id'] == cid]['Difficulty Level'].iloc[0]) >= competency_score
#             ]
#             top_courses = sorted(
#                 [(cid, cf_scores[cid]) for cid in valid_courses],
#                 key=lambda x: x[1], reverse=True
#             )[:5]
#             for course_id, _ in top_courses:
#                 course_name = courses_list[courses_list['course_id'] == course_id]['course_name'].iloc[0]
#                 recommended_courses.append(course_name)
#         else:
#             try:
#                 course_index = courses_list[courses_list['course_name'] == course_name].index[0]
#                 course_id = courses_list.iloc[course_index]['course_id']
#                 distances = similarity[course_index]
#                 content_scores = sorted(list(enumerate(distances)), reverse=True, key=lambda x: x[1])[1:10]
#                 content_scores = {courses_list.iloc[idx]['course_id']: score for idx, score in content_scores}

#                 hybrid_scores = {}
#                 for cid in content_scores:
#                     if normalize_difficulty(courses_list[courses_list['course_id'] == cid]['Difficulty Level'].iloc[0]) >= competency_score:
#                         hybrid_scores[cid] = alpha * content_scores[cid] + (1 - alpha) * cf_scores.get(cid, 0)

#                 top_courses = sorted(hybrid_scores.items(), key=lambda x: x[1], reverse=True)[:5]
#                 for course_id, _ in top_courses:
#                     course_row = courses_list[courses_list['course_id'] == course_id]
#                     if not course_row.empty:
#                         course = course_row.iloc[0]
#                         recommended_courses.append({
#                             'course_id': int(course['course_id']),
#                             'course_name': course.get('course_name', ''),
#                             'difficulty_level': course.get('Difficulty Level', ''),
#                             'university': course.get('University', ''),
#                             'skills': course.get('Skills', ''),
#                             'description': course.get('Course Description', ''),
#                             'price': course.get('Price', ''),
#                             'rating': float(course.get('Course Rating', 0))
#                         })
 
#             except IndexError:
#                 return [f"Course '{course_name}' not found."]
#     else:
#         try:
#             course_index = courses_list[courses_list['course_name'] == course_name].index[0]
#             distances = sorted(list(enumerate(similarity[course_index])), reverse=True, key=lambda x: x[1])
#             for i in distances[1:6]:
#                 if normalize_difficulty(courses_list.iloc[i[0]]['Difficulty Level']) >= competency_score:
#                     course_name = courses_list.iloc[i[0]]['course_name']
#                     recommended_courses.append(course_name)
#         except IndexError:
#             return [f"Course '{course_name}' not found."]

#     return recommended_courses

# # Lưu trữ
# try:
#     pickle.dump(courses_list, open('models/courses.pkl', 'wb'))
#     pickle.dump(similarity, open('models/similarity.pkl', 'wb'))
#     pickle.dump(svd, open('models/svd.pkl', 'wb'))
#     pickle.dump(user_interactions, open('models/user_interactions.pkl', 'wb'))
#     pickle.dump(user_competency, open('models/user_competency.pkl', 'wb'))
#     pickle.dump(user_features, open('models/user_features.pkl', 'wb'))
#     print("All files saved successfully.")
# except Exception as e:
#     print(f"Error saving pickle files: {e}")
#     exit(1)

# # Ví dụ sử dụng
# print("Recommendations for user_id=1:")
# print(recommend(user_id=1))
# print("\nHybrid recommendations for user_id=1 and course 'Business Strategy Business Model Canvas Analysis with Miro':")
# print(recommend(user_id=1, course_name='Business Strategy Business Model Canvas Analysis with Miro'))
# print("✅ Processing completed.")


##Code này đáp ứng được 1 phần của th Bằng

# import pandas as pd
# import numpy as np
# from sentence_transformers import SentenceTransformer
# from sklearn.metrics.pairwise import cosine_similarity
# from sklearn.cluster import KMeans
# from surprise import SVD, Dataset, Reader
# import pickle
# import os
# import logging
# from datetime import datetime

# # Set up logging
# logging.basicConfig(level=logging.INFO)
# logger = logging.getLogger(__name__)

# def normalize_difficulty(difficulty):
#     """Normalize course difficulty levels to a score."""
#     mapping = {'Beginner': 0.3, 'Intermediate': 0.6, 'Advanced': 0.9}
#     return mapping.get(difficulty, 0.3)

# def calculate_competency(user_interactions, quiz_results, enrollments):
#     """Calculate user competency score."""
#     if user_interactions.empty:
#         return 0.3
#     avg_quiz_score = quiz_results['score'].mean() / 100 if not quiz_results.empty else 0
#     completion_rate = len(enrollments[enrollments['status'] == 'completed']) / len(enrollments) if not enrollments.empty else 0
#     avg_completion_time = (enrollments['completed_at'] - enrollments['enrolled_at']).mean().total_seconds() / 3600 if not enrollments.empty else 0
#     avg_completion_time_score = max(0, 1 - avg_completion_time / 24)
#     total_courses_completed = len(enrollments[enrollments['status'] == 'completed'])
#     competency_score = (0.4 * avg_quiz_score) + (0.3 * completion_rate) + (0.2 * avg_completion_time_score) + (0.1 * min(total_courses_completed / 10, 1))
#     return min(max(competency_score, 0.3), 0.9)

# def recommend(user_id=None, course_name=None, alpha=0.5, courses_list=None, similarity=None, svd=None, user_interactions=None, user_competency=None, user_features=None):
#     """
#     Generate course recommendations based on user_id, course_name, or both.
#     Returns a list of dictionaries with complete course details.
#     """
#     if user_id is None and course_name is None:
#         return ["Please provide user_id or course_name."]

#     recommended_courses = []
#     competency_score = user_competency[user_competency['user_id'] == user_id]['competency_score'].iloc[0] if user_id in user_competency['user_id'].values else 0.3

#     def get_course_details(course_id):
#         """Helper function to extract complete course details."""
#         course_row = courses_list[courses_list['course_id'] == course_id]
#         if not course_row.empty:
#             course = course_row.iloc[0]
#             return {
#                 'course_id': int(course['course_id']),
#                 'course_name': course.get('course_name', ''),
#                 'difficulty_level': course.get('Difficulty Level', ''),
#                 'university': course.get('University', 'Unknown'),
#                 'skills': course.get('Skills', ''),
#                 'description': course.get('Course Description', ''),
#                 'price': str(course.get('Price', 'Unknown')),
#                 'rating': float(course.get('Course Rating', 0)),
#                 'course_url': course.get('Course URL', 'Unknown'),
#                 'status': course.get('Status', 'Unknown'),
#                 'categories': course.get('Categories', 'Unknown')
#             }
#         return None

#     if user_id is not None:
#         user_cluster = user_features[user_features['user_id'] == user_id]['cluster'].iloc[0] if user_id in user_features['user_id'].values else 0
#         cluster_users = user_features[user_features['cluster'] == user_cluster]['user_id']
#         cluster_interactions = user_interactions[user_interactions['user_id'].isin(cluster_users)]
#         course_ids = courses_list['course_id'].unique()
#         cf_predictions = [svd.predict(user_id, course_id) for course_id in course_ids]
#         cf_scores = {pred.iid: pred.est for pred in cf_predictions}

#         if course_name is None:
#             valid_courses = [
#                 cid for cid in course_ids
#                 if normalize_difficulty(courses_list[courses_list['course_id'] == cid]['Difficulty Level'].iloc[0]) >= competency_score
#             ]
#             top_courses = sorted(
#                 [(cid, cf_scores[cid]) for cid in valid_courses],
#                 key=lambda x: x[1], reverse=True
#             )[:5]
#             for course_id, _ in top_courses:
#                 course_details = get_course_details(course_id)
#                 if course_details:
#                     recommended_courses.append(course_details)
#         else:
#             try:
#                 course_index = courses_list[courses_list['course_name'] == course_name].index[0]
#                 course_id = courses_list.iloc[course_index]['course_id']
#                 distances = similarity[course_index]
#                 content_scores = sorted(list(enumerate(distances)), reverse=True, key=lambda x: x[1])[1:10]
#                 content_scores = {courses_list.iloc[idx]['course_id']: score for idx, score in content_scores}

#                 hybrid_scores = {}
#                 for cid in content_scores:
#                     if normalize_difficulty(courses_list[courses_list['course_id'] == cid]['Difficulty Level'].iloc[0]) >= competency_score:
#                         hybrid_scores[cid] = alpha * content_scores[cid] + (1 - alpha) * cf_scores.get(cid, 0)

#                 top_courses = sorted(hybrid_scores.items(), key=lambda x: x[1], reverse=True)[:5]
#                 for course_id, _ in top_courses:
#                     course_details = get_course_details(course_id)
#                     if course_details:
#                         recommended_courses.append(course_details)
#             except IndexError:
#                 return [f"Course '{course_name}' not found."]
#     else:
#         try:
#             course_index = courses_list[courses_list['course_name'] == course_name].index[0]
#             distances = sorted(list(enumerate(similarity[course_index])), reverse=True, key=lambda x: x[1])
#             for i in distances[1:6]:
#                 course_id = courses_list.iloc[i[0]]['course_id']
#                 if normalize_difficulty(courses_list.iloc[i[0]]['Difficulty Level']) >= competency_score:
#                     course_details = get_course_details(course_id)
#                     if course_details:
#                         recommended_courses.append(course_details)
#         except IndexError:
#             return [f"Course '{course_name}' not found."]

#     return recommended_courses

# def update_model():
#     """
#     Train the model and save the artifacts to the models/ directory.
#     """
#     logger.info("Starting model update")
#     if not os.path.exists('models'):
#         os.makedirs('models')

#     try:
#         data = pd.read_csv('Data/Coursera_new.csv')
#         user_behavior = pd.read_csv('Data/Courseuserbehavior_new.csv')
#     except FileNotFoundError as e:
#         logger.error(f"Data file missing: {e}")
#         raise Exception(f"Data file missing: {e}")

#     required_columns = ['Course Name', 'University', 'Difficulty Level', 'Course Rating', 'Course URL', 'Course Description', 'Skills', 'Price', 'Status', 'Categories']
#     missing_columns = [col for col in required_columns if col not in data.columns]
#     if missing_columns:
#         logger.error(f"Missing columns in Coursera_new.csv: {missing_columns}")
#         raise Exception(f"Missing columns in Coursera_new.csv: {missing_columns}")

#     data['Course Description'] = data['Course Description'].fillna('')
#     data['course_name'] = data['Course Name']
#     data['course_id'] = range(1, len(data) + 1)
#     courses_list = data[['course_id', 'course_name', 'Course Description', 'Difficulty Level', 'University', 'Skills', 'Price', 'Course Rating', 'Course URL', 'Status', 'Categories']]

#     try:
#         model = SentenceTransformer('all-MiniLM-L6-v2')
#         course_texts = (courses_list['Course Description'] + ' ' + courses_list['University'] + ' ' + courses_list['Skills']).tolist()
#         logger.info("Encoding texts...")
#         course_embeddings = model.encode(course_texts, show_progress_bar=True)
#         logger.info("Computing cosine similarity...")
#         similarity = cosine_similarity(course_embeddings)
#         logger.info("Similarity computed")
#     except Exception as e:
#         logger.error(f"Error generating BERT embeddings: {e}")
#         raise Exception(f"Error generating BERT embeddings: {e}")

#     if 'userid_DI' not in user_behavior.columns:
#         logger.error("'userid_DI' column not found in Courseuserbehavior.csv")
#         raise Exception("'userid_DI' column not found in Courseuserbehavior.csv")
    
#     unique_course_ids = user_behavior['course_id'].unique()
#     unique_user_ids = user_behavior['userid_DI'].unique()
#     np.random.seed(42)
#     course_id_map = {cid: np.random.choice(courses_list['course_id']) for cid in unique_course_ids}
#     user_id_map = {uid: i+1 for i, uid in enumerate(unique_user_ids)}

#     user_interactions = pd.DataFrame({
#         'user_id': user_behavior['userid_DI'].map(user_id_map),
#         'course_id': user_behavior['course_id'].map(course_id_map),
#         'rating': np.random.choice([1, 2, 3, 4, 5], size=len(user_behavior), p=[0.1, 0.1, 0.2, 0.3, 0.3]),
#         'viewed': user_behavior['viewed'].astype(bool),
#         'completed': user_behavior['certified'].astype(bool),
#         'timestamp': pd.to_datetime(user_behavior['last_event_DI'], errors='coerce').fillna(pd.Timestamp.now())
#     })

#     quiz_results = pd.DataFrame({
#         'user_id': user_interactions['user_id'],
#         'score': np.random.uniform(50, 100, size=len(user_interactions))
#     })
#     enrollments = pd.DataFrame({
#         'user_id': user_interactions['user_id'],
#         'course_id': user_interactions['course_id'],
#         'enrolled_at': user_interactions['timestamp'],
#         'completed_at': user_interactions['timestamp'] + pd.Timedelta(hours=np.random.randint(1, 48)),
#         'status': np.random.choice(['active', 'completed'], size=len(user_interactions), p=[0.4, 0.6])
#     })

#     user_competency = pd.DataFrame({
#         'user_id': [user_id_map[uid] for uid in unique_user_ids],
#         'competency_score': [calculate_competency(
#             user_interactions[user_interactions['user_id'] == user_id_map[uid]],
#             quiz_results[quiz_results['user_id'] == user_id_map[uid]],
#             enrollments[enrollments['user_id'] == user_id_map[uid]]
#         ) for uid in unique_user_ids]
#     })

#     kmeans = KMeans(n_clusters=5, random_state=42)
#     user_features = user_behavior[['userid_DI', 'final_cc_cname_DI', 'LoE_DI', 'YoB']].drop_duplicates()
#     user_features['user_id'] = user_features['userid_DI'].map(user_id_map)
#     user_features['final_cc_cname_DI'] = user_features['final_cc_cname_DI'].fillna('Unknown')
#     user_features['LoE_DI'] = user_features['LoE_DI'].fillna('Unknown')
#     user_features['YoB'] = user_features['YoB'].fillna(user_features['YoB'].median())
#     user_features['cluster'] = kmeans.fit_predict(user_features[['YoB']].values)

#     reader = Reader(rating_scale=(1, 5))
#     surprise_data = Dataset.load_from_df(user_interactions[['user_id', 'course_id', 'rating']], reader)
#     trainset = surprise_data.build_full_trainset()
#     svd = SVD(n_factors=50, n_epochs=10, random_state=42)
#     svd.fit(trainset)

#     try:
#         with open('models/courses.pkl', 'wb') as f:
#             pickle.dump(courses_list, f)
#         with open('models/similarity.pkl', 'wb') as f:
#             pickle.dump(similarity, f)
#         with open('models/svd.pkl', 'wb') as f:
#             pickle.dump(svd, f)
#         with open('models/user_interactions.pkl', 'wb') as f:
#             pickle.dump(user_interactions, f)
#         with open('models/user_competency.pkl', 'wb') as f:
#             pickle.dump(user_competency, f)
#         with open('models/user_features.pkl', 'wb') as f:
#             pickle.dump(user_features, f)
#         logger.info("All files saved successfully")
#     except Exception as e:
#         logger.error(f"Error saving pickle files: {e}")
#         raise Exception(f"Error saving pickle files: {e}")

# # Code này lại random data enroll,instructor id
# import pandas as pd
# import numpy as np
# from sentence_transformers import SentenceTransformer
# from sklearn.metrics.pairwise import cosine_similarity
# from sklearn.cluster import KMeans
# from surprise import SVD, Dataset, Reader
# import pickle
# import os
# import logging
# from datetime import datetime
# import networkx as nx

# # Set up logging
# logging.basicConfig(level=logging.INFO)
# logger = logging.getLogger(__name__)

# def normalize_difficulty(difficulty):
#     """Normalize course difficulty levels to a score."""
#     mapping = {'Beginner': 0.3, 'Intermediate': 0.6, 'Advanced': 0.9}
#     return mapping.get(difficulty, 0.3)

# def calculate_competency(user_interactions, quiz_results, enrollments):
#     """Calculate user competency score."""
#     if user_interactions.empty:
#         return 0.3
#     avg_quiz_score = quiz_results['score'].mean() / 100 if not quiz_results.empty else 0
#     completion_rate = len(enrollments[enrollments['status'] == 'completed']) / len(enrollments) if not enrollments.empty else 0
#     avg_completion_time = (enrollments['completed_at'] - enrollments['enrolled_at']).mean().total_seconds() / 3600 if not enrollments.empty else 0
#     avg_completion_time_score = max(0, 1 - avg_completion_time / 24)
#     total_courses_completed = len(enrollments[enrollments['status'] == 'completed'])
#     competency_score = (0.4 * avg_quiz_score) + (0.3 * completion_rate) + (0.2 * avg_completion_time_score) + (0.1 * min(total_courses_completed / 10, 1))
#     return min(max(competency_score, 0.3), 0.9)

# def get_popular_courses(courses_list, user_interactions, limit=5):
#     """Fetch popular courses based on enrollment count and ratings."""
#     # Count enrollments per course
#     enrollment_counts = user_interactions.groupby('course_id').size().reset_index(name='enrollment_count')
#     # Merge with courses_list to get ratings
#     courses_with_counts = courses_list.merge(enrollment_counts, on='course_id', how='left').fillna({'enrollment_count': 0})
#     # Filter approved courses
#     approved_courses = courses_with_counts[courses_list['Status'] == 'approved']
#     # Sort by enrollment count and rating
#     popular_courses = approved_courses.sort_values(['enrollment_count', 'Course Rating'], ascending=[False, False]).head(limit)
#     # Convert to list of dictionaries
#     return [get_course_details(row['course_id'], courses_list) for _, row in popular_courses.iterrows()]

# def build_learning_pathways(courses_list, similarity):
#     """Construct learning pathways using course similarity and difficulty."""
#     G = nx.DiGraph()
#     for i, row_i in courses_list.iterrows():
#         G.add_node(row_i['course_id'], difficulty=normalize_difficulty(row_i['Difficulty Level']))
#         for j, row_j in courses_list.iterrows():
#             if i != j and normalize_difficulty(row_j['Difficulty Level']) > normalize_difficulty(row_i['Difficulty Level']):
#                 weight = 1 - similarity[i][j]  # Lower similarity means higher weight
#                 G.add_edge(row_i['course_id'], row_j['course_id'], weight=weight)
    
#     pathways = {}
#     for course_id in courses_list['course_id']:
#         successors = nx.single_source_dijkstra_path(G, course_id)
#         pathways[course_id] = successors
#     return pathways

# def get_course_details(course_id, courses_list):
#     """Helper function to extract complete course details."""
#     course_row = courses_list[courses_list['course_id'] == course_id]
#     if not course_row.empty:
#         course = course_row.iloc[0]
#         return {
#             'course_id': int(course['course_id']),
#             'course_name': course.get('course_name', ''),
#             'difficulty_level': course.get('Difficulty Level', ''),
#             'university': course.get('University', 'Unknown'),
#             'skills': course.get('Skills', ''),
#             'description': course.get('Course Description', ''),
#             'price': str(course.get('Price', 'Unknown')),
#             'rating': float(course.get('Course Rating', 0)),
#             'course_url': course.get('Course URL', 'Unknown'),
#             'status': course.get('Status', 'Unknown'),
#             'categories': course.get('categories', 'Uncategorized'),  # Simulated categories
#             'instructor_id': course.get('instructor_id', 0)  # Simulated instructor_id
#         }
#     return None

# def recommend(user_id=None, course_name=None, alpha=0.5, courses_list=None, similarity=None, svd=None, user_interactions=None, user_competency=None, user_features=None):
#     """
#     Generate course recommendations based on user_id, course_name, or both.
#     Returns a list of dictionaries with complete course details.
#     """
#     if user_id is None and course_name is None:
#         # General recommendations
#         logger.info("Generating popular course recommendations")
#         return get_popular_courses(courses_list, user_interactions)

#     recommended_courses = []
#     competency_score = user_competency[user_competency['user_id'] == user_id]['competency_score'].iloc[0] if user_id in user_competency['user_id'].values else 0.3

#     # Fetch learning pathways
#     pathways = build_learning_pathways(courses_list, similarity)

#     if user_id is not None:
#         user_cluster = user_features[user_features['user_id'] == user_id]['cluster'].iloc[0] if user_id in user_features['user_id'].values else 0
#         cluster_users = user_features[user_features['cluster'] == user_cluster]['user_id']
#         cluster_interactions = user_interactions[user_interactions['user_id'].isin(cluster_users)]
#         course_ids = courses_list['course_id'].unique()
#         cf_predictions = [svd.predict(user_id, course_id) for course_id in course_ids]
#         cf_scores = {pred.iid: pred.est for pred in cf_predictions}

#         # Author-based preference (simulated from interactions)
#         preferred_instructors = user_interactions[user_interactions['user_id'] == user_id][['course_id', 'rating']]
#         preferred_instructors = preferred_instructors[preferred_instructors['rating'] >= 4]['course_id']
#         preferred_instructors = courses_list[courses_list['course_id'].isin(preferred_instructors)]['instructor_id'].unique()

#         if course_name is None:
#             valid_courses = [
#                 cid for cid in course_ids
#                 if normalize_difficulty(courses_list[courses_list['course_id'] == cid]['Difficulty Level'].iloc[0]) >= competency_score
#             ]
#             top_courses = sorted(
#                 [(cid, cf_scores[cid] * (1.2 if cid in pathways.get(cid, {}) else 1)) for cid in valid_courses],
#                 key=lambda x: x[1], reverse=True
#             )[:5]
#             for course_id, _ in top_courses:
#                 course_details = get_course_details(course_id, courses_list)
#                 if course_details:
#                     recommended_courses.append(course_details)
#         else:
#             try:
#                 course_index = courses_list[courses_list['course_name'] == course_name].index[0]
#                 course_id = courses_list.iloc[course_index]['course_id']
#                 distances = similarity[course_index]
#                 content_scores = sorted(list(enumerate(distances)), reverse=True, key=lambda x: x[1])[1:10]
#                 content_scores = {courses_list.iloc[idx]['course_id']: score for idx, score in content_scores}

#                 hybrid_scores = {}
#                 for cid in content_scores:
#                     if normalize_difficulty(courses_list[courses_list['course_id'] == cid]['Difficulty Level'].iloc[0]) >= competency_score:
#                         instructor_id = courses_list[courses_list['course_id'] == cid]['instructor_id'].iloc[0]
#                         instructor_boost = 1.3 if instructor_id in preferred_instructors else 1
#                         pathway_boost = 1.2 if cid in pathways.get(course_id, {}) else 1
#                         hybrid_scores[cid] = (
#                             alpha * content_scores[cid] +
#                             (1 - alpha) * cf_scores.get(cid, 0)
#                         ) * instructor_boost * pathway_boost

#                 top_courses = sorted(hybrid_scores.items(), key=lambda x: x[1], reverse=True)[:5]
#                 for course_id, _ in top_courses:
#                     course_details = get_course_details(course_id, courses_list)
#                     if course_details:
#                         recommended_courses.append(course_details)
#             except IndexError:
#                 return [f"Course '{course_name}' not found."]
#     else:
#         try:
#             course_index = courses_list[courses_list['course_name'] == course_name].index[0]
#             course_id = courses_list.iloc[course_index]['course_id']
#             distances = sorted(list(enumerate(similarity[course_index])), reverse=True, key=lambda x: x[1])
#             for i in distances[1:6]:
#                 next_course_id = courses_list.iloc[i[0]]['course_id']
#                 if normalize_difficulty(courses_list.iloc[i[0]]['Difficulty Level']) >= competency_score:
#                     if next_course_id in pathways.get(course_id, {}):
#                         course_details = get_course_details(next_course_id, courses_list)
#                         if course_details:
#                             recommended_courses.append(course_details)
#         except IndexError:
#             return [f"Course '{course_name}' not found."]

#     return recommended_courses

# def update_model():
#     """
#     Train the model and save the artifacts to the models/ directory.
#     """
#     logger.info("Starting model update")
#     if not os.path.exists('models'):
#         os.makedirs('models')

#     try:
#         data = pd.read_csv('Data/Coursera_new.csv')
#         user_behavior = pd.read_csv('Data/Courseuserbehavior_new.csv')
#     except FileNotFoundError as e:
#         logger.error(f"Data file missing: {e}")
#         raise Exception(f"Data file missing: {e}")

#     required_columns = ['Course Name', 'University', 'Difficulty Level', 'Course Rating', 'Course URL', 'Course Description', 'Skills', 'Price', 'Status']
#     missing_columns = [col for col in required_columns if col not in data.columns]
#     if missing_columns:
#         logger.error(f"Missing columns in Coursera_new.csv: {missing_columns}")
#         raise Exception(f"Missing columns in Coursera_new.csv: {missing_columns}")

#     data['Course Description'] = data['Course Description'].fillna('')
#     data['course_name'] = data['Course Name']
#     data['course_id'] = range(1, len(data) + 1)
#     # Simulate categories and instructor_id since not in database
#     data['categories'] = data['Skills'].apply(lambda x: x.split(',')[0] if isinstance(x, str) and x else 'Uncategorized')
#     data['instructor_id'] = np.random.randint(1, 100, size=len(data))  # Simulated instructor IDs
#     courses_list = data[['course_id', 'course_name', 'Course Description', 'Difficulty Level', 'University', 'Skills', 'Price', 'Course Rating', 'Course URL', 'Status', 'categories', 'instructor_id']]

#     try:
#         model = SentenceTransformer('all-MiniLM-L6-v2')
#         course_texts = (courses_list['Course Description'] + ' ' + courses_list['University'] + ' ' + courses_list['Skills']).tolist()
#         logger.info("Encoding texts...")
#         course_embeddings = model.encode(course_texts, show_progress_bar=True)
#         logger.info("Computing cosine similarity...")
#         similarity = cosine_similarity(course_embeddings)
#         logger.info("Similarity computed")
#     except Exception as e:
#         logger.error(f"Error generating BERT embeddings: {e}")
#         raise Exception(f"Error generating BERT embeddings: {e}")

#     if 'userid_DI' not in user_behavior.columns:
#         logger.error("'userid_DI' column not found in Courseuserbehavior.csv")
#         raise Exception("'userid_DI' column not found in Courseuserbehavior.csv")
    
#     unique_course_ids = user_behavior['course_id'].unique()
#     unique_user_ids = user_behavior['userid_DI'].unique()
#     np.random.seed(42)
#     course_id_map = {cid: np.random.choice(courses_list['course_id']) for cid in unique_course_ids}
#     user_id_map = {uid: i+1 for i, uid in enumerate(unique_user_ids)}

#     user_interactions = pd.DataFrame({
#         'user_id': user_behavior['userid_DI'].map(user_id_map),
#         'course_id': user_behavior['course_id'].map(course_id_map),
#         'rating': np.random.choice([1, 2, 3, 4, 5], size=len(user_behavior), p=[0.1, 0.1, 0.2, 0.3, 0.3]),
#         'viewed': user_behavior['viewed'].astype(bool),
#         'completed': user_behavior['certified'].astype(bool),
#         'timestamp': pd.to_datetime(user_behavior['last_event_DI'], errors='coerce').fillna(pd.Timestamp.now())
#     })

#     quiz_results = pd.DataFrame({
#         'user_id': user_interactions['user_id'],
#         'score': np.random.uniform(50, 100, size=len(user_interactions))
#     })
#     enrollments = pd.DataFrame({
#         'user_id': user_interactions['user_id'],
#         'course_id': user_interactions['course_id'],
#         'enrolled_at': user_interactions['timestamp'],
#         'completed_at': user_interactions['timestamp'] + pd.Timedelta(hours=np.random.randint(1, 48)),
#         'status': np.random.choice(['active', 'completed'], size=len(user_interactions), p=[0.4, 0.6])
#     })

#     user_competency = pd.DataFrame({
#         'user_id': [user_id_map[uid] for uid in unique_user_ids],
#         'competency_score': [calculate_competency(
#             user_interactions[user_interactions['user_id'] == user_id_map[uid]],
#             quiz_results[quiz_results['user_id'] == user_id_map[uid]],
#             enrollments[enrollments['user_id'] == user_id_map[uid]]
#         ) for uid in unique_user_ids]
#     })

#     kmeans = KMeans(n_clusters=5, random_state=42)
#     user_features = user_behavior[['userid_DI', 'final_cc_cname_DI', 'LoE_DI', 'YoB']].drop_duplicates()
#     user_features['user_id'] = user_features['userid_DI'].map(user_id_map)
#     user_features['final_cc_cname_DI'] = user_features['final_cc_cname_DI'].fillna('Unknown')
#     user_features['LoE_DI'] = user_features['LoE_DI'].fillna('Unknown')
#     user_features['YoB'] = user_features['YoB'].fillna(user_features['YoB'].median())
#     user_features['cluster'] = kmeans.fit_predict(user_features[['YoB']].values)

#     reader = Reader(rating_scale=(1, 5))
#     surprise_data = Dataset.load_from_df(user_interactions[['user_id', 'course_id', 'rating']], reader)
#     trainset = surprise_data.build_full_trainset()
#     svd = SVD(n_factors=50, n_epochs=10, random_state=42)
#     svd.fit(trainset)

#     try:
#         with open('models/courses.pkl', 'wb') as f:
#             pickle.dump(courses_list, f)
#         with open('models/similarity.pkl', 'wb') as f:
#             pickle.dump(similarity, f)
#         with open('models/svd.pkl', 'wb') as f:
#             pickle.dump(svd, f)
#         with open('models/user_interactions.pkl', 'wb') as f:
#             pickle.dump(user_interactions, f)
#         with open('models/user_competency.pkl', 'wb') as f:
#             pickle.dump(user_competency, f)
#         with open('models/user_features.pkl', 'wb') as f:
#             pickle.dump(user_features, f)
#         logger.info("All files saved successfully")
#     except Exception as e:
#         logger.error(f"Error saving pickle files: {e}")
#         raise Exception(f"Error saving pickle files: {e}")

# import pandas as pd
# import numpy as np
# from sentence_transformers import SentenceTransformer
# from sklearn.metrics.pairwise import cosine_similarity
# from sklearn.cluster import KMeans
# from surprise import SVD, Dataset, Reader
# import pickle
# import os
# import logging
# from datetime import datetime
# import networkx as nx

# # Set up logging
# logging.basicConfig(level=logging.INFO)
# logger = logging.getLogger(__name__)

# def normalize_difficulty(difficulty):
#     """Normalize course difficulty levels to a score."""
#     mapping = {'Beginner': 0.3, 'Intermediate': 0.6, 'Advanced': 0.9}
#     return mapping.get(difficulty, 0.3)

# def calculate_competency(user_interactions, quiz_results, enrollments):
#     """Calculate user competency score."""
#     if user_interactions.empty:
#         return 0.3
#     avg_quiz_score = quiz_results['score'].mean() / 100 if not quiz_results.empty else 0
#     completion_rate = len(enrollments[enrollments['status'] == 'completed']) / len(enrollments) if not enrollments.empty else 0
#     avg_completion_time = (enrollments['completed_at'] - enrollments['enrolled_at']).mean().total_seconds() / 3600 if not enrollments.empty and enrollments['completed_at'].notnull().any() else 0
#     avg_completion_time_score = max(0, 1 - avg_completion_time / 24)
#     total_courses_completed = len(enrollments[enrollments['status'] == 'completed'])
#     competency_score = (0.4 * avg_quiz_score) + (0.3 * completion_rate) + (0.2 * avg_completion_time_score) + (0.1 * min(total_courses_completed / 10, 1))
#     return min(max(competency_score, 0.3), 0.9)

# def get_popular_courses(courses_list, user_interactions, limit=5):
#     """Fetch popular courses based on enrollment count and ratings."""
#     enrollment_counts = user_interactions.groupby('course_id').size().reset_index(name='enrollment_count')
#     courses_with_counts = courses_list.merge(enrollment_counts, on='course_id', how='left').fillna({'enrollment_count': 0})
#     approved_courses = courses_with_counts[courses_list['Status'] == 'approved']
#     popular_courses = approved_courses.sort_values(['enrollment_count', 'Course Rating'], ascending=[False, False]).head(limit)
#     return [get_course_details(row['course_id'], courses_list) for _, row in popular_courses.iterrows()]

# def build_learning_pathways(courses_list, similarity):
#     """Construct learning pathways using course similarity and difficulty."""
#     G = nx.DiGraph()
#     for i, row_i in courses_list.iterrows():
#         G.add_node(row_i['course_id'], difficulty=normalize_difficulty(row_i['Difficulty Level']))
#         for j, row_j in courses_list.iterrows():
#             if i != j and normalize_difficulty(row_j['Difficulty Level']) > normalize_difficulty(row_i['Difficulty Level']):
#                 weight = 1 - similarity[i][j]
#                 G.add_edge(row_i['course_id'], row_j['course_id'], weight=weight)
    
#     pathways = {}
#     for course_id in courses_list['course_id']:
#         successors = nx.single_source_dijkstra_path(G, course_id)
#         pathways[course_id] = successors
#     return pathways

# def get_course_details(course_id, courses_list):
#     """Helper function to extract complete course details."""
#     course_row = courses_list[courses_list['course_id'] == course_id]
#     if not course_row.empty:
#         course = course_row.iloc[0]
#         return {
#             'course_id': int(course['course_id']),
#             'course_name': course.get('course_name', ''),
#             'difficulty_level': course.get('Difficulty Level', ''),
#             'university': course.get('University', 'Unknown'),
#             'skills': course.get('Skills', ''),
#             'description': course.get('Course Description', ''),
#             'price': str(course.get('Price', 'Unknown')),
#             'rating': float(course.get('Course Rating', 0)),
#             'course_url': course.get('Course URL', 'Unknown'),
#             'status': course.get('Status', 'Unknown'),
#             'categories': course.get('Categories', 'Uncategorized'),
#             'instructor_id': course.get('Instructor IDs', '0')
#         }
#     return None

# def recommend(user_id=None, course_name=None, alpha=0.5, courses_list=None, similarity=None, svd=None, user_interactions=None, user_competency=None, user_features=None):
#     """
#     Generate course recommendations based on user_id, course_name, or both.
#     Returns a list of dictionaries with complete course details.
#     """
#     if user_id is None and course_name is None:
#         logger.info("Generating popular course recommendations")
#         return get_popular_courses(courses_list, user_interactions)

#     recommended_courses = []
#     competency_score = user_competency[user_competency['user_id'] == user_id]['competency_score'].iloc[0] if user_id in user_competency['user_id'].values else 0.3

#     pathways = build_learning_pathways(courses_list, similarity)

#     if user_id is not None:
#         user_cluster = user_features[user_features['user_id'] == user_id]['cluster'].iloc[0] if user_id in user_features['user_id'].values else 0
#         cluster_users = user_features[user_features['cluster'] == user_cluster]['user_id']
#         cluster_interactions = user_interactions[user_interactions['user_id'].isin(cluster_users)]
#         course_ids = courses_list['course_id'].unique()
#         cf_predictions = [svd.predict(user_id, course_id) for course_id in course_ids]
#         cf_scores = {pred.iid: pred.est for pred in cf_predictions}

#         preferred_instructors = user_interactions[user_interactions['user_id'] == user_id][['course_id', 'rating']]
#         preferred_instructors = preferred_instructors[preferred_instructors['rating'] >= 4]['course_id']
#         preferred_instructors = courses_list[courses_list['course_id'].isin(preferred_instructors)]['Instructor IDs'].apply(lambda x: x.split(',') if isinstance(x, str) else []).explode().unique()

#         if course_name is None:
#             valid_courses = [
#                 cid for cid in course_ids
#                 if normalize_difficulty(courses_list[courses_list['course_id'] == cid]['Difficulty Level'].iloc[0]) >= competency_score
#             ]
#             top_courses = sorted(
#                 [(cid, cf_scores[cid] * (1.2 if cid in pathways.get(cid, {}) else 1)) for cid in valid_courses],
#                 key=lambda x: x[1], reverse=True
#             )[:5]
#             for course_id, _ in top_courses:
#                 course_details = get_course_details(course_id, courses_list)
#                 if course_details:
#                     recommended_courses.append(course_details)
#         else:
#             try:
#                 course_index = courses_list[courses_list['course_name'] == course_name].index[0]
#                 course_id = courses_list.iloc[course_index]['course_id']
#                 distances = similarity[course_index]
#                 content_scores = sorted(list(enumerate(distances)), reverse=True, key=lambda x: x[1])[1:10]
#                 content_scores = {courses_list.iloc[idx]['course_id']: score for idx, score in content_scores}

#                 hybrid_scores = {}
#                 for cid in content_scores:
#                     if normalize_difficulty(courses_list[courses_list['course_id'] == cid]['Difficulty Level'].iloc[0]) >= competency_score:
#                         instructor_ids = courses_list[courses_list['course_id'] == cid]['Instructor IDs'].iloc[0]
#                         instructor_ids = instructor_ids.split(',') if isinstance(instructor_ids, str) else []
#                         instructor_boost = 1.3 if any(i in preferred_instructors for i in instructor_ids) else 1
#                         pathway_boost = 1.2 if cid in pathways.get(course_id, {}) else 1
#                         hybrid_scores[cid] = (
#                             alpha * content_scores[cid] +
#                             (1 - alpha) * cf_scores.get(cid, 0)
#                         ) * instructor_boost * pathway_boost

#                 top_courses = sorted(hybrid_scores.items(), key=lambda x: x[1], reverse=True)[:5]
#                 for course_id, _ in top_courses:
#                     course_details = get_course_details(course_id, courses_list)
#                     if course_details:
#                         recommended_courses.append(course_details)
#             except IndexError:
#                 return [f"Course '{course_name}' not found."]
#     else:
#         try:
#             course_index = courses_list[courses_list['course_name'] == course_name].index[0]
#             course_id = courses_list.iloc[course_index]['course_id']
#             distances = sorted(list(enumerate(similarity[course_index])), reverse=True, key=lambda x: x[1])
#             for i in distances[1:6]:
#                 next_course_id = courses_list.iloc[i[0]]['course_id']
#                 if normalize_difficulty(courses_list.iloc[i[0]]['Difficulty Level']) >= competency_score:
#                     if next_course_id in pathways.get(course_id, {}):
#                         course_details = get_course_details(next_course_id, courses_list)
#                         if course_details:
#                             recommended_courses.append(course_details)
#         except IndexError:
#             return [f"Course '{course_name}' not found."]

#     return recommended_courses

# def update_model():
#     """
#     Train the model and save the artifacts to the models/ directory.
#     """
#     logger.info("Starting model update")
#     if not os.path.exists('models'):
#         os.makedirs('models')

#     try:
#         data = pd.read_csv('Data/Coursera_new.csv')
#         user_behavior = pd.read_csv('Data/Courseuserbehavior_new.csv')
#         quiz_results = pd.read_csv('Data/quiz_results.csv')
#         enrollments = pd.read_csv('Data/enrollments.csv')
#     except FileNotFoundError as e:
#         logger.error(f"Data file missing: {e}")
#         raise Exception(f"Data file missing: {e}")

#     required_columns = ['Course Name', 'University', 'Difficulty Level', 'Course Rating', 'Course URL', 'Course Description', 'Skills', 'Price', 'Status', 'Categories', 'Instructor IDs']
#     missing_columns = [col for col in required_columns if col not in data.columns]
#     if missing_columns:
#         logger.error(f"Missing columns in Coursera_new.csv: {missing_columns}")
#         raise Exception(f"Missing columns in Coursera_new.csv: {missing_columns}")

#     data['Course Description'] = data['Course Description'].fillna('')
#     data['course_name'] = data['Course Name']
#     data['course_id'] = range(1, len(data) + 1)
#     data['Categories'] = data['Categories'].fillna('Uncategorized')
#     data['Instructor IDs'] = data['Instructor IDs'].fillna('0')
#     courses_list = data[['course_id', 'course_name', 'Course Description', 'Difficulty Level', 'University', 'Skills', 'Price', 'Course Rating', 'Course URL', 'Status', 'Categories', 'Instructor IDs']]

#     try:
#         model = SentenceTransformer('all-MiniLM-L6-v2')
#         course_texts = (courses_list['Course Description'] + ' ' + courses_list['University'] + ' ' + courses_list['Skills']).tolist()
#         logger.info("Encoding texts...")
#         course_embeddings = model.encode(course_texts, show_progress_bar=True)
#         logger.info("Computing cosine similarity...")
#         similarity = cosine_similarity(course_embeddings)
#         logger.info("Similarity computed")
#     except Exception as e:
#         logger.error(f"Error generating BERT embeddings: {e}")
#         raise Exception(f"Error generating BERT embeddings: {e}")

#     if 'userid_DI' not in user_behavior.columns:
#         logger.error("'userid_DI' column not found in Courseuserbehavior.csv")
#         raise Exception("'userid_DI' column not found in Courseuserbehavior.csv")
    
#     unique_course_ids = user_behavior['course_id'].unique()
#     unique_user_ids = user_behavior['userid_DI'].unique()
#     np.random.seed(42)
#     course_id_map = {cid: np.random.choice(courses_list['course_id']) for cid in unique_course_ids}
#     user_id_map = {uid: i+1 for i, uid in enumerate(unique_user_ids)}

#     user_interactions = pd.DataFrame({
#         'user_id': user_behavior['userid_DI'].map(user_id_map),
#         'course_id': user_behavior['course_id'].map(course_id_map),
#         'rating': np.random.choice([1, 2, 3, 4, 5], size=len(user_behavior), p=[0.1, 0.1, 0.2, 0.3, 0.3]),
#         'viewed': user_behavior['viewed'].astype(bool),
#         'completed': user_behavior['certified'].astype(bool),
#         'timestamp': pd.to_datetime(user_behavior['last_event_DI'], errors='coerce').fillna(pd.Timestamp.now())
#     })

#     # Convert datetime columns
#     quiz_results['started_at'] = pd.to_datetime(quiz_results['started_at'], errors='coerce')
#     quiz_results['completed_at'] = pd.to_datetime(quiz_results['completed_at'], errors='coerce')
#     enrollments['enrolled_at'] = pd.to_datetime(enrollments['enrolled_at'], errors='coerce')
#     enrollments['completed_at'] = pd.to_datetime(enrollments['completed_at'], errors='coerce')

#     user_competency = pd.DataFrame({
#         'user_id': [user_id_map[uid] for uid in unique_user_ids],
#         'competency_score': [calculate_competency(
#             user_interactions[user_interactions['user_id'] == user_id_map[uid]],
#             quiz_results[quiz_results['user_id'] == user_id_map[uid]],
#             enrollments[enrollments['user_id'] == user_id_map[uid]]
#         ) for uid in unique_user_ids]
#     })

#     kmeans = KMeans(n_clusters=5, random_state=42)
#     user_features = user_behavior[['userid_DI', 'final_cc_cname_DI', 'LoE_DI', 'YoB']].drop_duplicates()
#     user_features['user_id'] = user_features['userid_DI'].map(user_id_map)
#     user_features['final_cc_cname_DI'] = user_features['final_cc_cname_DI'].fillna('Unknown')
#     user_features['LoE_DI'] = user_features['LoE_DI'].fillna('Unknown')
#     user_features['YoB'] = user_features['YoB'].fillna(user_features['YoB'].median())
#     user_features['cluster'] = kmeans.fit_predict(user_features[['YoB']].values)

#     reader = Reader(rating_scale=(1, 5))
#     surprise_data = Dataset.load_from_df(user_interactions[['user_id', 'course_id', 'rating']], reader)
#     trainset = surprise_data.build_full_trainset()
#     svd = SVD(n_factors=50, n_epochs=10, random_state=42)
#     svd.fit(trainset)

#     try:
#         with open('models/courses.pkl', 'wb') as f:
#             pickle.dump(courses_list, f)
#         with open('models/similarity.pkl', 'wb') as f:
#             pickle.dump(similarity, f)
#         with open('models/svd.pkl', 'wb') as f:
#             pickle.dump(svd, f)
#         with open('models/user_interactions.pkl', 'wb') as f:
#             pickle.dump(user_interactions, f)
#         with open('models/user_competency.pkl', 'wb') as f:
#             pickle.dump(user_competency, f)
#         with open('models/user_features.pkl', 'wb') as f:
#             pickle.dump(user_features, f)
#         logger.info("All files saved successfully")
#     except Exception as e:
#         logger.error(f"Error saving pickle files: {e}")
#         raise Exception(f"Error saving pickle files: {e}")

import pandas as pd
import numpy as np
from sentence_transformers import SentenceTransformer
from sklearn.metrics.pairwise import cosine_similarity
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.cluster import KMeans
from surprise import SVD, Dataset, Reader
import pickle
import os
import logging
from datetime import datetime
import networkx as nx

# Set up logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

def normalize_difficulty(difficulty):
    """Normalize course difficulty levels to a score."""
    mapping = {'Beginner': 0.3, 'Intermediate': 0.6, 'Advanced': 0.9}
    return mapping.get(difficulty, 0.3)

# def calculate_competency(user_interactions, quiz_results, enrollments):
#     """Calculate user competency score."""
#     if user_interactions.empty:
#         return 0.3
#     avg_quiz_score = quiz_results['score'].mean() / 100 if not quiz_results.empty else 0
#     completion_rate = len(enrollments[enrollments['status'] == 'completed']) / len(enrollments) if not enrollments.empty else 0
#     avg_completion_time = (enrollments['completed_at'] - enrollments['enrolled_at']).mean().total_seconds() / 3600 if not enrollments.empty and enrollments['completed_at'].notnull().any() else 0
#     avg_completion_time_score = max(0, 1 - avg_completion_time / 24)
#     total_courses_completed = len(enrollments[enrollments['status'] == 'completed'])
#     competency_score = (0.4 * avg_quiz_score) + (0.3 * completion_rate) + (0.2 * avg_completion_time_score) + (0.1 * min(total_courses_completed / 10, 1))
#     return min(max(competency_score, 0.3), 0.9)

def calculate_competency(user_interactions, quiz_results, enrollments, loe_di='Unknown'):
    """Calculate user competency score, incorporating LoE_DI."""
    # Map LoE_DI to a baseline competency score
    loe_mapping = {
        'Less than Secondary': 0.2,
        'Secondary': 0.3,
        'High School': 0.3,
        'Bachelor\'s': 0.5,
        'Master\'s': 0.7,
        'Doctorate': 0.9,
        'Unknown': 0.3
    }
    loe_score = loe_mapping.get(loe_di, 0.3)

    if user_interactions.empty and quiz_results.empty and enrollments.empty:
        return loe_score  # Rely on LoE_DI for new users

    # Performance-based competency
    avg_quiz_score = quiz_results['score'].mean() / 100 if not quiz_results.empty else 0
    completion_rate = len(enrollments[enrollments['status'] == 'completed']) / len(enrollments) if not enrollments.empty else 0
    avg_completion_time = (enrollments['completed_at'] - enrollments['enrolled_at']).mean().total_seconds() / 3600 if not enrollments.empty and enrollments['completed_at'].notnull().any() else 0
    avg_completion_time_score = max(0, 1 - avg_completion_time / 24)
    total_courses_completed = len(enrollments[enrollments['status'] == 'completed'])

    # Performance-based score (70% weight)
    performance_score = (
        0.4 * avg_quiz_score +
        0.3 * completion_rate +
        0.2 * avg_completion_time_score +
        0.1 * min(total_courses_completed / 10, 1)
    )

    # Blend performance (70%) and LoE_DI (30%)
    competency_score = 0.7 * performance_score + 0.3 * loe_score
    return min(max(competency_score, 0.3), 0.9)

def get_popular_courses(courses_list, user_interactions, limit=5):
    """Fetch popular courses based on enrollment count and ratings."""
    enrollment_counts = user_interactions.groupby('course_id').size().reset_index(name='enrollment_count')
    courses_with_counts = courses_list.merge(enrollment_counts, on='course_id', how='left').fillna({'enrollment_count': 0})
    approved_courses = courses_with_counts[courses_list['Status'] == 'approved']
    popular_courses = approved_courses.sort_values(['enrollment_count', 'Course Rating'], ascending=[False, False]).head(limit)
    return [get_course_details(row['course_id'], courses_list) for _, row in popular_courses.iterrows()]

# def build_learning_pathways(courses_list, similarity):
#     """Construct learning pathways using course similarity and difficulty."""
#     G = nx.DiGraph()
#     for i, row_i in courses_list.iterrows():
#         G.add_node(row_i['course_id'], difficulty=normalize_difficulty(row_i['Difficulty Level']))
#         for j, row_j in courses_list.iterrows():
#             if i != j and normalize_difficulty(row_j['Difficulty Level']) > normalize_difficulty(row_i['Difficulty Level']):
#                 weight = 1 - similarity[i][j]
#                 G.add_edge(row_i['course_id'], row_j['course_id'], weight=weight)
    
#     pathways = {}
#     for course_id in courses_list['course_id']:
#         successors = nx.single_source_dijkstra_path(G, course_id)
#         pathways[course_id] = successors
#     return pathways
def build_learning_pathways(courses_list, similarity):
    G = nx.DiGraph()
    for i, row_i in courses_list.iterrows():
        G.add_node(row_i['course_id'], difficulty=normalize_difficulty(row_i['Difficulty Level']))
        topics_i = extract_topic_sequence(row_i)
        for j, row_j in courses_list.iterrows():
            if i != j:
                topics_j = extract_topic_sequence(row_j)
                topic_overlap = len(set(topics_i) & set(topics_j))
                is_progression = False
                if topics_j and topics_i:
                    last_topic_i = topics_i[-1]
                    if last_topic_i in topics_j and topics_j.index(last_topic_i) < len(topics_j) - 1:
                        is_progression = True
                    elif topic_overlap > 0 and normalize_difficulty(row_j['Difficulty Level']) > normalize_difficulty(row_i['Difficulty Level']):
                        is_progression = True
                if is_progression:
                    weight = 1 - similarity[i][j]
                    weight *= (1 - topic_overlap / max(len(topics_i), len(topics_j), 1))
                    G.add_edge(row_i['course_id'], row_j['course_id'], weight=weight)
    
    pathways = {}
    for course_id in courses_list['course_id']:
        successors = nx.single_source_dijkstra_path(G, course_id)
        pathways[course_id] = successors
        logger.info(f"Pathway for course_id={course_id}: {len(successors)} successors")
    return pathways

# def get_course_details(course_id, courses_list):
#     """Helper function to extract complete course details."""
#     course_row = courses_list[courses_list['course_id'] == course_id]
#     if not course_row.empty:
#         course = course_row.iloc[0]
#         return {
#             'course_id': int(course['course_id']),
#             'course_name': course.get('course_name', ''),
#             'difficulty_level': course.get('Difficulty Level', ''),
#             'university': course.get('University', 'Unknown'),
#             'skills': course.get('Skills', ''),
#             'description': course.get('Course Description', ''),
#             'price': str(course.get('Price', 'Unknown')),
#             'rating': float(course.get('Course Rating', 0)),
#             'course_url': course.get('Course URL', 'Unknown'),
#             'status': course.get('Status', 'Unknown'),
#             'categories': course.get('Categories', 'Uncategorized'),
#             'instructor_id': course.get('Instructor IDs', '0')
#         }
#     return None
def get_course_details(course_id, courses_list):
    course_row = courses_list[courses_list['course_id'] == course_id]
    if not course_row.empty:
        course = course_row.iloc[0]
        rating = course.get('Course Rating', 0)
        # Ensure rating is a valid float
        rating = 0 if not np.isfinite(float(rating)) else float(rating)
        return {
            'course_id': int(course['course_id']),
            'course_name': course.get('course_name', ''),
            'difficulty_level': course.get('Difficulty Level', ''),
            'university': course.get('University', 'Unknown'),
            'skills': course.get('Skills', ''),
            'description': course.get('Course Description', ''),
            'price': str(course.get('Price', 'Unknown')),
            'rating': rating,
            'course_url': course.get('Course URL', 'Unknown'),
            'status': course.get('Status', 'Unknown'),
            'categories': course.get('Categories', 'Uncategorized'),
            'instructor_id': course.get('Instructor IDs', '0')
        }
    return None
def deduplicate_similar_courses(content_scores, courses_list, user_interactions, preferred_instructors, preferred_categories, competency_score, similarity):
    """Deduplicate courses with similar content, selecting the best based on multiple criteria."""
    # Calculate enrollment counts for popularity
    enrollment_counts = user_interactions.groupby('course_id').size().to_dict()
    max_enrollments = max(enrollment_counts.values(), default=1) or 1  # Avoid division by zero

    # Group courses by high similarity (threshold > 0.9)
    similar_groups = []
    used_course_ids = set()
    for cid1, score1 in content_scores.items():
        if cid1 in used_course_ids:
            continue
        group = [(cid1, score1)]
        for cid2, score2 in content_scores.items():
            if cid2 != cid1 and cid2 not in used_course_ids:
                # Check similarity between cid1 and cid2
                idx1 = courses_list[courses_list['course_id'] == cid1].index[0]
                idx2 = courses_list[courses_list['course_id'] == cid2].index[0]
                if similarity[idx1][idx2] > 0.9:  # High similarity threshold
                    group.append((cid2, score2))
                    used_course_ids.add(cid2)
        if group:
            similar_groups.append(group)
        used_course_ids.add(cid1)

    # Select the best course from each group
    deduplicated_scores = {}
    for group in similar_groups:
        best_course = None
        best_score = -1
        for cid, content_score in group:
            # Calculate prioritization score
            course_row = courses_list[courses_list['course_id'] == cid]
            course_rating = course_row['Course Rating'].iloc[0] / 5.0  # Normalize to [0, 1]
            enrollment_score = enrollment_counts.get(cid, 0) / max_enrollments  # Normalize
            instructor_ids = course_row['Instructor IDs'].iloc[0].split(',') if isinstance(course_row['Instructor IDs'].iloc[0], str) else []
            instructor_boost = 1.3 if any(i in preferred_instructors for i in instructor_ids) else 1
            course_categories = course_row['Categories'].iloc[0].split(',') if isinstance(course_row['Categories'].iloc[0], str) else []
            category_boost = 1.5 if any(cat in preferred_categories for cat in course_categories) else 1
            difficulty_score = 1 - abs(normalize_difficulty(course_row['Difficulty Level'].iloc[0]) - competency_score)  # Closer to competency is better

            # Weighted score: 40% rating, 30% enrollment, 20% instructor, 10% difficulty
            prioritization_score = (
                0.4 * course_rating +
                0.3 * enrollment_score +
                0.2 * (instructor_boost - 1) / 0.3 +  # Normalize boost
                0.1 * difficulty_score
            ) * category_boost

            if prioritization_score > best_score:
                best_score = prioritization_score
                best_course = (cid, content_score)

        if best_course:
            deduplicated_scores[best_course[0]] = best_course[1]

    return deduplicated_scores

# def recommend(user_id=None, course_name=None, alpha=0.5, courses_list=None, similarity=None, svd=None, user_interactions=None, user_competency=None, user_features=None, svd_predictions=None, pathways=None,student_categories=None,reviews=None):
#     """
#     Generate course recommendations based on user_id, course_name, or both.
#     Returns a list of dictionaries with complete course details.
#     """
#     if user_id is None and course_name is None:
#         logger.info("Generating popular course recommendations")
#         return get_popular_courses(courses_list, user_interactions)

#     recommended_courses = []
#     competency_score = user_competency[user_competency['user_id'] == user_id]['competency_score'].iloc[0] if user_id in user_competency['user_id'].values else 0.3
#     preferred_categories = student_categories[student_categories['user_id'] == user_id]['category_name'].tolist() if user_id is not None else []
#     if user_id is not None:
#         user_cluster = user_features[user_features['user_id'] == user_id]['cluster'].iloc[0] if user_id in user_features['user_id'].values else 0
#         cluster_users = user_features[user_features['cluster'] == user_cluster]['user_id']
#         cluster_interactions = user_interactions[user_interactions['user_id'].isin(cluster_users)]
        
#         # Use precomputed SVD predictions
#         cf_scores = svd_predictions.get(user_id, {})
#         # Filter courses interacted by cluster users
#         course_ids = cluster_interactions['course_id'].unique()
#         course_ids = [cid for cid in course_ids if cid in courses_list['course_id'].values]

#         # Kiểm tra đánh giá thấp với feedback_type
#         low_rated_courses = reviews[(reviews['user_id'] == user_id) & (reviews['rating'] <= 2) & 
#                                    (reviews['feedback_type'] != 'not_interested')]['course_id'].tolist()
#         if low_rated_courses and course_name:
#             alpha = 0.8  # Ưu tiên content-based nếu đánh giá thấp không phải do không quan tâm

#         preferred_instructors = user_interactions[user_interactions['user_id'] == user_id][['course_id', 'rating']]
#         preferred_instructors = preferred_instructors[preferred_instructors['rating'] >= 4]['course_id']
#         preferred_instructors = courses_list[courses_list['course_id'].isin(preferred_instructors)]['Instructor IDs'].apply(lambda x: x.split(',') if isinstance(x, str) else []).explode().unique()

#         if course_name is None:
#             valid_courses = [
#                 cid for cid in course_ids
#                 if normalize_difficulty(courses_list[courses_list['course_id'] == cid]['Difficulty Level'].iloc[0]) >= competency_score
#                 and courses_list[courses_list['course_id'] == cid]['Status'].iloc[0] == 'approved'
#             ]
#             # Ưu tiên khóa học trong danh mục
#             category_boosted_courses = []
#             for cid in valid_courses:
#                 course_categories = courses_list[courses_list['course_id'] == cid]['Categories'].iloc[0].split(',') if isinstance(courses_list[courses_list['course_id'] == cid]['Categories'].iloc[0], str) else []
#                 category_boost = 1.5 if any(cat in preferred_categories for cat in course_categories) else 1
#                 pathway_boost = 1.2 if cid in pathways.get(cid, {}) else 1
#                 score = cf_scores.get(cid, 0) * category_boost * pathway_boost
#                 category_boosted_courses.append((cid, score))

#             top_courses = sorted(
#                 [(cid, cf_scores.get(cid, 0) * (1.2 if cid in pathways.get(cid, {}) else 1)) for cid in valid_courses],
#                 key=lambda x: x[1], reverse=True
#             )[:5]
#             for course_id, _ in top_courses:
#                 course_details = get_course_details(course_id, courses_list)
#                 if course_details:
#                     recommended_courses.append(course_details)
#         else:
#             # try:
#             #     course_index = courses_list[courses_list['course_name'] == course_name].index[0]
#             #     course_id = courses_list.iloc[course_index]['course_id']
#             #     distances = similarity[course_index]
#             #     content_scores = sorted(list(enumerate(distances)), reverse=True, key=lambda x: x[1])[1:10]
#             #     content_scores = {courses_list.iloc[idx]['course_id']: score for idx, score in content_scores}

#             #     hybrid_scores = {}
#             #     for cid in content_scores:
#             #         if normalize_difficulty(courses_list[courses_list['course_id'] == cid]['Difficulty Level'].iloc[0]) >= competency_score:
#             #             instructor_ids = courses_list[courses_list['course_id'] == cid]['Instructor IDs'].iloc[0]
#             #             instructor_ids = instructor_ids.split(',') if isinstance(instructor_ids, str) else []
#             #             instructor_boost = 1.3 if any(i in preferred_instructors for i in instructor_ids) else 1
#             #             pathway_boost = 1.2 if cid in pathways.get(course_id, {}) else 1
#             #             hybrid_scores[cid] = (
#             #                 alpha * content_scores[cid] +
#             #                 (1 - alpha) * cf_scores.get(cid, 0)
#             #             ) * instructor_boost * pathway_boost

#             #     top_courses = sorted(hybrid_scores.items(), key=lambda x: x[1], reverse=True)[:5]
#             #     for course_id, _ in top_courses:
#             #         course_details = get_course_details(course_id, courses_list)
#             #         if course_details:
#             #             recommended_courses.append(course_details)
#             # except IndexError:
#             #     return [f"Course '{course_name}' not found."]
#             try:
#                 course_index = courses_list[courses_list['course_name'] == course_name].index[0]
#                 course_id = courses_list.iloc[course_index]['course_id']
#                 distances = similarity[course_index]
#                 content_scores = sorted(list(enumerate(distances)), reverse=True, key=lambda x: x[1])[1:10]
#                 content_scores = {courses_list.iloc[idx]['course_id']: score for idx, score in content_scores}

#                 hybrid_scores = {}
#                 for cid in content_scores:
#                     if normalize_difficulty(courses_list[courses_list['course_id'] == cid]['Difficulty Level'].iloc[0]) >= competency_score:
#                         instructor_ids = courses_list[courses_list['course_id'] == cid]['Instructor IDs'].iloc[0]
#                         instructor_ids = instructor_ids.split(',') if isinstance(instructor_ids, str) else []
#                         instructor_boost = 1.3 if any(i in preferred_instructors for i in instructor_ids) else 1
#                         pathway_boost = 1.2 if cid in pathways.get(course_id, {}) else 1
#                         course_categories = courses_list[courses_list['course_id'] == cid]['Categories'].iloc[0].split(',') if isinstance(courses_list[courses_list['course_id'] == cid]['Categories'].iloc[0], str) else []
#                         category_boost = 1.5 if any(cat in preferred_categories for cat in course_categories) else 1
#                         hybrid_scores[cid] = (
#                             alpha * content_scores[cid] +
#                             (1 - alpha) * cf_scores.get(cid, 0)
#                         ) * instructor_boost * pathway_boost * category_boost

#                 top_courses = sorted(hybrid_scores.items(), key=lambda x: x[1], reverse=True)[:5]
#                 for course_id, _ in top_courses:
#                     course_details = get_course_details(course_id, courses_list)
#                     if course_details:
#                         recommended_courses.append(course_details)
#             except IndexError:
#                 return [f"Course '{course_name}' not found."]
#     else:
#         try:
#             course_index = courses_list[courses_list['course_name'] == course_name].index[0]
#             course_id = courses_list.iloc[course_index]['course_id']
#             distances = sorted(list(enumerate(similarity[course_index])), reverse=True, key=lambda x: x[1])
#             for i in distances[1:6]:
#                 next_course_id = courses_list.iloc[i[0]]['course_id']
#                 if normalize_difficulty(courses_list.iloc[i[0]]['Difficulty Level']) >= competency_score:
#                     if next_course_id in pathways.get(course_id, {}):
#                         course_details = get_course_details(next_course_id, courses_list)
#                         if course_details:
#                             recommended_courses.append(course_details)
#         except IndexError:
#             return [f"Course '{course_name}' not found."]

#     return recommended_courses
def extract_topic_sequence(course_row):
    """Extract ordered list of topics from Skills or Course Description."""
    skills = course_row['Skills'].split(',') if isinstance(course_row['Skills'], str) else []
    skills = [s.strip().lower() for s in skills if s.strip()]
    
    # Fallback to Course Description if Skills is empty
    if not skills and isinstance(course_row['Course Description'], str):
        # Simple keyword-based extraction (can be enhanced with NLP)
        description = course_row['Course Description'].lower()
        common_topics = ['html', 'css', 'javascript', 'python', 'sql', 'react', 'angular']
        skills = [topic for topic in common_topics if topic in description]
    
    # Remove duplicates while preserving order
    seen = set()
    ordered_topics = [s for s in skills if not (s in seen or seen.add(s))]
    return ordered_topics

def recommend(user_id=None, course_name=None, alpha=0.5, courses_list=None, similarity=None, svd=None, user_interactions=None, user_competency=None, user_features=None, svd_predictions=None, pathways=None, student_categories=None, reviews=None):
    """
    Generate course recommendations based on user_id, course_name, or both.
    Returns a list of dictionaries with complete course details.
    """
    if user_id is None and course_name is None:
        logger.info("Generating popular course recommendations")
        return get_popular_courses(courses_list, user_interactions)

    recommended_courses = []
    competency_score = user_competency[user_competency['user_id'] == user_id]['competency_score'].iloc[0] if user_id in user_competency['user_id'].values else 0.3
    preferred_categories = student_categories[student_categories['user_id'] == user_id]['category_name'].tolist() if user_id is not None else []
    
    if user_id is not None:
        user_cluster = user_features[user_features['user_id'] == user_id]['cluster'].iloc[0] if user_id in user_features['user_id'].values else 0
        cluster_users = user_features[user_features['cluster'] == user_cluster]['user_id']
        cluster_interactions = user_interactions[user_interactions['user_id'].isin(cluster_users)]
        
        # Use precomputed SVD predictions
        cf_scores = svd_predictions.get(user_id, {})
        # Filter courses interacted by cluster users and ensure status is approved
        course_ids = cluster_interactions['course_id'].unique()
        course_ids = [
            cid for cid in course_ids 
            if cid in courses_list['course_id'].values 
            and courses_list[courses_list['course_id'] == cid]['Status'].iloc[0] == 'approved'
        ]

        # Kiểm tra đánh giá thấp với feedback_type
        low_rated_courses = reviews[(reviews['user_id'] == user_id) & (reviews['rating'] <= 2) & 
                                   (reviews['feedback_type'] != 'not_interested')]['course_id'].tolist()
        if low_rated_courses and course_name:
            alpha = 0.8  # Prioritize content-based if low ratings are not due to lack of interest

        preferred_instructors = user_interactions[user_interactions['user_id'] == user_id][['course_id', 'rating']]
        preferred_instructors = preferred_instructors[preferred_instructors['rating'] >= 4]['course_id']
        preferred_instructors = courses_list[courses_list['course_id'].isin(preferred_instructors)]['Instructor IDs'].apply(lambda x: x.split(',') if isinstance(x, str) else []).explode().unique()

        if course_name is None:
            valid_courses = [
                cid for cid in course_ids
                if normalize_difficulty(courses_list[courses_list['course_id'] == cid]['Difficulty Level'].iloc[0]) >= competency_score
                and courses_list[courses_list['course_id'] == cid]['Status'].iloc[0] == 'approved'
            ]
            # Prioritize courses in preferred categories
            category_boosted_courses = []
            for cid in valid_courses:
                course_categories = courses_list[courses_list['course_id'] == cid]['Categories'].iloc[0].split(',') if isinstance(courses_list[courses_list['course_id'] == cid]['Categories'].iloc[0], str) else []
                category_boost = 1.5 if any(cat in preferred_categories for cat in course_categories) else 1
                pathway_boost = 1.2 if cid in pathways.get(cid, {}) else 1
                score = cf_scores.get(cid, 0) * category_boost * pathway_boost
                category_boosted_courses.append((cid, score))

            top_courses = sorted(
                [(cid, cf_scores.get(cid, 0) * (1.2 if cid in pathways.get(cid, {}) else 1)) for cid in valid_courses],
                key=lambda x: x[1], reverse=True
            )[:5]
            for course_id, _ in top_courses:
                course_details = get_course_details(course_id, courses_list)
                if course_details:
                    recommended_courses.append(course_details)
        else:
            try:
                course_index = courses_list[courses_list['course_name'] == course_name].index[0]
                course_id = courses_list.iloc[course_index]['course_id']
                if courses_list[courses_list['course_id'] == course_id]['Status'].iloc[0] != 'approved':
                    return [f"Course '{course_name}' is not approved."]
                distances = similarity[course_index]
                content_scores = sorted(list(enumerate(distances)), reverse=True, key=lambda x: x[1])[1:10]
                content_scores = {
                    courses_list.iloc[idx]['course_id']: score 
                    for idx, score in content_scores 
                    if courses_list.iloc[idx]['Status'] == 'approved'
                    and courses_list.iloc[idx]['course_id'] in pathways.get(course_id, {})
                }
                preferred_instructors = preferred_instructors[preferred_instructors['rating'] >= 4]['course_id']      
                
                # Deduplicate similar courses, reduce instructor bias
                content_scores = deduplicate_similar_courses(
                    content_scores, courses_list, user_interactions, 
                    preferred_instructors, preferred_categories, competency_score,similarity
                )

                hybrid_scores = {}
                for cid in content_scores:
                    if (normalize_difficulty(courses_list[courses_list['course_id'] == cid]['Difficulty Level'].iloc[0]) >= competency_score and
                        courses_list[courses_list['course_id'] == cid]['Status'].iloc[0] == 'approved'):
                        instructor_ids = courses_list[courses_list['course_id'] == cid]['Instructor IDs'].iloc[0]
                        instructor_ids = instructor_ids.split(',') if isinstance(instructor_ids, str) else []
                        instructor_boost = 1.1 if any(i in preferred_instructors for i in instructor_ids) else 1  # Reduced boost for diversity
                        pathway_boost = 1.2 if cid in pathways.get(course_id, {}) else 1
                        course_categories = courses_list[courses_list['course_id'] == cid]['Categories'].iloc[0].split(',') if isinstance(courses_list[courses_list['course_id'] == cid]['Categories'].iloc[0], str) else []
                        category_boost = 1.5 if any(cat in preferred_categories for cat in course_categories) else 1
                        # Add topic progression boost
                        topics_current = extract_topic_sequence(courses_list[courses_list['course_id'] == course_id].iloc[0])
                        topics_next = extract_topic_sequence(courses_list[courses_list['course_id'] == cid].iloc[0])
                        topic_boost = 1.3 if topics_next and topics_current and topics_current[-1] in topics_next and topics_next.index(topics_current[-1]) < len(topics_next) - 1 else 1
                        hybrid_scores[cid] = (
                            alpha * content_scores[cid] +
                            (1 - alpha) * cf_scores.get(cid, 0)
                        ) * instructor_boost * pathway_boost * category_boost * topic_boost

                top_courses = sorted(hybrid_scores.items(), key=lambda x: x[1], reverse=True)[:5]
                for course_id, _ in top_courses:
                    course_details = get_course_details(course_id, courses_list)
                    if course_details:
                        recommended_courses.append(course_details)
            except IndexError:
                return [f"Course '{course_name}' not found."]
    else:
        try:
            course_index = courses_list[courses_list['course_name'] == course_name].index[0]
            course_id = courses_list.iloc[course_index]['course_id']
            if courses_list[courses_list['course_id'] == course_id]['Status'].iloc[0] != 'approved':
                return [f"Course '{course_name}' is not approved."]
            distances = sorted(list(enumerate(similarity[course_index])), reverse=True, key=lambda x: x[1])
            content_scores = {
                courses_list.iloc[idx]['course_id']: score 
                for idx, score in distances[1:10] 
                if courses_list.iloc[idx]['Status'] == 'approved'
                and courses_list.iloc[idx]['course_id'] in pathways.get(course_id, {})
            }
            logger.info(f"Content scores after filtering: {content_scores}")
            preferred_instructors = set()
            # Deduplicate similar courses
            content_scores = deduplicate_similar_courses(
                content_scores, courses_list, user_interactions, 
                preferred_instructors, preferred_categories, competency_score,similarity
            )
            logger.info(f"Content scores after filtering: {content_scores}")
            # In recommend(), for the else block (no user_id)
            for i, (cid, _) in enumerate(sorted(content_scores.items(), key=lambda x: x[1], reverse=True)[:5]):
                next_course_id = cid
                course_difficulty = normalize_difficulty(courses_list[courses_list['course_id'] == next_course_id]['Difficulty Level'].iloc[0])
                logger.info(f"Course_id={next_course_id}, Difficulty={course_difficulty}, Competency={competency_score}")
                if (course_difficulty >= 0.0  # Lower threshold to 0
                    and courses_list[courses_list['course_id'] == next_course_id]['Status'].iloc[0] == 'approved'):
                    if next_course_id in pathways.get(course_id, {}):
                        course_details = get_course_details(next_course_id, courses_list)
                        if course_details:
                            recommended_courses.append(course_details)
        except IndexError:
            return [f"Course '{course_name}' not found."]
    # After building recommended_courses
    for course in recommended_courses:
        logger.info(f"Course details: {course}")
        for key, value in course.items():
            if isinstance(value, float) and not np.isfinite(value):
                logger.error(f"Invalid float in {key}: {value}")
                course[key] = 0  # Replace invalid float
    return recommended_courses

def recommend_similar_courses(course_name, data_file='Data/Coursera_new.csv', num_recommendations=5, random_sample_size=10):
    """
    Recommend courses similar to the input course based on difficulty, skills, and categories.
    
    Parameters:
    - course_name (str): Name of the input course.
    - data_file (str): Path to the CSV file containing course data.
    - num_recommendations (int): Number of courses to recommend.
    - random_sample_size (int): Number of candidate courses to sample randomly before ranking.
    
    Returns:
    - list: List of dictionaries containing recommended course details.
    """
    try:
        # Load data
        df = pd.read_csv(data_file)
        logger.info(f"Loaded {len(df)} courses from {data_file}")
        
        # Clean data
        df['Course Description'] = df['Course Description'].fillna('')
        df['Skills'] = df['Skills'].fillna('')
        df['Categories'] = df['Categories'].fillna('Uncategorized')
        df['Status'] = df['Status'].fillna('approved')
        df['Course Rating'] = pd.to_numeric(df['Course Rating'], errors='coerce').fillna(0)
        df['Price'] = pd.to_numeric(df['Price'], errors='coerce').fillna(0).astype(str)
        df['Difficulty Level'] = df['Difficulty Level'].fillna('Unknown').astype(str)
        logger.info(f"Difficulty Level unique values: {df['Difficulty Level'].unique()}")
        
        # Check if course exists
        if course_name not in df['Course Name'].values:
            logger.error(f"Course '{course_name}' not found.")
            return [{"error": f"Course '{course_name}' not found."}]
        
        # Get input course details
        input_course = df[df['Course Name'] == course_name].iloc[0]
        input_difficulty = str(input_course['Difficulty Level'])
        input_skills = input_course['Skills'].lower().split(',') if input_course['Skills'] else []
        input_categories = input_course['Categories'].split(',') if input_course['Categories'] else []
        input_skills = [s.strip() for s in input_skills if s.strip()]
        input_categories = [c.strip() for c in input_categories if c.strip()]
        
        logger.info(f"Input course: {course_name}, Difficulty: {input_difficulty}, Skills: {input_skills}, Categories: {input_categories}")
        
        # Create text for TF-IDF (combine Skills and Categories)
        df['combined_text'] = df['Skills'].str.lower() + ' ' + df['Categories'].str.lower()
        input_text = ' '.join(input_skills + input_categories)
        
        # Compute TF-IDF similarity
        vectorizer = TfidfVectorizer(stop_words='english')
        tfidf_matrix = vectorizer.fit_transform(df['combined_text'])
        input_vector = vectorizer.transform([input_text])
        similarity_scores = cosine_similarity(input_vector, tfidf_matrix).flatten()
        logger.info(f"Similarity scores range: {similarity_scores.min()} to {similarity_scores.max()}")
        
        # Create similarity DataFrame
        similarity_df = pd.DataFrame({
            'course_id': df.index + 1,
            'course_name': df['Course Name'],
            'similarity': similarity_scores,
            'difficulty': df['Difficulty Level'].astype(str),
            'status': df['Status'],
            'skills': df['Skills'],
            'categories': df['Categories']
        })
        
        # Filter candidates
        candidates = similarity_df[
            (similarity_df['status'] == 'approved') &
            (similarity_df['course_name'] != course_name) &
            (similarity_df['similarity'] > 0)
        ]
        logger.info(f"Number of candidates after filtering: {len(candidates)}")
        
        if candidates.empty:
            logger.warning("No candidates found after filtering.")
            return [{"warning": "No similar courses found due to filtering."}]
        
        # Boost for same difficulty
        candidates = candidates.copy()  # Avoid SettingWithCopyWarning
        candidates['difficulty_boost'] = np.where(candidates['difficulty'].str.strip() == input_difficulty.strip(), 1.5, 1.0)
        candidates['score'] = candidates['similarity'] * candidates['difficulty_boost']
        logger.info(f"Difficulty boost applied: {candidates[['course_name', 'difficulty', 'difficulty_boost']].head().to_dict('records')}")
        
        # Filter by skill and category overlap
        def skill_overlap(row):
            course_skills = row['skills'].lower().split(',') if row['skills'] else []
            course_skills = [s.strip() for s in course_skills if s.strip()]
            overlap = len(set(course_skills) & set(input_skills))
            return overlap / max(len(input_skills), 1) if input_skills else 0.0
        
        def category_overlap(row):
            course_categories = row['categories'].split(',') if row['categories'] else []
            course_categories = [c.strip() for c in course_categories if c.strip()]
            overlap = len(set(course_categories) & set(input_categories))
            return overlap / max(len(input_categories), 1) if input_categories else 0.0
        
        candidates['skill_overlap'] = candidates.apply(skill_overlap, axis=1)
        candidates['category_overlap'] = candidates.apply(category_overlap, axis=1)
        candidates['score'] *= (1 + 0.3 * candidates['skill_overlap'] + 0.2 * candidates['category_overlap'])
        logger.info(f"Overlap scores applied: {candidates[['course_name', 'skill_overlap', 'category_overlap', 'score']].head().to_dict('records')}")
        
        # Randomly sample candidates for diversity
        if len(candidates) > random_sample_size:
            candidates = candidates.sample(n=random_sample_size, random_state=42)
            logger.info(f"Sampled {len(candidates)} candidates for diversity")
        
        # Sort by score
        top_candidates = candidates.sort_values(by='score', ascending=False).head(num_recommendations)
        logger.info(f"Top candidates: {top_candidates[['course_name', 'score', 'similarity', 'skill_overlap', 'category_overlap']].to_dict('records')}")
        
        # Format output
        recommendations = []
        for _, row in top_candidates.iterrows():
            course_row = df[df['Course Name'] == row['course_name']].iloc[0]
            recommendations.append({
                'course_id': int(row['course_id']),
                'course_name': row['course_name'],
                'difficulty_level': course_row['Difficulty Level'],
                'university': course_row['University'],
                'skills': course_row['Skills'],
                'description': course_row['Course Description'],
                'price': str(course_row['Price']),
                'rating': float(course_row['Course Rating']),
                'course_url': course_row['Course URL'],
                'status': course_row['Status'],
                'categories': course_row['Categories'],
                'instructor_id': course_row.get('Instructor IDs', 'Unknown')
            })
        
        if not recommendations:
            logger.warning("No similar courses found after final selection.")
            return [{"warning": "No similar courses found."}]
        
        return recommendations
    
    except Exception as e:
        logger.error(f"Error in recommendation: {str(e)}")
        return [{"error": f"Error: {str(e)}"}]

def update_model():
    """
    Train the model and save the artifacts to the models/ directory.
    """
    logger.info("Starting model update")
    if not os.path.exists('models'):
        os.makedirs('models')

    try:
        data = pd.read_csv('Data/Coursera_new.csv').head(1000)
        user_behavior = pd.read_csv('Data/Courseuserbehavior_new.csv')
        quiz_results = pd.read_csv('Data/quiz_results.csv')
        enrollments = pd.read_csv('Data/enrollments.csv')
        reviews = pd.read_csv('Data/reviews.csv')
        student_categories = pd.read_csv('Data/student_categories.csv')
    except FileNotFoundError as e:
        logger.error(f"Data file missing: {e}")
        raise Exception(f"Data file missing: {e}")

    required_columns = ['Course Name', 'University', 'Difficulty Level', 'Course Rating', 'Course URL', 'Course Description', 'Skills', 'Price', 'Status', 'Categories', 'Instructor IDs']
    missing_columns = [col for col in required_columns if col not in data.columns]
    if missing_columns:
        logger.error(f"Missing columns in Coursera_new.csv: {missing_columns}")
        raise Exception(f"Missing columns in Coursera_new.csv: {missing_columns}")

    # data['Course Description'] = data['Course Description'].fillna('')
    # data['course_name'] = data['Course Name']
    # data['course_id'] = range(1, len(data) + 1)
    # data['Categories'] = data['Categories'].fillna('Uncategorized')
    # data['Instructor IDs'] = data['Instructor IDs'].fillna('0')
    # courses_list = data[['course_id', 'course_name', 'Course Description', 'Difficulty Level', 'University', 'Skills', 'Price', 'Course Rating', 'Course URL', 'Status', 'Categories', 'Instructor IDs']]
    data['Course Description'] = data['Course Description'].fillna('')
    data['course_name'] = data['Course Name']
    data['course_id'] = range(1, len(data) + 1)
    data['Categories'] = data['Categories'].fillna('Uncategorized')
    data['Instructor IDs'] = data['Instructor IDs'].fillna('0')
    data['Status'] = data['Status'].fillna('approved')
    # Add cleaning for Course Rating and Price
    data['Course Rating'] = pd.to_numeric(data['Course Rating'], errors='coerce').fillna(0).clip(0, 5)  # Ensure valid ratings
    data['Price'] = pd.to_numeric(data['Price'], errors='coerce').fillna(0).astype(str)  # Convert to string after cleaning
    courses_list = data[['course_id', 'course_name', 'Course Description', 'Difficulty Level', 'University', 'Skills', 'Price', 'Course Rating', 'Course URL', 'Status', 'Categories', 'Instructor IDs']]
    try:
        model = SentenceTransformer('all-MiniLM-L6-v2')
        course_texts = (courses_list['Course Description'] + ' ' + courses_list['University'] + ' ' + courses_list['Skills']).tolist()
        logger.info("Encoding texts...")
        course_embeddings = model.encode(course_texts, show_progress_bar=True)
        logger.info("Computing cosine similarity...")
        similarity = cosine_similarity(course_embeddings)
        logger.info("Similarity computed")
    except Exception as e:
        logger.error(f"Error generating BERT embeddings: {e}")
        raise Exception(f"Error generating BERT embeddings: {e}")

    # Precompute learning pathways
    logger.info("Building learning pathways...")
    pathways = build_learning_pathways(courses_list, similarity)
    logger.info("Pathways computed")

    if 'userid_DI' not in user_behavior.columns:
        logger.error("'userid_DI' column not found in Courseuserbehavior.csv")
        raise Exception("'userid_DI' column not found in Courseuserbehavior.csv")
    
    unique_course_ids = user_behavior['course_id'].unique()
    unique_user_ids = user_behavior['userid_DI'].unique()
    np.random.seed(42)
    course_id_map = {cid: np.random.choice(courses_list['course_id']) for cid in unique_course_ids}
    user_id_map = {uid: i+1 for i, uid in enumerate(unique_user_ids)}

    # user_interactions = pd.DataFrame({
    #     'user_id': user_behavior['userid_DI'].map(user_id_map),
    #     'course_id': user_behavior['course_id'].map(course_id_map),
    #     'rating': np.random.choice([1, 2, 3, 4, 5], size=len(user_behavior), p=[0.1, 0.1, 0.2, 0.3, 0.3]),
    #     'viewed': user_behavior['viewed'].astype(bool),
    #     'completed': user_behavior['certified'].astype(bool),
    #     'timestamp': pd.to_datetime(user_behavior['last_event_DI'], errors='coerce').fillna(pd.Timestamp.now())
    # })
    user_interactions = pd.DataFrame({
    'user_id': user_behavior['userid_DI'].map(user_id_map),
    'course_id': user_behavior['course_id'].map(course_id_map),
    'rating': np.random.choice([1, 2, 3, 4, 5], size=len(user_behavior), p=[0.1, 0.1, 0.2, 0.3, 0.3]),
    'viewed': user_behavior['viewed'].astype(bool),
    'completed': user_behavior['certified'].astype(bool),
    'timestamp': pd.to_datetime(user_behavior['last_event_DI'], errors='coerce').fillna(pd.Timestamp.now())
    })

    # Gộp feedback_type từ reviews
    reviews_subset = reviews[['user_id', 'course_id', 'rating', 'feedback_type']].copy()
    user_interactions = user_interactions.merge(
        reviews_subset,
        on=['user_id', 'course_id'],
        how='left',
        suffixes=('', '_review')
    )
    # Sử dụng rating từ reviews nếu có, nếu không giữ rating ngẫu nhiên
    user_interactions['rating'] = user_interactions['rating_review'].combine_first(user_interactions['rating'])
    user_interactions['feedback_type'] = user_interactions['feedback_type'].fillna('unknown')
    user_interactions = user_interactions.drop(columns=['rating_review'])

    quiz_results['started_at'] = pd.to_datetime(quiz_results['started_at'], errors='coerce')
    quiz_results['completed_at'] = pd.to_datetime(quiz_results['completed_at'], errors='coerce')
    enrollments['enrolled_at'] = pd.to_datetime(enrollments['enrolled_at'], errors='coerce')
    enrollments['completed_at'] = pd.to_datetime(enrollments['completed_at'], errors='coerce')

    user_competency = pd.DataFrame({
        'user_id': [user_id_map[uid] for uid in unique_user_ids],
        'competency_score': [calculate_competency(
            user_interactions[user_interactions['user_id'] == user_id_map[uid]],
            quiz_results[quiz_results['user_id'] == user_id_map[uid]],
            enrollments[enrollments['user_id'] == user_id_map[uid]]
        ) for uid in unique_user_ids]
    })

    # kmeans = KMeans(n_clusters=5, random_state=42)
    # user_features = user_behavior[['userid_DI', 'final_cc_cname_DI', 'LoE_DI', 'YoB']].drop_duplicates()
    # user_features['user_id'] = user_features['userid_DI'].map(user_id_map)
    # user_features['final_cc_cname_DI'] = user_features['final_cc_cname_DI'].fillna('Unknown')
    # user_features['LoE_DI'] = user_features['LoE_DI'].fillna('Unknown')
    # user_features['YoB'] = user_features['YoB'].fillna(user_features['YoB'].median())
    # user_features['cluster'] = kmeans.fit_predict(user_features[['YoB']].values)
    kmeans = KMeans(n_clusters=5, random_state=42)
    user_features = user_behavior[['userid_DI', 'final_cc_cname_DI', 'LoE_DI', 'YoB']].drop_duplicates()
    user_features['user_id'] = user_features['userid_DI'].map(user_id_map)
    user_features['final_cc_cname_DI'] = user_features['final_cc_cname_DI'].fillna('Unknown')
    user_features['LoE_DI'] = user_features['LoE_DI'].fillna('Unknown')
    user_features['YoB'] = user_features['YoB'].fillna(user_features['YoB'].median())
    user_features['cluster'] = kmeans.fit_predict(user_features[['YoB']].values)

    # Calculate user competency with LoE_DI
    user_competency = pd.DataFrame({
        'user_id': [user_id_map[uid] for uid in unique_user_ids],
        'competency_score': [
            calculate_competency(
                user_interactions[user_interactions['user_id'] == user_id_map[uid]],
                quiz_results[quiz_results['user_id'] == user_id_map[uid]],
                enrollments[enrollments['user_id'] == user_id_map[uid]],
                loe_di=user_features[user_features['user_id'] == user_id_map[uid]]['LoE_DI'].iloc[0] if user_id_map[uid] in user_features['user_id'].values else 'Unknown'
            ) for uid in unique_user_ids
        ]
    })

    reader = Reader(rating_scale=(1, 5))
    surprise_data = Dataset.load_from_df(user_interactions[['user_id', 'course_id', 'rating']], reader)
    trainset = surprise_data.build_full_trainset()
    svd = SVD(n_factors=50, n_epochs=10, random_state=42)
    svd.fit(trainset)

    # Precompute SVD predictions for all user-course pairs
    logger.info("Precomputing SVD predictions...")
    # svd_predictions = {}
    # for uid in unique_user_ids:
    #     user_id = user_id_map[uid]
    #     svd_predictions[user_id] = {}
    #     for course_id in courses_list['course_id']:
    #         pred = svd.predict(user_id, course_id)
    #         svd_predictions[user_id][course_id] = pred.est
    # Add debug code in update_model() before saving svd_predictions
    svd_predictions = {}
    for uid in unique_user_ids:
        user_id = user_id_map[uid]
        svd_predictions[user_id] = {}
        for course_id in courses_list['course_id']:
            pred = svd.predict(user_id, course_id)
            score = pred.est
            if not np.isfinite(score):
                score = 0  # Default to 0 for invalid predictions
            svd_predictions[user_id][course_id] = score

    try:
        with open('models/courses.pkl', 'wb') as f:
            pickle.dump(courses_list, f)
        with open('models/similarity.pkl', 'wb') as f:
            pickle.dump(similarity, f)
        with open('models/svd.pkl', 'wb') as f:
            pickle.dump(svd, f)
        with open('models/user_interactions.pkl', 'wb') as f:
            pickle.dump(user_interactions, f)
        with open('models/user_competency.pkl', 'wb') as f:
            pickle.dump(user_competency, f)
        with open('models/user_features.pkl', 'wb') as f:
            pickle.dump(user_features, f)
        with open('models/svd_predictions.pkl', 'wb') as f:
            pickle.dump(svd_predictions, f)
        with open('models/pathways.pkl', 'wb') as f:
            pickle.dump(pathways, f)
        with open('models/student_categories.pkl', 'wb') as f:
            pickle.dump(student_categories, f)
        with open('models/reviews.pkl', 'wb') as f:
            pickle.dump(reviews, f)
        logger.info("All files saved successfully")
    except Exception as e:
        logger.error(f"Error saving pickle files: {e}")
        raise Exception(f"Error saving pickle files: {e}")
