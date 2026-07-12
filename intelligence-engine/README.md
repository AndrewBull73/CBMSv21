# CBMS Intelligence Engine

Python service scaffold for the CBMS Intelligence Engine.

The service is intentionally separate from the PHP application. CBMS remains the workflow, security, approval, and audit system; this service calculates forecasts, scenarios, risk scores, insights, and later ML predictions from approved read-only views.

## Local Run

```powershell
cd intelligence-engine
py -3.12 -m venv .venv
.\.venv\Scripts\python.exe -m pip install -r requirements.txt
.\.venv\Scripts\python.exe -m uvicorn app.main:app --host 127.0.0.1 --port 8010
```

If PowerShell blocks `.\.venv\Scripts\Activate.ps1`, use the direct `.venv\Scripts\python.exe`
commands above instead of activating the environment.

Set this in `backend-php/.env`:

```text
INTELLIGENCE_ENGINE_URL=http://127.0.0.1:8010
INTELLIGENCE_ENGINE_API_KEY=
INTELLIGENCE_ENGINE_TIMEOUT=20
```

## Initial Endpoints

- `GET /health`
- `POST /forecast/basic`
- `POST /scenario/evaluate`
- `POST /ml/train`
- `POST /ml/predict`

The first release does not update official CBMS data. All results are advisory and must be reviewed through CBMS workflows before use.
The ML baseline supports numeric features and limited categorical/code features using dependency-free encoding.
