import numpy as np
import pytest

from app.config import Settings
from app.face_engine import FaceEngine, FaceProcessingError


def test_cosine_similarity_normalizes_candidate():
    probe = np.asarray([1.0, 0.0, 0.0], dtype=np.float32)
    assert FaceEngine.similarity(probe, [5.0, 0.0, 0.0]) == pytest.approx(1.0)


def test_dimension_mismatch_is_rejected():
    probe = np.asarray([1.0, 0.0], dtype=np.float32)
    with pytest.raises(FaceProcessingError) as error:
        FaceEngine.similarity(probe, [1.0, 0.0, 0.0])
    assert error.value.code == "EMBEDDING_MISMATCH"


def test_model_identity_is_explicit():
    engine = FaceEngine(Settings(model_name="buffalo_l"))
    assert engine.model_version == "insightface-buffalo_l"
