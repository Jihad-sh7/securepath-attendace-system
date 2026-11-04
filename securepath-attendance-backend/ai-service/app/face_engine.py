from __future__ import annotations

from dataclasses import dataclass

import cv2
import numpy as np
from insightface.app import FaceAnalysis

from .config import Settings


class FaceProcessingError(Exception):
    def __init__(self, code: str, message: str, details: dict | None = None):
        super().__init__(message)
        self.code = code
        self.message = message
        self.details = details


@dataclass(frozen=True)
class ExtractedFace:
    embedding: np.ndarray
    detection_score: float
    quality_score: float


class FaceEngine:
    def __init__(self, settings: Settings):
        self.settings = settings
        self.analysis: FaceAnalysis | None = None
        self.embedding_dimension: int | None = None

    @property
    def model_version(self) -> str:
        return f"insightface-{self.settings.model_name}"

    def load(self) -> None:
        self.analysis = FaceAnalysis(
            name=self.settings.model_name,
            root=self.settings.model_root,
            providers=[self.settings.execution_provider],
        )
        ctx_id = 0 if self.settings.execution_provider == "CUDAExecutionProvider" else -1
        self.analysis.prepare(
            ctx_id=ctx_id,
            det_size=(self.settings.detection_width, self.settings.detection_height),
        )
        for model in self.analysis.models.values():
            shape = getattr(model, "output_shape", None)
            if getattr(model, "taskname", None) == "recognition" and shape:
                self.embedding_dimension = int(shape[-1])
                break

    def extract(self, content: bytes) -> ExtractedFace:
        if self.analysis is None:
            raise RuntimeError("Face model is not loaded")
        image = cv2.imdecode(np.frombuffer(content, dtype=np.uint8), cv2.IMREAD_COLOR)
        if image is None:
            raise FaceProcessingError("INVALID_IMAGE", "The uploaded file is not a readable image.")
        faces = self.analysis.get(image)
        if not faces:
            raise FaceProcessingError("NO_FACE", "No face was detected. Center one face and try again.")
        if len(faces) != 1:
            raise FaceProcessingError("MULTIPLE_FACES", "Exactly one face must be visible.", {"faceCount": len(faces)})

        face = faces[0]
        score = float(face.det_score)
        x1, y1, x2, y2 = [float(value) for value in face.bbox]
        width, height = max(0.0, x2 - x1), max(0.0, y2 - y1)
        image_area = float(image.shape[0] * image.shape[1])
        area_ratio = (width * height) / image_area if image_area else 0.0
        if score < self.settings.minimum_detection_score:
            raise FaceProcessingError("LOW_QUALITY", "The face detection is not clear enough.", {"detectionScore": score})
        if min(width, height) < self.settings.minimum_face_pixels or area_ratio < self.settings.minimum_face_area_ratio:
            raise FaceProcessingError("FACE_TOO_SMALL", "Move closer to the camera and try again.")

        crop = image[max(0, int(y1)):min(image.shape[0], int(y2)), max(0, int(x1)):min(image.shape[1], int(x2))]
        blur_score = float(cv2.Laplacian(cv2.cvtColor(crop, cv2.COLOR_BGR2GRAY), cv2.CV_64F).var()) if crop.size else 0.0
        if blur_score < self.settings.minimum_blur_score:
            raise FaceProcessingError("BLURRY_FACE", "Hold still and try again.", {"blurScore": blur_score})

        embedding = np.asarray(face.embedding, dtype=np.float32)
        norm = float(np.linalg.norm(embedding))
        if norm == 0:
            raise FaceProcessingError("EMBEDDING_FAILED", "The face template could not be generated.")
        embedding /= norm
        self.embedding_dimension = int(embedding.shape[0])
        quality = min(1.0, (score * 0.6) + (min(area_ratio / 0.16, 1.0) * 0.2) + (min(blur_score / 250.0, 1.0) * 0.2))
        return ExtractedFace(embedding, score, round(quality, 4))

    @staticmethod
    def similarity(left: np.ndarray, right: list[float]) -> float:
        candidate = np.asarray(right, dtype=np.float32)
        if candidate.shape != left.shape:
            raise FaceProcessingError("EMBEDDING_MISMATCH", "Candidate embedding dimensions do not match the active model.")
        norm = float(np.linalg.norm(candidate))
        if norm == 0:
            return -1.0
        return float(np.dot(left, candidate / norm))
