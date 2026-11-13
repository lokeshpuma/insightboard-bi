# InsightBoard Python Analytics Microservice

FastAPI internal computational profiling service for CSV datasets.

## Features
- Inspects and parses CSV files with automatic delimiter and BOM detection.
- Type inference: `integer`, `decimal`, `boolean`, `date`, `datetime`, `string`, `identifier`.
- Descriptive statistical summaries: count, mean, median, standard deviation, min, max, quartiles.
- Categorical frequency distribution (bounded).
- Numeric Pearson correlation matrix.
- IQR-based candidate outlier detection.
- Safe error handling: no stack traces or private paths exposed.
- Token-authenticated server-to-server API.

## Running Locally
```bash
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
uvicorn app.main:app --host 127.0.0.1 --port 8001
```

## Running Tests
```bash
PYTHONPATH=. pytest tests/
```

