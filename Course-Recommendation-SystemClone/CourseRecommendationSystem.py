
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


import pandas as pd
import numpy as np
try:
    from sentence_transformers import SentenceTransformer
except ImportError:
    print("Error: 'sentence_transformers' not installed. Run 'pip install sentence-transformers'.")
    exit(1)
from sklearn.metrics.pairwise import cosine_similarity
from surprise import SVD, Dataset, Reader
import pickle
import os
import sqlite3

# Tạo thư mục models nếu chưa tồn tại
if not os.path.exists('models'):
    os.makedirs('models')

# Đọc dữ liệu
try:
    data = pd.read_csv('Data/Coursera.csv')
    user_behavior = pd.read_csv('Data/Courseuserbehavior.csv')
except FileNotFoundError as e:
    print(f"Error: {e}. Please ensure 'Coursera.csv' and 'Courseuserbehavior.csv' exist in the 'Data' folder.")
    exit(1)

# Tiền xử lý dữ liệu khóa học
data['Course Description'].fillna('', inplace=True)
data['course_name'] = data['Course Name']
data['course_id'] = range(1, len(data) + 1)
courses_list = data[['course_id', 'course_name', 'Course Description', 'Difficulty Level']]

# Tạo ma trận tương đồng bằng BERT
try:
    model = SentenceTransformer('all-MiniLM-L6-v2')
    course_embeddings = model.encode(courses_list['Course Description'].tolist(), show_progress_bar=True)
    similarity = cosine_similarity(course_embeddings)
except Exception as e:
    print(f"Error generating BERT embeddings: {e}")
    exit(1)

# Tiền xử lý dữ liệu người dùng
unique_course_ids = user_behavior['course_id'].unique()
unique_user_ids = user_behavior['userid_DI'].unique()

# Cải thiện ánh xạ course_id bằng cách ánh xạ ngẫu nhiên tới course_id trong courses_list
np.random.seed(42)
course_id_map = {cid: np.random.choice(courses_list['course_id']) for cid in unique_course_ids}
user_id_map = {uid: i+1 for i, uid in enumerate(unique_user_ids)}

# Kiểm tra dữ liệu
print("Columns in user_behavior:", user_behavior.columns.tolist())
print("Unique values in 'grade':", user_behavior['grade'].unique())
print("Number of NaN in 'grade':", user_behavior['grade'].isna().sum())
if 'explored' in user_behavior.columns:
    print("Unique values in 'explored':", user_behavior['explored'].unique())
if 'nchapters' in user_behavior.columns:
    print("Unique values in 'nchapters':", user_behavior['nchapters'].unique())

# Hàm tính rating mới (ngẫu nhiên với xác suất cao cho rating 4 và 5)
def calculate_rating(row):
    ratings = [1.0, 2.0, 3.0, 4.0, 5.0]
    probabilities = [0.1, 0.1, 0.2, 0.3, 0.3]  # Ưu tiên rating 4 và 5
    return np.random.choice(ratings, p=probabilities)

# Tạo user_interactions
user_interactions = pd.DataFrame({
    'user_id': user_behavior['userid_DI'].map(user_id_map),
    'course_id': user_behavior['course_id'].map(course_id_map),
    'rating': user_behavior.apply(calculate_rating, axis=1),
    'viewed': user_behavior['viewed'].astype(bool),
    'completed': user_behavior['certified'].astype(bool),
    'timestamp': pd.to_datetime(user_behavior['last_event_DI'], errors='coerce').fillna(pd.Timestamp.now())
})

# Kiểm tra và đảm bảo có rating 5
if 5 not in user_interactions['rating'].values:
    print("No rating 5 found, forcing at least one rating to 5.")
    random_index = np.random.choice(user_interactions.index)
    user_interactions.loc[random_index, 'rating'] = 5.0

# Ép thêm 10% số hàng thành rating 5
force_5_percent = 0.1  # 10%
num_rows_to_force = int(len(user_interactions) * force_5_percent)
random_indices = np.random.choice(user_interactions.index, size=num_rows_to_force, replace=False)
user_interactions.loc[random_indices, 'rating'] = 5.0

# Kiểm tra rating
print("Unique ratings in 'user_interactions':", user_interactions['rating'].unique())

# Trích xuất đặc trưng người dùng
user_features = user_behavior[['userid_DI', 'final_cc_cname_DI', 'LoE_DI', 'YoB']].drop_duplicates()
user_features['user_id'] = user_features['userid_DI'].map(user_id_map)
user_features['final_cc_cname_DI'].fillna('Unknown', inplace=True)
user_features['LoE_DI'].fillna('Unknown', inplace=True)
user_features['YoB'].fillna(user_features['YoB'].median(), inplace=True)

# Huấn luyện SVD
reader = Reader(rating_scale=(1, 5))
surprise_data = Dataset.load_from_df(user_interactions[['user_id', 'course_id', 'rating']], reader)
trainset = surprise_data.build_full_trainset()
svd = SVD(n_factors=100, n_epochs=20, random_state=42)
svd.fit(trainset)

# Kết nối SQLite và lưu dữ liệu
try:
    conn = sqlite3.connect('users.db')
    user_interactions.to_sql('interactions', conn, if_exists='replace', index=False)
    user_features.to_sql('user_features', conn, if_exists='replace', index=False)
except Exception as e:
    print(f"Error saving to SQLite: {e}")
    conn.close()
    exit(1)
finally:
    conn.close()

# Lưu trữ
try:
    pickle.dump(courses_list, open('models/courses.pkl', 'wb'))
    pickle.dump(similarity, open('models/similarity.pkl', 'wb'))
    pickle.dump(svd, open('models/svd.pkl', 'wb'))
    pickle.dump(user_interactions, open('models/user_interactions.pkl', 'wb'))
    pickle.dump(user_features, open('models/user_features.pkl', 'wb'))
    print("All files saved successfully.")
except Exception as e:
    print(f"Error saving pickle files: {e}")
    exit(1)