from __future__ import annotations

import json
import secrets
from contextlib import asynccontextmanager

import numpy as np
from fastapi import Depends, FastAPI, File, Form, Header, HTTPException, UploadFile
from fastapi.responses import JSONResponse
from pydantic import TypeAdapter, ValidationError

from .config import get_settings
from .face_engine import FaceEngine, FaceProcessingError
from .schemas import ApiError, Candidate

settings = get_settings()
engine = FaceEngine(settings)


@asynccontextmanager
async def lifespan(_: FastAPI):
    engine.load()
    yield


app = FastAPI(title="SecurePath Face AI", version="1.0.0", lifespan=lifespan)


def authorize(authorization: str | None = Header(default=None)) -> None:
    if settings.api_token:
        expected = f"Bearer {settings.api_token}"
        if authorization is None or not secrets.compare_digest(authorization, expected):
            raise HTTPException(status_code=401, detail="Invalid internal service token")


@app.exception_handler(FaceProcessingError)
async def face_error_handler(_, error: FaceProcessingError):
    return JSONResponse(status_code=422, content=ApiError(code=error.code, message=error.message, details=error.details).model_dump())


@app.get("/health")
def health():
    if engine.analysis is None:
        raise HTTPException(status_code=503, detail="Model is not loaded")
    return {"success": True, "status": "ready", "modelVersion": engine.model_version, "provider": settings.execution_provider}


@app.get("/model-info", dependencies=[Depends(authorize)])
def model_info():
    return {
        "success": True,
        "modelName": settings.model_name,
        "modelVersion": engine.model_version,
        "embeddingDimension": engine.embedding_dimension,
        "provider": settings.execution_provider,
        "matchThreshold": settings.match_threshold,
        "thresholdNote": "Development default; calibrate with representative local data before production.",
    }


@app.post("/embed", dependencies=[Depends(authorize)])
async def embed(image: UploadFile = File(...)):
    face = engine.extract(await image.read())
    return {"success": True, "embedding": face.embedding.tolist(), "embeddingDimension": len(face.embedding), "modelVersion": engine.model_version, "qualityScore": face.quality_score, "detectionScore": face.detection_score}


@app.post("/enroll", dependencies=[Depends(authorize)])
async def enroll(personId: str = Form(...), front: UploadFile = File(...), left: UploadFile = File(...), right: UploadFile = File(...)):
    samples = [engine.extract(await upload.read()) for upload in (front, left, right)]
    dimension = len(samples[0].embedding)
    if any(len(sample.embedding) != dimension for sample in samples):
        raise FaceProcessingError("EMBEDDING_MISMATCH", "Enrollment samples produced inconsistent templates.")
    combined = np.mean([sample.embedding for sample in samples], axis=0)
    combined /= np.linalg.norm(combined)
    return {
        "success": True,
        "personId": personId,
        "embedding": combined.tolist(),
        "embeddingDimension": dimension,
        "modelVersion": engine.model_version,
        "qualityScore": round(sum(sample.quality_score for sample in samples) / len(samples), 4),
        "sampleCount": len(samples),
    }


@app.post("/verify", dependencies=[Depends(authorize)])
async def verify(image: UploadFile = File(...), registeredEmbedding: str = Form(...), modelVersion: str = Form(...)):
    if modelVersion != engine.model_version:
        raise FaceProcessingError("MODEL_VERSION_MISMATCH", "The registered template uses a different model version.")
    face = engine.extract(await image.read())
    try:
        registered = TypeAdapter(list[float]).validate_python(json.loads(registeredEmbedding))
    except (json.JSONDecodeError, ValidationError) as error:
        raise FaceProcessingError("INVALID_EMBEDDING", "The registered embedding is invalid.") from error
    confidence = engine.similarity(face.embedding, registered)
    return {"success": True, "matched": confidence >= settings.match_threshold, "confidence": round(confidence, 6), "threshold": settings.match_threshold, "modelVersion": engine.model_version, "qualityScore": face.quality_score}


@app.post("/identify", dependencies=[Depends(authorize)])
async def identify(image: UploadFile = File(...), candidates: str = Form(...)):
    try:
        parsed = TypeAdapter(list[Candidate]).validate_python(json.loads(candidates))
    except (json.JSONDecodeError, ValidationError) as error:
        raise FaceProcessingError("INVALID_CANDIDATES", "The candidate list is invalid.") from error
    face = engine.extract(await image.read())
    compatible = [candidate for candidate in parsed if candidate.modelVersion == engine.model_version]
    if not compatible:
        raise FaceProcessingError("NO_COMPATIBLE_TEMPLATES", "No enrolled templates match the active AI model.")
    scored = [(candidate, engine.similarity(face.embedding, candidate.embedding)) for candidate in compatible]
    best, confidence = max(scored, key=lambda item: item[1])
    matched = confidence >= settings.match_threshold
    return {"success": True, "matched": matched, "personId": best.personId if matched else None, "confidence": round(confidence, 6), "threshold": settings.match_threshold, "modelVersion": engine.model_version, "qualityScore": face.quality_score}
