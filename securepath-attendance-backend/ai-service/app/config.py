from functools import lru_cache

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_prefix="FACE_AI_", case_sensitive=False)

    model_name: str = "buffalo_l"
    model_root: str = "/root/.insightface"
    execution_provider: str = "CPUExecutionProvider"
    detection_width: int = 640
    detection_height: int = 640
    minimum_detection_score: float = 0.60
    minimum_face_pixels: int = 100
    minimum_face_area_ratio: float = 0.025
    minimum_blur_score: float = 45.0
    match_threshold: float = 0.45
    api_token: str | None = None


@lru_cache
def get_settings() -> Settings:
    return Settings()
