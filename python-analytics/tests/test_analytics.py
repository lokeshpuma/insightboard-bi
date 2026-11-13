import io
import pytest
from fastapi.testclient import TestClient
from app.main import app
from app.core.config import settings

client = TestClient(app)
AUTH_HEADER = {"Authorization": f"Bearer {settings.service_token}"}

def test_health_endpoint():
    response = client.get("/internal/v1/health")
    assert response.status_code == 200
    data = response.json()
    assert data["status"] == "ok"
    assert "version" in data
    assert "timestamp" in data

def test_unauthorized_access():
    csv_content = b"name,age,salary\nAlice,30,75000\nBob,25,50000\n"
    files = {"file": ("test.csv", io.BytesIO(csv_content), "text/csv")}
    response = client.post("/internal/v1/analyze", files=files)
    assert response.status_code in [401, 403]

def test_forbidden_token():
    csv_content = b"name,age,salary\nAlice,30,75000\nBob,25,50000\n"
    files = {"file": ("test.csv", io.BytesIO(csv_content), "text/csv")}
    response = client.post(
        "/internal/v1/analyze",
        files=files,
        headers={"Authorization": "Bearer wrong-token"},
    )
    assert response.status_code == 403

def test_analyze_valid_csv():
    csv_content = (
        b"order_id,category,amount,is_refunded,order_date\n"
        b"101,Electronics,199.99,false,2026-01-10\n"
        b"102,Books,29.50,false,2026-01-11\n"
        b"103,Electronics,499.00,true,2026-01-12\n"
        b"104,Clothing,89.00,false,2026-01-12\n"
    )
    files = {"file": ("orders.csv", io.BytesIO(csv_content), "text/csv")}
    response = client.post("/internal/v1/analyze", files=files, headers=AUTH_HEADER)
    assert response.status_code == 200
    data = response.json()
    assert data["status"] == "completed"
    assert data["row_count"] == 4
    assert data["column_count"] == 5

    # Check columns
    cols = {c["label"]: c for c in data["columns"]}
    assert "order_id" in cols
    assert "category" in cols
    assert "amount" in cols

    # Amount should be decimal
    assert cols["amount"]["inferred_type"] == "decimal"
    assert cols["amount"]["statistics"]["min"] == 29.5
    assert cols["amount"]["statistics"]["max"] == 499.0

    # Order date should be date
    assert cols["order_date"]["inferred_type"] == "date"

    # Category frequency
    assert cols["category"]["top_categories"] is not None

def test_analyze_semicolon_delimiter():
    csv_content = b"name;score;active\nAlice;95;1\nBob;80;0\n"
    files = {"file": ("test.csv", io.BytesIO(csv_content), "text/csv")}
    data = {"delimiter": ";"}
    response = client.post("/internal/v1/analyze", files=files, data=data, headers=AUTH_HEADER)
    assert response.status_code == 200
    res = response.json()
    assert res["row_count"] == 2
    assert res["column_count"] == 3

def test_empty_csv_file():
    files = {"file": ("empty.csv", io.BytesIO(b""), "text/csv")}
    response = client.post("/internal/v1/analyze", files=files, headers=AUTH_HEADER)
    assert response.status_code == 422

def test_utf8_bom_handling():
    csv_content = b"\xef\xbb\xbfcity,temperature\nTokyo,22.5\nParis,18.0\n"
    files = {"file": ("bom.csv", io.BytesIO(csv_content), "text/csv")}
    response = client.post("/internal/v1/analyze", files=files, headers=AUTH_HEADER)
    assert response.status_code == 200
    res = response.json()
    assert res["row_count"] == 2
    assert res["columns"][0]["label"] == "city"

