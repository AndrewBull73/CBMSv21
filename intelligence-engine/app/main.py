from __future__ import annotations

from datetime import datetime, timezone
from math import sqrt
from typing import Literal

from fastapi import FastAPI
from pydantic import BaseModel, Field


app = FastAPI(
    title="CBMS Intelligence Engine",
    version="0.1.0",
    description="Advisory analytics, forecasting, scenario modelling, risk scoring, and ML execution service for CBMS.",
)


class TimeSeriesPoint(BaseModel):
    period: str
    amount: float


class ForecastRequest(BaseModel):
    forecast_type: str = Field(default="expenditure")
    method: Literal["historical_average_growth", "moving_average", "linear_trend", "user_defined_growth_rate"] = "historical_average_growth"
    history: list[TimeSeriesPoint] = Field(default_factory=list)
    periods_ahead: int = Field(default=12, ge=1, le=60)
    user_growth_rate: float | None = None


class ScenarioAssumption(BaseModel):
    code: str
    value: float
    description: str | None = None


class ScenarioRequest(BaseModel):
    scenario_type: str = Field(default="generic")
    baseline_amount: float = 0.0
    assumptions: list[ScenarioAssumption] = Field(default_factory=list)


class MLTrainingRequest(BaseModel):
    model_code: str
    model_type: Literal["REGRESSION", "CLASSIFICATION", "TIME_SERIES", "ANOMALY_DETECTION", "RULE_ASSISTED"] = "REGRESSION"
    target_column: str
    feature_columns: list[str] = Field(default_factory=list)
    rows: list[dict] = Field(default_factory=list)


class MLPredictionRequest(BaseModel):
    model_code: str
    model_type: Literal["REGRESSION", "CLASSIFICATION", "TIME_SERIES", "ANOMALY_DETECTION", "RULE_ASSISTED"] = "REGRESSION"
    target_column: str | None = None
    feature_columns: list[str] = Field(default_factory=list)
    model_artifact: dict = Field(default_factory=dict)
    rows: list[dict] = Field(default_factory=list)


@app.get("/health")
def health() -> dict:
    return {
        "ok": True,
        "service": "CBMS Intelligence Engine",
        "version": app.version,
        "timestamp_utc": datetime.now(timezone.utc).isoformat(),
        "capabilities": [
            "health",
            "basic_forecast",
            "scenario_evaluation",
            "ml_training",
            "ml_prediction",
            "ml_categorical_features",
        ],
    }


@app.post("/forecast/basic")
def basic_forecast(request: ForecastRequest) -> dict:
    history = sorted(request.history, key=lambda item: item.period)
    if not history:
        return {
            "ok": False,
            "message": "No historical data was supplied.",
            "results": [],
        }

    amounts = [point.amount for point in history]
    last_amount = amounts[-1]
    growth_rate = _growth_rate(request, amounts)
    results = []
    forecast_amount = last_amount
    for index in range(1, request.periods_ahead + 1):
        forecast_amount = forecast_amount * (1.0 + growth_rate)
        confidence_width = abs(forecast_amount) * min(0.35, 0.05 + (index * 0.01))
        results.append(
            {
                "period_index": index,
                "forecast_amount": round(forecast_amount, 2),
                "lower_bound_amount": round(forecast_amount - confidence_width, 2),
                "upper_bound_amount": round(forecast_amount + confidence_width, 2),
            }
        )

    return {
        "ok": True,
        "forecast_type": request.forecast_type,
        "method": request.method,
        "growth_rate": growth_rate,
        "confidence_score": max(0.35, min(0.9, 0.75 - (0.01 * request.periods_ahead))),
        "drivers": [
            "Historical trend",
            "Recent baseline amount",
            "Selected forecast method",
        ],
        "results": results,
        "advisory": True,
    }


@app.post("/scenario/evaluate")
def evaluate_scenario(request: ScenarioRequest) -> dict:
    adjusted = request.baseline_amount
    impacts = []
    for assumption in request.assumptions:
        code = assumption.code.lower()
        value = assumption.value
        if "reduce" in code or "cut" in code or "fall" in code:
            impact = -(request.baseline_amount * value)
        else:
            impact = request.baseline_amount * value
        adjusted += impact
        impacts.append(
            {
                "code": assumption.code,
                "description": assumption.description,
                "impact_amount": round(impact, 2),
            }
        )

    variance = adjusted - request.baseline_amount
    variance_percent = (variance / request.baseline_amount) if request.baseline_amount else 0.0
    risk_level = "LOW"
    if abs(variance_percent) >= 0.2:
        risk_level = "HIGH"
    elif abs(variance_percent) >= 0.1:
        risk_level = "MEDIUM"

    return {
        "ok": True,
        "scenario_type": request.scenario_type,
        "baseline_amount": round(request.baseline_amount, 2),
        "scenario_amount": round(adjusted, 2),
        "variance_amount": round(variance, 2),
        "variance_percent": round(variance_percent, 6),
        "risk_level": risk_level,
        "impacts": impacts,
        "disclaimer": "This recommendation is advisory only. Final decisions remain subject to authorised CBMS review and approval.",
    }


@app.post("/ml/train")
def train_ml_model(request: MLTrainingRequest) -> dict:
    prepared = _prepare_training_rows(request.rows, request.target_column, request.feature_columns)
    if len(prepared) < 10:
        return {
            "ok": False,
            "message": "At least 10 complete numeric rows are required for baseline training.",
            "row_count": len(prepared),
        }

    if request.model_type == "CLASSIFICATION":
        return _classification_baseline(request, prepared)

    return _regression_baseline(request, prepared)


@app.post("/ml/predict")
def predict_ml_model(request: MLPredictionRequest) -> dict:
    artifact = request.model_artifact or {}
    algorithm = str(artifact.get("algorithm") or "")
    predictions = []

    for index, row in enumerate(request.rows, start=1):
        entity_code = str(row.get("_entity_code") or index)
        entity_type = str(row.get("_entity_type") or request.model_code or "ML_ENTITY")
        predicted = _predict_value(row, artifact)
        if predicted is None:
            continue
        actual = _as_float(row.get(request.target_column)) if request.target_column else None
        variance = (actual - predicted) if actual is not None else None
        variance_percent = (variance / max(abs(predicted), 1.0)) if variance is not None else None
        risk_score = _prediction_risk_score(predicted, actual)
        risk_level = _prediction_risk_level(risk_score)
        driver = str(artifact.get("top_feature") or artifact.get("selected_feature") or "")
        row_context = {
            key: row.get(key)
            for key in [
                "FiscalYearID",
                "BudgetVersionID",
                "PeriodNo",
                "Segment1",
                "Segment2",
                "Segment3",
                "ProgramCode",
                "EconomicCode",
                "CurrencyCode",
                "BudgetAmount",
                "ReleasedAmount",
                "WarrantAmount",
                "CommitmentAmount",
                "ActualAmount",
                "AvailableBalance",
                "ExecutionRate",
                "CumulativeBudgetAmount",
                "CumulativeActualAmount",
                "CumulativeExecutionRate",
                "ExpectedExecutionRate",
                "VarianceAmount",
                "VariancePct",
                "ActualSpikePct",
                "PriorYearActualChangePct",
                "IsActualWithoutBudget",
                "IsNegativeAvailableBalance",
                "IsActualSpike",
                "IsPriorYearActualSpike",
                "IsDormantLineActivity",
                "IsBudgetWithoutExecution",
                "IsAboveExpectedYTD",
                "RiskReason",
                "AnomalyTypeCode",
            ]
            if key in row
        }
        predictions.append(
            {
                "entity_type": entity_type,
                "entity_code": entity_code,
                "prediction_value": round(predicted, 6),
                "risk_score": round(risk_score, 6),
                "confidence_score": round(max(0.1, min(0.95, 1.0 - risk_score)), 6),
                "prediction_json": {
                    "algorithm": algorithm,
                    "actual_value": actual,
                    "predicted_value": round(predicted, 6),
                    "variance_amount": round(variance, 6) if variance is not None else None,
                    "variance_percent": round(variance_percent, 6) if variance_percent is not None else None,
                    "risk_level": risk_level,
                    "interpretation": _prediction_interpretation(predicted, actual, risk_level),
                    "selected_feature": driver,
                    "selected_feature_value": row.get(driver) if driver else None,
                    "row_context": row_context,
                    "top_terms": artifact.get("top_terms") if isinstance(artifact.get("top_terms"), list) else [],
                    "advisory": True,
                },
            }
        )

    return {
        "ok": True,
        "model_code": request.model_code,
        "model_type": request.model_type,
        "row_count": len(request.rows),
        "prediction_count": len(predictions),
        "predictions": predictions,
        "advisory": True,
    }


def _predict_value(row: dict, artifact: dict) -> float | None:
    algorithm = str(artifact.get("algorithm") or "")
    if algorithm == "multi_feature_linear_regression":
        return _predict_from_multi_feature_model(row, artifact)

    if algorithm == "single_feature_linear_regression":
        selected_feature = str(artifact.get("selected_feature") or "")
        parameters = artifact.get("parameters") if isinstance(artifact.get("parameters"), dict) else {}
        feature_value = _as_float(row.get(selected_feature))
        intercept = _as_float(parameters.get("intercept"))
        slope = _as_float(parameters.get("slope"))
        if feature_value is None or intercept is None or slope is None:
            return None
        return intercept + (slope * feature_value)

    if algorithm == "majority_class_baseline":
        return _as_float(artifact.get("majority_label"))

    return _as_float(artifact.get("target_mean"))


def _prediction_risk_score(predicted: float, actual: float | None) -> float:
    if actual is None:
        return 0.5
    denominator = max(abs(actual), abs(predicted), 1.0)
    return max(0.0, min(1.0, abs(predicted - actual) / denominator))


def _prediction_risk_level(risk_score: float) -> str:
    if risk_score >= 0.35:
        return "HIGH"
    if risk_score >= 0.15:
        return "MEDIUM"
    return "LOW"


def _prediction_interpretation(predicted: float, actual: float | None, risk_level: str) -> str:
    if actual is None:
        return "The model generated an expected value, but no actual value was available for comparison."
    variance = actual - predicted
    direction = "higher than" if variance > 0 else "lower than"
    if abs(variance) < 0.000001:
        return "Actual value is very close to the model expectation."
    return f"Actual value is {direction} the model expectation. Review priority: {risk_level}."


def _prepare_training_rows(rows: list[dict], target_column: str, feature_columns: list[str]) -> list[dict]:
    prepared = []
    for row in rows:
        target = _as_float(row.get(target_column))
        if target is None:
            continue
        features = {}
        for column in feature_columns:
            value = row.get(column)
            if value is not None and str(value).strip() != "":
                features[column] = value
        if features:
            prepared.append({"target": target, "features": features})
    return prepared


def _regression_baseline(request: MLTrainingRequest, rows: list[dict]) -> dict:
    split_at = max(1, int(len(rows) * 0.8))
    train_rows = rows[:split_at]
    test_rows = rows[split_at:] or rows[-max(1, min(20, len(rows))):]
    target_mean = sum(row["target"] for row in train_rows) / len(train_rows)

    multi_model = _fit_multi_feature_regression(request.feature_columns, train_rows)
    if multi_model is not None:
        actuals = [row["target"] for row in test_rows]
        predictions = [_predict_from_multi_feature_model(row["features"], multi_model) for row in test_rows]
        mae = sum(abs(a - p) for a, p in zip(actuals, predictions)) / len(actuals)
        rmse = sqrt(sum((a - p) ** 2 for a, p in zip(actuals, predictions)) / len(actuals))
        r2 = _r2_score(actuals, predictions)
        accuracy_score = max(0.0, min(1.0, r2 if r2 > 0 else 0.0))
        return {
            "ok": True,
            "model_code": request.model_code,
            "model_type": request.model_type,
            "algorithm": "multi_feature_linear_regression",
            "row_count": len(rows),
            "training_row_count": len(train_rows),
            "test_row_count": len(test_rows),
            "target_column": request.target_column,
            "selected_feature": multi_model.get("top_feature"),
            "metrics": {
                "mae": round(mae, 6),
                "rmse": round(rmse, 6),
                "r2": round(r2, 6),
                "accuracy_score": round(accuracy_score, 6),
            },
            "model_artifact": multi_model,
            "advisory": True,
        }

    best_feature = None
    best_model = None
    best_r2 = float("-inf")
    for feature in request.feature_columns:
        pairs = [(row["features"][feature], row["target"]) for row in train_rows if feature in row["features"]]
        if len(pairs) < 5:
            continue
        xs = [pair[0] for pair in pairs]
        ys = [pair[1] for pair in pairs]
        x_mean = sum(xs) / len(xs)
        y_mean = sum(ys) / len(ys)
        denominator = sum((x - x_mean) ** 2 for x in xs)
        if denominator == 0:
            continue
        slope = sum((x - x_mean) * (y - y_mean) for x, y in pairs) / denominator
        intercept = y_mean - (slope * x_mean)
        r2 = _r2_score(
            [row["target"] for row in test_rows if feature in row["features"]],
            [intercept + slope * row["features"][feature] for row in test_rows if feature in row["features"]],
        )
        if r2 > best_r2:
            best_r2 = r2
            best_feature = feature
            best_model = {"intercept": intercept, "slope": slope}

    actuals = [row["target"] for row in test_rows]
    if best_feature and best_model:
        predictions = [
            best_model["intercept"] + best_model["slope"] * row["features"].get(best_feature, 0.0)
            for row in test_rows
        ]
        algorithm = "single_feature_linear_regression"
    else:
        predictions = [target_mean for _ in test_rows]
        algorithm = "mean_baseline"

    mae = sum(abs(a - p) for a, p in zip(actuals, predictions)) / len(actuals)
    rmse = sqrt(sum((a - p) ** 2 for a, p in zip(actuals, predictions)) / len(actuals))
    r2 = _r2_score(actuals, predictions)
    accuracy_score = max(0.0, min(1.0, r2 if r2 > 0 else 0.0))

    return {
        "ok": True,
        "model_code": request.model_code,
        "model_type": request.model_type,
        "algorithm": algorithm,
        "row_count": len(rows),
        "training_row_count": len(train_rows),
        "test_row_count": len(test_rows),
        "target_column": request.target_column,
        "selected_feature": best_feature,
        "metrics": {
            "mae": round(mae, 6),
            "rmse": round(rmse, 6),
            "r2": round(r2, 6),
            "accuracy_score": round(accuracy_score, 6),
        },
        "model_artifact": {
            "algorithm": algorithm,
            "selected_feature": best_feature,
            "target_mean": round(target_mean, 6),
            "parameters": best_model,
        },
        "advisory": True,
    }


def _fit_multi_feature_regression(feature_columns: list[str], train_rows: list[dict]) -> dict | None:
    if len(train_rows) < 10 or not feature_columns:
        return None

    schema = _feature_schema(feature_columns, train_rows)
    if not schema["terms"]:
        return None

    matrix = [_encode_features(row["features"], schema) for row in train_rows]
    targets = [row["target"] for row in train_rows]
    target_mean = sum(targets) / len(targets)
    weights = [0.0 for _ in schema["terms"]]
    bias = target_mean
    learning_rate = 0.03
    iterations = 450
    n = len(matrix)

    for _ in range(iterations):
        bias_gradient = 0.0
        weight_gradients = [0.0 for _ in weights]
        for features, target in zip(matrix, targets):
            predicted = bias + sum(weight * value for weight, value in zip(weights, features))
            error = predicted - target
            bias_gradient += error
            for index, value in enumerate(features):
                weight_gradients[index] += error * value
        bias -= learning_rate * (bias_gradient / n)
        for index in range(len(weights)):
            weights[index] -= learning_rate * ((weight_gradients[index] / n) + (0.0001 * weights[index]))

    weighted_terms = sorted(
        zip(schema["terms"], weights),
        key=lambda item: abs(item[1]),
        reverse=True,
    )
    top_feature = weighted_terms[0][0]["source"] if weighted_terms else None
    return {
        "algorithm": "multi_feature_linear_regression",
        "target_mean": round(target_mean, 6),
        "bias": bias,
        "weights": weights,
        "schema": schema,
        "top_feature": top_feature,
        "top_terms": [
            {"term": term["name"], "source": term["source"], "weight": round(weight, 6)}
            for term, weight in weighted_terms[:10]
        ],
    }


def _feature_schema(feature_columns: list[str], train_rows: list[dict]) -> dict:
    terms = []
    numeric = {}
    categorical = {}

    for column in feature_columns:
        numeric_values = []
        categorical_counts = {}
        force_categorical = _is_code_like_feature(column)
        for row in train_rows:
            raw = row["features"].get(column)
            numeric_value = _as_float(raw)
            if numeric_value is not None and not force_categorical:
                numeric_values.append(numeric_value)
            elif raw is not None and str(raw).strip() != "":
                key = str(raw).strip()
                categorical_counts[key] = categorical_counts.get(key, 0) + 1

        if len(numeric_values) >= max(5, int(len(train_rows) * 0.6)):
            mean = sum(numeric_values) / len(numeric_values)
            variance = sum((value - mean) ** 2 for value in numeric_values) / len(numeric_values)
            std = sqrt(variance) or 1.0
            numeric[column] = {"mean": mean, "std": std}
            terms.append({"name": column, "source": column, "kind": "numeric"})
            continue

        top_categories = [
            category for category, count in sorted(categorical_counts.items(), key=lambda item: item[1], reverse=True)[:20]
            if count >= 2
        ]
        if top_categories:
            categorical[column] = top_categories
            for category in top_categories:
                terms.append({"name": column + "=" + category, "source": column, "kind": "categorical", "category": category})

    return {"numeric": numeric, "categorical": categorical, "terms": terms}


def _is_code_like_feature(column: str) -> bool:
    name = column.lower()
    if name in {"periodno", "fiscalyearid", "budgetversionid", "versionid"}:
        return False
    return name.startswith("segment") or name.endswith("code") or name.endswith("id")


def _encode_features(features: dict, schema: dict) -> list[float]:
    encoded = []
    for term in schema["terms"]:
        source = term["source"]
        if term["kind"] == "numeric":
            value = _as_float(features.get(source))
            stats = schema["numeric"].get(source, {"mean": 0.0, "std": 1.0})
            encoded.append(((value if value is not None else stats["mean"]) - stats["mean"]) / (stats["std"] or 1.0))
        else:
            encoded.append(1.0 if str(features.get(source, "")).strip() == str(term["category"]) else 0.0)
    return encoded


def _predict_from_multi_feature_model(features: dict, artifact: dict) -> float:
    schema = artifact.get("schema") if isinstance(artifact.get("schema"), dict) else {}
    encoded = _encode_features(features, schema)
    weights = artifact.get("weights") if isinstance(artifact.get("weights"), list) else []
    bias = _as_float(artifact.get("bias")) or _as_float(artifact.get("target_mean")) or 0.0
    return bias + sum(float(weight) * value for weight, value in zip(weights, encoded))


def _classification_baseline(request: MLTrainingRequest, rows: list[dict]) -> dict:
    labels = [round(row["target"]) for row in rows]
    counts = {}
    for label in labels:
        counts[label] = counts.get(label, 0) + 1
    majority_label = max(counts, key=counts.get)
    accuracy = counts[majority_label] / len(labels)
    return {
        "ok": True,
        "model_code": request.model_code,
        "model_type": request.model_type,
        "algorithm": "majority_class_baseline",
        "row_count": len(rows),
        "target_column": request.target_column,
        "metrics": {
            "accuracy_score": round(accuracy, 6),
            "majority_label": majority_label,
        },
        "model_artifact": {
            "algorithm": "majority_class_baseline",
            "majority_label": majority_label,
        },
        "advisory": True,
    }


def _as_float(value) -> float | None:
    if value is None or value == "":
        return None
    try:
        return float(str(value).replace(",", ""))
    except (TypeError, ValueError):
        return None


def _r2_score(actuals: list[float], predictions: list[float]) -> float:
    if not actuals or len(actuals) != len(predictions):
        return 0.0
    mean_actual = sum(actuals) / len(actuals)
    total = sum((actual - mean_actual) ** 2 for actual in actuals)
    if total == 0:
        return 0.0
    residual = sum((actual - predicted) ** 2 for actual, predicted in zip(actuals, predictions))
    return 1.0 - (residual / total)


def _growth_rate(request: ForecastRequest, amounts: list[float]) -> float:
    if request.method == "user_defined_growth_rate" and request.user_growth_rate is not None:
        return request.user_growth_rate

    if request.method == "moving_average":
        window = amounts[-3:] if len(amounts) >= 3 else amounts
        average = sum(window) / len(window)
        return ((average - amounts[0]) / abs(amounts[0])) / max(1, len(amounts) - 1) if amounts[0] else 0.0

    if request.method == "linear_trend" and len(amounts) >= 2:
        return ((amounts[-1] - amounts[0]) / abs(amounts[0])) / max(1, len(amounts) - 1) if amounts[0] else 0.0

    changes = []
    for previous, current in zip(amounts, amounts[1:]):
        if previous:
            changes.append((current - previous) / abs(previous))
    return sum(changes) / len(changes) if changes else 0.0
