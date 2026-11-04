import numpy as np
import pytest
from fastapi.testclient import TestClient

from app.face_engine import ExtractedFace, FaceProcessingError
from app.main import app, engine


client = TestClient(app)


def upload_fields():
    return {
        "front": ("front.jpg", b"front", "image/jpeg"),
        "left": ("left.jpg", b"left", "image/jpeg"),
        "right": ("right.jpg", b"right", "image/jpeg"),
    }


def test_health_reports_loaded_buffalo_l(monkeypatch):
    monkeypatch.setattr(engine, "analysis", object())
    response = client.get("/health")

    assert response.status_code == 200
    assert response.json()["modelVersion"] == "insightface-buffalo_l"


def test_enroll_aggregates_and_normalizes_three_real_templates(monkeypatch):
    samples = iter([
        ExtractedFace(np.asarray([1.0, 0.0], dtype=np.float32), 0.99, 0.9),
        ExtractedFace(np.asarray([0.8, 0.2], dtype=np.float32), 0.98, 0.8),
        ExtractedFace(np.asarray([0.9, 0.1], dtype=np.float32), 0.97, 0.85),
    ])
    monkeypatch.setattr(engine, "extract", lambda _: next(samples))

    response = client.post("/enroll", data={"personId": "EMP-00001"}, files=upload_fields())

    assert response.status_code == 200
    body = response.json()
    assert body["success"] is True
    assert body["embeddingDimension"] == 2
    assert body["modelVersion"] == "insightface-buffalo_l"
    assert body["sampleCount"] == 3
    assert np.linalg.norm(body["embedding"]) == pytest.approx(1.0)


@pytest.mark.parametrize("code", ["NO_FACE", "MULTIPLE_FACES", "INVALID_IMAGE"])
def test_enroll_returns_structured_image_errors(monkeypatch, code):
    monkeypatch.setattr(
        engine,
        "extract",
        lambda _: (_ for _ in ()).throw(FaceProcessingError(code, "Image rejected")),
    )

    response = client.post("/enroll", data={"personId": "EMP-00001"}, files=upload_fields())

    assert response.status_code == 422
    assert response.json() == {
        "success": False,
        "code": code,
        "message": "Image rejected",
        "details": None,
    }
