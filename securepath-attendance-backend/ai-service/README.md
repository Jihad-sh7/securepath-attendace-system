# SecurePath Face AI

Private FastAPI service for InsightFace `buffalo_l` enrollment and recognition. Laravel is the only intended caller. The model is downloaded into the Docker `insightface-models` volume on first startup.

The default cosine threshold (`0.45`) is a development starting point, not a production guarantee. Calibrate it with representative consented images from the actual cameras and environment before deployment. This service performs image-quality checks but does not claim spoof-resistant liveness detection.
