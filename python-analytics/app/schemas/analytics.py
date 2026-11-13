from datetime import datetime
from typing import Any, List, Optional
from pydantic import BaseModel, Field

class HealthResponse(BaseModel):
    status: str = "ok"
    version: str = "1.0.0"
    timestamp: str

class NumericStatistics(BaseModel):
    count: int
    mean: Optional[float] = None
    median: Optional[float] = None
    stddev: Optional[float] = None
    min: Optional[float] = None
    max: Optional[float] = None
    q1: Optional[float] = None
    q3: Optional[float] = None

class CategoryFrequency(BaseModel):
    value: str
    count: int
    percentage: float

class ColumnProfile(BaseModel):
    key: str
    label: str
    inferred_type: str
    semantic_role: str
    null_count: int
    null_percentage: float
    distinct_count: int
    sample_values: List[Any] = Field(default_factory=list)
    statistics: Optional[NumericStatistics] = None
    top_categories: Optional[List[CategoryFrequency]] = None
    outlier_candidates_count: Optional[int] = None

class DataQuality(BaseModel):
    duplicate_row_count: int
    duplicate_row_percentage: float
    warnings: List[str] = Field(default_factory=list)

class CorrelationMatrix(BaseModel):
    columns: List[str] = Field(default_factory=list)
    matrix: List[List[float]] = Field(default_factory=list)

class AnalyzeResponse(BaseModel):
    status: str = "completed"
    row_count: int
    column_count: int
    columns: List[ColumnProfile]
    quality: DataQuality
    correlations: CorrelationMatrix
    generated_at: str

