import uvicorn
from main import app  # import trực tiếp app từ main.py

if __name__ == "__main__":
    uvicorn.run(app, host="127.0.0.1", port=9000)
