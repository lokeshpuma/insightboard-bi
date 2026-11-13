import io
import re
from datetime import datetime, timezone
from typing import Any
import numpy as np
import pandas as pd
from app.schemas.analytics import (
    AnalyzeResponse,
    CategoryFrequency,
    ColumnProfile,
    CorrelationMatrix,
    DataQuality,
    NumericStatistics,
)

def normalize_key(name: str, index: int) -> str:
    cleaned = re.sub(r"[^a-zA-Z0-9_]+", "_", str(name).strip().lower()).strip("_")
    if not cleaned:
        cleaned = f"column_{index}"
    return f"col_{index}_{cleaned}"

def sanitize_float(val: Any) -> float | None:
    if val is None or pd.isna(val) or np.isinf(val):
        return None
    return round(float(val), 4)

def infer_column_type(series: pd.Series) -> tuple[str, str]:
    """
    Infers data type and semantic role for a pandas series.
    Returns: (inferred_type, semantic_role)
    Types: integer, decimal, boolean, date, datetime, identifier, string
    Roles: measure, dimension, date, identifier
    """
    valid = series.dropna()
    total_valid = len(valid)

    if total_valid == 0:
        return "string", "dimension"

    # Check boolean
    unique_vals = set(valid.astype(str).str.strip().str.lower().unique())
    if unique_vals.issubset({"true", "false", "1", "0", "yes", "no", "t", "f"}):
        return "boolean", "dimension"

    # Try numeric
    try:
        numeric_series = pd.to_numeric(valid, errors="raise")
        # Check if all values are whole numbers (integer)
        if (numeric_series % 1 == 0).all():
            # Check if likely an ID / identifier (e.g. sequentially unique or small range)
            if numeric_series.nunique() == total_valid and total_valid > 50 and numeric_series.min() >= 1:
                return "integer", "measure"
            return "integer", "measure"
        return "decimal", "measure"
    except (ValueError, TypeError):
        pass

    # Try date / datetime
    # Check if strings look like dates first to avoid parsing arbitrary numbers
    sample_str = valid.astype(str).head(20)
    has_date_delimiters = sample_str.str.contains(r"[-/:]").all()
    if has_date_delimiters:
        try:
            parsed_dates = pd.to_datetime(valid, errors="raise")
            # If time components are all zero, it's date, else datetime
            if (parsed_dates.dt.hour == 0).all() and (parsed_dates.dt.minute == 0).all() and (parsed_dates.dt.second == 0).all():
                return "date", "date"
            return "datetime", "date"
        except (ValueError, TypeError, pd.errors.ParserError):
            pass

    # Identifier check for strings (high cardinality, alphanumeric patterns)
    if valid.nunique() == total_valid and total_valid > 50:
        return "string", "identifier"

    return "string", "dimension"

def profile_csv(
    file_bytes: bytes,
    delimiter: str = ",",
    encoding: str = "utf-8",
    max_categories: int = 20,
    correlation_column_limit: int = 10,
) -> AnalyzeResponse:
    warnings = []

    # Handle encoding & BOM
    detected_encoding = encoding or "utf-8"
    if file_bytes.startswith(b"\xef\xbb\xbf"):
        file_bytes = file_bytes[3:]
        detected_encoding = "utf-8"

    # Read CSV into pandas DataFrame
    stream = io.BytesIO(file_bytes)
    try:
        df = pd.read_csv(
            stream,
            sep=delimiter,
            encoding=detected_encoding,
            low_memory=False,
            dtype=object,  # Read everything as object first for accurate type inference
        )
    except UnicodeDecodeError:
        stream.seek(0)
        df = pd.read_csv(stream, sep=delimiter, encoding="latin1", low_memory=False, dtype=object)
        warnings.append("File was parsed using Latin-1 fallback due to invalid UTF-8 sequences.")

    row_count = len(df)
    column_count = len(df.columns)

    if column_count == 0:
        raise ValueError("The provided CSV file contains no columns or valid header rows.")

    # Duplicate row calculation
    duplicate_rows = int(df.duplicated().sum())
    duplicate_percentage = round((duplicate_rows / row_count * 100) if row_count > 0 else 0.0, 2)

    # Process columns
    columns_profile = []
    numeric_columns = []

    used_keys = set()
    for idx, col_name in enumerate(df.columns):
        col_label = str(col_name).strip() if pd.notna(col_name) else f"Column {idx + 1}"
        field_key = normalize_key(col_label, idx)
        
        # Ensure key uniqueness
        unique_key = field_key
        counter = 1
        while unique_key in used_keys:
            unique_key = f"{field_key}_{counter}"
            counter += 1
        used_keys.add(unique_key)

        series = df[col_name]
        null_count = int(series.isna().sum())
        null_percentage = round((null_count / row_count * 100) if row_count > 0 else 0.0, 2)
        distinct_count = int(series.nunique(dropna=True))

        # Sample values
        sample_vals = series.dropna().head(5).tolist()

        # Inferred type & role
        inferred_type, semantic_role = infer_column_type(series)

        stats = None
        top_cats = None
        outlier_count = 0

        if inferred_type in ["integer", "decimal"]:
            numeric_series = pd.to_numeric(series, errors="coerce").dropna()
            numeric_columns.append((unique_key, numeric_series))

            if len(numeric_series) > 0:
                count_val = int(len(numeric_series))
                mean_val = sanitize_float(numeric_series.mean())
                median_val = sanitize_float(numeric_series.median())
                stddev_val = sanitize_float(numeric_series.std()) if count_val > 1 else 0.0
                min_val = sanitize_float(numeric_series.min())
                max_val = sanitize_float(numeric_series.max())
                q1_val = sanitize_float(numeric_series.quantile(0.25))
                q3_val = sanitize_float(numeric_series.quantile(0.75))

                stats = NumericStatistics(
                    count=count_val,
                    mean=mean_val,
                    median=median_val,
                    stddev=stddev_val,
                    min=min_val,
                    max=max_val,
                    q1=q1_val,
                    q3=q3_val,
                )

                # Outlier detection using IQR
                if q1_val is not None and q3_val is not None:
                    iqr = q3_val - q1_val
                    lower_bound = q1_val - (1.5 * iqr)
                    upper_bound = q3_val + (1.5 * iqr)
                    outliers = ((numeric_series < lower_bound) | (numeric_series > upper_bound)).sum()
                    outlier_count = int(outliers)
        else:
            # Categorical frequency
            valid_cats = series.dropna()
            if len(valid_cats) > 0:
                val_counts = valid_cats.value_counts().head(max_categories)
                top_cats = [
                    CategoryFrequency(
                        value=str(val),
                        count=int(cnt),
                        percentage=round((cnt / len(valid_cats) * 100), 2),
                    )
                    for val, cnt in val_counts.items()
                ]

        columns_profile.append(
            ColumnProfile(
                key=unique_key,
                label=col_label,
                inferred_type=inferred_type,
                semantic_role=semantic_role,
                null_count=null_count,
                null_percentage=null_percentage,
                distinct_count=distinct_count,
                sample_values=sample_vals,
                statistics=stats,
                top_categories=top_cats,
                outlier_candidates_count=outlier_count if inferred_type in ["integer", "decimal"] else None,
            )
        )

    # Correlation matrix for numeric columns
    corr_cols = []
    corr_matrix = []
    if len(numeric_columns) >= 2:
        selected_num_cols = numeric_columns[:correlation_column_limit]
        corr_cols = [k for k, _ in selected_num_cols]
        num_df = pd.DataFrame({k: s for k, s in selected_num_cols})
        calculated_corr = num_df.corr().fillna(0.0)

        for col_x in corr_cols:
            row_vals = []
            for col_y in corr_cols:
                v = calculated_corr.loc[col_x, col_y]
                row_vals.append(sanitize_float(v) or 0.0)
            corr_matrix.append(row_vals)

    quality = DataQuality(
        duplicate_row_count=duplicate_rows,
        duplicate_row_percentage=duplicate_percentage,
        warnings=warnings,
    )

    correlations = CorrelationMatrix(
        columns=corr_cols,
        matrix=corr_matrix,
    )

    return AnalyzeResponse(
        status="completed",
        row_count=row_count,
        column_count=column_count,
        columns=columns_profile,
        quality=quality,
        correlations=correlations,
        generated_at=datetime.now(timezone.utc).isoformat(),
    )
