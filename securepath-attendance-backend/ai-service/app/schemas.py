from typing import Any

from pydantic import BaseModel, Field


class Candidate(BaseModel):
    personId: str
    embedding: list[float]
    modelVersion: str


class FaceResult(BaseModel):
    embedding: list[float]
    embeddingDimension: int
    modelVersion: str
    qualityScore: float
    detectionScore: float


class IdentifyResult(BaseModel):
    matched: bool
    personId: str | None = None
    confidence: float = 0.0
    threshold: float
    modelVersion: str
    qualityScore: float


class ApiError(BaseModel):
    success: bool = False
    code: str
    message: str
    details: dict[str, Any] | None = None


class VerifyInput(BaseModel):
    registeredEmbedding: list[float] = Field(min_length=1)
    modelVersion: str
